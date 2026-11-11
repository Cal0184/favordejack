<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/armeria.php';

function armeria_respond(bool $ok, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function armeria_str(array $d, string $key, int $max): string
{
    $v = $d[$key] ?? '';
    if (!is_scalar($v)) return '';
    $v = str_replace(["\r\n", "\r"], "\n", trim((string)$v));
    return mb_substr($v, 0, $max, 'UTF-8');
}

function armeria_int(array $d, string $key, int $min, int $max): ?int
{
    $v = $d[$key] ?? null;
    if ($v === null || $v === '' || !is_numeric($v)) return null;
    return max($min, min($max, (int)$v));
}

function armeria_objeto(mysqli $db, int $id): array
{
    $rows = armeria_all($db, 'SELECT * FROM ArmaObjeto WHERE ID=? LIMIT 1', [$id]);
    if (!$rows) throw new RuntimeException('Ese objeto ya no existe. Recarga la página.');
    return $rows[0];
}

/** Borra una regla que ya no usa ningún objeto. */
function armeria_regla_huerfana(mysqli $db, ?int $reglaId): void
{
    if (!$reglaId) return;
    $used = armeria_all($db, 'SELECT COUNT(*) AS n FROM ArmaObjeto WHERE ReglaID=?', [$reglaId]);
    if ((int)$used[0]['n'] === 0) armeria_q($db, 'DELETE FROM ArmaRegla WHERE ID=?', [$reglaId])->close();
}

try {
    // Lectura: la misma que usa la página para refrescar sin recargar.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        favor_session_start();
        try {
            $user = favor_current_user();
        } catch (Throwable $e) {
            $user = null;
        }
        favor_session_close();
        $db = favor_db();
        armeria_respond(true, ['catalogo' => armeria_catalogo($db, ($user['role'] ?? '') === 'admin')]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') armeria_respond(false, ['error' => 'Método no permitido.'], 405);
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) throw new RuntimeException('Pedido no válido.');
    favor_session_start();
    favor_verify_csrf((string)($data['csrf'] ?? ''));
    $admin = favor_require_admin();
    favor_session_close();

    $db = favor_db();
    armeria_ensure_schema($db);
    $action = (string)($data['action'] ?? '');
    $message = '';
    $selected = null;

    if ($action === 'guardar') {
        $o = is_array($data['objeto'] ?? null) ? $data['objeto'] : [];
        $id = (int)($o['id'] ?? 0);
        $prev = $id > 0 ? armeria_objeto($db, $id) : null;
        $tipo = (string)($o['tipo'] ?? '');
        if (!in_array($tipo, ARMERIA_TIPOS, true)) throw new RuntimeException('Elige si es un arma, una armadura o un escudo.');
        $cats = [];
        foreach (armeria_all($db, 'SELECT Clave,Tipo FROM ArmaCategoria') as $c) $cats[$c['Clave']] = $c['Tipo'];
        $cat = (string)($o['categoria'] ?? '');
        if (($cats[$cat] ?? '') !== $tipo) throw new RuntimeException('La categoría no corresponde a ese tipo de objeto.');
        $nombre = armeria_str($o, 'nombre', 160);
        if ($nombre === '') throw new RuntimeException('El objeto necesita un nombre.');
        $dup = armeria_all($db, 'SELECT ID FROM ArmaObjeto WHERE Nombre=? AND Categoria=? AND ID<>? LIMIT 1', [$nombre, $cat, $id]);
        if ($dup) throw new RuntimeException('Ya hay un objeto con ese nombre en esa categoría.');
        $coste = armeria_str($o, 'coste', 40);

        $row = [
            'Descripcion' => armeria_str($o, 'descripcion', 20000),
            'Notas' => armeria_str($o, 'notas', 5000),
            'Tipo' => $tipo, 'Categoria' => $cat, 'Nombre' => $nombre,
            'Coste' => $coste, 'CosteCobre' => armeria_coste_cobre($coste),
            'Dano' => '', 'DanoDados' => '', 'DanoPromedio' => null, 'TiposDano' => '', 'Alcance' => '',
            'PropiedadesTexto' => '',
            'CA' => '', 'CABase' => null, 'CABono' => null, 'ModDes' => null, 'DesMax' => null,
            'Fuerza' => '', 'FuerzaMin' => null, 'Sigilo' => '', 'SigiloDesventaja' => 0,
        ];
        if ($tipo === 'arma') {
            $dano = armeria_str($o, 'dano', 120);
            [$dados, $prom, $tipos] = armeria_analizar_dano($dano);
            $row['Dano'] = $dano;
            $row['DanoDados'] = $dados;
            $row['DanoPromedio'] = $prom;
            $row['TiposDano'] = implode(',', $tipos);
            $row['Alcance'] = armeria_str($o, 'alcance', 40);
        } else {
            $row['CA'] = armeria_str($o, 'ca', 60);
            $row['Fuerza'] = armeria_str($o, 'fuerza', 40);
            $row['FuerzaMin'] = armeria_int($o, 'fuerzaMin', 1, 30);
            $row['Sigilo'] = armeria_str($o, 'sigilo', 40);
            $row['SigiloDesventaja'] = !empty($o['sigiloDesventaja']) ? 1 : 0;
            if ($tipo === 'armadura') {
                $row['CABase'] = armeria_int($o, 'caBase', 0, 40);
                $mod = (string)($o['modDes'] ?? '');
                $row['ModDes'] = in_array($mod, ARMERIA_MOD_DES, true) ? $mod : null;
                $row['DesMax'] = $row['ModDes'] === 'max' ? armeria_int($o, 'desMax', 0, 10) : null;
            } else {
                $row['CABono'] = armeria_int($o, 'caBono', -10, 20);
            }
        }

        // Propiedades: solo las del glosario.
        $propIds = [];
        foreach (armeria_all($db, 'SELECT ID,Clave,Nombre FROM ArmaPropiedad') as $p) $propIds[$p['Clave']] = ['id' => (int)$p['ID'], 'nombre' => $p['Nombre']];
        $props = [];
        foreach ((array)($o['propiedades'] ?? []) as $p) {
            if (!is_array($p) || !isset($propIds[(string)($p['clave'] ?? '')])) continue;
            $props[] = ['clave' => (string)$p['clave'], 'detalle' => armeria_str($p, 'detalle', 60)];
        }
        if (count($props) > 30) throw new RuntimeException('Demasiadas propiedades.');
        // El texto de propiedades de la tabla se rehace desde las etiquetas.
        $row['PropiedadesTexto'] = implode(', ', array_map(static fn(array $p): string => $propIds[$p['clave']]['nombre'] . ($p['detalle'] !== '' ? ' (' . $p['detalle'] . ')' : ''), $props));
        if ($tipo === 'armadura') $row['PropiedadesTexto'] = '';

        // Regla especial: se edita su texto; si la comparten varios objetos, cambia en todos.
        $reglaTexto = armeria_str($o, 'reglaTexto', 20000);
        $reglaTitulo = armeria_str($o, 'reglaTitulo', 160) ?: $nombre;
        $reglaId = $prev && $prev['ReglaID'] !== null ? (int)$prev['ReglaID'] : null;
        $oldRegla = $reglaId;

        // Firma de lo que decide el atributo (categoría + propiedades) para saber si hay que reclasificar.
        $firma = static fn(string $c, array $claves): string => $c . '|' . implode(',', array_unique(array_filter($claves, static fn($k) => in_array($k, ['sutil', 'arrojadiza', 'municion'], true))));
        $antes = $prev ? $firma((string)$prev['Categoria'], array_column(armeria_all($db, 'SELECT p.Clave FROM ArmaObjetoPropiedad op JOIN ArmaPropiedad p ON p.ID=op.PropiedadID WHERE op.ObjetoID=? ORDER BY p.Clave', [$id]), 'Clave')) : null;
        $clavesNuevas = array_column($props, 'clave');
        sort($clavesNuevas);
        $reclasificar = !$prev || $prev['Tipo'] !== $tipo || $antes !== $firma($cat, $clavesNuevas);

        $db->begin_transaction();
        try {
            if ($reglaTexto === '') {
                $reglaId = null;
            } elseif ($reglaId) {
                $cur = armeria_all($db, 'SELECT Titulo,Texto FROM ArmaRegla WHERE ID=?', [$reglaId]);
                if ($cur && ($cur[0]['Titulo'] !== $reglaTitulo || $cur[0]['Texto'] !== $reglaTexto)) {
                    armeria_q($db, 'UPDATE ArmaRegla SET Titulo=?,Texto=?,Editado=1 WHERE ID=?', [$reglaTitulo, $reglaTexto, $reglaId])->close();
                }
            } else {
                $clave = armeria_clave_libre($db, 'ArmaRegla', 'regla-' . armeria_slug($reglaTitulo));
                armeria_q($db, "INSERT INTO ArmaRegla (Clave,Titulo,Texto,Origen,Editado) VALUES (?,?,?,'admin',1)", [$clave, $reglaTitulo, $reglaTexto])->close();
                $reglaId = (int)$db->insert_id;
            }
            $row['ReglaID'] = $reglaId;

            $cols = array_keys($row);
            if ($prev) {
                $set = implode(',', array_map(static fn(string $c): string => "$c=?", $cols));
                armeria_q($db, "UPDATE ArmaObjeto SET $set,Editado=1 WHERE ID=?", array_merge(array_values($row), [$id]))->close();
                $message = 'Cambios guardados.';
            } else {
                $clave = armeria_clave_libre($db, 'ArmaObjeto', armeria_slug($nombre));
                $orden = (int)(armeria_all($db, 'SELECT COALESCE(MAX(Orden),0)+1 AS n FROM ArmaObjeto')[0]['n']);
                $marks = implode(',', array_fill(0, count($cols) + 5, '?'));
                armeria_q($db, 'INSERT INTO ArmaObjeto (Clave,Origen,Editado,Orden,CreadoPor,' . implode(',', $cols) . ") VALUES ($marks)",
                    array_merge([$clave, 'admin', 1, $orden, (string)$admin['username']], array_values($row)))->close();
                $id = (int)$db->insert_id;
                $message = 'Objeto añadido al catálogo.';
            }
            armeria_guardar_propiedades($db, $id, $props);
            // Atributo de ataque: si nadie lo corrigió a mano ni tiene excepción propia, sigue las reglas generales.
            $cl = armeria_all($db, 'SELECT ClasifEditada,AtaqueExcepcion FROM ArmaObjeto WHERE ID=?', [$id])[0];
            if ($reclasificar && (int)$cl['ClasifEditada'] === 0 && trim((string)$cl['AtaqueExcepcion']) === '') {
                if ($tipo === 'arma') {
                    [$modos, $motivo] = armeria_clasificar_general($cat, array_column($props, 'clave'));
                    armeria_guardar_ataque($db, $id, $modos, ['base' => armeria_resumen_ataque($modos), 'motivo' => $motivo], false);
                } else {
                    armeria_guardar_ataque($db, $id, [], ['base' => null], false);
                }
            }
            if ($oldRegla && $oldRegla !== $reglaId) armeria_regla_huerfana($db, $oldRegla);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        $selected = $id;
    } elseif ($action === 'activar') {
        $id = (int)($data['id'] ?? 0);
        armeria_objeto($db, $id);
        $on = !empty($data['activo']) ? 1 : 0;
        armeria_q($db, 'UPDATE ArmaObjeto SET Activo=? WHERE ID=?', [$on, $id])->close();
        $message = $on ? 'El objeto vuelve a estar visible.' : 'Objeto desactivado: los jugadores ya no lo ven.';
        $selected = $id;
    } elseif ($action === 'eliminar') {
        $id = (int)($data['id'] ?? 0);
        $o = armeria_objeto($db, $id);
        if ($o['Origen'] !== 'admin') throw new RuntimeException('Los objetos del documento no se borran: desactívalo para ocultarlo.');
        $db->begin_transaction();
        try {
            armeria_q($db, 'DELETE FROM ArmaObjeto WHERE ID=?', [$id])->close();
            armeria_regla_huerfana($db, $o['ReglaID'] !== null ? (int)$o['ReglaID'] : null);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        $message = 'Objeto eliminado.';
    } elseif ($action === 'propiedad') {
        $p = is_array($data['propiedad'] ?? null) ? $data['propiedad'] : [];
        $id = (int)($p['id'] ?? 0);
        $nombre = armeria_str($p, 'nombre', 80);
        $def = armeria_str($p, 'definicion', 20000);
        if ($nombre === '') throw new RuntimeException('La propiedad necesita un nombre.');
        if ($def === '') throw new RuntimeException('Escribe la definición de la propiedad.');
        $dup = armeria_all($db, 'SELECT ID FROM ArmaPropiedad WHERE Nombre=? AND ID<>? LIMIT 1', [$nombre, $id]);
        if ($dup) throw new RuntimeException('Ya existe una propiedad con ese nombre.');
        if ($id > 0) {
            if (!armeria_all($db, 'SELECT ID FROM ArmaPropiedad WHERE ID=?', [$id])) throw new RuntimeException('Esa propiedad ya no existe.');
            armeria_q($db, 'UPDATE ArmaPropiedad SET Nombre=?,Definicion=?,Editado=1 WHERE ID=?', [$nombre, $def, $id])->close();
            $message = 'Propiedad actualizada.';
        } else {
            $clave = armeria_clave_libre($db, 'ArmaPropiedad', armeria_slug($nombre));
            armeria_q($db, "INSERT INTO ArmaPropiedad (Clave,Nombre,Definicion,Orden,Origen,Editado) VALUES (?,?,?,999,'admin',1)", [$clave, $nombre, $def])->close();
            $message = 'Propiedad añadida al glosario.';
        }
    } elseif ($action === 'eliminar_propiedad') {
        $id = (int)($data['id'] ?? 0);
        $rows = armeria_all($db, 'SELECT Origen FROM ArmaPropiedad WHERE ID=?', [$id]);
        if (!$rows) throw new RuntimeException('Esa propiedad ya no existe.');
        if ($rows[0]['Origen'] !== 'admin') throw new RuntimeException('Las propiedades del documento no se borran; puedes editar su texto.');
        $used = armeria_all($db, 'SELECT COUNT(*) AS n FROM ArmaObjetoPropiedad WHERE PropiedadID=?', [$id]);
        if ((int)$used[0]['n'] > 0) throw new RuntimeException('Esa propiedad la usan ' . $used[0]['n'] . ' objetos. Quítasela primero.');
        armeria_q($db, 'DELETE FROM ArmaPropiedad WHERE ID=?', [$id])->close();
        $message = 'Propiedad eliminada.';
    } elseif ($action === 'ataque') {
        // Corrección manual del atributo de ataque y los requisitos. AtaqueBase (reglas generales) no se toca.
        $id = (int)($data['id'] ?? 0);
        armeria_objeto($db, $id);
        $modos = [];
        foreach ((array)($data['modos'] ?? []) as $m) {
            if (!is_array($m)) continue;
            $at = (string)($m['atributo'] ?? '');
            if (!in_array($at, ARMERIA_ATRIBUTOS, true)) throw new RuntimeException('Cada modo necesita un atributo: Fuerza, Destreza, Fuerza o Destreza, o Por revisar.');
            $modos[] = ['modo' => armeria_str($m, 'modo', 80), 'atributo' => $at, 'dano' => armeria_str($m, 'dano', 60), 'nota' => armeria_str($m, 'nota', 300)];
        }
        if (count($modos) > 8) throw new RuntimeException('Como máximo 8 modos de ataque.');
        $reqs = [];
        foreach ((array)($data['requisitos'] ?? []) as $r) {
            if (!is_array($r) || armeria_str($r, 'texto', 300) === '') continue;
            $reqs[] = ['tipo' => (string)($r['tipo'] ?? ''), 'atributo' => (string)($r['atributo'] ?? ''), 'minimo' => armeria_int($r, 'minimo', 1, 30), 'texto' => armeria_str($r, 'texto', 300)];
        }
        $db->begin_transaction();
        try {
            armeria_guardar_ataque($db, $id, $modos, [
                'motivo' => armeria_str($data, 'motivo', 5000),
                'excepcion' => armeria_str($data, 'excepcion', 5000),
                'duda' => armeria_str($data, 'duda', 5000),
                'revisar' => !empty($data['revisar']),
            ], true, false);
            armeria_guardar_requisitos($db, $id, $reqs);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        $message = 'Atributo de ataque y requisitos guardados.';
        $selected = $id;
    } elseif ($action === 'ataque_restaurar') {
        $id = (int)($data['id'] ?? 0);
        armeria_objeto($db, $id);
        if (!armeria_restaurar_clasificacion($db, $id)) throw new RuntimeException('Este objeto no viene del documento: no hay clasificación original que restaurar.');
        $message = 'Se restauró la clasificación del documento.';
        $selected = $id;
    } elseif ($action === 'nota') {
        $n = is_array($data['nota'] ?? null) ? $data['nota'] : [];
        $id = (int)($n['id'] ?? 0);
        $tipo = (string)($n['tipo'] ?? '');
        if (!in_array($tipo, ARMERIA_NOTA_TIPOS, true)) throw new RuntimeException('Elige el tipo de nota.');
        $titulo = armeria_str($n, 'titulo', 160);
        $texto = armeria_str($n, 'texto', 20000);
        if ($titulo === '' || $texto === '') throw new RuntimeException('La nota necesita un título y un texto.');
        $obj = (int)($n['objetoId'] ?? 0);
        $obj = $obj > 0 ? (int)armeria_objeto($db, $obj)['ID'] : null;
        $visible = !empty($n['visible']) ? 1 : 0;
        if ($id > 0) {
            if (!armeria_all($db, 'SELECT ID FROM ArmaNota WHERE ID=?', [$id])) throw new RuntimeException('Esa nota ya no existe.');
            armeria_q($db, 'UPDATE ArmaNota SET ObjetoID=?,Tipo=?,Titulo=?,Texto=?,Visible=?,Autor=? WHERE ID=?', [$obj, $tipo, $titulo, $texto, $visible, (string)$admin['username'], $id])->close();
            $message = 'Nota actualizada.';
        } else {
            armeria_q($db, 'INSERT INTO ArmaNota (ObjetoID,Tipo,Titulo,Texto,Visible,Autor) VALUES (?,?,?,?,?,?)', [$obj, $tipo, $titulo, $texto, $visible, (string)$admin['username']])->close();
            $message = 'Nota añadida.';
        }
        $selected = $obj;
    } elseif ($action === 'eliminar_nota') {
        $id = (int)($data['id'] ?? 0);
        armeria_q($db, 'DELETE FROM ArmaNota WHERE ID=?', [$id])->close();
        $message = 'Nota eliminada.';
    } elseif ($action === 'reimportar') {
        $s = armeria_importar($db);
        $message = "Importación lista: {$s['creados']} nuevos, {$s['actualizados']} revisados, {$s['respetados']} editados o desactivados sin tocar.";
    } else {
        throw new RuntimeException('Acción desconocida.');
    }

    armeria_respond(true, ['message' => $message, 'selected' => $selected, 'catalogo' => armeria_catalogo($db, true)]);
} catch (Throwable $e) {
    $code = $e instanceof RuntimeException && !$e instanceof mysqli_sql_exception ? 400 : 500;
    if ($code === 500) error_log('armeria/api: ' . $e->getMessage());
    armeria_respond(false, ['error' => $code === 500 ? 'Error del servidor. Inténtalo otra vez.' : $e->getMessage()], $code);
}
