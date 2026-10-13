<?php
declare(strict_types=1);

/**
 * Lector de fichas de personaje D&D 5e en PDF rellenable (AcroForm).
 *
 * No necesita librerías: lee los objetos del PDF (incluidos los comprimidos
 * en object streams), busca los campos de formulario y devuelve su valor.
 * Funciona con la ficha oficial de Wizards of the Coast en español e inglés,
 * que usan los mismos nombres de campo (CharacterName, ClassLevel, STRscore…).
 * Una ficha escaneada o "aplanada" no tiene campos y no se puede leer así.
 */

final class FichaPdfReader
{
    private string $data = '';
    private int $pos = 0;
    private int $len = 0;
    /** @var array<int, mixed> */
    private array $objects = [];

    /** @return array<string, string> nombre completo del campo => valor */
    public static function fields(string $bytes): array
    {
        if (!str_starts_with(ltrim(substr($bytes, 0, 1024)), '%PDF-')) {
            throw new RuntimeException('El archivo no es un PDF.');
        }
        $reader = new self();
        $reader->loadObjects($bytes);
        return $reader->collectFields();
    }

    private function loadObjects(string $bytes): void
    {
        if (preg_match('~/Encrypt\s+\d+\s+\d+\s+R~', $bytes)) {
            throw new RuntimeException('El PDF está protegido con contraseña o cifrado. Guárdalo sin protección e inténtalo de nuevo.');
        }
        // Los objetos que aparecen más tarde en el archivo reemplazan a los anteriores
        // (así funcionan los guardados incrementales de Acrobat).
        preg_match_all('~(?<![0-9])(\d+)\s+(\d+)\s+obj\b~', $bytes, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as $i => $match) {
            $num = (int)$m[1][$i][0];
            $start = $match[1] + strlen($match[0]);
            try {
                $this->data = $bytes;
                $this->len = strlen($bytes);
                $this->pos = $start;
                $value = $this->parseValue();
                $this->skipWs();
                if (is_array($value) && ($value['t'] ?? '') === 'dict' && substr($bytes, $this->pos, 6) === 'stream') {
                    $value = ['t' => 'stream', 'dict' => $value['v'], 'raw' => $this->readStream($bytes, $value['v'])];
                    $type = $value['dict']['Type'] ?? null;
                    if (is_array($type) && $type['t'] === 'name' && $type['v'] === 'ObjStm') {
                        $this->loadObjectStream($value);
                        continue;
                    }
                }
                $this->objects[$num] = $value;
            } catch (Throwable) {
                continue;
            }
        }
    }

    private function readStream(string $bytes, array $dict): string
    {
        $p = $this->pos + 6;
        if (($bytes[$p] ?? '') === "\r") $p++;
        if (($bytes[$p] ?? '') === "\n") $p++;
        $length = $dict['Length'] ?? null;
        if (is_int($length) && $length >= 0 && substr($bytes, $p + $length, 30) !== false
            && preg_match('~^\s*endstream~', (string)substr($bytes, $p + $length, 30))) {
            return substr($bytes, $p, $length);
        }
        $end = strpos($bytes, 'endstream', $p);
        if ($end === false) throw new RuntimeException('Stream sin cerrar.');
        return rtrim(substr($bytes, $p, $end - $p), "\r\n");
    }

    private function loadObjectStream(array $stream): void
    {
        $content = $this->decodeStream($stream);
        if ($content === null) return;
        $n = (int)($stream['dict']['N'] ?? 0);
        $first = (int)($stream['dict']['First'] ?? 0);
        $header = preg_split('~\s+~', trim(substr($content, 0, $first))) ?: [];
        for ($i = 0; $i + 1 < count($header) && $i / 2 < $n; $i += 2) {
            $num = (int)$header[$i];
            $offset = (int)$header[$i + 1];
            try {
                $this->data = $content;
                $this->len = strlen($content);
                $this->pos = $first + $offset;
                $this->objects[$num] = $this->parseValue();
            } catch (Throwable) {
                continue;
            }
        }
    }

    private function decodeStream(array $stream): ?string
    {
        $filter = $stream['dict']['Filter'] ?? null;
        $filters = [];
        if (is_array($filter) && $filter['t'] === 'name') $filters = [$filter['v']];
        if (is_array($filter) && $filter['t'] === 'array') {
            foreach ($filter['v'] as $f) if (is_array($f) && $f['t'] === 'name') $filters[] = $f['v'];
        }
        $raw = $stream['raw'];
        foreach ($filters as $f) {
            if ($f !== 'FlateDecode') return null;
            $out = @gzuncompress($raw);
            if ($out === false) $out = @gzinflate(substr($raw, 2));
            if ($out === false) return null;
            $raw = $out;
        }
        return $raw;
    }

    // ---------- Analizador de objetos PDF ----------

    private function skipWs(): void
    {
        while ($this->pos < $this->len) {
            $c = $this->data[$this->pos];
            if ($c === '%') {
                while ($this->pos < $this->len && $this->data[$this->pos] !== "\n" && $this->data[$this->pos] !== "\r") $this->pos++;
            } elseif (strpos(" \t\r\n\f\0", $c) !== false) {
                $this->pos++;
            } else {
                break;
            }
        }
    }

    private function parseValue(): mixed
    {
        $this->skipWs();
        if ($this->pos >= $this->len) throw new RuntimeException('Fin inesperado.');
        $c = $this->data[$this->pos];
        if ($c === '<' && ($this->data[$this->pos + 1] ?? '') === '<') return $this->parseDict();
        if ($c === '<') return $this->parseHexString();
        if ($c === '(') return $this->parseLiteralString();
        if ($c === '[') return $this->parseArray();
        if ($c === '/') return $this->parseName();
        if (preg_match('~\G[+-]?(\d+\.?\d*|\.\d+)~', $this->data, $m, 0, $this->pos)) {
            $this->pos += strlen($m[0]);
            if (preg_match('~\G\s+(\d+)\s+R(?![A-Za-z])~', $this->data, $r, 0, $this->pos) && ctype_digit($m[0])) {
                $this->pos += strlen($r[0]);
                return ['t' => 'ref', 'v' => (int)$m[0]];
            }
            return str_contains($m[0], '.') ? (float)$m[0] : (int)$m[0];
        }
        if (preg_match('~\G(true|false|null)~', $this->data, $m, 0, $this->pos)) {
            $this->pos += strlen($m[0]);
            return $m[0] === 'null' ? null : $m[0] === 'true';
        }
        throw new RuntimeException('Token desconocido.');
    }

    private function parseDict(): array
    {
        $this->pos += 2;
        $out = [];
        while (true) {
            $this->skipWs();
            if (substr($this->data, $this->pos, 2) === '>>') {
                $this->pos += 2;
                return ['t' => 'dict', 'v' => $out];
            }
            $key = $this->parseValue();
            if (!is_array($key) || $key['t'] !== 'name') throw new RuntimeException('Clave inválida.');
            $out[$key['v']] = $this->parseValue();
        }
    }

    private function parseArray(): array
    {
        $this->pos++;
        $out = [];
        while (true) {
            $this->skipWs();
            if (($this->data[$this->pos] ?? ']') === ']') {
                $this->pos++;
                return ['t' => 'array', 'v' => $out];
            }
            $out[] = $this->parseValue();
        }
    }

    private function parseName(): array
    {
        $this->pos++;
        preg_match('~\G[^\s/<>\[\]()%{}]*~', $this->data, $m, 0, $this->pos);
        $this->pos += strlen($m[0]);
        $name = preg_replace_callback('~#([0-9A-Fa-f]{2})~', static fn($h) => chr(hexdec($h[1])), $m[0]);
        return ['t' => 'name', 'v' => $name];
    }

    private function parseHexString(): array
    {
        $end = strpos($this->data, '>', $this->pos);
        if ($end === false) throw new RuntimeException('Cadena hex sin cerrar.');
        $hex = preg_replace('~[^0-9A-Fa-f]~', '', substr($this->data, $this->pos + 1, $end - $this->pos - 1));
        if (strlen($hex) % 2) $hex .= '0';
        $this->pos = $end + 1;
        return ['t' => 'str', 'v' => (string)hex2bin($hex)];
    }

    private function parseLiteralString(): array
    {
        $this->pos++;
        $depth = 1;
        $out = '';
        $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0c", '(' => '(', ')' => ')', '\\' => '\\'];
        while ($this->pos < $this->len) {
            $c = $this->data[$this->pos++];
            if ($c === '\\') {
                $n = $this->data[$this->pos] ?? '';
                if (isset($map[$n])) {
                    $out .= $map[$n];
                    $this->pos++;
                } elseif ($n >= '0' && $n <= '7') {
                    preg_match('~\G[0-7]{1,3}~', $this->data, $m, 0, $this->pos);
                    $out .= chr(octdec($m[0]) & 0xFF);
                    $this->pos += strlen($m[0]);
                } elseif ($n === "\r") {
                    $this->pos++;
                    if (($this->data[$this->pos] ?? '') === "\n") $this->pos++;
                } elseif ($n === "\n") {
                    $this->pos++;
                }
                continue;
            }
            if ($c === '(') $depth++;
            if ($c === ')' && --$depth === 0) return ['t' => 'str', 'v' => $out];
            $out .= $c;
        }
        throw new RuntimeException('Cadena sin cerrar.');
    }

    // ---------- Campos de formulario ----------

    private function resolve(mixed $value, int $depth = 0): mixed
    {
        while (is_array($value) && ($value['t'] ?? '') === 'ref' && $depth++ < 32) {
            $value = $this->objects[$value['v']] ?? null;
        }
        return $value;
    }

    private function dictOf(mixed $value): ?array
    {
        $value = $this->resolve($value);
        if (is_array($value) && ($value['t'] ?? '') === 'dict') return $value['v'];
        if (is_array($value) && ($value['t'] ?? '') === 'stream') return $value['dict'];
        return null;
    }

    private function collectFields(): array
    {
        $fields = [];
        $roots = [];
        foreach ($this->objects as $obj) {
            $dict = $this->dictOf($obj);
            if ($dict && isset($dict['AcroForm'])) {
                $form = $this->dictOf($dict['AcroForm']);
                $list = $this->resolve($form['Fields'] ?? null);
                if (is_array($list) && $list['t'] === 'array') $roots = $list['v'];
            }
        }
        if (!$roots) {
            throw new RuntimeException('Este PDF no tiene campos rellenables. Solo se pueden leer fichas rellenables (no escaneadas ni impresas a PDF).');
        }
        $seen = [];
        $walk = function (mixed $ref, string $prefix, mixed $inheritedV, mixed $inheritedOpt, int $depth) use (&$walk, &$fields, &$seen): void {
            if ($depth > 20) return;
            if (is_array($ref) && ($ref['t'] ?? '') === 'ref') {
                if (isset($seen[$ref['v']])) return;
                $seen[$ref['v']] = true;
            }
            $dict = $this->dictOf($ref);
            if (!$dict) return;
            $name = $prefix;
            if (isset($dict['T'])) {
                $t = $this->text($this->resolve($dict['T']));
                $name = $prefix === '' ? $t : $prefix . '.' . $t;
            }
            $v = array_key_exists('V', $dict) ? $dict['V'] : $inheritedV;
            $opt = $dict['Opt'] ?? $inheritedOpt;
            $kids = $this->resolve($dict['Kids'] ?? null);
            $namedKids = false;
            if (is_array($kids) && $kids['t'] === 'array') {
                foreach ($kids['v'] as $kid) {
                    $kd = $this->dictOf($kid);
                    if ($kd && isset($kd['T'])) {
                        $namedKids = true;
                        $walk($kid, $name, $v, $opt, $depth + 1);
                    }
                }
            }
            if (!$namedKids && isset($dict['T']) && $name !== '') {
                $fields[$name] = $this->fieldValue($this->resolve($v), $this->resolve($opt));
            }
        };
        foreach ($roots as $ref) $walk($ref, '', null, null, 0);
        return $fields;
    }

    private function fieldValue(mixed $v, mixed $opt): string
    {
        if (is_array($v) && $v['t'] === 'name') {
            return $v['v'] === 'Off' ? '' : $v['v'];
        }
        if (is_array($v) && $v['t'] === 'array') {
            $parts = [];
            foreach ($v['v'] as $item) $parts[] = $this->fieldValue($this->resolve($item), $opt);
            return implode(', ', array_filter($parts, static fn($p) => $p !== ''));
        }
        if (!is_array($v) || $v['t'] !== 'str') return is_scalar($v) ? (string)$v : '';
        $text = $this->text($v);
        // Listas desplegables: el valor guardado puede ser un código ("shalf") con un texto visible ("Mediano Fornido").
        if (is_array($opt) && $opt['t'] === 'array') {
            foreach ($opt['v'] as $o) {
                $o = $this->resolve($o);
                if (is_array($o) && $o['t'] === 'array' && count($o['v']) >= 2) {
                    $export = $this->text($this->resolve($o['v'][0]));
                    if ($export === $text) return $this->text($this->resolve($o['v'][1]));
                }
            }
        }
        return $text;
    }

    private function text(mixed $str): string
    {
        if (!is_array($str) || !in_array($str['t'] ?? '', ['str', 'name'], true)) return '';
        $s = $str['v'];
        if (str_starts_with($s, "\xFE\xFF")) {
            $s = (string)mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16BE');
        } elseif (str_starts_with($s, "\xEF\xBB\xBF")) {
            $s = substr($s, 3);
        } elseif (!mb_check_encoding($s, 'UTF-8') || preg_match('~[\x80-\xFF]~', $s)) {
            $s = self::pdfDocToUtf8($s);
        }
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        return trim($s);
    }

    private static function pdfDocToUtf8(string $s): string
    {
        static $high = [
            0x80 => '•', 0x81 => '†', 0x82 => '‡', 0x83 => '…', 0x84 => '—', 0x85 => '–', 0x86 => 'ƒ', 0x87 => '⁄',
            0x88 => '‹', 0x89 => '›', 0x8A => '−', 0x8B => '‰', 0x8C => '„', 0x8D => '“', 0x8E => '”', 0x8F => '‘',
            0x90 => '’', 0x91 => '‚', 0x92 => '™', 0x93 => 'ﬁ', 0x94 => 'ﬂ', 0x95 => 'Ł', 0x96 => 'Œ', 0x97 => 'Š',
            0x98 => 'Ÿ', 0x99 => 'Ž', 0x9A => 'ı', 0x9B => 'ł', 0x9C => 'œ', 0x9D => 'š', 0x9E => 'ž', 0xA0 => '€',
        ];
        $out = '';
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $b = ord($s[$i]);
            if ($b < 0x80) $out .= $s[$i];
            elseif (isset($high[$b])) $out .= $high[$b];
            elseif ($b > 0xA0) $out .= mb_chr($b, 'UTF-8');
        }
        return $out;
    }
}

// ---------- Interpretación de la ficha ----------

function ficha_fold(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return preg_replace('~\s+~', ' ', $s) ?? $s;
}

/** Clases conocidas: texto normalizado => nombre como se usa en el Archivo. */
function ficha_known_classes(): array
{
    return [
        'barbaro' => 'Bárbaro', 'barbarian' => 'Bárbaro',
        'bardo' => 'Bardo', 'bard' => 'Bardo',
        'brujo' => 'Brujo', 'warlock' => 'Brujo',
        'clerigo' => 'Clérigo', 'cleric' => 'Clérigo',
        'druida' => 'Druida', 'druid' => 'Druida',
        'explorador' => 'Explorador', 'ranger' => 'Explorador',
        'guerrero' => 'Guerrero', 'fighter' => 'Guerrero',
        'hechicero' => 'Hechicero', 'sorcerer' => 'Hechicero',
        'mago' => 'Mago', 'wizard' => 'Mago',
        'monje' => 'Monje', 'monk' => 'Monje',
        'paladin' => 'Paladín',
        'picaro' => 'Pícaro', 'rogue' => 'Pícaro',
        'artifice' => 'Artífice', 'artificer' => 'Artífice',
        'blood hunter' => 'Blood Hunter', 'cazador de sangre' => 'Blood Hunter',
    ];
}

/**
 * "Barbaro Lvl 5" → ['class' => 'Bárbaro', 'level' => 5]
 * "Guerrero 3 / Pícaro 2" → ['class' => 'Multiclase', 'level' => 5, 'classes' => [...]]
 */
function ficha_parse_class_level(string $raw): array
{
    $known = ficha_known_classes();
    $folded = ficha_fold($raw);
    $found = [];
    foreach ($known as $key => $label) {
        if (preg_match('~(?<![a-z])' . preg_quote($key, '~') . '(?![a-z])~', $folded, $m, PREG_OFFSET_CAPTURE)) {
            $found[$m[0][1]] = $label;
        }
    }
    ksort($found);
    $found = array_values(array_unique($found));
    preg_match_all('~\d+~', $raw, $nums);
    $levels = array_map('intval', $nums[0]);
    $level = null;
    if (count($found) > 1 && count($levels) >= count($found)) $level = array_sum($levels);
    elseif ($levels) $level = $levels[0];
    if ($level !== null && ($level < 1 || $level > 30)) $level = null;

    if (count($found) > 1) return ['class' => 'Multiclase', 'level' => $level, 'classes' => $found];
    if (count($found) === 1) return ['class' => $found[0], 'level' => $level, 'classes' => $found];
    $text = trim(preg_replace('~\b(nivel|nv|lvl|level|niv)\.?\b|\d+~iu', '', $raw) ?? '');
    return ['class' => $text !== '' ? mb_convert_case($text, MB_CASE_TITLE, 'UTF-8') : '', 'level' => $level, 'classes' => []];
}

function ficha_int(string $value, int $min, int $max): ?int
{
    if (!preg_match('~-?\d+~', $value, $m)) return null;
    $n = (int)$m[0];
    return $n >= $min && $n <= $max ? $n : null;
}

/** Altura escrita como "1,20 m", "120 cm", "4'2\"" → metros (decimal 4,2). */
function ficha_height_m(string $value): ?float
{
    $v = ficha_fold($value);
    if ($v === '') return null;
    if (preg_match("~(\d+)\s*(?:'|pies|ft)\s*(\d+)?~", $v, $m)) {
        $meters = ((int)$m[1] * 12 + (int)($m[2] ?? 0)) * 0.0254;
    } elseif (preg_match('~(\d+(?:[.,]\d+)?)\s*cm~', $v, $m)) {
        $meters = (float)str_replace(',', '.', $m[1]) / 100;
    } elseif (preg_match('~(\d+(?:[.,]\d+)?)~', $v, $m)) {
        $meters = (float)str_replace(',', '.', $m[1]);
        if ($meters > 10) $meters /= 100;
    } else {
        return null;
    }
    return $meters > 0 && $meters < 100 ? round($meters, 2) : null;
}

/** Convierte el texto libre de "Equipo" en una lista de objetos. */
function ficha_split_items(string $text): array
{
    $text = trim(str_replace(["\n", ';', '•', '●'], ',', $text));
    if ($text === '') return [];
    $parts = preg_split('~\s*,\s*|\s+y\s+(?=(?:un|una|unos|unas|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|\d+)\b)~iu', $text) ?: [];
    $numbers = ['un' => 1, 'una' => 1, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
        'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'unos' => 1, 'unas' => 1, 'a' => 1, 'an' => 1];
    $items = [];
    foreach ($parts as $part) {
        $part = trim($part, " \t.-_");
        if ($part === '' || mb_strlen($part) < 2) continue;
        $qty = 1;
        if (preg_match('~^(\d+)\s*(?:x\s*)?(.+)$~iu', $part, $m)) {
            $qty = max(1, min(9999, (int)$m[1]));
            $part = $m[2];
        } elseif (preg_match('~^(\pL+)\s+(.+)$~u', $part, $m) && isset($numbers[ficha_fold($m[1])])) {
            $qty = $numbers[ficha_fold($m[1])];
            $part = $m[2];
        } elseif (preg_match('~^(.+?)\s*\(?x\s*(\d+)\)?$~iu', $part, $m)) {
            $qty = max(1, min(9999, (int)$m[2]));
            $part = $m[1];
        }
        $part = trim($part);
        if ($part === '') continue;
        $name = mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($part, 1, null, 'UTF-8');
        $items[] = ['name' => mb_substr($name, 0, 160, 'UTF-8'), 'quantity' => $qty];
    }
    return $items;
}

function ficha_item_category(string $name): string
{
    $n = ficha_fold($name);
    if (preg_match('~(objeto\s*magico|magic|encantad|pocion|varita|baston|orbe|reliquia|\+\d)~u', $n)) return 'Objeto mágico';
    if (preg_match('~(arma|armadura|espada|daga|hacha|arco|ballesta|lanza|martillo|mazo|escudo|casco|alabarda|cimitarra|estoque|maza|jabalina|honda|garrote|flecha|virote|tridente|latigo|mangual|pica|guja|lucero)~u', $n)) return 'Arma / Armadura';
    if (preg_match('~(joya|gema|anillo|collar|broche|adorno|cosmetico|tesoro|perla|diamante|rubi|zafiro|esmeralda)~u', $n)) return 'Vanidad';
    return 'Utilidad';
}

/**
 * Convierte los campos crudos en una ficha normalizada.
 * 'profile' = columnas de PersonajePerfil, 'items' = objetos para Inventario,
 * 'coins' = monedas, 'sheet' = todo lo demás para la pestaña "Hoja".
 */
function ficha_interpret(array $f): array
{
    $g = static fn(string $k): string => trim((string)($f[$k] ?? ''));
    if ($g('CharacterName') === '' && $g('ClassLevel') === '' && $g('STRscore') === '') {
        throw new RuntimeException('El PDF tiene campos, pero no parece una ficha de personaje D&D 5e (no encontré nombre, clase ni características).');
    }

    $classLevel = ficha_parse_class_level($g('ClassLevel'));
    $abilities = [];
    foreach (['STR' => 'Fuerza', 'DEX' => 'Destreza', 'CON' => 'Constitucion', 'INT' => 'Inteligencia', 'WIS' => 'Sabiduria', 'CHA' => 'Carisma'] as $code => $col) {
        $abilities[$col] = ficha_int($g($code . 'score'), 1, 30);
    }

    $profile = [
        'NombreCompleto' => mb_substr($g('CharacterName'), 0, 150, 'UTF-8'),
        'Raza' => mb_substr($g('Race'), 0, 100, 'UTF-8'),
        'Clase' => mb_substr($classLevel['class'], 0, 150, 'UTF-8'),
        'Nivel' => $classLevel['level'],
        'Edad' => mb_substr($g('Age'), 0, 50, 'UTF-8'),
        'Altura' => ficha_height_m($g('Height')),
        'Biografia' => trim($g('Backstory') . "\n\n" . $g('Backstory 1') . "\n" . $g('Backstory 2') . "\n" . $g('Backstory 3')),
    ] + $abilities;
    $profile['Biografia'] = trim(preg_replace("~\n{3,}~", "\n\n", $profile['Biografia']) ?? '');

    // Inventario: ataques (equipados), armadura, equipo y tesoro.
    $items = [];
    for ($i = 1; $i <= 3; $i++) {
        $name = $g('Attack' . $i);
        if ($name === '') continue;
        $desc = trim(implode(' · ', array_filter([
            $g('AtkBonus' . $i) !== '' ? 'Ataque ' . $g('AtkBonus' . $i) : '',
            $g('Damage' . $i) !== '' ? 'Daño ' . $g('Damage' . $i) : '',
        ])));
        $items[] = ['name' => $name, 'quantity' => 1, 'category' => 'Arma / Armadura', 'equipped' => true, 'description' => $desc, 'source' => 'Ataques'];
    }
    $armor = $g('ArmorWorn');
    if ($armor !== '' && !preg_match('~^(sin armadura|ninguna|no|none|-+)$~iu', $armor)) {
        $items[] = ['name' => $armor, 'quantity' => 1, 'category' => 'Arma / Armadura', 'equipped' => true,
            'description' => $g('ACworn') !== '' ? 'CA ' . $g('ACworn') : '', 'source' => 'Armadura'];
    }
    if ($g('shieldyes') !== '') {
        $items[] = ['name' => 'Escudo', 'quantity' => 1, 'category' => 'Arma / Armadura', 'equipped' => true, 'description' => '', 'source' => 'Armadura'];
    }
    foreach (ficha_split_items($g('Equipment')) as $it) {
        $items[] = $it + ['category' => ficha_item_category($it['name']), 'equipped' => false, 'description' => '', 'source' => 'Equipo'];
    }
    foreach (ficha_split_items(trim($g('Treasure') . ', ' . $g('Treasure 2'), ', ')) as $it) {
        $items[] = $it + ['category' => ficha_item_category($it['name']) === 'Utilidad' ? 'Vanidad' : ficha_item_category($it['name']), 'equipped' => false, 'description' => '', 'source' => 'Tesoro'];
    }
    // Quita duplicados por nombre dentro de la misma ficha (sumando cantidades).
    $unique = [];
    foreach ($items as $it) {
        $key = ficha_fold($it['name']);
        if (isset($unique[$key])) {
            if ($it['source'] !== 'Ataques' && $unique[$key]['source'] !== 'Ataques') $unique[$key]['quantity'] += $it['quantity'];
            continue;
        }
        $unique[$key] = $it;
    }

    $coins = [
        'ppt' => ficha_int($g('Platinum'), 0, 1000000000),
        'po' => ficha_int($g('Gold') !== '' ? $g('Gold') : $g('GP'), 0, 1000000000),
        'pe' => ficha_int($g('Electrum'), 0, 1000000000),
        'pl' => ficha_int($g('Silver'), 0, 1000000000),
        'pc' => ficha_int($g('Copper'), 0, 1000000000),
    ];

    $skills = [
        'Acrobacias' => ['Acrobatics', 'acroPROF'], 'Atletismo' => ['Athletics', 'athPROF'],
        'C. Arcano' => ['Arcana', 'arcanaPROF'], 'Engaño' => ['Deception', 'decepPROF'],
        'Historia' => ['History', 'histPROF'], 'Interpretación' => ['Performance', 'perfPROF'],
        'Intimidación' => ['Intimidation', 'intimPROF'], 'Investigación' => ['Investigation', 'investPROF'],
        'Juego de Manos' => ['SleightofHand', 'sohPROF'], 'Medicina' => ['Medicine', 'medPROF'],
        'Naturaleza' => ['Nature', 'naturePROF'], 'Percepción' => ['Perception', 'perPROF'],
        'Perspicacia' => ['Insight', 'insightPROF'], 'Persuasión' => ['Persuasion', 'persPROF'],
        'Religión' => ['Religion', 'religPROF'], 'Sigilo' => ['Stealth', 'stealthPROF'],
        'Supervivencia' => ['Survival', 'survPROF'], 'T. con Animales' => ['AnHan', 'anhanPROF'],
    ];
    $skillList = [];
    foreach ($skills as $label => [$value, $prof]) {
        if ($g($value) === '' && $g($prof) === '') continue;
        $skillList[] = ['name' => $label, 'value' => ficha_signed($g($value)), 'proficient' => $g($prof) !== ''];
    }
    $saves = [];
    foreach (['STR' => 'Fuerza', 'DEX' => 'Destreza', 'CON' => 'Constitución', 'INT' => 'Inteligencia', 'WIS' => 'Sabiduría', 'CHA' => 'Carisma'] as $code => $label) {
        if ($g($code . 'save') === '') continue;
        $saves[] = ['name' => $label, 'value' => ficha_signed($g($code . 'save')), 'proficient' => $g($code . 'savePROF') !== ''];
    }
    $attacks = [];
    for ($i = 1; $i <= 3; $i++) {
        if ($g('Attack' . $i) === '') continue;
        $attacks[] = ['name' => $g('Attack' . $i), 'bonus' => $g('AtkBonus' . $i), 'damage' => $g('Damage' . $i)];
    }
    $spells = [];
    foreach ($f as $k => $v) {
        if (preg_match('~^Spells? ?\d+$~', (string)$k) && trim((string)$v) !== '') $spells[] = trim((string)$v);
    }

    $sheet = array_filter([
        'claseNivel' => $g('ClassLevel'),
        'clases' => $classLevel['classes'],
        'trasfondo' => $g('Background'),
        'alineamiento' => $g('Alignment'),
        'jugadorFicha' => $g('PlayerName'),
        'experiencia' => preg_match('~\d~', $g('ExperiencePoints')) ? $g('ExperiencePoints') : '',
        'ca' => $g('AC'),
        'iniciativa' => $g('Init'),
        'velocidad' => $g('Speed'),
        'pgMax' => $g('HPMax'),
        'pgActuales' => $g('CurrentHP'),
        'pgTemporales' => $g('TempHP'),
        'dadosGolpe' => $g('HitDiceTotal') !== '' ? $g('HitDiceTotal') : $g('Text1'),
        'competencia' => ficha_signed($g('ProfBonus')),
        'percepcionPasiva' => $g('PWP'),
        'salvaciones' => $saves,
        'habilidades' => $skillList,
        'ataques' => $attacks,
        'ataquesNotas' => $g('AttacksSpellsMisc'),
        'competenciasIdiomas' => $g('ProfsLangs'),
        'rasgos' => $g('FeaturesTraits'),
        'rasgosAdicionales' => trim($g('Feat+Traits') . "\n\n" . $g('Feat+Traits 2')),
        'personalidad' => $g('PersonalityTraits'),
        'ideales' => $g('Ideals'),
        'vinculos' => $g('Bonds'),
        'defectos' => $g('Flaws'),
        'aspecto' => array_filter(['Edad' => $g('Age'), 'Altura' => $g('Height'), 'Peso' => $g('Weight'), 'Ojos' => $g('Eyes'), 'Piel' => $g('Skin'), 'Pelo' => $g('Hair')]),
        'aliados' => trim($g('Allies') . ($g('FactionName') !== '' ? "\n" . $g('FactionName') : '')),
        'conjuros' => array_filter([
            'clase' => $g('SpellClass'), 'aptitud' => $g('SpellAbility'), 'cd' => $g('SpellSaveDC'), 'ataque' => $g('SAB'),
            'lista' => $spells,
        ]),
    ], static fn($v) => $v !== '' && $v !== [] && $v !== null);

    return [
        'name' => $g('CharacterName'),
        'playerName' => $g('PlayerName'),
        'profile' => $profile,
        'items' => array_values($unique),
        'coins' => $coins,
        'sheet' => $sheet,
    ];
}

function ficha_signed(string $value): string
{
    $value = trim($value);
    if ($value === '' || !preg_match('~^[+-]?\d+$~', $value)) return $value;
    return ((int)$value > 0 ? '+' : '') . (int)$value;
}
