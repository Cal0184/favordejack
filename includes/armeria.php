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
/** Ilustraciones: armeria/img/armas/<clave>.webp (ficha) y mini/<clave>.webp (lista), sacadas del Word ilustrado. */
const ARMERIA_IMG = __DIR__ . '/../armeria/img/armas';
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
        AtaqueAtributo varchar(10) NULL,
        AtaqueBase varchar(10) NULL,
        AtaqueMotivo text NULL,
        AtaqueExcepcion text NULL,
        AtaqueDuda text NULL,
        ClasifEditada tinyint(1) NOT NULL DEFAULT 0,
        Descripcion text NULL,
        UNIQUE KEY uq_armaobjeto_clave (Clave),
        KEY idx_armaobjeto_categoria (Categoria, Activo),
        KEY idx_armaobjeto_ataque (AtaqueAtributo),
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

    // Instalaciones con la primera versión del módulo: añade las columnas nuevas.
    foreach ([
        'AtaqueAtributo' => 'varchar(10) NULL', 'AtaqueBase' => 'varchar(10) NULL', 'AtaqueMotivo' => 'text NULL',
        'AtaqueExcepcion' => 'text NULL', 'AtaqueDuda' => 'text NULL', 'ClasifEditada' => 'tinyint(1) NOT NULL DEFAULT 0', 'Descripcion' => 'text NULL',
    ] as $col => $ddl) armeria_columna($db, 'ArmaObjeto', $col, $ddl);

    // Atributo de ataque: uno o varios modos por objeto (cuerpo a cuerpo, arrojadiza, disparo…).
    $db->query("CREATE TABLE IF NOT EXISTS ArmaAtaqueModo (
        ObjetoID int unsigned NOT NULL,
        Orden tinyint unsigned NOT NULL,
        Modo varchar(80) NOT NULL,
        Atributo varchar(10) NOT NULL,
        Dano varchar(60) NOT NULL DEFAULT '',
        Nota varchar(300) NOT NULL DEFAULT '',
        PRIMARY KEY (ObjetoID, Orden),
        KEY idx_aam_atributo (Atributo),
        CONSTRAINT fk_aam_objeto FOREIGN KEY (ObjetoID) REFERENCES ArmaObjeto (ID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Requisitos (Fuerza 20, tamaño…) y condiciones de uso; no son el atributo de ataque.
    $db->query("CREATE TABLE IF NOT EXISTS ArmaRequisito (
        ObjetoID int unsigned NOT NULL,
        Orden tinyint unsigned NOT NULL,
        Tipo varchar(12) NOT NULL DEFAULT 'requisito',
        Atributo varchar(10) NULL,
        Minimo tinyint unsigned NULL,
        Texto varchar(300) NOT NULL,
        PRIMARY KEY (ObjetoID, Orden),
        KEY idx_areq_atributo (Atributo),
        CONSTRAINT fk_areq_objeto FOREIGN KEY (ObjetoID) REFERENCES ArmaObjeto (ID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Aclaraciones, excepciones y notas de la administración (nunca se mezclan con el texto del Word).
    $db->query("CREATE TABLE IF NOT EXISTS ArmaNota (
        ID int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ObjetoID int unsigned NULL,
        Tipo varchar(12) NOT NULL DEFAULT 'aclaracion',
        Titulo varchar(160) NOT NULL,
        Texto mediumtext NOT NULL,
        Visible tinyint(1) NOT NULL DEFAULT 1,
        Autor varchar(50) NULL,
        Creado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Actualizado timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_armanota_objeto (ObjetoID),
        CONSTRAINT fk_armanota_objeto FOREIGN KEY (ObjetoID) REFERENCES ArmaObjeto (ID) ON DELETE CASCADE
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

function armeria_columna(mysqli $db, string $tabla, string $col, string $ddl): void
{
    $rows = armeria_all($db, 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1', [$tabla, $col]);
    if (!$rows) $db->query("ALTER TABLE `$tabla` ADD COLUMN `$col` $ddl");
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
        foreach (armeria_all($db, 'SELECT ID,Clave,Editado,Activo,ClasifEditada FROM ArmaObjeto') as $r) $existing[$r['Clave']] = $r;

        foreach ($data['objetos'] as $o) {
            $row = armeria_fila_desde_json($o, $reglaIds);
            $prev = $existing[$o['clave']] ?? null;
            // La clasificación (atributo y requisitos) se guarda aparte: solo la protege ClasifEditada.
            if ($prev && (int)$prev['ClasifEditada'] === 0) armeria_guardar_clasificacion($db, (int)$prev['ID'], $o);
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
            if (!$prev) armeria_guardar_clasificacion($db, $id, $o);
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

const ARMERIA_ATRIBUTOS = ['FUE', 'DES', 'FUE/DES', 'REVISAR'];
const ARMERIA_RESUMENES = ['FUE', 'DES', 'FUE/DES', 'MODOS', 'REVISAR'];
const ARMERIA_NOTA_TIPOS = ['aclaracion', 'excepcion', 'nota', 'errata'];

/** Resumen de los modos: un atributo, «MODOS» si cambian según el modo, «REVISAR» si alguno está pendiente. */
function armeria_resumen_ataque(array $modos): ?string
{
    $ats = array_values(array_unique(array_map(static fn(array $m): string => (string)$m['atributo'], $modos)));
    if (!$ats) return null;
    if (in_array('REVISAR', $ats, true)) return 'REVISAR';
    return count($ats) === 1 ? $ats[0] : 'MODOS';
}

/**
 * Guarda el atributo de ataque y los requisitos de un objeto desde el JSON del documento
 * ($o['ataque'], $o['requisitos']). AtaqueBase conserva la clasificación por reglas generales.
 */
function armeria_guardar_clasificacion(mysqli $db, int $id, array $o): void
{
    $a = is_array($o['ataque'] ?? null) ? $o['ataque'] : null;
    armeria_guardar_ataque($db, $id, $a ? $a['modos'] : [], [
        'base' => $a['base'] ?? null,
        'motivo' => $a['motivo'] ?? '',
        'excepcion' => $a['excepcion'] ?? '',
        'duda' => $a['duda'] ?? '',
    ], false);
    armeria_guardar_requisitos($db, $id, $o['requisitos'] ?? []);
}

/** $modos = [['modo'=>…, 'atributo'=>'FUE'|'DES'|'FUE/DES'|'REVISAR', 'dano'=>…, 'nota'=>…], …] */
function armeria_guardar_ataque(mysqli $db, int $id, array $modos, array $info, bool $porAdmin, bool $tocarBase = true): void
{
    $limpios = [];
    foreach ($modos as $m) {
        if (!is_array($m) || !in_array((string)($m['atributo'] ?? ''), ARMERIA_ATRIBUTOS, true)) continue;
        $limpios[] = [
            'modo' => mb_substr(trim((string)($m['modo'] ?? '')), 0, 80, 'UTF-8') ?: 'Ataque',
            'atributo' => (string)$m['atributo'],
            'dano' => mb_substr(trim((string)($m['dano'] ?? '')), 0, 60, 'UTF-8'),
            'nota' => mb_substr(trim((string)($m['nota'] ?? '')), 0, 300, 'UTF-8'),
        ];
    }
    $resumen = armeria_resumen_ataque($limpios);
    if (!empty($info['revisar']) && $limpios) $resumen = 'REVISAR';
    armeria_q($db, 'DELETE FROM ArmaAtaqueModo WHERE ObjetoID=?', [$id])->close();
    foreach (array_slice($limpios, 0, 8) as $i => $m) {
        armeria_q($db, 'INSERT INTO ArmaAtaqueModo (ObjetoID,Orden,Modo,Atributo,Dano,Nota) VALUES (?,?,?,?,?,?)', [$id, $i + 1, $m['modo'], $m['atributo'], $m['dano'], $m['nota']])->close();
    }
    $sql = 'UPDATE ArmaObjeto SET AtaqueAtributo=?,AtaqueMotivo=?,AtaqueExcepcion=?,AtaqueDuda=?' . ($tocarBase ? ',AtaqueBase=?' : '') . ($porAdmin ? ',ClasifEditada=1' : '') . ' WHERE ID=?';
    $params = [$resumen, (string)($info['motivo'] ?? ''), (string)($info['excepcion'] ?? ''), (string)($info['duda'] ?? '')];
    if ($tocarBase) $params[] = $info['base'] ?? null;
    $params[] = $id;
    armeria_q($db, $sql, $params)->close();
}

/** $reqs = [['tipo'=>'requisito'|'condicion', 'atributo'=>'FUE'|'DES'|'FUE/DES'|null, 'minimo'=>20|null, 'texto'=>…], …] */
function armeria_guardar_requisitos(mysqli $db, int $id, array $reqs): void
{
    armeria_q($db, 'DELETE FROM ArmaRequisito WHERE ObjetoID=?', [$id])->close();
    $n = 0;
    foreach ($reqs as $r) {
        if (!is_array($r)) continue;
        $texto = mb_substr(trim((string)($r['texto'] ?? '')), 0, 300, 'UTF-8');
        if ($texto === '' || $n >= 10) continue;
        $tipo = ($r['tipo'] ?? '') === 'condicion' ? 'condicion' : 'requisito';
        $at = in_array((string)($r['atributo'] ?? ''), ['FUE', 'DES', 'FUE/DES'], true) ? (string)$r['atributo'] : null;
        $min = isset($r['minimo']) && is_numeric($r['minimo']) ? max(1, min(30, (int)$r['minimo'])) : null;
        armeria_q($db, 'INSERT INTO ArmaRequisito (ObjetoID,Orden,Tipo,Atributo,Minimo,Texto) VALUES (?,?,?,?,?,?)', [$id, ++$n, $tipo, $at, $min, $texto])->close();
    }
}

/** Vuelve a la clasificación del documento para un objeto (deshace la corrección del admin). */
function armeria_restaurar_clasificacion(mysqli $db, int $id): bool
{
    $rows = armeria_all($db, 'SELECT Clave FROM ArmaObjeto WHERE ID=?', [$id]);
    if (!$rows || !is_file(ARMERIA_DATA)) return false;
    $data = json_decode((string)file_get_contents(ARMERIA_DATA), true);
    foreach ($data['objetos'] ?? [] as $o) {
        if ($o['clave'] !== $rows[0]['Clave']) continue;
        armeria_guardar_clasificacion($db, $id, $o);
        armeria_q($db, 'UPDATE ArmaObjeto SET ClasifEditada=0 WHERE ID=?', [$id])->close();
        return true;
    }
    return false;
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
    $modos = [];
    foreach (armeria_all($db, 'SELECT ObjetoID,Modo,Atributo,Dano,Nota FROM ArmaAtaqueModo ORDER BY ObjetoID, Orden') as $m) {
        $modos[(int)$m['ObjetoID']][] = ['modo' => $m['Modo'], 'atributo' => $m['Atributo'], 'dano' => $m['Dano'], 'nota' => $m['Nota']];
    }
    $reqs = [];
    foreach (armeria_all($db, 'SELECT ObjetoID,Tipo,Atributo,Minimo,Texto FROM ArmaRequisito ORDER BY ObjetoID, Orden') as $r) {
        $reqs[(int)$r['ObjetoID']][] = ['tipo' => $r['Tipo'], 'atributo' => $r['Atributo'], 'minimo' => $r['Minimo'] !== null ? (int)$r['Minimo'] : null, 'texto' => $r['Texto']];
    }
    $notas = [];
    foreach (armeria_all($db, 'SELECT ID,ObjetoID,Tipo,Titulo,Texto,Visible,Autor,Actualizado FROM ArmaNota ' . ($admin ? '' : 'WHERE Visible=1 ') . 'ORDER BY Tipo, Titulo') as $n) {
        $notas[] = ['id' => (int)$n['ID'], 'objetoId' => $n['ObjetoID'] !== null ? (int)$n['ObjetoID'] : null, 'tipo' => $n['Tipo'], 'titulo' => $n['Titulo'], 'texto' => $n['Texto'],
            'visible' => (bool)$n['Visible'], 'fecha' => substr((string)$n['Actualizado'], 0, 10)] + ($admin ? ['autor' => (string)$n['Autor']] : []);
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
            'requisitos' => $reqs[$id] ?? [],
            'descripcion' => (string)($o['Descripcion'] ?? ''),
            // Avisos de la importación (datos dudosos del Word): los ve todo el mundo en «Reglas y notas».
            'notas' => (string)($o['Notas'] ?? ''),
            'ataque' => $o['AtaqueAtributo'] === null ? null : [
                'atributo' => $o['AtaqueAtributo'],
                'base' => $o['AtaqueBase'],
                'modos' => $modos[$id] ?? [],
                'motivo' => (string)$o['AtaqueMotivo'],
                'excepcion' => (string)$o['AtaqueExcepcion'],
                'duda' => (string)$o['AtaqueDuda'],
                'editado' => (bool)$o['ClasifEditada'],
            ],
        ];
        if ($img = armeria_imagen($o['Clave'])) $item['imagen'] = $img;
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
                'original' => json_decode((string)($o['Original'] ?? '[]'), true) ?: [],
            ];
        }
        $out[] = $item;
    }
    $cats = [];
    foreach (armeria_all($db, 'SELECT Clave,Nombre,Tipo FROM ArmaCategoria ORDER BY Orden') as $c) $cats[] = ['clave' => $c['Clave'], 'nombre' => $c['Nombre'], 'tipo' => $c['Tipo']];
    $intro = armeria_all($db, "SELECT Valor FROM FavorMeta WHERE Clave='armeria_intro'");
    $total = (int)armeria_all($db, 'SELECT COUNT(*) AS n FROM ArmaObjeto WHERE Activo=1')[0]['n'];
    return [
        'total' => $total,
        'notas' => $notas,
        'categorias' => $cats,
        'propiedades' => $props,
        'propiedadesIntro' => (string)($intro[0]['Valor'] ?? ''),
        'reglas' => $reglas,
        'objetos' => $out,
    ];
}

/** Clave estable para un objeto nuevo: «espada-de-plata», «espada-de-plata-2»… */
/** Rutas (relativas a /armeria/) de la ilustración del objeto, o null si no tiene. */
function armeria_imagen(string $clave): ?array
{
    if (!preg_match('/^[a-z0-9-]+$/', $clave)) return null;
    $f = ARMERIA_IMG . "/$clave.webp";
    if (!is_file($f)) return null;
    $v = '?v=' . filemtime($f);
    $mini = is_file(ARMERIA_IMG . "/mini/$clave.webp") ? "img/armas/mini/$clave.webp$v" : "img/armas/$clave.webp$v";
    return ['ficha' => "img/armas/$clave.webp$v", 'mini' => $mini];
}

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

/**
 * Clasificación por reglas generales (la misma que armeria/herramientas/clasificar-ataque.py),
 * para objetos nuevos o editados sin excepción propia. Devuelve [modos, motivo].
 */
function armeria_clasificar_general(string $categoria, array $propClaves): array
{
    $ps = array_flip($propClaves);
    $distancia = str_ends_with($categoria, 'distancia');
    $sutil = isset($ps['sutil']);
    $modos = [];
    $motivo = [];
    if ($distancia) {
        $at = $sutil ? 'FUE/DES' : 'DES';
        $modos[] = ['modo' => isset($ps['arrojadiza']) && !isset($ps['municion']) ? 'Arrojadiza' : 'A distancia', 'atributo' => $at, 'dano' => '', 'nota' => ''];
        $motivo[] = $sutil
            ? 'Es un arma a distancia con la propiedad Sutil: puedes elegir Fuerza o Destreza para las tiradas de ataque y daño (el mismo modificador en las dos).'
            : 'Es un arma a distancia: usa Destreza para las tiradas de ataque y daño.';
    } else {
        $at = $sutil ? 'FUE/DES' : 'FUE';
        $modos[] = ['modo' => 'Cuerpo a cuerpo', 'atributo' => $at, 'dano' => '', 'nota' => ''];
        $motivo[] = $sutil
            ? 'Esta arma posee la propiedad Sutil, por lo que puedes elegir Fuerza o Destreza para las tiradas de ataque y daño (el mismo modificador en las dos).'
            : 'Es un arma cuerpo a cuerpo sin la propiedad Sutil: usa Fuerza para las tiradas de ataque y daño.';
        if (isset($ps['arrojadiza'])) {
            $modos[] = ['modo' => 'Arrojadiza', 'atributo' => $at, 'dano' => '', 'nota' => ''];
            $motivo[] = 'Si la lanzas (Arrojadiza), usas el mismo modificador que en cuerpo a cuerpo, como indica la propiedad Lanzado del documento.';
        }
        if (isset($ps['municion'])) {
            $modos[] = ['modo' => 'Disparo con munición', 'atributo' => 'DES', 'dano' => '', 'nota' => ''];
            $motivo[] = 'Su modo de disparo con munición es un ataque a distancia, que usa Destreza.';
        }
    }
    return [$modos, implode(' ', $motivo)];
}
