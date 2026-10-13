<?php
declare(strict_types=1);

/*
 * Configuración de este servidor.
 *
 * Todo lo que cambia de un hosting a otro vive en UN archivo fuera de Git:
 * config.local.php, en la raíz de la web (al lado de index.php). La plantilla
 * es config.example.php.
 *
 * Si config.local.php no existe se usa el antiguo bancadigital/config.php,
 * así el servidor actual sigue funcionando sin tocar nada.
 */
function favor_config(): array
{
    static $config = null;
    if ($config !== null) return $config;

    $root = dirname(__DIR__);
    $config = [];
    if (is_file($root . '/config.local.php')) {
        $loaded = require $root . '/config.local.php';
        if (is_array($loaded)) $config = $loaded;
    } elseif (is_file($root . '/bancadigital/config.php')) {
        $legacy = require $root . '/bancadigital/config.php';
        if (is_array($legacy)) $config = ['db' => $legacy];
    }
    if (!isset($config['db']) || !is_array($config['db'])) $config['db'] = [];
    return $config;
}
