<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';

function guild_session_start(): void { favor_session_start(); }
function guild_csrf(): string { return favor_csrf(); }
function guild_is_admin(): bool { return favor_is_admin(); }
function guild_is_logged_in(): bool { return favor_is_logged_in(); }
function guild_user(): ?array { return favor_current_user(); }
function guild_admin_user(): ?array { return favor_is_admin() ? favor_current_user() : null; }
function guild_require_admin(): array { return favor_require_admin(); }
function guild_verify_csrf(?string $token): void { favor_verify_csrf($token); }

/**
 * Añade la categoría de expediente sin exigir una migración manual.
 * Los registros existentes quedan como Personaje; después pueden cambiarse a NPC.
 */
function guild_ensure_profile_category_schema(mysqli $db): void
{
    $check = $db->query("SHOW COLUMNS FROM PersonajePerfil LIKE 'Categoria'");
    $exists = $check && $check->num_rows > 0;
    if ($check) $check->free();
    if ($exists) return;

    $db->query("ALTER TABLE PersonajePerfil ADD COLUMN Categoria VARCHAR(20) NOT NULL DEFAULT 'Personaje' AFTER Estado");
}
