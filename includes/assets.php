<?php
declare(strict_types=1);

require_once __DIR__ . '/paths.php';

/**
 * Favor de Jack · rutas de recursos compartidos.
 * Las imágenes de jugadores/personajes viven fuera de Banca para que cualquier
 * módulo pueda reutilizarlas sin depender de /bancadigital/.
 */

function favor_asset_db_path(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';

    // No tocamos URLs externas ni data URLs.
    if (preg_match('#^(?:https?://|data:)#i', $value)) return $value;

    // Convierte URLs absolutas del propio sitio a una ruta canónica de BD.
    $prefix = favor_base_path();
    if (str_starts_with($value, $prefix)) $value = substr($value, strlen($prefix));
    $value = ltrim($value, '/');

    // Compatibilidad con las rutas históricas de la Banca.
    if (str_starts_with($value, 'bancadigital/icons/')) {
        $value = 'media/icons/' . substr($value, strlen('bancadigital/icons/'));
    } elseif (str_starts_with($value, 'bancadigital/characters/')) {
        $value = 'media/characters/' . substr($value, strlen('bancadigital/characters/'));
    } elseif (str_starts_with($value, 'icons/')) {
        $value = 'media/icons/' . substr($value, strlen('icons/'));
    } elseif (str_starts_with($value, 'characters/')) {
        $value = 'media/characters/' . substr($value, strlen('characters/'));
    }

    return mb_substr($value, 0, 500, 'UTF-8');
}

function favor_asset_url(?string $value): string
{
    $value = favor_asset_db_path($value);
    if ($value === '') return '';
    if (preg_match('#^(?:https?://|data:)#i', $value)) return $value;
    return favor_base_path() . ltrim($value, '/');
}

function favor_safe_asset_input(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('#^https?://#i', $value)) return mb_substr($value, 0, 500, 'UTF-8');

    $path = favor_asset_db_path($value);
    if (preg_match('#^(?:media/(?:icons|characters|profiles|shops)|tiendas/emporio/Cartas|tiendas/(?:balas-y-sangre|ladamadelasllamas|lapocionpicante|lavaritaeconomica)/assets)/[A-Za-z0-9_\-./%()\x{00C0}-\x{024F} ]+$#u', $path)) {
        return $path;
    }
    return '';
}
