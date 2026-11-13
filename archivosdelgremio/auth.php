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

/** Personajes que el archivero retiró de Archivos a propósito: [id => true]. */
function guild_removed_profiles(mysqli $db): array
{
    $row = $db->query("SELECT Valor FROM FavorMeta WHERE Clave='archivos_retirados' LIMIT 1")->fetch_assoc();
    $list = json_decode((string)($row['Valor'] ?? '[]'), true);
    return is_array($list) ? array_fill_keys(array_map('strval', $list), true) : [];
}

/** Apunta (o quita, con $removed=false) un personaje retirado de Archivos. */
function guild_mark_removed_profile(mysqli $db, string $id, bool $removed = true): void
{
    $list = guild_removed_profiles($db);
    if ($removed) $list[$id] = true; else unset($list[$id]);
    $json = json_encode(array_keys($list), JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare("INSERT INTO FavorMeta (Clave,Valor) VALUES ('archivos_retirados',?) ON DUPLICATE KEY UPDATE Valor=VALUES(Valor)");
    $stmt->bind_param('s', $json);
    $stmt->execute();
    $stmt->close();
}
