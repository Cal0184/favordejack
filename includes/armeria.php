<?php
declare(strict_types=1);

/*
 * Favor de Jack · Armas y Armaduras (catálogo de armamento).
 *
 * Tablas (sin una tabla por tipo de arma):
 *   ArmaCategoria        las 8 categorías del documento.
 *   ArmaObjeto           cada arma, armadura o escudo. «Clave» es el identificador
 *                        estable para enlazarlo desde otros módulos (Inventario…).
 *   ArmaPropiedad        glosario de propiedades (una definición, compartida).
 *   ArmaObjetoPropiedad  qué propiedades tiene cada objeto y su detalle: «1d8», «20/60».
 *   ArmaRegla            reglas especiales; un objeto apunta a una (dos pueden compartirla).
 *
 * La importación lee armeria/data/armamento.json (sacado del Word) y se puede
 * repetir: busca por «Clave», no duplica, y nunca pisa lo que el admin editó.
 */

const ARMERIA_DATA = __DIR__ . '/../armeria/data/armamento.json';
const ARMERIA_TIPOS = ['arma', 'armadura', 'escudo'];
const ARMERIA_MOD_DES = ['completo', 'max', 'ninguno'];
const ARMERIA_TIPOS_DANO = ['cortante', 'perforante', 'contundente', 'fuego', 'frío', 'ácido', 'veneno', 'relámpago', 'trueno', 'necrótico', 'radiante', 'psíquico', 'fuerza'];

function armeria_q(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if ($params) {
        $stmt->bind_param(str_repeat('s', count($params)), ...array_map(static fn($v) => $v === null ? null : (is_bool($v) ? ($v ? '1' : '0') : (string)$v), $params));
    }
    $stmt->execute();
    return $stmt;
}

function armeria_all(mysqli $db, string $sql, array $params = []): array
{
    $stmt = armeria_q($db, $sql, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function armeria_ensure_schema(mysqli $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $db->query("CREATE TABLE IF NOT EXISTS FavorMeta (
        Clave varchar(80) NOT NULL PRIMARY KEY,
        Valor text NULL,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS ArmaCategoria (
        Clave varchar(40) NOT NULL PRIMARY KEY,
        Nombre varchar(80) NOT NULL,
        Tipo varchar(12) NOT NULL,
        Orden smallint NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS ArmaRegla (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Clave varchar(120) NOT NULL,
        Titulo varchar(160) NOT NULL,
        Texto mediumtext NOT NULL,
        Origen varchar(12) NOT NULL DEFAULT 'documento',
        Editado tinyint(1) NOT NULL DEFAULT 0,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_armaregla_clave (Clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS ArmaPropiedad (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Clave varchar(60) NOT NULL,
        Nombre varchar(80) NOT NULL,
        TituloDocumento varchar(80) NOT NULL DEFAULT '',
        Definicion mediumtext NOT NULL,
        Orden smallint NOT NULL DEFAULT 0,
        Origen varchar(12) NOT NULL DEFAULT 'documento',
        Editado tinyint(1) NOT NULL DEFAULT 0,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_armapropiedad_clave (Clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS ArmaObjeto (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Clave varchar(120) NOT NULL,
        Tipo varchar(12) NOT NULL,
        Categoria varchar(40) NOT NULL,
        Nombre varchar(160) NOT NULL,
        Coste varchar(40) NOT NULL DEFAULT '',
        CosteCobre int unsigned NULL,
        Dano varchar(120) NOT NULL DEFAULT '',
        DanoDados varchar(20) NOT NULL DEFAULT '',
        DanoPromedio decimal(6,2) NULL,
        TiposDano varchar(160) NOT NULL DEFAULT '',
        PropiedadesTexto varchar(500) NOT NULL DEFAULT '',
        Alcance varchar(40) NOT NULL DEFAULT '',
        CA varchar(60) NOT NULL DEFAULT '',
        CABase tinyint unsigned NULL,
        CABono tinyint NULL,
        ModDes varchar(12) NULL,
        DesMax tinyint unsigned NULL,
        Fuerza varchar(40) NOT NULL DEFAULT '',
        FuerzaMin tinyint unsigned NULL,
        Sigilo varchar(40) NOT NULL DEFAULT '',
        SigiloDesventaja tinyint(1) NOT NULL DEFAULT 0,
        ReglaID int unsigned NULL,
        Notas text NULL,
        Original text NULL,
        Orden int NOT NULL DEFAULT 0,
        Activo tinyint(1) NOT NULL DEFAULT 1,
        Origen varchar(12) NOT NULL DEFAULT 'documento',
        Editado tinyint(1) NOT NULL DEFAULT 0,
        CreadoPor varchar(50) NULL,
        Creado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_armaobjeto_clave (Clave),
        KEY idx_armaobjeto_categoria (Categoria, Activo),
        KEY idx_armaobjeto_nombre (Nombre),
        KEY idx_armaobjeto_regla (ReglaID),
        CONSTRAINT fk_armaobjeto_regla FOREIGN KEY (ReglaID) REFERENCES ArmaRegla (ID) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS ArmaObjetoPropiedad (
        ObjetoID int unsigned NOT NULL,
        PropiedadID int unsigned NOT NULL,
        Detalle varchar(60) NOT NULL DEFAULT '',
        Orden tinyint unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (ObjetoID, PropiedadID),
        KEY idx_aop_propiedad (PropiedadID),
        CONSTRAINT fk_aop_objeto FOREIGN KEY (ObjetoID) REFERENCES ArmaObjeto (ID) ON DELETE CASCADE,
        CONSTRAINT fk_aop_propiedad FOREIGN KEY (PropiedadID) REFERENCES ArmaPropiedad (ID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Importa (o reimporta) cuando cambia el archivo del documento.
    if (is_file(ARMERIA_DATA)) {
        $hash = sha1_file(ARMERIA_DATA) ?: '';
        $rows = armeria_all($db, "SELECT Valor FROM FavorMeta WHERE Clave='armeria_import'");
        if (($rows[0]['Valor'] ?? '') !== $hash) {
            armeria_importar($db);
            armeria_q($db, "INSERT INTO FavorMeta (Clave,Valor) VALUES ('armeria_import',?) ON DUPLICATE KEY UPDATE Valor=VALUES(Valor)", [$hash])->close();
        }
    }
}

/**
 * Importación repetible. Devuelve un resumen con lo creado y actualizado.
 * Los registros que el admin editó (Editado=1) o desactivó no se tocan.
 */
function armeria_importar(mysqli $db): array
{
    $data = json_decode((string)file_get_contents(ARMERIA_DATA), true);
    if (!is_array($data) || !isset($data['objetos'])) throw new RuntimeException('armamento.json no es válido.');
    $sum = ['creados' => 0, 'actualizados' => 0, 'respetados' => 0, 'reglas' => 0, 'propiedades' => 0];

    $db->begin_transaction();
    try {
        foreach ($data['categorias'] as $c) {
            armeria_q($db, 'INSERT INTO ArmaCategoria (Clave,Nombre,Tipo,Orden) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE Nombre=VALUES(Nombre),Tipo=VALUES(Tipo),Orden=VALUES(Orden)',
                [$c['clave'], $c['nombre'], $c['tipo'], $c['orden']])->close();
        }
        armeria_q($db, "INSERT INTO FavorMeta (Clave,Valor) VALUES ('armeria_intro',?) ON DUPLICATE KEY UPDATE Valor=VALUES(Valor)", [(string)($data['propiedadesIntro'] ?? '')])->close();

        foreach ($data['propiedades'] as $p) {
            $stmt = armeria_q($db, 'INSERT INTO ArmaPropiedad (Clave,Nombre,TituloDocumento,Definicion,Orden) VALUES (?,?,?,?,?) '
                . 'ON DUPLICATE KEY UPDATE Nombre=IF(Editado,Nombre,VALUES(Nombre)),TituloDocumento=VALUES(TituloDocumento),Definicion=IF(Editado,Definicion,VALUES(Definicion)),Orden=VALUES(Orden)',
                [$p['clave'], $p['nombre'], $p['tituloDocumento'], $p['definicion'], $p['orden']]);
            $sum['propiedades'] += $stmt->affected_rows > 0 ? 1 : 0;
            $stmt->close();
        }
        foreach ($data['reglas'] as $r) {
            $stmt = armeria_q($db, 'INSERT INTO ArmaRegla (Clave,Titulo,Texto) VALUES (?,?,?) '
                . 'ON DUPLICATE KEY UPDATE Titulo=IF(Editado,Titulo,VALUES(Titulo)),Texto=IF(Editado,Texto,VALUES(Texto))',
                [$r['clave'], $r['titulo'], $r['texto']]);
            $sum['reglas'] += $stmt->affected_rows > 0 ? 1 : 0;
            $stmt->close();
        }

        $propIds = [];
        foreach (armeria_all($db, 'SELECT ID,Clave FROM ArmaPropiedad') as $r) $propIds[$r['Clave']] = (int)$r['ID'];
        $reglaIds = [];
        foreach (armeria_all($db, 'SELECT ID,Clave FROM ArmaRegla') as $r) $reglaIds[$r['Clave']] = (int)$r['ID'];
        $existing = [];
        foreach (armeria_all($db, 'SELECT ID,Clave,Editado,Activo FROM ArmaObjeto') as $r) $existing[$r['Clave']] = $r;

        foreach ($data['objetos'] as $o) {
            $row = armeria_fila_desde_json($o, $reglaIds);
            $prev = $existing[$o['clave']] ?? null;
            if ($prev && ((int)$prev['Editado'] === 1 || (int)$prev['Activo'] === 0)) {
                $sum['respetados']++;
                continue;
            }
            $cols = array_keys($row);
            if ($prev) {
                $set = implode(',', array_map(static fn(string $c): string => "$c=?", $cols));
                armeria_q($db, "UPDATE ArmaObjeto SET $set WHERE ID=?", array_merge(array_values($row), [$prev['ID']]))->close();
                $id = (int)$prev['ID'];
                $sum['actualizados']++;
            } else {
                $marks = implode(',', array_fill(0, count($cols) + 2, '?'));
                armeria_q($db, 'INSERT INTO ArmaObjeto (Clave,Origen,' . implode(',', $cols) . ") VALUES ($marks)",
                    array_merge([$o['clave'], 'documento'], array_values($row)))->close();
                $id = (int)$db->insert_id;
                $sum['creados']++;
            }
            armeria_guardar_propiedades($db, $id, array_map(static fn(array $p): array => ['clave' => $p['clave'], 'detalle' => $p['detalle']], $o['propiedades']), $propIds);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return $sum;
}

function armeria_fila_desde_json(array $o, array $reglaIds): array
{
    return [
        'Tipo' => $o['tipo'],
        'Categoria' => $o['categoria'],
        'Nombre' => $o['nombre'],
        'Coste' => (string)($o['coste'] ?? ''),
        'CosteCobre' => $o['costeCobre'] ?? null,
        'Dano' => (string)($o['dano'] ?? ''),
        'DanoDados' => (string)($o['danoDados'] ?? ''),
        'DanoPromedio' => $o['danoPromedio'] ?? null,
        'TiposDano' => implode(',', $o['tiposDano'] ?? []),
        'PropiedadesTexto' => (string)($o['propiedadesTexto'] ?? ''),
        'Alcance' => (string)($o['alcance'] ?? ''),
        'CA' => (string)($o['ca'] ?? ''),
        'CABase' => $o['caBase'] ?? null,
        'CABono' => $o['caBono'] ?? null,
        'ModDes' => $o['modDes'] ?? null,
        'DesMax' => $o['desMax'] ?? null,
        'Fuerza' => (string)($o['fuerza'] ?? ''),
        'FuerzaMin' => $o['fuerzaMin'] ?? null,
        'Sigilo' => (string)($o['sigilo'] ?? ''),
        'SigiloDesventaja' => !empty($o['sigiloDesventaja']) ? 1 : 0,
        'ReglaID' => !empty($o['regla']) ? ($reglaIds[$o['regla']] ?? null) : null,
        'Notas' => implode("\n", $o['avisos'] ?? []),
        'Original' => json_encode($o['original'] ?? [], JSON_UNESCAPED_UNICODE),
        'Orden' => (int)$o['orden'],
    ];
}

/** $props = [['clave' => 'versatil', 'detalle' => '1d8'], …] en el orden del documento. */
function armeria_guardar_propiedades(mysqli $db, int $objetoId, array $props, ?array $propIds = null): void
{
    if ($propIds === null) {
        $propIds = [];
        foreach (armeria_all($db, 'SELECT ID,Clave FROM ArmaPropiedad') as $r) $propIds[$r['Clave']] = (int)$r['ID'];
    }
    armeria_q($db, 'DELETE FROM ArmaObjetoPropiedad WHERE ObjetoID=?', [$objetoId])->close();
    $orden = 0;
    $seen = [];
    foreach ($props as $p) {
        $pid = $propIds[$p['clave']] ?? null;
        if (!$pid || isset($seen[$pid])) continue;
        $seen[$pid] = true;
        armeria_q($db, 'INSERT INTO ArmaObjetoPropiedad (ObjetoID,PropiedadID,Detalle,Orden) VALUES (?,?,?,?)',
            [$objetoId, $pid, mb_substr(trim((string)$p['detalle']), 0, 60, 'UTF-8'), ++$orden])->close();
    }
}

/**
 * Todo el catálogo en un solo viaje (unas 200 filas): la página filtra y busca
 * en el navegador, sin recargar. Los inactivos y las notas de revisión solo
 * llegan al admin.
 */
function armeria_catalogo(mysqli $db, bool $admin): array
{
    armeria_ensure_schema($db);
    $where = $admin ? '' : 'WHERE o.Activo=1';
    $objetos = armeria_all($db, 'SELECT o.*, c.Nombre AS CategoriaNombre FROM ArmaObjeto o LEFT JOIN ArmaCategoria c ON c.Clave=o.Categoria '
        . "$where ORDER BY c.Orden, o.Orden, o.Nombre");
    $links = [];
    foreach (armeria_all($db, 'SELECT op.ObjetoID, p.Clave, op.Detalle FROM ArmaObjetoPropiedad op JOIN ArmaPropiedad p ON p.ID=op.PropiedadID ORDER BY op.ObjetoID, op.Orden') as $l) {
        $links[(int)$l['ObjetoID']][] = ['clave' => $l['Clave'], 'detalle' => $l['Detalle']];
    }
    $reglas = [];
    foreach (armeria_all($db, 'SELECT ID,Clave,Titulo,Texto,Editado FROM ArmaRegla ORDER BY Titulo') as $r) {
        $reglas[] = ['id' => (int)$r['ID'], 'clave' => $r['Clave'], 'titulo' => $r['Titulo'], 'texto' => $r['Texto']] + ($admin ? ['editado' => (bool)$r['Editado']] : []);
    }
    $props = [];
    foreach (armeria_all($db, 'SELECT ID,Clave,Nombre,TituloDocumento,Definicion,Orden,Origen,Editado FROM ArmaPropiedad ORDER BY Nombre') as $p) {
        $props[] = ['id' => (int)$p['ID'], 'clave' => $p['Clave'], 'nombre' => $p['Nombre'], 'tituloDocumento' => $p['TituloDocumento'], 'definicion' => $p['Definicion']]
            + ($admin ? ['editado' => (bool)$p['Editado'], 'origen' => $p['Origen']] : []);
    }
    $out = [];
    foreach ($objetos as $o) {
        $id = (int)$o['ID'];
        $item = [
            'id' => $id,
            'clave' => $o['Clave'],
            'tipo' => $o['Tipo'],
            'categoria' => $o['Categoria'],
            'nombre' => $o['Nombre'],
            'coste' => $o['Coste'],
            'costeCobre' => $o['CosteCobre'] !== null ? (int)$o['CosteCobre'] : null,
            'reglaId' => $o['ReglaID'] !== null ? (int)$o['ReglaID'] : null,
            'propiedades' => $links[$id] ?? [],
        ];
        if ($o['Tipo'] === 'arma') {
            $item += [
                'dano' => $o['Dano'],
                'danoPromedio' => $o['DanoPromedio'] !== null ? (float)$o['DanoPromedio'] : null,
                'tiposDano' => $o['TiposDano'] !== '' ? explode(',', $o['TiposDano']) : [],
                'propiedadesTexto' => $o['PropiedadesTexto'],
                'alcance' => $o['Alcance'],
            ];
        } else {
            $item += [
                'ca' => $o['CA'],
                'caBase' => $o['CABase'] !== null ? (int)$o['CABase'] : null,
                'caBono' => $o['CABono'] !== null ? (int)$o['CABono'] : null,
                'modDes' => $o['ModDes'],
                'desMax' => $o['DesMax'] !== null ? (int)$o['DesMax'] : null,
                'fuerza' => $o['Fuerza'],
                'fuerzaMin' => $o['FuerzaMin'] !== null ? (int)$o['FuerzaMin'] : null,
                'sigilo' => $o['Sigilo'],
                'sigiloDesventaja' => (bool)$o['SigiloDesventaja'],
            ];
            if ($o['Tipo'] === 'escudo') $item['propiedadesTexto'] = $o['PropiedadesTexto'];
        }
        if ($admin) {
            $item += [
                'activo' => (bool)$o['Activo'],
                'origen' => $o['Origen'],
                'editado' => (bool)$o['Editado'],
                'notas' => (string)($o['Notas'] ?? ''),
                'original' => json_decode((string)($o['Original'] ?? '[]'), true) ?: [],
            ];
        }
        $out[] = $item;
    }
    $cats = [];
    foreach (armeria_all($db, 'SELECT Clave,Nombre,Tipo FROM ArmaCategoria ORDER BY Orden') as $c) $cats[] = ['clave' => $c['Clave'], 'nombre' => $c['Nombre'], 'tipo' => $c['Tipo']];
    $intro = armeria_all($db, "SELECT Valor FROM FavorMeta WHERE Clave='armeria_intro'");
    return [
        'categorias' => $cats,
        'propiedades' => $props,
        'propiedadesIntro' => (string)($intro[0]['Valor'] ?? ''),
        'reglas' => $reglas,
        'objetos' => $out,
    ];
}

/** Clave estable para un objeto nuevo: «espada-de-plata», «espada-de-plata-2»… */
function armeria_slug(string $text): string
{
    $s = mb_strtolower(trim($text), 'UTF-8');
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', "'" => '']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'objeto';
}

function armeria_clave_libre(mysqli $db, string $tabla, string $base): string
{
    $tabla = in_array($tabla, ['ArmaObjeto', 'ArmaRegla', 'ArmaPropiedad'], true) ? $tabla : 'ArmaObjeto';
    $base = mb_substr($base, 0, 100);
    $k = $base;
    for ($i = 2; $i < 500; $i++) {
        $rows = armeria_all($db, "SELECT 1 FROM $tabla WHERE Clave=? LIMIT 1", [$k]);
        if (!$rows) return $k;
        $k = $base . '-' . $i;
    }
    throw new RuntimeException('No pude crear una clave única.');
}

/** «10 Oro», «5 Plata», «2 Cobre», «800 po»… a piezas de cobre (para ordenar y filtrar). */
function armeria_coste_cobre(string $coste): ?int
{
    $n = mb_strtolower(trim($coste), 'UTF-8');
    if (!preg_match('/^(\d+(?:[.,]\d+)?)\s*(cobre|pc|plata|pp|oro|po|platino|ppt)\.?$/u', $n, $m)) return null;
    $mult = ['cobre' => 1, 'pc' => 1, 'plata' => 10, 'pp' => 10, 'oro' => 100, 'po' => 100, 'platino' => 1000, 'ppt' => 1000][$m[2]];
    return (int)round((float)str_replace(',', '.', $m[1]) * $mult);
}

/** «2d6 cortante» → ['2d6', 7.0, ['cortante']]. */
function armeria_analizar_dano(string $dano): array
{
    $n = mb_strtolower($dano, 'UTF-8');
    $tipos = [];
    foreach (ARMERIA_TIPOS_DANO as $t) if (preg_match('/(?<![\p{L}])' . preg_quote($t, '/') . '(?![\p{L}])/u', $n)) $tipos[] = $t;
    $dados = '';
    $prom = null;
    if (preg_match('/\b(\d+)d(\d+)\b/', $n, $m)) {
        $dados = $m[0];
        $prom = (int)$m[1] * ((int)$m[2] + 1) / 2;
    } elseif (preg_match('/\b(\d+)\b/', $n, $m)) {
        $dados = $m[1];
        $prom = (float)$m[1];
    }
    return [$dados, $prom, $tipos];
}
