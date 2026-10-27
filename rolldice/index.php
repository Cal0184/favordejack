<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth-widget.php';
favor_session_start();
favor_session_close();

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$siteTitle = 'El Favor de Jack — Lanzador de Dados';
$siteDescription = 'Lanzador de dados de fantasía para partidas de rol: d2, d4, d6, d8, d10, d12, d20, d100, fórmulas personalizadas, ventaja, desventaja y tiradas guardadas.';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Agrega una versión basada en la fecha del archivo para evitar que el navegador
// mantenga CSS/JS antiguos en caché cuando actualices la página en el servidor.
function asset(string $path): string
{
    $cleanPath = ltrim($path, '/');
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);
    $version = is_file($absolutePath) ? (string) filemtime($absolutePath) : '1';
    return e($cleanPath . '?v=' . $version);
}
?>
<!DOCTYPE html>

<html lang="es">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1" name="viewport"/>
<title><?= e($siteTitle) ?></title>
<meta content="<?= e($siteDescription) ?>" name="description"/>
<link href="<?= asset('base.css') ?>" rel="stylesheet"/>
<link href="<?= asset('dice.css') ?>" rel="stylesheet"/>
<link href="<?= asset('jack.css') ?>" rel="stylesheet"/>
<link href="<?= e(favor_static(FAVOR_LOGO)) ?>" rel="icon" type="image/webp"/></head>
<body>
<?php favor_auth_widget(); ?>
<div class="jack-page-shell">
<header class="jack-hero jack-hero-v2">
<img alt="Icono del Favor de Jack" class="jack-brand-icon" src="<?= e(favor_static(FAVOR_LOGO)) ?>"/>
<div class="jack-hero-copy">
<p class="jack-kicker">LA SUERTE ESTÁ ECHADA</p>
<h1>El Favor de <span>Jack</span></h1>
<p class="jack-subtitle">Lanza. Mira cómo gira el destino. Acepta el resultado.</p>
</div>
<a class="jack-back-button" href="<?= favor_url_html() ?>" aria-label="Volver a El Favor de Jack">
<span aria-hidden="true">←</span> Volver
</a>
</header>
<main>
<div class="responsive" id="fast_dice_roller_block"><div class="jack-left-column"><div class="dice_attribute_container responsive" id="dice_attribute_container">
<div class="dice_attribute_container_element responsive">
<div class="dice_attribute_button responsive" data-label="Cantidad de dados">
<div class="left_right_dice_attribute_button" onclick="addDiceAmount(-1)">
<p class="left_right_dice_attribute_button_text">-</p>
</div>
<p class="option_dice_attribute_button responsive" id="option_dice_attribute_button_diceAmount" onclick="focusOnInput('diceAmountInput')">
<input class="diceAmountInput" id="diceAmountInput" onchange="updateDiceAmount()" value="1"/>
<span class="option_dice_attribute_button_span_d">d</span>
</p>
<div class="left_right_dice_attribute_button" onclick="addDiceAmount(1)">
<p class="left_right_dice_attribute_button_text">+</p>
</div>
</div>
<div class="dice_attribute_button responsive" data-label="Modificador">
<div class="left_right_dice_attribute_button" onclick="addDiceAdvantageAmount(-1)">
<p class="left_right_dice_attribute_button_text">-</p>
</div>
<p class="option_dice_attribute_button responsive" onclick="focusOnInput('diceAdvantageAmountInput')">
<input class="diceAdvantageAmountInput" id="diceAdvantageAmountInput" onchange="updateDiceAdvantageAmount()" value="+0"/>
</p>
<div class="left_right_dice_attribute_button" onclick="addDiceAdvantageAmount(1)">
<p class="left_right_dice_attribute_button_text">+</p>
</div>
</div>
</div><div class="jack-roll-mode-panel" id="jack_roll_mode_panel">
<div class="jack-mode-copy">
<span class="jack-control-eyebrow">Destino</span>
<strong>Modo de tirada</strong>
<small id="jack_advantage_help">Una tirada por dado.</small>
</div>
<div aria-label="Modo de tirada" class="jack-mode-switch" role="group">
<button class="jack-mode-btn active" data-mode="normal" onclick="setAdvantageMode('normal')" type="button">Normal</button>
<button class="jack-mode-btn" data-mode="advantage" onclick="setAdvantageMode('advantage')" type="button">Ventaja</button>
<button class="jack-mode-btn" data-mode="disadvantage" onclick="setAdvantageMode('disadvantage')" type="button">Desventaja</button>
</div>
</div><section class="jack-expression-panel" id="jack_expression_panel">
<div class="jack-expression-copy">
<span class="jack-control-eyebrow">Tirada escrita</span>
<strong>Escribe una fórmula</strong>
<small>Combina dados y modificadores: 2d6+5+3d4+2d6</small>
</div>
<div class="jack-expression-controls">
<input aria-label="Fórmula de tirada" autocomplete="off" id="jack_expression_input" placeholder="Ej. 2d6+5+3d4+2d6" spellcheck="false"/>
<button id="jack_expression_roll" onclick="rollDiceExpression()" type="button">Lanzar fórmula</button>
</div>
<p aria-live="polite" class="jack-expression-error" id="jack_expression_error"></p>
</section>
<div class="dice_reset_button" onclick="resetDiceAttributes()">
<p class="dice_reset_button_text">Reiniciar</p>
</div>
<p class="dice_roll_button_container_title">Elige el dado del destino</p>
<div class="dice_roll_button_container responsive">
<div class="dice_roll_button" onclick="rollDice(2)" title="Lanzar d2">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text">d2</p>
<img alt="dice_2" class="dice_roll_button_img" src="<?= asset('images/dice_2.webp') ?>"/>
</div>
<div class="dice_roll_button" onclick="rollDice(4)" title="Lanzar d4">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text">d4</p>
<img alt="dice_4" class="dice_roll_button_img" src="<?= asset('images/dice_4.webp') ?>"/>
</div>
<div class="dice_roll_button" onclick="rollDice(6)" title="Lanzar d6">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text">d6</p>
<img alt="dice_6" class="dice_roll_button_img" src="<?= asset('images/dice_6.webp') ?>"/>
</div>
<div class="dice_roll_button" onclick="rollDice(8)" title="Lanzar d8">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text">d8</p>
<img alt="dice_8" class="dice_roll_button_img" src="<?= asset('images/dice_8.webp') ?>"/>
</div>
<div class="dice_roll_button" onclick="rollDice(10)" title="Lanzar d10">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text">d10</p>
<img alt="dice_10" class="dice_roll_button_img" src="<?= asset('images/dice_10.webp') ?>"/>
</div>
<div class="dice_roll_button" onclick="rollDice(12)" title="Lanzar d12">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text">d12</p>
<img alt="dice_12" class="dice_roll_button_img" src="<?= asset('images/dice_12.webp') ?>"/>
</div>
<div class="dice_roll_button" onclick="rollDice(20)" title="Lanzar d20">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text">d20</p>
<img alt="dice_20" class="dice_roll_button_img" src="<?= asset('images/dice_20.webp') ?>"/>
</div>
<div class="dice_roll_button" onclick="rollDice(100)" title="Lanzar d100">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text">d100</p>
<img alt="dice_10" class="dice_roll_button_img" src="<?= asset('images/dice_100.webp') ?>"/>
</div>
<div class="dice_roll_button_custom_block responsive">
<div class="dice_roll_button" onclick="customRollDice()">
<p class="dice_roll_button_roll_text">Lanzar</p>
<p class="dice_roll_button_text" id="dice_roll_button_custom_text">d16</p>
<p class="dice_roll_button_custom_text_1">Personalizado</p>
</div>
<div class="dice_roll_button_custom_container responsive">
<div class="dice_roll_button_custom_text_2_container">
<div class="dice_roll_button_custom_text_2_element">
<label class="dice_roll_button_custom_text_2" for="dice_roll_button_custom_input">Editar</label>
</div>
</div>
<input aria-label="Caras del dado personalizado" class="dice_roll_button_custom_input" id="dice_roll_button_custom_input" onchange="setCustomRollDice()" value="16"/>
</div>
</div>
</div>
<div class="dice_roll_other_option_container responsive" id="dice_roll_other_option_container">
<p class="regulartext" id="dnd_dice_roller_toggle_text" style="width: fit-content; font-style: italic;">Desactiva para saltar la animación.</p>
<div class="dice_roll_style_selector_container responsive_left" onclick="toggleAnimationHistory()">
<div class="dice_roll_style_check_box selected" id="dice_roll_style_check_box_toggle_animation_history"></div>
<p class="dice_roll_style_text" id="dice_roll_style_text_toggle_animation_history">Animación <span class="dice_roll_style_text_span">/ Tirada instantánea</span></p>
</div>
</div>
</div><section class="jack-saved-rolls" id="jack_saved_rolls_section">
<div class="jack-section-heading">
<div>
<span class="jack-control-eyebrow">Atajos de Jack</span>
<h2>Tiradas guardadas</h2>
</div>
<p>Guarda tiradas simples o fórmulas completas y lánzalas con un clic.</p>
</div>
<div class="jack-save-composer">
<input autocomplete="off" id="jack_saved_roll_name" maxlength="36" placeholder="Nombre de la tirada (ej. Percepción)"/>
<button class="jack-save-btn" onclick="saveCurrentRoll()" type="button">Guardar tirada actual</button>
</div>
<div class="jack-saved-rolls-list" id="jack_saved_rolls_list">
<div class="jack-empty-saved">Aún no has guardado ninguna tirada.</div>
</div>
</section></div><div class="jack-right-column"><div class="dice_log_container" id="dice_log_container">
<div class="dice_main_result_container" id="dice_main_result_container">
<div class="roll_history_element_main_bg">
<div class="roll_history_element_main" id="roll_animation_element_main">
<div class="roll_history_element_name_result_container responsive_space-between">
<p class="roll_history_element_name_left_text" id="roll_animation_element_name_left_text_main">1d6 + 0</p>
<p class="roll_history_element_name_right_text_main">Resultado</p>
</div>
<div class="roll_history_element_name_result_container responsive_left">
<p class="roll_history_element_result_left_text" id="roll_animation_element_result_left_text_main">0</p>
<p class="roll_history_element_result_center_text responsive_left" id="roll_animation_element_result_center_text_main"></p>
<p class="roll_history_element_result_right_text" id="roll_animation_element_result_right_text_main">+0</p>
</div>
</div>
</div>
<div class="jack-roll-mode-detail" id="jack_roll_mode_detail"><span class="jack-mode-mark">NORMAL</span><span>Una tirada por dado.</span></div><div class="roll_history_separator">
<div class="roll_animation_separator" id="roll_animation_separator"></div>
</div>
<div class="dice_animation_scene" id="dice_animation_scene">
<div class="s_d_container">
<div class="s_d"></div>
</div>
<div class="dice_wrapper" id="dice_wrapper">
<div class="d_2 dice_element" id="d_2" onclick="rollDice('clickDice')">
<div class="f_d_2">
<p class="t_f_d_2">1</p>
</div>
<div class="f_d_2">
<p class="t_f_d_2">2</p>
</div>
<div class="s_f_d_2"></div>
<div class="s_f_d_2"></div>
</div>
<div class="d_4 dice_element" id="d_4" onclick="rollDice('clickDice')">
<div class="f_d_4">
<p class="t_f_d_4 t_f_d_4_left">2</p>
<p class="t_f_d_4 t_f_d_4_up">1</p>
<p class="t_f_d_4 t_f_d_4_right">4</p>
</div>
<div class="f_d_4">
<p class="t_f_d_4 t_f_d_4_left">3</p>
<p class="t_f_d_4 t_f_d_4_up">1</p>
<p class="t_f_d_4 t_f_d_4_right">2</p>
</div>
<div class="f_d_4">
<p class="t_f_d_4 t_f_d_4_left">4</p>
<p class="t_f_d_4 t_f_d_4_up">1</p>
<p class="t_f_d_4 t_f_d_4_right">3</p>
</div>
<div class="f_d_4">
<p class="t_f_d_4 t_f_d_4_left">4</p>
<p class="t_f_d_4 t_f_d_4_up">3</p>
<p class="t_f_d_4 t_f_d_4_right">2</p>
</div>
</div>
<div class="d_6 dice_element" id="d_6" onclick="rollDice('clickDice')">
<div class="f_d_6">
<p class="t_f_d_6">4</p>
</div>
<div class="f_d_6">
<p class="t_f_d_6">3</p>
</div>
<div class="f_d_6">
<p class="t_f_d_6">5</p>
</div>
<div class="f_d_6">
<p class="t_f_d_6">2</p>
</div>
<div class="f_d_6">
<p class="t_f_d_6">1</p>
</div>
<div class="f_d_6">
<p class="t_f_d_6">6</p>
</div>
</div>
<div class="d_8 dice_element" id="d_8" onclick="rollDice('clickDice')">
<div class="f_d_8">
<p class="t_f_d_8">1</p>
</div>
<div class="f_d_8">
<p class="t_f_d_8">5</p>
</div>
<div class="f_d_8">
<p class="t_f_d_8">7</p>
</div>
<div class="f_d_8">
<p class="t_f_d_8">3</p>
</div>
<div class="f_d_8">
<p class="t_f_d_8">6</p>
</div>
<div class="f_d_8">
<p class="t_f_d_8">8</p>
</div>
<div class="f_d_8">
<p class="t_f_d_8">4</p>
</div>
<div class="f_d_8">
<p class="t_f_d_8">2</p>
</div>
</div>
<div class="d_10 dice_element" id="d_10" onclick="rollDice('clickDice')">
<div class="f_d_10">
<p class="t_f_d_10">1</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">2</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">3</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">4</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">5</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">6</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">7</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">8</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">9</p>
</div>
<div class="f_d_10">
<p class="t_f_d_10">10</p>
</div>
</div>
<div class="d_12 dice_element" id="d_12" onclick="rollDice('clickDice')">
<div class="f_d_12">
<p class="t_f_d_12">1</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">2</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">3</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">4</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">5</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">6</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">12</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">8</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">9</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">10</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">11</p>
</div>
<div class="f_d_12">
<p class="t_f_d_12">7</p>
</div>
</div>
<div class="d_20 dice_element" id="d_20" onclick="rollDice('clickDice')">
<div class="f_d_20">
<p class="t_f_d_20">1</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">2</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">3</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">4</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">5</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">6</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">7</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">8</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">9</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">10</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">11</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">12</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">13</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">14</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">15</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">16</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">17</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">18</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">19</p>
</div>
<div class="f_d_20">
<p class="t_f_d_20">20</p>
</div>
</div>
<div class="d_custom dice_element" id="d_custom" onclick="rollDice('clickDice')">
<div class="f_d_custom">
<p class="t_f_d_custom" id="t_f_d_custom"></p>
</div>
<div class="f_d_custom"></div>
<div class="f_d_custom"></div>
<div class="f_d_custom"></div>
<div class="f_d_custom"></div>
<div class="f_d_custom"></div>
<div class="f_d_custom"></div>
<div class="f_d_custom"></div>
<div class="f_d_custom"></div>
<div class="f_d_custom"></div>
<div class="new_f_d_custom">
<p class="new_t_f_d_custom" id="new_t_f_d_custom"></p>
</div>
</div>
</div>
</div>
</div>
<div class="roll_history_container" id="roll_history_container">
<div class="roll_history_element_main_bg">
<div class="roll_history_element_main" id="roll_history_element_main">
<div class="roll_history_element_name_result_container responsive_space-between">
<p class="roll_history_element_name_left_text" id="roll_history_element_name_left_text_main">1d6 + 0</p>
<p class="roll_history_element_name_right_text_main">Resultado</p>
</div>
<div class="roll_history_element_name_result_container responsive_left">
<p class="roll_history_element_result_left_text" id="roll_history_element_result_left_text_main">0</p>
<p class="roll_history_element_result_center_text responsive_left" id="roll_history_element_result_center_text_main"></p>
<p class="roll_history_element_result_right_text" id="roll_history_element_result_right_text_main">+0</p>
</div>
</div>
</div>
<div class="roll_history_separator"></div>
<div class="roll_history_element_container" id="roll_history_element_container">
<div class="roll_dice_info_history_container" id="history_element_0">
<p class="roll_dice_info_history_text">Tus últimas tiradas aparecerán aquí.</p>
</div>
</div>
</div>
</div><div class="dnd_roll_again_container responsive">
<div class="dnd_roll_again_element dnd_roll_again_element_1" onclick="rollDice(diceNumber)">
<p class="dnd_roll_again_text">Lanzar de nuevo</p>
</div>
<div class="dnd_roll_again_element dnd_roll_again_element_2" onclick="goToSection('selectDice')">
<p class="dnd_roll_again_text">Cambiar dados</p>
</div>
</div><div class="e_dice_log_block">
<p class="e_dice_log_title">Historial de tiradas</p>
<div class="e_dice_log_container" id="e_dice_log_container">
<div class="e_roll_dice_info_history_container" id="e_dice_log_element_0">
<p class="roll_dice_info_history_text">Tus tiradas anteriores aparecerán aquí.</p>
</div>
</div>
</div></div></div>
</main>

</div>
<script src="<?= asset('dice.js') ?>"></script>
<script src="<?= asset('jack-features.js') ?>"></script></body>
</html>