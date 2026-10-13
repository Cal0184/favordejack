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

/** Nombres de conjuros frecuentes (inglés SRD y español) para no confundirlos con objetos. */
function ficha_known_spells(): array
{
    static $set = null;
    if ($set !== null) return $set;
    $names = 'acid splash|aid|alarm|animal friendship|animate dead|antimagic field|arcane lock|armor of agathys|arms of hadar|bane|banishment|barkskin|beacon of hope|bestow curse|blade ward|bless|blight|blindness/deafness|blink|blur|branding smite|burning hands|call lightning|calm emotions|chain lightning|charm person|chill touch|chromatic orb|circle of death|clairvoyance|cloudkill|color spray|command|commune|cone of cold|confusion|conjure animals|counterspell|create food and water|create or destroy water|cure wounds|dancing lights|darkness|darkvision|daylight|death ward|detect evil and good|detect magic|detect poison and disease|detect thoughts|dimension door|disguise self|disintegrate|dispel evil and good|dispel magic|divination|divine favor|divine word|dominate person|dragon\'s breath|druidcraft|earth tremor|eldritch blast|enhance ability|enlarge/reduce|ensnaring strike|entangle|expeditious retreat|faerie fire|false life|fear|feather fall|find familiar|find steed|fire bolt|fire shield|fireball|fire ball|flame blade|flame strike|flaming sphere|fly|fog cloud|freedom of movement|friends|gaseous form|geas|gentle repose|giant insect|glyph of warding|goodberry|grease|greater invisibility|greater restoration|guardian of faith|guidance|guiding bolt|gust of wind|hail of thorns|haste|heal|healing word|heat metal|hellish rebuke|heroism|hex|hold monster|hold person|holy aura|hunter\'s mark|hypnotic pattern|ice knife|ice storm|identify|inflict wounds|insect plague|invisibility|jump|knock|lesser restoration|levitate|light|lightning bolt|locate object|longstrider|mage armor|mage hand|magic missile|magic weapon|mass cure wounds|mass healing word|melf\'s acid arrow|mending|message|minor illusion|mirror image|misty step|moonbeam|nondetection|pass without trace|phantasmal force|plane shift|poison spray|polymorph|power word kill|prayer of healing|prestidigitation|produce flame|protection from energy|protection from evil and good|protection from poison|raise dead|ray of frost|ray of sickness|remove curse|resistance|resurrection|revivify|sacred flame|sanctuary|scorching ray|scrying|searing smite|see invisibility|shatter|shield|shield of faith|shillelagh|shocking grasp|silence|silent image|sleep|sleet storm|slow|spare the dying|speak with animals|speak with dead|spider climb|spike growth|spirit guardians|spiritual weapon|stinking cloud|stone shape|stoneskin|suggestion|thaumaturgy|thorn whip|thunderwave|thunderous smite|toll the dead|true strike|vicious mockery|wall of fire|wall of force|wall of stone|warding bond|water breathing|water walk|web|wind wall|word of radiance|wrathful smite|zone of truth|'
        . 'agarre electrizante|alarma|ayuda|bendecir|bendicion|bola de fuego|curar heridas|palabra curativa|palabra de curacion|arma espiritual|guardianes espirituales|restablecimiento menor|restauracion menor|restablecimiento mayor|silencio|disipar magia|levantar maldicion|quitar maldicion|luz del dia|manos ardientes|rayo abrasador|esfera flamigera|muro de fuego|escudo de fuego|escudo|escudo de fe|proyectil magico|misil magico|armadura de mago|mano de mago|luz|llama sagrada|taumaturgia|orientacion|guia|resistencia|perdonar a los moribundos|toque gelido|rayo de escarcha|descarga sobrenatural|rafaga sobrenatural|saeta de fuego|rayo de fuego|dormir|hechizar persona|hechizar a persona|detectar magia|invisibilidad|paso brumoso|telarana|sugestion|contrahechizo|volar|celeridad|relampago|rayo|revivir|retirada expeditiva|caida de pluma|disfrazarse|identificar|santuario|orden imperiosa|perdicion|infligir heridas|saeta guia|rayo guia|crear o destruir agua|purificar comida y bebida|cancion de descanso|burla cruel|mensaje|prestidigitacion|ilusion menor|reparar|amistad|fuego feerico|fuego feérico|bayas buenas|enmaranar|ola atronadora|onda atronadora|marca del cazador|maleficio|reprension infernal|armadura de agathys|brazos de hadar|nube de oscurecimiento|oscuridad|vision en la oscuridad|imagen multiple|reflejos|desplazamiento|arma magica|calor metalico|piel robliza|sosegar emociones|calmar emociones|zona de verdad|aumentar habilidad|potenciar caracteristica|plegaria de curacion|curar heridas en masa|palabra curativa en masa|revivificar|resurreccion|faro de esperanza|proteccion contra el bien y el mal|proteccion contra la energia|proteccion contra veneno|guardian de la fe|custodia contra la muerte|libertad de movimiento|destierro|piel petrea|tormenta de hielo|cono de frio|golpe flamigero|columna flamigera|comunion|curar|sanar|temblor de tierra|meteoritos pequenos|esfera de fuego';
    $set = array_flip(explode('|', $names));
    return $set;
}

function ficha_spell_heading(string $heading): bool
{
    return (bool)preg_match('~(conjur|hechiz|spell|diario|siempre\s+listo|preparad|truco|cantrip|ritual|dominio|always\s+prepared|nivel\s*\d|lvl\s*\d|^\d+\s*(º|°|er|do|ro|to|vo|no)?\s*nivel|magia|plegaria|oracion)~u', ficha_fold($heading));
}

/** Pronunciación tipo "(KRIP-aa BUR-saa-oh)": típica de las listas de conjuros de Cal. */
function ficha_has_pronunciation(string $text): bool
{
    return (bool)preg_match('~\((?=[^)]*[A-Z]{2,})[A-Za-z\'\- ]+\)~u', $text);
}

function ficha_looks_like_spell(string $name): bool
{
    if (ficha_has_pronunciation($name)) return true;
    $base = ficha_fold(preg_replace('~\(.*?\)~u', '', $name) ?? $name);
    $base = trim($base, " .:;!-");
    return $base !== '' && isset(ficha_known_spells()[$base]);
}

function ficha_clean_entry(string $part): string
{
    // Quita viñetas y emojis del principio (💖, ●, -, *…).
    $part = preg_replace('~^[\s\p{So}\p{Sk}\p{Cs}\x{FE0F}\x{200D}•●○■◆◇▪►\-–—*·>]+~u', '', $part) ?? $part;
    return trim($part, " \t.-_");
}

/**
 * Convierte el texto libre de "Equipo" o "Tesoro" en entradas.
 * Cada entrada lleva 'kind': item (objeto), spell (conjuro) o money (dinero),
 * así la vista previa solo propone añadir al inventario lo que parece un objeto.
 */
function ficha_split_items(string $text): array
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') return [];
    $numbers = ['un' => 1, 'una' => 1, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
        'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'unos' => 1, 'unas' => 1, 'a' => 1, 'an' => 1];
    $units = 'pies|pie|ft|feet|metros|metro|m|libras|libra|lb|lbs|kg|kilos|gramos|g|litros|litro|onzas|varas|yardas|pulgadas';
    $coin = 'po|pp|pl|pc|pe|ppt|gp|sp|cp|ep|pt|mo|monedas?\s+de\s+(?:oro|plata|cobre|platino|electro)';
    $entries = [];
    $group = 0;
    $groupSpell = false;
    foreach (explode("\n", $text) as $line) {
        $line = ficha_clean_entry($line);
        if ($line === '') continue;
        // "Diario:" o "Siempre Listos:" son títulos de una lista, no objetos.
        if (preg_match('~^(.{1,40}):$~u', $line, $hm)) {
            $group++;
            $groupSpell = ficha_spell_heading($hm[1]);
            continue;
        }
        // "Conjuros: bless, cure wounds" en una sola línea.
        $lineSpell = $groupSpell;
        if (preg_match('~^([^:,]{1,30}):\s*(.+)$~u', $line, $hm) && ficha_spell_heading($hm[1])) {
            $lineSpell = true;
            $line = $hm[2];
        }
        $parts = preg_split('~\s*[,;]\s*|\s+y\s+(?=(?:un|una|unos|unas|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|\d+)\b)~iu', $line) ?: [];
        foreach ($parts as $part) {
            $part = ficha_clean_entry($part);
            if ($part === '' || mb_strlen($part) < 2) continue;
            $kind = 'item';
            $qty = 1;
            $ownSpell = ficha_looks_like_spell($part);
            if ($ownSpell || ($lineSpell && ficha_item_category($part) === 'Utilidad')) {
                // Dentro de una lista de conjuros, algo como "anillo de oro" sigue siendo un objeto.
                $kind = 'spell';
            } elseif (preg_match('~^\d[\d.,]*\s*(?:' . $coin . ')\.?$~iu', $part)) {
                $kind = 'money';
            } else {
                // "bolsa con mi dinero 15 po" → "Bolsa con mi dinero" (el dinero se lleva en la Banca).
                $part = trim(preg_replace('~\s*\d[\d.,]*\s*(?:' . $coin . ')\.?$|\s+(?:' . $coin . ')\.?$~iu', '', $part) ?? $part);
                if (preg_match('~^(\d+)\s*(' . $units . ')\.?\s+(?:de\s+)?(.+)$~iu', $part, $m)) {
                    // "50 pies de cuerda" es una cuerda de 50 pies, no 50 cuerdas.
                    $part = $m[3] . ' (' . $m[1] . ' ' . $m[2] . ')';
                } elseif (preg_match('~^(\d+)\s*(?:x\s*)?(.+)$~iu', $part, $m)) {
                    $qty = max(1, min(9999, (int)$m[1]));
                    $part = $m[2];
                } elseif (preg_match('~^(\pL+)\s+(.+)$~u', $part, $m) && isset($numbers[ficha_fold($m[1])])) {
                    $qty = $numbers[ficha_fold($m[1])];
                    $part = $m[2];
                } elseif (preg_match('~^(.+?)\s*\(?x\s*(\d+)\)?$~iu', $part, $m)) {
                    $qty = max(1, min(9999, (int)$m[2]));
                    $part = $m[1];
                }
            }
            $part = trim($part, " \t.-_");
            if ($part === '') continue;
            $name = mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($part, 1, null, 'UTF-8');
            $entries[] = ['name' => mb_substr($name, 0, 160, 'UTF-8'), 'quantity' => $qty, 'kind' => $kind, 'group' => $group];
        }
    }
    // Si en un mismo bloque la mayoría parecen conjuros, el bloque entero es una lista de conjuros.
    $byGroup = [];
    foreach ($entries as $e) $byGroup[$e['group']][] = $e['kind'];
    foreach ($entries as &$e) {
        $kinds = $byGroup[$e['group']];
        $spells = count(array_filter($kinds, static fn($k) => $k === 'spell'));
        if ($e['group'] > 0 && $e['kind'] === 'item' && $spells >= 2 && $spells * 2 >= count($kinds)
            && ficha_item_category($e['name']) === 'Utilidad') $e['kind'] = 'spell';
        unset($e['group']);
    }
    unset($e);
    return $entries;
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
        $items[] = ['name' => $name, 'quantity' => 1, 'category' => 'Arma / Armadura', 'equipped' => true, 'description' => $desc, 'source' => 'Ataques', 'kind' => 'item'];
    }
    $armor = $g('ArmorWorn');
    if ($armor !== '' && !preg_match('~^(sin armadura|ninguna|no|none|-+)$~iu', $armor)) {
        $items[] = ['name' => $armor, 'quantity' => 1, 'category' => 'Arma / Armadura', 'equipped' => true,
            'description' => $g('ACworn') !== '' ? 'CA ' . $g('ACworn') : '', 'source' => 'Armadura', 'kind' => 'item'];
    }
    if ($g('shieldyes') !== '') {
        $items[] = ['name' => 'Escudo', 'quantity' => 1, 'category' => 'Arma / Armadura', 'equipped' => true, 'description' => '', 'source' => 'Armadura', 'kind' => 'item'];
    }
    $detectedSpells = [];
    foreach (['Equipo' => $g('Equipment'), 'Tesoro' => trim($g('Treasure') . "\n" . $g('Treasure 2'))] as $source => $text) {
        foreach (ficha_split_items($text) as $it) {
            if ($it['kind'] === 'spell') $detectedSpells[] = $it['name'];
            $category = ficha_item_category($it['name']);
            if ($it['kind'] === 'spell') $category = 'Objeto mágico';
            elseif ($source === 'Tesoro' && $category === 'Utilidad') $category = 'Vanidad';
            $items[] = $it + ['category' => $category, 'equipped' => false, 'description' => '', 'source' => $source];
        }
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
            'lista' => array_values(array_unique(array_merge($spells, $detectedSpells))),
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
