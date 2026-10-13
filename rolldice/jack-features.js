/* Favor de Jack — funciones propias: ventaja/desventaja + tiradas guardadas */
var jackRollMode = 'normal';
var jackRollPairs = [];
var jackSavedRollsMemory = [];
const JACK_SAVED_KEY = 'favor-de-jack:saved-rolls:v2';

function jackModeLabel(mode = jackRollMode) {
    return mode === 'advantage' ? 'Ventaja' : mode === 'disadvantage' ? 'Desventaja' : 'Normal';
}

function jackModeHelp(mode = jackRollMode) {
    if (mode === 'advantage') return 'Se tiran dos dados y se conserva el resultado mayor.';
    if (mode === 'disadvantage') return 'Se tiran dos dados y se conserva el resultado menor.';
    return 'Una tirada por dado.';
}

function jackModifierText() {
    return `${diceAdvantageAmount < 0 ? '-' : '+'}${Math.abs(diceAdvantageAmount)}`;
}

function jackRollLabel() {
    const mode = jackRollMode === 'normal' ? '' : ` · ${jackModeLabel()}`;
    return `${diceAmount}d${diceNumber} ${jackModifierText()}${mode}`;
}

function setAdvantageMode(mode) {
    if (!['normal', 'advantage', 'disadvantage'].includes(mode)) mode = 'normal';
    jackRollMode = mode;
    document.querySelectorAll('.jack-mode-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    const help = document.getElementById('jack_advantage_help');
    if (help) help.textContent = jackModeHelp();
    updateJackRollModeDetail(false);
}

function updateJackRollModeDetail(showPairs = true) {
    const detail = document.getElementById('jack_roll_mode_detail');
    if (!detail) return;
    const mark = detail.querySelector('.jack-mode-mark');
    const copy = detail.querySelector('span:last-child');
    if (mark) mark.textContent = jackModeLabel().toUpperCase();

    let text = jackModeHelp();
    if (showPairs && jackRollMode !== 'normal' && jackRollPairs.length) {
        const pairText = jackRollPairs.map(p => `${p.a} / ${p.b} → ${p.chosen}`).join(' · ');
        text = `${jackModeHelp()} ${pairText}`;
    }
    if (copy) copy.textContent = text;

    detail.classList.toggle('advantage', jackRollMode === 'advantage');
    detail.classList.toggle('disadvantage', jackRollMode === 'disadvantage');
}

function updateJackMainLabels() {
    const label = jackRollLabel();
    ['roll_animation_element_name_left_text_main', 'roll_history_element_name_left_text_main'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = label;
    });
}

// Conservamos el motor original, pero sustituimos únicamente la generación del resultado.
window.rollDice = function(selectedDiceNumber) {
    if (selectedDiceNumber !== 'clickDice') diceNumber = Number(selectedDiceNumber);

    goToSection('rollSection');
    rollIndex++;
    if (rollIndex === 1) {
        const right = document.getElementById('roll_history_element_result_right_text_main');
        if (right) right.style.left = '0';
    }

    rollResult = [0];
    jackRollPairs = [];
    let subtotal = 0;

    for (let i = 1; i <= diceAmount; i++) {
        const a = 1 + Math.floor(Math.random() * diceNumber);
        let chosen = a;
        let b = null;

        if (jackRollMode !== 'normal') {
            b = 1 + Math.floor(Math.random() * diceNumber);
            chosen = jackRollMode === 'advantage' ? Math.max(a, b) : Math.min(a, b);
            jackRollPairs.push({ a, b, chosen });
        }

        rollResult[i] = chosen;
        subtotal += chosen;
    }

    rollResult[0] = subtotal + diceAdvantageAmount;
    rollResultString = rollResult.slice(1).map(result => `<span class="roll_history_element_result_span">${result}</span>`).join(' ');
    e_rollResultString = rollResult.slice(1).map(result => `<span class="e_roll_history_element_result_span">${result}</span>`).join(' ');

    cancelPreviousAnimations();
    rollDiceAnimationMode();
    rollDiceFastMode();
    updateJackMainLabels();
    updateJackRollModeDetail(true);

    const historyItem = document.getElementById(`history_element_${rollIndex}`);
    if (historyItem) {
        const label = historyItem.querySelector('.roll_history_element_name_left_text');
        if (label) label.textContent = jackRollLabel();
    }
};

// El historial extendido incluye también el modo de tirada y, cuando corresponde, los dos dados comparados.
window.logFunction = function() {
    let diceLogTimeout = 0;
    const currentDateTime = new Date();
    const formattedDate = `${String(currentDateTime.getDate()).padStart(2,'0')}/${String(currentDateTime.getMonth()+1).padStart(2,'0')}/${currentDateTime.getFullYear()} ${String(currentDateTime.getHours()).padStart(2,'0')}:${String(currentDateTime.getMinutes()).padStart(2,'0')}`;

    if (rollMode === 'animation') diceLogTimeout = Math.max(0, (rollResult.length - 1) * animationDuration - 400);

    const rollLabel = jackRollLabel();
    const total = rollResult[0];
    const resultHTML = e_rollResultString;
    const modifier = jackModifierText();
    const mode = jackRollMode;
    const pairs = jackRollPairs.map(p => `${p.a}/${p.b}→${p.chosen}`).join(' · ');
    const currentIndex = rollIndex;

    const timeoutLog = setTimeout(function () {
        const container = document.getElementById('e_dice_log_container');
        if (!container) return;
        const modeLine = mode === 'normal' ? '' : `<p class="jack-history-mode ${mode}">${jackModeLabel(mode)} · ${pairs}</p>`;
        container.innerHTML = `
        <div id="e_dice_log_element_${currentIndex}" class="e_dice_log_element responsive_left">
            <p class="e_roll_history_element_name_right_text">#${currentIndex} &nbsp;&nbsp;&nbsp; ${formattedDate}</p>
            <div class="e_dice_log_element_1 responsive_left">
                <p class="e_roll_history_element_name_left_text">${rollLabel}</p>
                <p class="e_roll_history_element_result_left_text">${total}</p>
                <p class="e_roll_history_element_result_center_text responsive_left">${resultHTML}</p>
                <p class="e_roll_history_element_result_right_text">${modifier}</p>
            </div>
            ${modeLine}
        </div>` + container.innerHTML;

        const empty = document.getElementById('e_dice_log_element_0');
        if (empty) empty.remove();
        const old = document.getElementById(`e_dice_log_element_${currentIndex - (maxHistorySize + maxExtendedHistorySize)}`);
        if (old) old.remove();
    }, diceLogTimeout);

    animationTimeouts.push(timeoutLog);
};

const jackOriginalResetDiceAttributes = window.resetDiceAttributes;
window.resetDiceAttributes = function() {
    jackOriginalResetDiceAttributes();
    setAdvantageMode('normal');
};

const jackOriginalToggleAnimationHistory = window.toggleAnimationHistory;
window.toggleAnimationHistory = function() {
    jackOriginalToggleAnimationHistory();
    updateJackMainLabels();
    updateJackRollModeDetail(true);
};

function jackStorageRead() {
    try {
        const raw = localStorage.getItem(JACK_SAVED_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch (error) {
        return jackSavedRollsMemory;
    }
}

function jackStorageWrite(value) {
    jackSavedRollsMemory = value;
    try { localStorage.setItem(JACK_SAVED_KEY, JSON.stringify(value)); } catch (error) {}
}

function saveCurrentRoll() {
    const input = document.getElementById('jack_saved_roll_name');
    const fallback = `${diceAmount}d${diceNumber} ${jackModifierText()}`;
    const name = (input?.value || '').trim() || fallback;
    const saved = jackStorageRead();
    saved.unshift({
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        name: name.slice(0, 36),
        amount: diceAmount,
        sides: diceNumber,
        modifier: diceAdvantageAmount,
        mode: jackRollMode
    });
    jackStorageWrite(saved.slice(0, 24));
    if (input) input.value = '';
    renderSavedRolls();
}

function loadSavedRoll(id, rollNow = false) {
    const preset = jackStorageRead().find(item => item.id === id);
    if (!preset) return;

    diceAmount = Number(preset.amount) || 1;
    diceNumber = Number(preset.sides) || 20;
    diceAdvantageAmount = Number(preset.modifier) || 0;
    diceAdvantageSign = diceAdvantageAmount < 0 ? '-' : '+';

    const amountInput = document.getElementById('diceAmountInput');
    const modifierInput = document.getElementById('diceAdvantageAmountInput');
    if (amountInput) amountInput.value = diceAmount;
    if (modifierInput) modifierInput.value = jackModifierText();

    if (![2,4,6,8,10,12,20,100].includes(diceNumber)) {
        const customInput = document.getElementById('dice_roll_button_custom_input');
        const customText = document.getElementById('dice_roll_button_custom_text');
        if (customInput) customInput.value = diceNumber;
        if (customText) customText.textContent = `d${diceNumber}`;
    }

    setAdvantageMode(preset.mode || 'normal');
    updateJackMainLabels();
    if (rollNow) rollDice(diceNumber);
    else goToSection('selectDice');
}

function deleteSavedRoll(id) {
    jackStorageWrite(jackStorageRead().filter(item => item.id !== id));
    renderSavedRolls();
}

function renderSavedRolls() {
    const container = document.getElementById('jack_saved_rolls_list');
    if (!container) return;
    const saved = jackStorageRead();
    container.replaceChildren();

    if (!saved.length) {
        const empty = document.createElement('div');
        empty.className = 'jack-empty-saved';
        empty.textContent = 'Aún no has guardado ninguna tirada.';
        container.appendChild(empty);
        return;
    }

    saved.forEach(item => {
        const card = document.createElement('article');
        card.className = 'jack-saved-card';

        const info = document.createElement('div');
        info.className = 'jack-saved-info';
        const name = document.createElement('strong');
        name.textContent = item.name;
        const formula = document.createElement('span');
        const sign = Number(item.modifier) < 0 ? '-' : '+';
        formula.textContent = `${item.amount}d${item.sides} ${sign}${Math.abs(Number(item.modifier) || 0)} · ${jackModeLabel(item.mode)}`;
        info.append(name, formula);

        const actions = document.createElement('div');
        actions.className = 'jack-saved-actions';
        const load = document.createElement('button');
        load.type = 'button'; load.className = 'jack-preset-load'; load.textContent = 'Cargar';
        load.addEventListener('click', () => loadSavedRoll(item.id, false));
        const roll = document.createElement('button');
        roll.type = 'button'; roll.className = 'jack-preset-roll'; roll.textContent = 'Lanzar';
        roll.addEventListener('click', () => loadSavedRoll(item.id, true));
        const del = document.createElement('button');
        del.type = 'button'; del.className = 'jack-preset-delete'; del.textContent = '×'; del.title = 'Eliminar tirada guardada';
        del.addEventListener('click', () => deleteSavedRoll(item.id));
        actions.append(load, roll, del);

        card.append(info, actions);
        container.appendChild(card);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setAdvantageMode('normal');
    renderSavedRolls();
    updateJackMainLabels();
});

function markJackSelectedDie() {
    document.querySelectorAll('.dice_roll_button').forEach(el => el.classList.remove('jack-selected-die'));
    const buttons = Array.from(document.querySelectorAll('.dice_roll_button'));
    const target = buttons.find(el => {
        const oc = el.getAttribute('onclick') || '';
        return oc === `rollDice(${diceNumber})` || (![2,4,6,8,10,12,20,100].includes(diceNumber) && oc === 'customRollDice()');
    });
    if (target) target.classList.add('jack-selected-die');
}

const jackRollDiceWithSelection = window.rollDice;
window.rollDice = function(selectedDiceNumber) {
    jackRollDiceWithSelection(selectedDiceNumber);
    markJackSelectedDie();
};

document.addEventListener('DOMContentLoaded', () => {
    markJackSelectedDie();
    const saveName = document.getElementById('jack_saved_roll_name');
    if (saveName) saveName.addEventListener('keydown', event => {
        if (event.key === 'Enter') saveCurrentRoll();
    });
});

/* =========================================================
   V3 — DOS animaciones simultáneas para ventaja/desventaja
   ========================================================= */
const jackOriginalRollDiceAnimationModeV2 = window.rollDiceAnimationMode;

function jackEnsureDualDiceStage() {
    const scene = document.getElementById('dice_animation_scene');
    if (!scene) return null;

    let stage = document.getElementById('jack_dual_dice_stage');
    if (!stage) {
        stage = document.createElement('div');
        stage.id = 'jack_dual_dice_stage';
        stage.className = 'jack-dual-dice-stage';
        stage.setAttribute('aria-live', 'polite');
        scene.appendChild(stage);
    }
    return stage;
}

function jackHideDualDiceStage() {
    const stage = document.getElementById('jack_dual_dice_stage');
    if (stage) {
        stage.classList.remove('active', 'advantage', 'disadvantage');
        stage.replaceChildren();
    }

    const originalWrapper = document.getElementById('dice_wrapper');
    if (originalWrapper) originalWrapper.style.display = '';
    const originalShadow = document.querySelector('#dice_animation_scene > .s_d_container');
    if (originalShadow) originalShadow.style.display = '';
}

function jackStripCloneIdentity(node) {
    if (!(node instanceof Element)) return;
    node.removeAttribute('id');
    node.removeAttribute('onclick');
    node.querySelectorAll('[id], [onclick]').forEach(el => {
        el.removeAttribute('id');
        el.removeAttribute('onclick');
    });
}

function jackWrapperTransformForDice(sides) {
    switch (Number(sides)) {
        case 12: return 'rotateX(-85deg) rotateY(25deg)';
        case 2:  return 'rotateX(-10deg) rotateY(30deg) rotateZ(-30deg)';
        case 4:  return 'rotateX(0deg) rotateY(0deg) rotateZ(0deg)';
        default: return 'rotateX(-65deg) rotateY(40deg)';
    }
}

function jackResultRotation(sides, faceResult) {
    const supported = [2, 4, 6, 8, 10, 12, 20].includes(Number(sides));
    if (supported) {
        const key = `d_${sides}`;
        return diceFaceRotations[key][faceResult - 1];
    }
    return { x: 5, y: -36, z: 0 };
}

function jackBuildAnimatedDie(faceResult, slotName, spinVariant = 0) {
    const supported = [2, 4, 6, 8, 10, 12, 20].includes(Number(diceNumber));
    const sourceKey = supported ? `d_${diceNumber}` : 'd_custom';
    const source = document.getElementById(sourceKey);
    if (!source) return null;

    const slot = document.createElement('div');
    slot.className = 'jack-dual-slot';
    slot.dataset.result = String(faceResult);
    slot.addEventListener('click', () => rollDice('clickDice'));

    const caption = document.createElement('div');
    caption.className = 'jack-dual-caption';
    caption.textContent = slotName;

    const visual = document.createElement('div');
    visual.className = 'jack-dual-visual';

    const shadow = document.createElement('div');
    shadow.className = 'jack-dual-shadow';

    const wrapper = document.createElement('div');
    wrapper.className = 'jack-dual-wrapper';
    wrapper.style.transform = jackWrapperTransformForDice(diceNumber);

    const die = source.cloneNode(true);
    jackStripCloneIdentity(die);
    die.style.display = 'block';
    die.style.transition = 'transform 800ms cubic-bezier(.18,.7,.19,1)';

    // Los dados personalizados (incluido d100) muestran el resultado en texto.
    if (!supported) {
        const resultText = die.querySelector('.t_f_d_custom');
        const diceText = die.querySelector('.new_t_f_d_custom');
        if (resultText) resultText.textContent = faceResult;
        if (diceText) diceText.textContent = `d${diceNumber}`;
    }

    const result = document.createElement('div');
    result.className = 'jack-dual-result-pill';
    result.textContent = 'Girando…';

    wrapper.appendChild(die);
    visual.append(shadow, wrapper);
    slot.append(caption, visual, result);

    const rotation = jackResultRotation(diceNumber, faceResult);
    const direction = spinVariant % 2 === 0 ? 1 : -1;
    const startX = rotation.x - direction * 420;
    const startY = rotation.y + direction * 540;
    const startZ = rotation.z + direction * 90;
    die.style.transform = `rotateX(${startX}deg) rotateY(${startY}deg) rotateZ(${startZ}deg)`;

    // Dos frames garantizan que el navegador pinte la posición inicial antes de girar.
    requestAnimationFrame(() => requestAnimationFrame(() => {
        const turnsX = rotation.x + direction * 720;
        const turnsY = rotation.y + direction * 720;
        die.style.transform = `rotateX(${turnsX}deg) rotateY(${turnsY}deg) rotateZ(${rotation.z}deg)`;
    }));

    return slot;
}

function jackRevealDualChoice(stage, pair) {
    const slots = Array.from(stage.querySelectorAll('.jack-dual-slot'));
    slots.forEach(slot => {
        const value = Number(slot.dataset.result);
        const pill = slot.querySelector('.jack-dual-result-pill');
        const caption = slot.querySelector('.jack-dual-caption');
        const isTie = pair.a === pair.b;
        const isChosen = isTie || value === pair.chosen;

        slot.classList.toggle('chosen', isChosen);
        slot.classList.toggle('discarded', !isChosen);

        if (pill) pill.textContent = String(value);
        if (caption) {
            caption.textContent = isTie ? 'EMPATE' : (isChosen ? 'ELEGIDO' : 'DESCARTADO');
        }
    });
}

function jackRenderDualPair(pair, animate = true) {
    const stage = jackEnsureDualDiceStage();
    if (!stage) return;

    const originalWrapper = document.getElementById('dice_wrapper');
    if (originalWrapper) originalWrapper.style.display = 'none';
    const originalShadow = document.querySelector('#dice_animation_scene > .s_d_container');
    if (originalShadow) originalShadow.style.display = 'none';

    stage.replaceChildren();
    stage.className = `jack-dual-dice-stage active ${jackRollMode}`;

    const left = jackBuildAnimatedDie(pair.a, 'TIRADA A', 0);
    const right = jackBuildAnimatedDie(pair.b, 'TIRADA B', 1);
    if (left) stage.appendChild(left);
    if (right) stage.appendChild(right);

    if (!animate) {
        // Para mostrar una tirada anterior al volver a activar animaciones.
        stage.querySelectorAll('.dice_element').forEach((die, index) => {
            const value = index === 0 ? pair.a : pair.b;
            const rotation = jackResultRotation(diceNumber, value);
            die.style.transition = 'none';
            die.style.transform = `rotateX(${rotation.x}deg) rotateY(${rotation.y}deg) rotateZ(${rotation.z}deg)`;
        });
        jackRevealDualChoice(stage, pair);
    }
}

window.rollDiceAnimationMode = function() {
    // Tiradas normales siguen usando exactamente el motor original.
    if (jackRollMode === 'normal' || !jackRollPairs.length) {
        jackHideDualDiceStage();
        return jackOriginalRollDiceAnimationModeV2();
    }

    let mainResultSum = diceAdvantageAmount;
    const separator = document.getElementById('roll_animation_separator');
    const totalEl = document.getElementById('roll_animation_element_result_left_text_main');
    const resultsEl = document.getElementById('roll_animation_element_result_center_text_main');
    const modifierEl = document.getElementById('roll_animation_element_result_right_text_main');

    if (separator) {
        separator.style.transform = 'scaleX(0)';
        separator.style.opacity = '1';
    }
    if (modifierEl) {
        modifierEl.style.left = '-20px';
        modifierEl.textContent = jackModifierText();
    }
    if (totalEl) totalEl.textContent = '-';
    if (resultsEl) resultsEl.innerHTML = '';
    updateJackMainLabels();

    // En tirada instantánea no necesitamos reproducir animaciones.
    if (rollMode === 'history') {
        jackHideDualDiceStage();
        if (totalEl) totalEl.textContent = rollResult[0];
        if (resultsEl) resultsEl.innerHTML = rollResultString;
        if (modifierEl) modifierEl.style.left = '0px';
        return;
    }

    jackRollPairs.forEach((pair, index) => {
        const timeoutId = setTimeout(() => {
            jackRenderDualPair(pair, true);

            const revealTimeout = setTimeout(() => {
                const stage = document.getElementById('jack_dual_dice_stage');
                if (stage) jackRevealDualChoice(stage, pair);

                mainResultSum += pair.chosen;
                if (totalEl) totalEl.textContent = mainResultSum;
                if (modifierEl && index === 0) modifierEl.style.left = '0';

                if (resultsEl) {
                    const newSpan = document.createElement('span');
                    newSpan.classList.add('roll_history_element_result_span', 'pop-in');
                    newSpan.innerText = pair.chosen;
                    resultsEl.appendChild(newSpan);
                    setTimeout(() => newSpan.classList.remove('pop-in'), 300);
                }

                if (separator && jackRollPairs.length > 1) {
                    separator.style.transform = `scaleX(${(index + 1) / jackRollPairs.length})`;
                }

                if (index === jackRollPairs.length - 1) {
                    if (totalEl) {
                        totalEl.classList.add('bounce-effect');
                        setTimeout(() => totalEl.classList.remove('bounce-effect'), 750);
                    }
                    if (separator && jackRollPairs.length > 1) {
                        setTimeout(() => {
                            separator.style.transform = 'scaleX(0)';
                            separator.style.opacity = '0.5';
                        }, 300);
                    }
                }
            }, 820);

            animationTimeouts.push(revealTimeout);
        }, index * animationDuration);

        animationTimeouts.push(timeoutId);
    });

    triggerMainElementAnimation('animation');
};

// Al alternar entre "instantánea" y "animación", conserva la vista doble.
const jackToggleAnimationHistoryV3Base = window.toggleAnimationHistory;
window.toggleAnimationHistory = function() {
    jackToggleAnimationHistoryV3Base();

    if (rollMode === 'animation' && jackRollMode !== 'normal' && jackRollPairs.length) {
        const lastPair = jackRollPairs[jackRollPairs.length - 1];
        // El motor V2 acaba de mostrar un solo dado; lo sustituimos por la pareja correcta.
        jackRenderDualPair(lastPair, false);
    } else if (jackRollMode === 'normal') {
        jackHideDualDiceStage();
    }
};

/* =========================================================
   V4 — Tiradas escritas / fórmulas compuestas
   Ejemplo: 2d6+5+3d4+2d6
   ========================================================= */
var jackLastRollKind = 'basic';
var jackLastExpression = '';
var jackLastExpressionResult = null;

function jackExpressionError(message = '') {
    const el = document.getElementById('jack_expression_error');
    if (el) el.textContent = message;
}

function jackParseExpression(rawExpression) {
    let expression = String(rawExpression || '')
        .toLowerCase()
        .replace(/[−–—]/g, '-')
        .replace(/\s+/g, '');

    if (!expression) throw new Error('Escribe una tirada, por ejemplo 2d6+5+3d4.');
    if (!/^[0-9d+\-]+$/.test(expression)) {
        throw new Error('Solo se permiten números, d, + y -.');
    }

    if (!/^[+-]/.test(expression)) expression = '+' + expression;
    const rawTerms = expression.match(/[+-][^+-]+/g) || [];
    if (!rawTerms.length || rawTerms.join('') !== expression) {
        throw new Error('La fórmula no tiene un formato válido.');
    }

    const terms = [];
    let totalDice = 0;

    rawTerms.forEach(token => {
        const sign = token[0] === '-' ? -1 : 1;
        const body = token.slice(1);
        if (!body) throw new Error('Hay un + o - sin valor después.');

        if (body.includes('d')) {
            const match = body.match(/^(\d*)d(\d+)$/);
            if (!match) throw new Error(`No entiendo el término “${body}”.`);
            const amount = match[1] === '' ? 1 : Number(match[1]);
            const sides = Number(match[2]);
            if (!Number.isInteger(amount) || amount < 1 || amount > 120) {
                throw new Error('Cada grupo puede tener entre 1 y 120 dados.');
            }
            if (!Number.isInteger(sides) || sides < 1 || sides > 10000) {
                throw new Error('Los dados pueden tener entre 1 y 10000 caras.');
            }
            totalDice += amount;
            terms.push({ type: 'dice', sign, amount, sides });
        } else {
            const value = Number(body);
            if (!Number.isInteger(value)) throw new Error(`“${body}” no es un número válido.`);
            if (value > 1000000) throw new Error('Ese modificador es demasiado grande.');
            terms.push({ type: 'constant', sign, value });
        }
    });

    if (totalDice === 0) throw new Error('La fórmula debe contener al menos un dado.');
    if (totalDice > 240) throw new Error('La fórmula supera el límite de 240 dados.');

    const normalized = terms.map((term, index) => {
        const prefix = term.sign < 0 ? '-' : (index === 0 ? '' : '+');
        if (term.type === 'dice') return `${prefix}${term.amount}d${term.sides}`;
        return `${prefix}${term.value}`;
    }).join('');

    return { terms, normalized, totalDice };
}

function jackRollParsedExpression(parsed) {
    let total = 0;
    const groups = [];

    parsed.terms.forEach(term => {
        if (term.type === 'constant') {
            const contribution = term.sign * term.value;
            total += contribution;
            groups.push({ ...term, contribution });
            return;
        }

        const rolls = [];
        let rawSubtotal = 0;
        for (let i = 0; i < term.amount; i++) {
            const value = 1 + Math.floor(Math.random() * term.sides);
            rolls.push(value);
            rawSubtotal += value;
        }
        const contribution = term.sign * rawSubtotal;
        total += contribution;
        groups.push({ ...term, rolls, rawSubtotal, contribution });
    });

    return { total, groups };
}

function jackExpressionGroupText(group) {
    const sign = group.sign < 0 ? '-' : '+';
    if (group.type === 'constant') return `${sign}${group.value}`;
    return `${sign}${group.amount}d${group.sides} [${group.rolls.join(', ')}]`;
}

function jackExpressionBreakdownText(result) {
    return result.groups.map(jackExpressionGroupText).join('  ');
}

function jackExpressionResultSpans(result, className = 'roll_history_element_result_span') {
    return result.groups.map(group => {
        if (group.type === 'constant') {
            return `<span class="${className}">${group.sign < 0 ? '-' : '+'}${group.value}</span>`;
        }
        const prefix = group.sign < 0 ? '-' : '';
        return `<span class="${className}">${prefix}${group.amount}d${group.sides}:${group.rolls.join(',')}</span>`;
    }).join(' ');
}

function jackHideExpressionStage() {
    const stage = document.getElementById('jack_expression_stage');
    if (stage) stage.remove();
    const originalWrapper = document.getElementById('dice_wrapper');
    if (originalWrapper) originalWrapper.style.display = '';
    const originalShadow = document.querySelector('#dice_animation_scene > .s_d_container');
    if (originalShadow) originalShadow.style.display = '';
}

function jackRenderExpressionStage(parsed, chosen, comparisonText = '') {
    const scene = document.getElementById('dice_animation_scene');
    if (!scene) return;
    jackHideDualDiceStage();
    jackHideExpressionStage();

    const originalWrapper = document.getElementById('dice_wrapper');
    if (originalWrapper) originalWrapper.style.display = 'none';
    const originalShadow = document.querySelector('#dice_animation_scene > .s_d_container');
    if (originalShadow) originalShadow.style.display = 'none';

    const stage = document.createElement('div');
    stage.id = 'jack_expression_stage';
    stage.className = 'jack-expression-stage pop-in';

    const title = document.createElement('strong');
    title.textContent = parsed.normalized;
    const total = document.createElement('div');
    total.className = 'jack-expression-total';
    total.textContent = chosen.total;
    const breakdown = document.createElement('div');
    breakdown.className = 'jack-expression-breakdown';

    chosen.groups.forEach(group => {
        const chip = document.createElement('span');
        chip.className = 'jack-expression-chip';
        if (group.type === 'constant') {
            chip.textContent = `${group.sign < 0 ? '-' : '+'}${group.value}`;
        } else {
            chip.textContent = `${group.sign < 0 ? '-' : ''}${group.amount}d${group.sides} [${group.rolls.join(', ')}]`;
        }
        breakdown.appendChild(chip);
    });

    stage.append(title, total, breakdown);
    if (comparisonText) {
        const comparison = document.createElement('div');
        comparison.className = 'jack-expression-comparison';
        comparison.textContent = comparisonText;
        stage.appendChild(comparison);
    }
    scene.appendChild(stage);
    setTimeout(() => stage.classList.remove('pop-in'), 300);
}

function jackAddExpressionHistory(parsed, chosen, comparisonText = '') {
    const container = document.getElementById('e_dice_log_container');
    if (!container) return;

    const currentDateTime = new Date();
    const formattedDate = `${String(currentDateTime.getDate()).padStart(2,'0')}/${String(currentDateTime.getMonth()+1).padStart(2,'0')}/${currentDateTime.getFullYear()} ${String(currentDateTime.getHours()).padStart(2,'0')}:${String(currentDateTime.getMinutes()).padStart(2,'0')}`;

    const item = document.createElement('div');
    item.id = `e_dice_log_element_${rollIndex}`;
    item.className = 'e_dice_log_element responsive_left';

    const date = document.createElement('p');
    date.className = 'e_roll_history_element_name_right_text';
    date.textContent = `#${rollIndex}     ${formattedDate}`;

    const row = document.createElement('div');
    row.className = 'e_dice_log_element_1 responsive_left';
    const label = document.createElement('p');
    label.className = 'e_roll_history_element_name_left_text';
    label.textContent = `${parsed.normalized}${jackRollMode === 'normal' ? '' : ` · ${jackModeLabel()}`}`;
    const total = document.createElement('p');
    total.className = 'e_roll_history_element_result_left_text';
    total.textContent = chosen.total;
    const breakdown = document.createElement('p');
    breakdown.className = 'e_roll_history_element_result_center_text responsive_left';
    breakdown.innerHTML = jackExpressionResultSpans(chosen, 'e_roll_history_element_result_span');
    const mark = document.createElement('p');
    mark.className = 'e_roll_history_element_result_right_text';
    mark.textContent = 'Σ';
    row.append(label, total, breakdown, mark);
    item.append(date, row);

    if (comparisonText) {
        const mode = document.createElement('p');
        mode.className = `jack-history-mode ${jackRollMode}`;
        mode.textContent = comparisonText;
        item.appendChild(mode);
    }

    const empty = document.getElementById('e_dice_log_element_0');
    if (empty) empty.remove();
    container.prepend(item);
    while (container.children.length > maxHistorySize + maxExtendedHistorySize) {
        container.lastElementChild.remove();
    }
}

function jackPopulateExpressionResultPanels(parsed, chosen) {
    const label = `${parsed.normalized}${jackRollMode === 'normal' ? '' : ` · ${jackModeLabel()}`}`;
    const resultHTML = jackExpressionResultSpans(chosen);
    ['roll_animation_element_name_left_text_main','roll_history_element_name_left_text_main'].forEach(id => {
        const el = document.getElementById(id); if (el) el.textContent = label;
    });
    ['roll_animation_element_result_left_text_main','roll_history_element_result_left_text_main'].forEach(id => {
        const el = document.getElementById(id); if (el) el.textContent = chosen.total;
    });
    ['roll_animation_element_result_center_text_main','roll_history_element_result_center_text_main'].forEach(id => {
        const el = document.getElementById(id); if (el) el.innerHTML = resultHTML;
    });
    ['roll_animation_element_result_right_text_main','roll_history_element_result_right_text_main'].forEach(id => {
        const el = document.getElementById(id); if (el) { el.textContent = 'Σ'; el.style.left = '0'; }
    });
}

window.rollDiceExpression = function() {
    const input = document.getElementById('jack_expression_input');
    const raw = input ? input.value : '';
    let parsed;
    try {
        parsed = jackParseExpression(raw);
        jackExpressionError('');
    } catch (error) {
        jackExpressionError(error.message || 'No pude interpretar esa tirada.');
        return;
    }

    if (input) input.value = parsed.normalized;
    goToSection('rollSection');
    cancelPreviousAnimations();
    rollIndex++;

    const first = jackRollParsedExpression(parsed);
    let second = null;
    let chosen = first;
    let comparisonText = '';

    if (jackRollMode !== 'normal') {
        second = jackRollParsedExpression(parsed);
        chosen = jackRollMode === 'advantage'
            ? (first.total >= second.total ? first : second)
            : (first.total <= second.total ? first : second);
        comparisonText = `${jackModeLabel()}: A ${first.total} / B ${second.total} → ${chosen.total}`;
    }

    jackLastRollKind = 'expression';
    jackLastExpression = parsed.normalized;
    jackLastExpressionResult = { parsed, first, second, chosen };

    // Las fórmulas siempre dejan visible el resultado; el historial ya vive debajo a la derecha.
    const main = document.getElementById('dice_main_result_container');
    const compactHistory = document.getElementById('roll_history_container');
    if (main) main.style.display = 'block';
    if (compactHistory) compactHistory.style.display = 'none';

    jackPopulateExpressionResultPanels(parsed, chosen);
    updateJackRollModeDetail(false);
    const detail = document.getElementById('jack_roll_mode_detail');
    if (detail) {
        const copy = detail.querySelector('span:last-child');
        if (copy) copy.textContent = comparisonText || `Fórmula: ${jackExpressionBreakdownText(chosen)}`;
    }

    jackRenderExpressionStage(parsed, chosen, comparisonText);
    jackAddExpressionHistory(parsed, chosen, comparisonText);
    triggerMainElementAnimation('animation');
};

// Una tirada normal vuelve a mostrar los dados 3D y pasa a ser la tirada activa para "Guardar".
const jackV4BaseRollDice = window.rollDice;
window.rollDice = function(selectedDiceNumber) {
    jackLastRollKind = 'basic';
    jackHideExpressionStage();
    return jackV4BaseRollDice(selectedDiceNumber);
};

// Guardado V4: conserva también fórmulas completas.
window.saveCurrentRoll = function() {
    const input = document.getElementById('jack_saved_roll_name');
    const saved = jackStorageRead();
    let item;

    if (jackLastRollKind === 'expression' && jackLastExpression) {
        item = {
            id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
            type: 'expression',
            name: ((input?.value || '').trim() || jackLastExpression).slice(0, 36),
            expression: jackLastExpression,
            mode: jackRollMode
        };
    } else {
        const fallback = `${diceAmount}d${diceNumber} ${jackModifierText()}`;
        item = {
            id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
            type: 'basic',
            name: ((input?.value || '').trim() || fallback).slice(0, 36),
            amount: diceAmount,
            sides: diceNumber,
            modifier: diceAdvantageAmount,
            mode: jackRollMode
        };
    }

    saved.unshift(item);
    jackStorageWrite(saved.slice(0, 24));
    if (input) input.value = '';
    renderSavedRolls();
};

window.loadSavedRoll = function(id, rollNow = false) {
    const preset = jackStorageRead().find(item => item.id === id);
    if (!preset) return;

    if (preset.type === 'expression' || preset.expression) {
        const expressionInput = document.getElementById('jack_expression_input');
        if (expressionInput) expressionInput.value = preset.expression || '';
        setAdvantageMode(preset.mode || 'normal');
        jackLastRollKind = 'expression';
        jackLastExpression = preset.expression || '';
        if (rollNow) rollDiceExpression();
        else goToSection('selectDice');
        return;
    }

    diceAmount = Number(preset.amount) || 1;
    diceNumber = Number(preset.sides) || 20;
    diceAdvantageAmount = Number(preset.modifier) || 0;
    diceAdvantageSign = diceAdvantageAmount < 0 ? '-' : '+';

    const amountInput = document.getElementById('diceAmountInput');
    const modifierInput = document.getElementById('diceAdvantageAmountInput');
    if (amountInput) amountInput.value = diceAmount;
    if (modifierInput) modifierInput.value = jackModifierText();

    if (![2,4,6,8,10,12,20,100].includes(diceNumber)) {
        const customInput = document.getElementById('dice_roll_button_custom_input');
        const customText = document.getElementById('dice_roll_button_custom_text');
        if (customInput) customInput.value = diceNumber;
        if (customText) customText.textContent = `d${diceNumber}`;
    }

    setAdvantageMode(preset.mode || 'normal');
    jackLastRollKind = 'basic';
    updateJackMainLabels();
    if (rollNow) rollDice(diceNumber);
    else goToSection('selectDice');
};

window.renderSavedRolls = function() {
    const container = document.getElementById('jack_saved_rolls_list');
    if (!container) return;
    const saved = jackStorageRead();
    container.replaceChildren();

    if (!saved.length) {
        const empty = document.createElement('div');
        empty.className = 'jack-empty-saved';
        empty.textContent = 'Aún no has guardado ninguna tirada.';
        container.appendChild(empty);
        return;
    }

    saved.forEach(item => {
        const card = document.createElement('article');
        card.className = 'jack-saved-card';

        const info = document.createElement('div');
        info.className = 'jack-saved-info';
        const name = document.createElement('strong');
        name.textContent = item.name;
        const formula = document.createElement('span');
        if (item.type === 'expression' || item.expression) {
            formula.textContent = `${item.expression} · ${jackModeLabel(item.mode)}`;
        } else {
            const sign = Number(item.modifier) < 0 ? '-' : '+';
            formula.textContent = `${item.amount}d${item.sides} ${sign}${Math.abs(Number(item.modifier) || 0)} · ${jackModeLabel(item.mode)}`;
        }
        info.append(name, formula);

        const actions = document.createElement('div');
        actions.className = 'jack-saved-actions';
        const load = document.createElement('button');
        load.type = 'button'; load.className = 'jack-preset-load'; load.textContent = 'Cargar';
        load.addEventListener('click', () => loadSavedRoll(item.id, false));
        const roll = document.createElement('button');
        roll.type = 'button'; roll.className = 'jack-preset-roll'; roll.textContent = 'Lanzar';
        roll.addEventListener('click', () => loadSavedRoll(item.id, true));
        const del = document.createElement('button');
        del.type = 'button'; del.className = 'jack-preset-delete'; del.textContent = '×'; del.title = 'Eliminar tirada guardada';
        del.addEventListener('click', () => deleteSavedRoll(item.id));
        actions.append(load, roll, del);

        card.append(info, actions);
        container.appendChild(card);
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const expressionInput = document.getElementById('jack_expression_input');
    if (expressionInput) {
        expressionInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') rollDiceExpression();
        });
        expressionInput.addEventListener('input', () => jackExpressionError(''));
    }
    renderSavedRolls();
});
