<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib.php';

function cr_respond(bool $ok, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Datos limpios de un evento (sin id ni fecha). */
function cr_clean_event(array $in, array $data, array $known): array
{
    $name = preg_replace('/\s+/u', ' ', cr_str($in, 'name', 140)) ?? '';
    if ($name === '') throw new RuntimeException('El nombre es obligatorio.');
    $year = cr_year($in['year'] ?? null);
    if ($year === null) throw new RuntimeException('El año es obligatorio.');
    $line = (string)($in['timelineId'] ?? 'main');
    if (!in_array($line, array_column($data['timelines'], 'id'), true)) $line = 'main';
    $music = (string)($in['music'] ?? '');
    if ($music !== '' && !in_array($music, array_column($data['music'], 'id'), true)) $music = '';
    return [
        'name' => $name,
        'year' => $year,
        'summary' => cr_str($in, 'summary', 8000),
        'timelineId' => $line,
        'characters' => cr_clean_characters($in['characters'] ?? [], $known),
        'image' => cr_media_path((string)($in['image'] ?? '')),
        'music' => $music,
        'draft' => !empty($in['draft']),
    ];
}

function cr_clean_line(array $in): array
{
    $name = preg_replace('/\s+/u', ' ', cr_str($in, 'name', 80)) ?? '';
    if ($name === '') throw new RuntimeException('El nombre es obligatorio.');
    $start = cr_year($in['startYear'] ?? null);
    $end = cr_year($in['endYear'] ?? null);
    if ($start === null || $end === null) throw new RuntimeException('Los años son obligatorios.');
    if ($end <= $start) throw new RuntimeException('El año de fin debe ser mayor al de inicio.');
    return ['name' => $name, 'color' => cr_color((string)($in['color'] ?? ''), '#8b5cf6'), 'startYear' => $start, 'endYear' => $end];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') cr_respond(false, ['error' => 'Método no permitido.'], 405);
    $multipart = str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data');
    $in = $multipart ? $_POST : json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in)) {
        // Un archivo más grande que post_max_size llega vacío.
        throw new RuntimeException($multipart || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
            ? 'El archivo pesa demasiado para el servidor (máximo ' . ini_get('post_max_size') . ').'
            : 'Pedido no válido.');
    }
    favor_verify_csrf((string)($in['csrf'] ?? ''));
    favor_require_admin();
    favor_session_close();

    $action = (string)($in['action'] ?? '');
    $id = (string)($in['id'] ?? '');

    if ($action === 'upload_image') {
        cr_respond(true, ['path' => cr_save_image($_FILES['file'] ?? [])]);
    }

    if ($action === 'upload_music') {
        $file = $_FILES['file'] ?? [];
        $path = cr_save_music($file);
        $name = mb_substr(trim(preg_replace('/\.[^.]+$/', '', (string)($in['name'] ?? $file['name'] ?? '')) ?? ''), 0, 100, 'UTF-8') ?: 'Canción';
        $track = ['id' => cr_id(), 'name' => $name, 'file' => $path];
        cr_update(static function (array $data) use ($track): array {
            $data['music'][] = $track;
            return $data;
        });
        cr_respond(true, ['track' => $track]);
    }

    if ($action === 'delete_music') {
        cr_update(static function (array $data) use ($id): array {
            $track = null;
            foreach ($data['music'] as $m) if (($m['id'] ?? '') === $id) $track = $m;
            if (!$track) throw new RuntimeException('Esa canción ya no existe.');
            $data['music'] = array_values(array_filter($data['music'], static fn($m) => ($m['id'] ?? '') !== $id));
            foreach ($data['events'] as &$e) if (($e['music'] ?? '') === $id) $e['music'] = '';
            unset($e);
            cr_delete_unused($data, (string)($track['file'] ?? ''));
            return $data;
        });
        cr_respond(true);
    }

    if ($action === 'save_event') {
        $known = cr_characters(favor_db());
        $saved = cr_update(static function (array $data, &$result) use ($in, $id, $known): array {
            $event = cr_clean_event(is_array($in['event'] ?? null) ? $in['event'] : [], $data, $known);
            $event['updated'] = date('Y-m-d');
            $at = $id !== '' ? array_search($id, array_column($data['events'], 'id'), true) : false;
            if ($id !== '' && $at === false) throw new RuntimeException('Ese evento ya no existe. Recarga la página.');
            if ($at !== false) {
                $old = (string)($data['events'][$at]['image'] ?? '');
                $result = ['id' => $id] + $event;
                $data['events'][$at] = $result;
                if ($old !== $event['image']) cr_delete_unused($data, $old);
            } else {
                $result = ['id' => cr_id()] + $event;
                $data['events'][] = $result;
            }
            return $data;
        });
        cr_respond(true, ['event' => $saved]);
    }

    if ($action === 'delete_event') {
        cr_update(static function (array $data) use ($id): array {
            $event = null;
            foreach ($data['events'] as $e) if (($e['id'] ?? '') === $id) $event = $e;
            if (!$event) throw new RuntimeException('Ese evento ya no existe. Recarga la página.');
            $data['events'] = array_values(array_filter($data['events'], static fn($e) => ($e['id'] ?? '') !== $id));
            cr_delete_unused($data, (string)($event['image'] ?? ''));
            return $data;
        });
        cr_respond(true);
    }

    if ($action === 'save_line') {
        if ($id === 'main') throw new RuntimeException('La línea principal no se edita.');
        $saved = cr_update(static function (array $data, &$result) use ($in, $id): array {
            $line = cr_clean_line(is_array($in['line'] ?? null) ? $in['line'] : []);
            $at = $id !== '' ? array_search($id, array_column($data['timelines'], 'id'), true) : false;
            if ($id !== '' && $at === false) throw new RuntimeException('Esa línea ya no existe. Recarga la página.');
            $result = ['id' => $at !== false ? $id : cr_id()] + $line;
            if ($at !== false) $data['timelines'][$at] = $result;
            else $data['timelines'][] = $result;
            return $data;
        });
        cr_respond(true, ['line' => $saved]);
    }

    if ($action === 'delete_line') {
        if ($id === 'main') throw new RuntimeException('La línea principal no se puede borrar.');
        cr_update(static function (array $data) use ($id): array {
            $before = count($data['timelines']);
            $data['timelines'] = array_values(array_filter($data['timelines'], static fn($t) => ($t['id'] ?? '') !== $id));
            if (count($data['timelines']) === $before) throw new RuntimeException('Esa línea ya no existe.');
            // Sus eventos pasan a la línea principal, como en la aplicación original.
            foreach ($data['events'] as &$e) if (($e['timelineId'] ?? '') === $id) $e['timelineId'] = 'main';
            unset($e);
            return $data;
        });
        cr_respond(true);
    }

    if ($action === 'rename') {
        $name = preg_replace('/\s+/u', ' ', cr_str($in, 'name', 80)) ?? '';
        if ($name === '') throw new RuntimeException('Escribe un nombre.');
        cr_update(static function (array $data) use ($name): array {
            $data['config']['campaignName'] = $name;
            return $data;
        });
        cr_respond(true);
    }

    // Traer lo guardado en la aplicación Crónicas del navegador (su archivo
    // «Exportar»). Las imágenes y canciones ya se subieron una por una; aquí
    // llegan los eventos y líneas con sus rutas nuevas.
    if ($action === 'import') {
        $known = cr_characters(favor_db());
        $count = cr_update(static function (array $data, &$result) use ($in, $known): array {
            $lines = is_array($in['timelines'] ?? null) ? $in['timelines'] : [];
            $lineMap = ['main' => 'main'];
            foreach ($lines as $t) {
                if (!is_array($t) || ($t['id'] ?? '') === 'main') continue;
                try { $clean = cr_clean_line($t); } catch (RuntimeException) { continue; }
                $newId = cr_id();
                $lineMap[(string)($t['id'] ?? '')] = $newId;
                $data['timelines'][] = ['id' => $newId] + $clean;
            }
            $result = 0;
            foreach (is_array($in['events'] ?? null) ? $in['events'] : [] as $e) {
                if (!is_array($e)) continue;
                $e['timelineId'] = $lineMap[(string)($e['timelineId'] ?? 'main')] ?? 'main';
                try { $clean = cr_clean_event($e, $data, $known); } catch (RuntimeException) { continue; }
                $data['events'][] = ['id' => cr_id()] + $clean + ['updated' => date('Y-m-d')];
                $result++;
            }
            $name = preg_replace('/\s+/u', ' ', cr_str($in, 'campaignName', 80)) ?? '';
            if ($name !== '' && ($data['config']['campaignName'] ?? 'Crónicas') === 'Crónicas') $data['config']['campaignName'] = $name;
            return $data;
        });
        cr_respond(true, ['events' => $count]);
    }

    cr_respond(false, ['error' => 'Acción desconocida.'], 400);
} catch (Throwable $e) {
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Error interno. Inténtalo otra vez.';
    if (!$e instanceof RuntimeException) error_log('cronicas/api.php: ' . $e);
    cr_respond(false, ['error' => $msg], 400);
}
