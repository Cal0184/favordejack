<?php
declare(strict_types=1);

/*
 * Respaldo desde la terminal (para servidores con SSH):
 *   php instalar/respaldo-cli.php exportar
 *   php instalar/respaldo-cli.php restaurar respaldos/favordejack-AAAAMMDD-HHMMSS.zip
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/respaldo.php';

$action = $argv[1] ?? '';
$db = favor_db();
try {
    if ($action === 'exportar') {
        echo favor_backup_export($db), "\n";
    } elseif ($action === 'restaurar' && isset($argv[2]) && is_file($argv[2])) {
        $summary = favor_backup_restore($db, $argv[2]);
        echo "Restaurado: {$summary['statements']} sentencias SQL, {$summary['files']} archivos.\n";
        echo "Estado anterior guardado en respaldos/{$summary['safety']}\n";
    } else {
        fwrite(STDERR, "Uso: php instalar/respaldo-cli.php exportar | restaurar paquete.zip\n");
        exit(1);
    }
} finally {
    $db->close();
}
