<?php
declare(strict_types=1);

/*
 * Favor de Jack · Regulaciones: leer, guardar y mostrar las reglas.
 *
 * Todo vive en data/regulations.json. El texto de cada regla se escribe en un
 * formato sencillo (ver reg_render) y se convierte a HTML al mostrarlo, siempre
 * escapado: nadie puede meter HTML ni scripts, ni siquiera el admin.
 */

require_once dirname(__DIR__) . '/includes/paths.php';

const REG_FILE = __DIR__ . '/data/regulations.json';
const REG_SPRITE = __DIR__ . '/assets/iconos.svg';
const REG_TYPES = ['regla' => 'Regla', 'buff' => 'Buff', 'nerf' => 'Nerf', 'ban' => 'Baneo'];
const REG_DEFAULT_ICON = 'scroll-unfurled';

/** Icono de cada categoría en el índice; las nuevas usan el pergamino. */
const REG_CATEGORY_ICONS = [
    'Acciones' => 'crossed-swords',
    'Combate' => 'shield',
    'Movimiento' => 'run',
    'Estados' => 'heart-beats',
    'Entorno y sentidos' => 'sun',
    'Descansos' => 'campfire',
    'Magia' => 'magic-swirl',
    'Armas y herramientas' => 'anvil-impact',
    'Clases y rasgos' => 'visored-helm',
    'Subclases' => 'scroll-unfurled',
];
const REG_TYPE_ICONS = ['buff' => 'upgrade', 'nerf' => 'armor-downgrade', 'ban' => 'cancel'];

function reg_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Dirección del archivo de iconos (con ?v= para la caché), relativa a regulaciones/. */
function reg_sprite_url(): string
{
    return 'assets/iconos.svg' . favor_v(REG_SPRITE);
}

function reg_icon(string $name, string $class = 'reg-icon'): string
{
    return '<svg class="' . reg_h($class) . '" aria-hidden="true"><use href="' . reg_h(reg_sprite_url() . '#' . $name) . '"/></svg>';
}

/** Nombres de los iconos disponibles (los del archivo iconos.svg). */
function reg_icon_names(): array
{
    static $names = null;
    if ($names === null) {
        preg_match_all('/<symbol id="([a-z0-9-]+)"/', (string)@file_get_contents(REG_SPRITE), $m);
        $names = $m[1] ?? [];
    }
    return $names;
}

function reg_load(): array
{
    $data = is_file(REG_FILE) ? json_decode((string)file_get_contents(REG_FILE), true) : null;
    if (!is_array($data)) $data = [];
    $data['rules'] = array_values(array_filter(is_array($data['rules'] ?? null) ? $data['rules'] : [], 'is_array'));
    $data['categories'] = array_values(array_filter(is_array($data['categories'] ?? null) ? $data['categories'] : [], 'is_string'));
    return $data;
}

/** Cambia el archivo bajo candado (dos admins a la vez no se pisan) y lo escribe entero o nada. */
function reg_update(callable $change): mixed
{
    $lock = @fopen(REG_FILE . '.lock', 'c');
    if (!$lock) throw new RuntimeException('No pude abrir el candado de regulaciones. Revisa los permisos de regulaciones/data/.');
    try {
        flock($lock, LOCK_EX);
        $data = reg_load();
        $result = null;
        $data = $change($data, $result);
        $data['updated'] = date('Y-m-d');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $tmp = REG_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if ($json === false || @file_put_contents($tmp, $json . "\n") === false || !@rename($tmp, REG_FILE)) {
            @unlink($tmp);
            throw new RuntimeException('No pude guardar las regulaciones. Revisa los permisos de regulaciones/data/.');
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Categorías en orden: las fijas del archivo y luego las que solo aparecen en alguna regla. */
function reg_categories(array $data, array $rules): array
{
    $list = $data['categories'] ?? [];
    foreach ($rules as $rule) {
        $c = (string)($rule['category'] ?? '');
        if ($c !== '' && !in_array($c, $list, true)) $list[] = $c;
    }
    return $list;
}

function reg_category_icon(string $category): string
{
    return REG_CATEGORY_ICONS[$category] ?? REG_DEFAULT_ICON;
}

function reg_inline(string $text): string
{
    $html = reg_h($text);
    $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html) ?? $html;
    return preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $html) ?? $html;
}

/**
 * Texto → HTML. Una línea por bloque:
 *   ## Título       → apartado
 *   1. paso         → pasos numerados
 *   - punto         → lista
 *   > nota          → recuadro de nota
 *   | a | b |       → tabla (la primera fila es la cabecera)
 *   **negrita**, *cursiva*; línea vacía = párrafo nuevo.
 */
function reg_render(string $text): string
{
    $out = [];
    $block = null;
    $items = [];
    $flush = static function () use (&$out, &$block, &$items): void {
        if ($block === null) return;
        if ($block === 'p') {
            $out[] = '<p>' . implode(' ', array_map('reg_inline', $items)) . '</p>';
        } elseif ($block === 'ol' || $block === 'ul') {
            $out[] = '<' . $block . ' class="reg-' . $block . '">'
                . implode('', array_map(static fn($i) => '<li>' . reg_inline($i) . '</li>', $items)) . '</' . $block . '>';
        } elseif ($block === 'note') {
            $out[] = '<div class="reg-note"><span class="reg-note-mark" aria-hidden="true">!</span><p>'
                . implode(' ', array_map('reg_inline', $items)) . '</p></div>';
        } elseif ($block === 'table') {
            $rows = array_map(static function (string $line): array {
                return array_map('trim', explode('|', trim(trim($line), '|')));
            }, $items);
            $head = array_shift($rows);
            $html = '<div class="reg-table"><table><thead><tr>';
            foreach ($head as $cell) $html .= '<th>' . reg_inline($cell) . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) $html .= '<td>' . reg_inline($cell) . '</td>';
                $html .= '</tr>';
            }
            $out[] = $html . '</tbody></table></div>';
        }
        $block = null;
        $items = [];
    };

    foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
        $line = trim($raw);
        if ($line === '') { $flush(); continue; }
        if (str_starts_with($line, '## ')) {
            $flush();
            $out[] = '<h3 class="reg-section"><span>' . reg_inline(substr($line, 3)) . '</span></h3>';
            continue;
        }
        if (preg_match('/^\|.*\|$/u', $line)) {
            // Las filas separadoras de Markdown (|---|---|) se ignoran.
            if (preg_match('/^\|[\s:|-]+\|$/', $line)) continue;
            $kind = 'table';
            $value = $line;
        } elseif (preg_match('/^\d+[.)]\s+(.*)$/u', $line, $m)) {
            $kind = 'ol';
            $value = $m[1];
        } elseif (preg_match('/^[-•*]\s+(.*)$/u', $line, $m)) {
            $kind = 'ul';
            $value = $m[1];
        } elseif (str_starts_with($line, '>')) {
            $kind = 'note';
            $value = ltrim(substr($line, 1));
        } else {
            $kind = 'p';
            $value = $line;
        }
        if ($block !== $kind) $flush();
        $block = $kind;
        $items[] = $value;
    }
    $flush();
    return implode("\n", $out);
}

function reg_slug(string $title): string
{
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
    $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $slug), '-'));
    return substr($slug !== '' ? $slug : 'regulacion', 0, 60);
}

function reg_str(array $in, string $key, int $max): string
{
    $value = trim(str_replace("\r\n", "\n", (string)($in[$key] ?? '')));
    return mb_substr($value, 0, $max, 'UTF-8');
}

/** Valida lo que manda el formulario y devuelve la regla limpia. */
function reg_clean(array $in): array
{
    $title = preg_replace('/\s+/u', ' ', reg_str($in, 'title', 120)) ?? '';
    $category = preg_replace('/\s+/u', ' ', reg_str($in, 'category', 40)) ?? '';
    $body = reg_str($in, 'body', 20000);
    if ($title === '') throw new RuntimeException('Ponle un título a la regulación.');
    if ($category === '') throw new RuntimeException('Elige o escribe una categoría.');
    if ($body === '') throw new RuntimeException('Escribe el texto de la regulación.');
    $type = (string)($in['type'] ?? 'regla');
    $icon = (string)($in['icon'] ?? '');
    return [
        'type' => isset(REG_TYPES[$type]) ? $type : 'regla',
        'category' => $category,
        'title' => $title,
        'icon' => in_array($icon, reg_icon_names(), true) ? $icon : reg_category_icon($category),
        'summary' => preg_replace('/\s+/u', ' ', reg_str($in, 'summary', 200)) ?? '',
        'body' => $body,
        'source' => reg_str($in, 'source', 120),
        'mesa' => !empty($in['mesa']),
        'draft' => !empty($in['draft']),
    ];
}
