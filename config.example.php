<?php
declare(strict_types=1);

/*
 * Plantilla de configuración de Favor de Jack.
 *
 * 1. Copia este archivo en el servidor con el nombre config.local.php
 *    (en la misma carpeta que index.php).
 * 2. Rellena los datos del USUARIO DE BASE DE DATOS creado en el panel del hosting.
 *
 * config.local.php nunca se sube a GitHub (está en .gitignore).
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'cal_cal',
        'username' => 'REEMPLAZAR_USUARIO',
        'password' => 'REEMPLAZAR_CONTRASEÑA',
    ],

    // Opcional. Déjalo vacío: la web detecta sola si vive en «/» o en una carpeta.
    // Solo si tu hosting lo detecta mal, pon la carpeta, por ejemplo '/favordejack/'.
    'base_path' => '',
];
