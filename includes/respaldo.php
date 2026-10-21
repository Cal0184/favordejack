<?php
declare(strict_types=1);

/*
 * Favor de Jack · paquete de respaldo («Exportar todo» / «Restaurar»).
 *
 * Un paquete .zip lleva todo lo que NO está en GitHub:
 *   base-de-datos.sql   todas las tablas con sus datos
 *   archivos/...        imágenes subidas, fichas PDF y los JSON que edita la web
 *   paquete.json        fecha, versión y recuento
 *
 * Con el código de GitHub + un paquete se monta la web completa en cualquier
 * servidor con PHP 8 y MySQL/MariaDB.
 */

require_once __DIR__ . '/paths.php';

const FAVOR_BACKUP_VERSION = 1;

/** Carpetas y archivos de datos que viajan en el paquete (relativos a la raíz de la web). */
function favor_backup_paths(): array
{
    return [
        'media/',
        'bancadigital/icons/',
        'bancadigital/characters/',
        'archivosdelgremio/fichas/',
        'tiendas/emporio/Cartas/',
        'tiendas/emporio/data/',
        'tiendas/balas-y-sangre/productos.json',
        'tiendas/ladamadelasllamas/productos.json',
        'tiendas/lapocionpicante/productos.json',
        'tiendas/lapocionpicante/registro-descuentos.json',
        'tiendas/descuentos.json',
        'tiendas/lavaritaeconomica/productos.json',
        'regulaciones/data/regulations.json',
        'mapa/tiendas.json',
    ];
}

function favor_backup_root(): string
{
    return dirname(__DIR__);
}

/** Carpeta privada donde se guardan los paquetes (bloqueada al navegador). */
function favor_backup_dir(): string
{
    $dir = favor_backup_root() . '/respaldos';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('No pude crear la carpeta respaldos/. Revisa los permisos.');
    }
    if (!is_file($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    }
    return $dir;
}

/** Nombre de paquete seguro (sin rutas): favordejack-AAAAMMDD-HHMMSS.zip */
function favor_backup_valid_name(string $name): bool
{
    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,120}\.zip$/', $name);
}

/** Paquetes guardados en respaldos/, el más reciente primero. */
function favor_backup_list(): array
{
    $dir = favor_backup_dir();
    $items = [];
    foreach (glob($dir . '/*.zip') ?: [] as $file) {
        $name = basename($file);
        if (!favor_backup_valid_name($name)) continue;
        $items[] = ['name' => $name, 'size' => filesize($file) ?: 0, 'time' => filemtime($file) ?: 0];
    }
    usort($items, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);
    return $items;
}

/* ===================== Exportar ===================== */

function favor_backup_sql_value(mysqli $db, $value): string
{
    if ($value === null) return 'NULL';
    return "'" . $db->real_escape_string((string)$value) . "'";
}

/** Vuelca todas las tablas a un .sql: una sentencia por línea (salvo CREATE TABLE). */
function favor_backup_dump_sql(mysqli $db, string $target): array
{
    $out = fopen($target, 'wb');
    if (!$out) throw new RuntimeException('No pude escribir el volcado de la base de datos.');

    fwrite($out, "-- Favor de Jack · base de datos · " . gmdate('Y-m-d H:i:s') . " UTC\n");
    fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET time_zone = '+00:00';\n");

    $tables = [];
    $result = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $result->fetch_row()) $tables[] = (string)$row[0];
    $result->free();

    $counts = [];
    foreach ($tables as $table) {
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $create = $db->query("SHOW CREATE TABLE $quoted")->fetch_row()[1];
        fwrite($out, "\nDROP TABLE IF EXISTS $quoted;\n" . $create . ";\n");

        $rows = $db->query("SELECT * FROM $quoted", MYSQLI_USE_RESULT);
        $columns = null;
        $batch = [];
        $count = 0;
        while ($row = $rows->fetch_assoc()) {
            if ($columns === null) {
                $columns = '(' . implode(', ', array_map(static fn($c) => '`' . str_replace('`', '``', (string)$c) . '`', array_keys($row))) . ')';
            }
            $batch[] = '(' . implode(', ', array_map(static fn($v) => favor_backup_sql_value($db, $v), array_values($row))) . ')';
            $count++;
            if (count($batch) >= 100) {
                fwrite($out, "INSERT INTO $quoted $columns VALUES " . implode(', ', $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) fwrite($out, "INSERT INTO $quoted $columns VALUES " . implode(', ', $batch) . ";\n");
        $rows->free();
        $counts[$table] = $count;
    }

    fwrite($out, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($out);
    return $counts;
}

/** Crea el paquete en respaldos/ y devuelve su ruta. */
function favor_backup_export(mysqli $db, string $label = ''): string
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('El servidor no tiene la extensión ZIP de PHP activada.');
    @set_time_limit(0);

    $dir = favor_backup_dir();
    $suffix = $label !== '' ? '-' . preg_replace('/[^a-z0-9-]/', '', strtolower($label)) : '';
    $name = 'favordejack-' . gmdate('Ymd-His') . $suffix . '.zip';
    $zipPath = $dir . '/' . $name;
    $sqlPath = $dir . '/.volcado-' . bin2hex(random_bytes(6)) . '.sql';

    try {
        $counts = favor_backup_dump_sql($db, $sqlPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('No pude crear el paquete .zip.');
        }
        $zip->addFile($sqlPath, 'base-de-datos.sql');

        $root = favor_backup_root();
        $files = 0;
        $bytes = 0;
        foreach (favor_backup_paths() as $path) {
            $full = $root . '/' . $path;
            if (is_file($full)) {
                $list = [$full];
            } elseif (is_dir($full)) {
                $list = [];
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) if ($file->isFile()) $list[] = $file->getPathname();
            } else {
                continue;
            }
            foreach ($list as $file) {
                $relative = ltrim(str_replace('\\', '/', substr($file, strlen($root))), '/');
                // Los .htaccess y cualquier código de estas carpetas ya vienen con GitHub.
                if (!favor_backup_allowed_path($relative)) continue;
                $entry = 'archivos/' . $relative;
                $zip->addFile($file, $entry);
                // Las imágenes ya vienen comprimidas: guardarlas tal cual es mucho más rápido.
                if (preg_match('/\.(?:png|jpe?g|webp|gif|pdf|mp3)$/i', $file)) $zip->setCompressionName($entry, ZipArchive::CM_STORE);
                $files++;
                $bytes += (int)filesize($file);
            }
        }

        $manifest = [
            'app' => 'favordejack',
            'version' => FAVOR_BACKUP_VERSION,
            'created' => gmdate('c'),
            'basePath' => PHP_SAPI === 'cli' ? null : favor_base_path(),
            'tables' => $counts,
            'files' => $files,
            'bytes' => $bytes,
        ];
        $zip->addFromString('paquete.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('LEEME.txt', favor_backup_readme());
        if (!$zip->close()) throw new RuntimeException('No pude terminar de escribir el paquete .zip.');
    } catch (Throwable $e) {
        if (is_file($zipPath)) @unlink($zipPath);
        throw $e;
    } finally {
        if (is_file($sqlPath)) @unlink($sqlPath);
    }

    return $zipPath;
}

function favor_backup_readme(): string
{
    return "PAQUETE DE RESPALDO · FAVOR DE JACK\n\n"
        . "Contiene la base de datos (base-de-datos.sql) y todos los archivos que la web\n"
        . "guarda fuera de GitHub (carpeta archivos/).\n\n"
        . "Para montar la web en otro servidor:\n"
        . "1. Sube el código de GitHub y crea config.local.php (ver config.example.php).\n"
        . "2. Sube este .zip a la carpeta respaldos/ de la web.\n"
        . "3. Entra como admin en admin/respaldo.php y pulsa «Restaurar».\n\n"
        . "No compartas este archivo: incluye las contraseñas cifradas de los usuarios.\n";
}

/* ===================== Restaurar ===================== */

/** ¿Puede escribirse esta ruta del paquete? Solo datos conocidos, nunca código. */
function favor_backup_allowed_path(string $relative): bool
{
    if ($relative === '' || str_contains($relative, '..') || str_contains($relative, '\\')
        || str_starts_with($relative, '/') || str_contains($relative, "\0")) {
        return false;
    }
    if (preg_match('/\.(?:php\d?|phtml|phar|htaccess|htpasswd|ini|cgi|pl|py|sh)$/i', $relative)
        || preg_match('#(?:^|/)\.ht#i', $relative)) {
        return false;
    }
    foreach (favor_backup_paths() as $allowed) {
        if (str_ends_with($allowed, '/') ? str_starts_with($relative, $allowed) : $relative === $allowed) return true;
    }
    return false;
}

/** Ejecuta un volcado hecho por favor_backup_dump_sql(). */
function favor_backup_run_sql(mysqli $db, $stream): int
{
    $statement = '';
    $executed = 0;
    while (($line = fgets($stream)) !== false) {
        if ($statement === '' && (str_starts_with($line, '-- ') || trim($line) === '')) continue;
        $statement .= $line;
        if (str_ends_with(rtrim($line, "\r\n"), ';')) {
            $db->query($statement);
            $statement = '';
            $executed++;
        }
    }
    if (trim($statement) !== '') throw new RuntimeException('El volcado SQL está incompleto.');
    return $executed;
}

/**
 * Restaura un paquete: primero guarda un respaldo automático del estado actual,
 * luego reemplaza la base de datos y escribe los archivos.
 */
function favor_backup_restore(mysqli $db, string $zipPath, bool $safetyBackup = true): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('El servidor no tiene la extensión ZIP de PHP activada.');
    @set_time_limit(0);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) throw new RuntimeException('No pude abrir el paquete .zip.');

    try {
        $manifest = json_decode((string)$zip->getFromName('paquete.json'), true);
        if (!is_array($manifest) || ($manifest['app'] ?? '') !== 'favordejack') {
            throw new RuntimeException('Este .zip no es un paquete de Favor de Jack.');
        }
        if ((int)($manifest['version'] ?? 0) > FAVOR_BACKUP_VERSION) {
            throw new RuntimeException('El paquete es de una versión más nueva de la web. Actualiza el código primero.');
        }
        if ($zip->locateName('base-de-datos.sql') === false) throw new RuntimeException('Al paquete le falta base-de-datos.sql.');

        // Revisa todas las rutas ANTES de tocar nada.
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (!str_starts_with($name, 'archivos/') || str_ends_with($name, '/')) continue;
            $relative = substr($name, strlen('archivos/'));
            if (!favor_backup_allowed_path($relative)) throw new RuntimeException("El paquete contiene una ruta no permitida: $relative");
            $entries[$i] = $relative;
        }

        $safety = $safetyBackup ? basename(favor_backup_export($db, 'antes-de-restaurar')) : null;

        $sql = $zip->getStream('base-de-datos.sql');
        if (!$sql) throw new RuntimeException('No pude leer base-de-datos.sql.');
        $statements = favor_backup_run_sql($db, $sql);
        fclose($sql);

        $root = favor_backup_root();
        $written = 0;
        foreach ($entries as $index => $relative) {
            $target = $root . '/' . $relative;
            $folder = dirname($target);
            if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
                throw new RuntimeException("No pude crear la carpeta de $relative.");
            }
            $in = $zip->getStream((string)$zip->getNameIndex($index));
            $out = fopen($target, 'wb');
            if (!$in || !$out) throw new RuntimeException("No pude escribir $relative.");
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            $written++;
        }
    } finally {
        $zip->close();
    }

    return [
        'created' => (string)($manifest['created'] ?? ''),
        'tables' => $manifest['tables'] ?? [],
        'statements' => $statements,
        'files' => $written,
        'safety' => $safety,
    ];
}
