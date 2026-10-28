<?php
declare(strict_types=1);

/*
 * Avisos en Discord por webhook.
 *
 * El DM pega el enlace del webhook en el Panel del DM. Se puede usar uno solo
 * para todo o uno distinto por tipo de aviso (compras, misiones, tiradas).
 * Se guarda en admin/data/discord.json (el navegador no puede leerlo; viaja en
 * el respaldo):
 *   webhooks: { general, compras, misiones, tiradas }   enlaces (vacío = usa general)
 *   events:   { compras, misiones, tiradas }            true/false
 *   log:      últimos envíos con su resultado
 *
 * Si Discord no responde, la web sigue igual: el aviso solo queda en el log.
 */

const FAVOR_DISCORD_FILE = __DIR__ . '/../admin/data/discord.json';
const FAVOR_DISCORD_EVENTS = [
    'compras' => 'Compras aprobadas en las tiendas',
    'misiones' => 'Misiones: nuevas, iniciadas, terminadas o eliminadas (tablón y Archivos)',
    'tiradas' => 'Tiradas críticas (20 o 1 natural en un d20)',
];
const FAVOR_DISCORD_COLORS = ['compras' => 0xC49251, 'misiones' => 0x5A7A3D, 'tiradas' => 0xB0413E, 'prueba' => 0x4F6F8F];
const FAVOR_DISCORD_LOG_MAX = 30;

function favor_discord_config(): array
{
    $raw = is_file(FAVOR_DISCORD_FILE) ? json_decode((string)@file_get_contents(FAVOR_DISCORD_FILE), true) : null;
    $raw = is_array($raw) ? $raw : [];
    $webhooks = ['general' => ''];
    foreach (array_keys(FAVOR_DISCORD_EVENTS) as $k) $webhooks[$k] = '';
    foreach ($webhooks as $k => $_) $webhooks[$k] = (string)($raw['webhooks'][$k] ?? '');
    $events = [];
    foreach (array_keys(FAVOR_DISCORD_EVENTS) as $k) $events[$k] = (bool)($raw['events'][$k] ?? true);
    return ['webhooks' => $webhooks, 'events' => $events, 'invite' => (string)($raw['invite'] ?? ''), 'log' => array_values(array_filter((array)($raw['log'] ?? []), 'is_array'))];
}

/** Guarda con candado; $change recibe la configuración y la devuelve. */
function favor_discord_update(callable $change): array
{
    $dir = dirname(FAVOR_DISCORD_FILE);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('No pude crear la carpeta admin/data/.');
    $lock = @fopen(FAVOR_DISCORD_FILE . '.lock', 'c');
    if (!$lock) throw new RuntimeException('No pude abrir el candado de discord.json. Revisa los permisos de admin/data/.');
    try {
        flock($lock, LOCK_EX);
        $config = $change(favor_discord_config());
        $config['log'] = array_slice(array_values((array)($config['log'] ?? [])), 0, FAVOR_DISCORD_LOG_MAX);
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = FAVOR_DISCORD_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if ($json === false || @file_put_contents($tmp, $json) === false || !@rename($tmp, FAVOR_DISCORD_FILE)) {
            @unlink($tmp);
            throw new RuntimeException('No pude guardar discord.json. Revisa los permisos de admin/data/.');
        }
        return $config;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Acepta solo enlaces de webhook de Discord ('' = sin enlace). */
function favor_discord_clean_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('#^https://(?:(?:ptb|canary)\.)?discord(?:app)?\.com/api/webhooks/\d+/[A-Za-z0-9_\-]+$#', $url)) {
        throw new RuntimeException('Ese enlace no parece un webhook de Discord. Debe empezar por https://discord.com/api/webhooks/');
    }
    return $url;
}

/** El enlace a usar para un tipo de aviso, o '' si está apagado o sin enlace. */
function favor_discord_url_for(array $config, string $event): string
{
    if ($event !== 'prueba' && empty($config['events'][$event])) return '';
    $url = (string)($config['webhooks'][$event] ?? '');
    return $url !== '' ? $url : (string)$config['webhooks']['general'];
}

/**
 * Manda un aviso. $fields: [[nombre, valor], …]. Devuelve '' si salió bien o el
 * motivo del fallo. Nunca lanza: un fallo de Discord no rompe la web.
 */
function favor_discord_send(string $event, string $title, string $text = '', array $fields = [], ?string $url = null): string
{
    try {
        $config = favor_discord_config();
        $url ??= favor_discord_url_for($config, $event);
        if ($url === '') return 'Sin webhook para «' . $event . '».';

        $embed = [
            'title' => mb_substr($title, 0, 250, 'UTF-8'),
            'color' => FAVOR_DISCORD_COLORS[$event] ?? 0xC49251,
            'timestamp' => gmdate('c'),
            'footer' => ['text' => 'Favor de Jack'],
        ];
        if ($text !== '') $embed['description'] = mb_substr($text, 0, 3500, 'UTF-8');
        foreach (array_slice($fields, 0, 10) as [$name, $value]) {
            if ((string)$value === '') continue;
            $embed['fields'][] = ['name' => mb_substr((string)$name, 0, 250, 'UTF-8'), 'value' => mb_substr((string)$value, 0, 1000, 'UTF-8'), 'inline' => mb_strlen((string)$value) < 40];
        }
        // allowed_mentions vacío: un texto con @everyone no avisa a todo el servidor.
        $payload = json_encode(['username' => 'Favor de Jack', 'embeds' => [$embed], 'allowed_mentions' => ['parse' => []]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        [$status, $error] = favor_discord_post($url, (string)$payload);
        $ok = $status >= 200 && $status < 300;
        $result = $ok ? '' : ($error !== '' ? $error : 'Discord respondió ' . $status . '.');
        try {
            favor_discord_update(static function (array $c) use ($event, $title, $ok, $result): array {
                array_unshift($c['log'], ['at' => date('c'), 'event' => $event, 'title' => mb_substr($title, 0, 120, 'UTF-8'), 'ok' => $ok, 'error' => $result]);
                return $c;
            });
        } catch (Throwable) {
            // Sin log no pasa nada.
        }
        return $result;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/** POST con un límite corto de tiempo. Devuelve [código HTTP, error]. */
function favor_discord_post(string $url, string $json): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [$status, $error !== '' ? 'No pude conectar con Discord: ' . $error : ''];
    }
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $json, 'timeout' => 5, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1];
    return [$status, $body === false && $status === 0 ? 'No pude conectar con Discord.' : ''];
}

/** Enlace de invitación al servidor de Discord ('' = sin enlace). */
function favor_discord_clean_invite(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('#^(?:https?://)?(?:www\.)?(discord\.gg|discord\.com/invite)/([A-Za-z0-9-]+)/?$#', $url, $m)) return 'https://' . $m[1] . '/' . $m[2];
    throw new RuntimeException('Ese enlace no parece una invitación de Discord (debe ser como https://discord.gg/abc123).');
}
