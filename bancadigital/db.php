<?php
declare(strict_types=1);

/*
 * Conexión MySQL aislada.
 * La función anónima evita que las variables de conexión puedan sobrescribir
 * variables del archivo que hace require de db.php (por ejemplo, las del login).
 */
return (static function (): mysqli {
    require_once dirname(__DIR__) . '/includes/config.php';
    $dbConfig = favor_config()['db'];
    if (!$dbConfig) {
        throw new RuntimeException('Falta la configuración de la base de datos (config.local.php).');
    }

    $dbHost = trim((string)($dbConfig['host'] ?? 'localhost'));
    $dbPort = (int)($dbConfig['port'] ?? 3306);
    $dbName = trim((string)($dbConfig['database'] ?? 'cal_cal'));
    $dbUsername = trim((string)($dbConfig['username'] ?? ''));
    $dbPassword = (string)($dbConfig['password'] ?? '');

    if (
        $dbName === '' ||
        $dbUsername === '' ||
        str_starts_with($dbUsername, 'REEMPLAZAR_') ||
        str_starts_with($dbPassword, 'REEMPLAZAR_')
    ) {
        throw new RuntimeException('Completa las credenciales MySQL en config.local.php.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName, $dbPort);
    $connection->set_charset('utf8mb4');
    $connection->query("SET time_zone = '+00:00'");

    return $connection;
})();
