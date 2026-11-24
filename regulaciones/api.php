<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib.php';

function reg_respond(bool $ok, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reg_respond(false, ['error' => 'Método no permitido.'], 405);
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) throw new RuntimeException('Pedido no válido.');
    favor_verify_csrf((string)($data['csrf'] ?? ''));
    favor_require_admin();
    favor_session_close();

    $action = (string)($data['action'] ?? '');
    $id = (string)($data['id'] ?? '');

    if ($action === 'save') {
        $rule = reg_clean(is_array($data['rule'] ?? null) ? $data['rule'] : []);
        $savedId = reg_update(static function (array $file, &$result) use ($rule, $id): array {
            $rule['updated'] = date('Y-m-d');
            $ids = array_column($file['rules'], 'id');
            $at = $id !== '' ? array_search($id, $ids, true) : false;
            if ($id !== '' && $at === false) throw new RuntimeException('Esa regulación ya no existe. Recarga la página.');
            if ($at !== false) {
                $file['rules'][$at] = ['id' => $id] + $rule;
                $result = $id;
            } else {
                $base = reg_slug($rule['title']);
                $new = $base;
                for ($n = 2; in_array($new, $ids, true); $n++) $new = $base . '-' . $n;
                $file['rules'][] = ['id' => $new] + $rule;
                $result = $new;
            }
            if (!in_array($rule['category'], $file['categories'], true)) $file['categories'][] = $rule['category'];
            return $file;
        });
        reg_respond(true, ['id' => $savedId]);
    }

    if ($action === 'delete') {
        reg_update(static function (array $file) use ($id): array {
            $before = count($file['rules']);
            $file['rules'] = array_values(array_filter($file['rules'], static fn(array $r): bool => ($r['id'] ?? '') !== $id));
            if (count($file['rules']) === $before) throw new RuntimeException('Esa regulación ya no existe. Recarga la página.');
            // Una categoría creada a mano que se queda vacía desaparece del índice.
            $used = array_column($file['rules'], 'category');
            $file['categories'] = array_values(array_filter($file['categories'], static fn(string $c): bool => isset(REG_CATEGORY_ICONS[$c]) || in_array($c, $used, true)));
            return $file;
        });
        reg_respond(true);
    }

    // ── Patch Notes ── «key» es la versión original del parche que se edita (vacía = nuevo).
    if ($action === 'save_patch') {
        $patch = reg_clean_patch(is_array($data['patch'] ?? null) ? $data['patch'] : []);
        $key = (string)($data['key'] ?? '');
        reg_update(static function (array $file) use ($patch, $key): array {
            $patches = reg_patches($file);
            $versions = array_column($patches, 'version');
            $at = $key !== '' ? array_search($key, $versions, true) : false;
            if ($key !== '' && $at === false) throw new RuntimeException('Ese parche ya no existe. Recarga la página.');
            $clash = array_search($patch['version'], $versions, true);
            if ($clash !== false && $clash !== $at) throw new RuntimeException('Ya hay un parche con la versión ' . $patch['version'] . '. Usa otro número.');
            if ($at !== false) $patches[$at] = $patch;
            else $patches[] = $patch;
            $file['patches'] = $patches;
            $file['patches'] = reg_patches($file);
            return $file;
        });
        reg_respond(true, ['id' => reg_patch_id($patch['version'])]);
    }

    if ($action === 'delete_patch') {
        $key = (string)($data['key'] ?? '');
        reg_update(static function (array $file) use ($key): array {
            $patches = reg_patches($file);
            $left = array_values(array_filter($patches, static fn(array $p): bool => $p['version'] !== $key));
            if (count($left) === count($patches)) throw new RuntimeException('Ese parche ya no existe. Recarga la página.');
            $file['patches'] = $left;
            return $file;
        });
        reg_respond(true);
    }

    reg_respond(false, ['error' => 'Acción desconocida.'], 400);
} catch (Throwable $e) {
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Error interno. Inténtalo otra vez.';
    if (!$e instanceof RuntimeException) error_log('regulaciones/api.php: ' . $e);
    reg_respond(false, ['error' => $msg], 400);
}
