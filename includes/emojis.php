<?php
declare(strict_types=1);

/*
 * Lista cerrada de emojis permitidos.
 * Se valida tanto en la interfaz como en el servidor para impedir
 * que se guarde cualquier valor escrito/manipulado manualmente.
 */
function favor_allowed_emojis(): array
{
    return [
        '🛡️' => 'Escudo',
        '⚔️' => 'Espadas',
        '🗡️' => 'Daga',
        '🏹' => 'Arco',
        '🪄' => 'Varita',
        '🔮' => 'Orbe mágico',
        '🧙' => 'Mago',
        '🧝' => 'Elfo',
        '🧚' => 'Hada',
        '🧛' => 'Vampiro',
        '🐉' => 'Dragón',
        '🐲' => 'Dragón oriental',
        '🐺' => 'Lobo',
        '🦇' => 'Murciélago',
        '🦅' => 'Águila',
        '💀' => 'Calavera',
        '👑' => 'Corona',
        '🏰' => 'Castillo',
        '🧿' => 'Talismán',
        '📜' => 'Pergamino',
    ];
}
