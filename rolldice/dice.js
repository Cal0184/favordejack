var diceAmount = 1;
var diceAdvantageAmount = 0;
var diceAdvantageSign = "+";
var diceNumber = 6;
var previousDiceNumber = 6;
var rollIndex = 0;

const maxDiceNumber = 121;
const maxDiceAmount = 121;
const maxHistorySize = 11;
const maxExtendedHistorySize = 40;
const animationDuration = 1000;

var previousRandomX = 0;
var previousRandomY = 0;

var animationTimeouts = [];

var rollResult = [];

rollResult[0] = 0;

var rollMode = "animation";

var rollResultString = "";
var e_rollResultString = "";

function goToSection(sectionType) {
    let sectionId;
    let offset;

    if(sectionType == "rollSection"){
        if (window.innerWidth < 400) {
            sectionId = "dice_roll_other_option_container";
            offset = -40;
        } else if ((window.innerWidth >= 400) && (window.innerWidth < 860)) {
            sectionId = "dice_roll_other_option_container";
            offset = 0;
        } else {
            sectionId = "dice_log_container";
            offset = 25;
        }
    } else if(sectionType == "selectDice"){
        sectionId = "dice_attribute_container";
        offset = 25;
    }
    
    const element = document.getElementById(sectionId);

    const y = element.getBoundingClientRect().top + window.scrollY - offset;

    window.scrollTo({ top: y, behavior: 'smooth' });
}

function addDiceAmount(amountToAdd) {
    let newAmount = diceAmount + amountToAdd;
    if (newAmount > 0 && newAmount < maxDiceAmount) {
        diceAmount = newAmount;
        document.getElementById("diceAmountInput").value = diceAmount;
    }
}

function updateDiceAmount() {
    let inputElement = document.getElementById("diceAmountInput");
    let customValue = parseInt(inputElement.value);

    if (isNaN(customValue) || customValue <= 0) {
        customValue = 1;
    } else if (customValue >= maxDiceAmount) {
        customValue = maxDiceAmount - 1;
    }

    diceAmount = customValue;
    inputElement.value = customValue;
}

function addDiceAdvantageAmount(amountToAdd) {
    diceAdvantageAmount += amountToAdd;
    updateDiceAdvantageDisplay();
}

function updateDiceAdvantageAmount() {
    const inputElement = document.getElementById("diceAdvantageAmountInput");
    let value = parseInt(inputElement.value);
    if (isNaN(value)) {
        value = 0;
    }
    diceAdvantageAmount = value;
    updateDiceAdvantageDisplay();
}

function updateDiceAdvantageDisplay() {
    const inputElement = document.getElementById("diceAdvantageAmountInput");
    
    if (diceAdvantageAmount < 0) {
        inputElement.value = diceAdvantageAmount;
        diceAdvantageSign = "-";
    } else {
        inputElement.value = `+${diceAdvantageAmount}`;
        diceAdvantageSign = "+";
    }
}

function focusOnInput(inputId) {
    const inputElement = document.getElementById(inputId);
    inputElement.focus();
    setTimeout(() => {
        inputElement.selectionStart = inputElement.selectionEnd = inputElement.value.length;
    }, 0);
}

function resetDiceAttributes(){
    diceAmount = 1;
    diceAdvantageAmount = 0;

    document.getElementById("diceAmountInput").value = diceAmount;
    document.getElementById("diceAdvantageAmountInput").value = "+0";
}

function toggleAnimationHistory(){
    const checkBox = document.getElementById("dice_roll_style_check_box_toggle_animation_history");
    checkBox.classList.toggle("selected");
    checkBox.classList.toggle("non_selected");

    goToSection("rollSection");

    rollMode = rollMode === "animation" ? "history" : "animation";
    
    cancelPreviousAnimations();

    if(!document.getElementById(`e_dice_log_element_${rollIndex}`)){
        logFunction();
    }

    if(rollMode == "animation"){
        document.getElementById("dice_animation_scene").style.display = "flex";
        document.getElementById("roll_history_container").style.display = "none";
        document.getElementById("dice_main_result_container").style.display = "block";

        document.getElementById("roll_animation_element_name_left_text_main").textContent = `${diceAmount}d${diceNumber} ${diceAdvantageSign} ${Math.abs(diceAdvantageAmount)}`;
        document.getElementById("roll_animation_element_result_left_text_main").textContent = `${rollResult[0]}`;
        document.getElementById("roll_animation_element_result_center_text_main").innerHTML = `${rollResultString}`;
        
        document.getElementById("roll_animation_element_result_right_text_main").textContent = `${diceAdvantageSign}${Math.abs(diceAdvantageAmount)}`;

        document.getElementById("dnd_dice_roller_toggle_text").textContent = "Desactiva para saltar la animación.";
        document.getElementById("dice_roll_style_text_toggle_animation_history").innerHTML = `Animación <span class="dice_roll_style_text_span">/ Tirada instantánea</span>`;
        
        document.getElementById(`roll_animation_element_main`).classList.remove("animate");

        diceRollerAnimation(rollResult[rollResult.length - 1], false);
    }else if(rollMode == "history"){
        document.getElementById("dice_animation_scene").style.display = "none";
        document.getElementById("roll_history_container").style.display = "block";
        document.getElementById("dice_main_result_container").style.display = "none";

        document.getElementById("dnd_dice_roller_toggle_text").textContent = "Activa para ver la animación de dados.";
        document.getElementById("dice_roll_style_text_toggle_animation_history").innerHTML = `Tirada instantánea <span class="dice_roll_style_text_span">/ Animación</span>`;
    
        document.getElementById(`roll_history_element_main`).classList.remove("animate");
    }
}

function setCustomRollDice(){
    let inputElement = document.getElementById("dice_roll_button_custom_input");
    let customValue = parseInt(inputElement.value);

    if (isNaN(customValue)) {
        customValue = 1;
    }

    if(customValue >= maxDiceNumber){
        customValue = maxDiceNumber - 1;
    } else if(customValue <= 0){
        customValue = 1;
    }

    inputElement.value = customValue;
    document.getElementById("dice_roll_button_custom_text").textContent = `d${customValue}`;
}

function customRollDice(){
    let customValue = parseInt(document.getElementById("dice_roll_button_custom_input").value);

    if(customValue >= maxDiceNumber){
        rollDice(maxDiceNumber - 1);
    } else if(customValue <= 0){
        rollDice(1);
    } else{
        rollDice(customValue);
    }
}

function rollDice(selectedDiceNumber) {
    if(selectedDiceNumber != "clickDice"){
        diceNumber = selectedDiceNumber;
    }

    goToSection("rollSection");

    rollIndex++;

    rollIndex == 1 && (document.getElementById("roll_history_element_result_right_text_main").style.left = "0");

	rollResult = [];

	rollResult[0] = 0;

    for (let i = 1; i <= diceAmount; i++){
		rollResult[i] = 1 + Math.floor(Math.random() * diceNumber);
		rollResult[0] += rollResult[i];
    }

	rollResult[0] += diceAdvantageAmount;

    rollResultString = rollResult.slice(1).map(result => `<span class="roll_history_element_result_span">${result}</span>`).join(' ');
    e_rollResultString = rollResult.slice(1).map(result => `<span class="e_roll_history_element_result_span">${result}</span>`).join(' ');

    cancelPreviousAnimations();

    rollDiceAnimationMode();
    rollDiceFastMode();
}

function rollDiceFastMode(){
    document.getElementById("roll_history_element_name_left_text_main").textContent = `${diceAmount}d${diceNumber} ${diceAdvantageSign} ${Math.abs(diceAdvantageAmount)}`;
    document.getElementById("roll_history_element_result_left_text_main").textContent = `${rollResult[0]}`;
    document.getElementById("roll_history_element_result_center_text_main").innerHTML = `${rollResultString}`;
    document.getElementById("roll_history_element_result_right_text_main").textContent = `${diceAdvantageSign}${Math.abs(diceAdvantageAmount)}`;

    document.getElementById("roll_history_element_container").innerHTML = `
        <div id="history_element_${rollIndex}" class="roll_history_element">
            <div class="roll_history_element_name_result_container responsive_space-between">
                <p class="roll_history_element_name_left_text">${diceAmount}d${diceNumber} ${diceAdvantageSign} ${Math.abs(diceAdvantageAmount)}</p>
                <p class="roll_history_element_name_right_text">Tirada #${rollIndex}</p>
            </div>
            <div class="roll_history_element_name_result_container responsive_left">
                <p class="roll_history_element_result_left_text">${rollResult[0]}</p>
                <p class="roll_history_element_result_center_text responsive_left">${rollResultString}</p>
                <p class="roll_history_element_result_right_text">${diceAdvantageSign}${Math.abs(diceAdvantageAmount)}</p>
            </div>
        </div>` + document.getElementById("roll_history_element_container").innerHTML;

    rollIndex > 1 && (document.getElementById(`history_element_${rollIndex - 1}`).style.display = "block");

    rollIndex == 2 && (document.getElementById("history_element_0").remove());
    
    if((rollIndex > maxHistorySize) || document.getElementById(`history_element_${rollIndex - maxHistorySize}`)){
        document.getElementById(`history_element_${rollIndex - maxHistorySize}`).remove();
    }

    triggerMainElementAnimation("history");

    logFunction();
}

function logFunction(){
    let diceLogTimeout = 0;

    const currentDateTime = new Date();
    const month = String(currentDateTime.getMonth() + 1).padStart(2, '0');
    const day = String(currentDateTime.getDate()).padStart(2, '0');
    const year = currentDateTime.getFullYear();
    const hours = String(currentDateTime.getHours()).padStart(2, '0');
    const minutes = String(currentDateTime.getMinutes()).padStart(2, '0');
    
    const formattedDate = `${month}/${day}/${year} ${hours}:${minutes}`;

    if(rollMode == "animation"){
        diceLogTimeout = (rollResult.length - 1) * animationDuration - 400;
    }

    const timeoutLog = setTimeout(function () {
        document.getElementById("e_dice_log_container").innerHTML = `
        <div id="e_dice_log_element_${rollIndex}" class="e_dice_log_element responsive_left">
            <p class="e_roll_history_element_name_right_text">#${rollIndex} &nbsp;&nbsp;&nbsp; ${formattedDate}</p>
            <div class="e_dice_log_element_1 responsive_left">
                <p class="e_roll_history_element_name_left_text">${diceAmount}d${diceNumber} ${diceAdvantageSign} ${Math.abs(diceAdvantageAmount)}</p>
                <p class="e_roll_history_element_result_left_text">${rollResult[0]}</p>
                <p class="e_roll_history_element_result_center_text responsive_left">${e_rollResultString}</p>
                <p class="e_roll_history_element_result_right_text">${diceAdvantageSign}${Math.abs(diceAdvantageAmount)}</p>
            </div>
        </div>` + document.getElementById("e_dice_log_container").innerHTML;

        rollIndex == 1 && (document.getElementById("e_dice_log_element_0").remove());

        if((rollIndex > maxHistorySize + maxExtendedHistorySize) || document.getElementById(`e_dice_log_element_${rollIndex - maxHistorySize}`)){
            document.getElementById(`e_dice_log_element_${rollIndex - maxHistorySize}`).remove();
        }
    }, diceLogTimeout);

    animationTimeouts.push(timeoutLog);
}

function triggerMainElementAnimation(animationType) {
    let element = document.getElementById(`roll_${animationType}_element_main`);

    if(element){
        element.classList.remove("animate");

        void element.offsetWidth;
    
        element.classList.add("animate");
    }
}

function rollDiceAnimationMode(){
    let mainResultSum = diceAdvantageAmount;

    document.getElementById("roll_animation_separator").style.transform = "scaleX(0)";
    document.getElementById("roll_animation_separator").style.opacity = "1";

    document.getElementById("roll_animation_element_result_right_text_main").style.left = "-20px"

    document.getElementById("roll_animation_element_name_left_text_main").textContent = `${diceAmount}d${diceNumber} ${diceAdvantageSign} ${Math.abs(diceAdvantageAmount)}`;
    document.getElementById("roll_animation_element_result_left_text_main").textContent = "-";
    document.getElementById("roll_animation_element_result_center_text_main").innerHTML = "";
    document.getElementById("roll_animation_element_result_right_text_main").textContent = `${diceAdvantageSign}${Math.abs(diceAdvantageAmount)}`;

    if(rollMode == "history"){
        document.getElementById("roll_animation_element_result_left_text_main").textContent = rollResult[0];
        document.getElementById("roll_animation_element_result_center_text_main").innerHTML = `${rollResultString}`;
        document.getElementById("roll_animation_element_result_right_text_main").style.left = "0px"
        return;
    }

    for (let i = 1; i < rollResult.length; i++) {
        const timeoutId = setTimeout(function () {
            diceRollerAnimation(rollResult[i], true);

            setTimeout(function() {
                if(i == (rollResult.length - 1)){
                    document.getElementById("roll_animation_element_result_left_text_main").classList.add("bounce-effect");

                    setTimeout(() => {
                        document.getElementById("roll_animation_element_result_left_text_main").classList.remove("bounce-effect");
                    }, 750);

                    if(rollResult.length > 2){
                        setTimeout(() => {
                            document.getElementById("roll_animation_separator").style.transform = "scaleX(0)";
                            document.getElementById("roll_animation_separator").style.opacity = "0.5";
                        }, 300);
                    }
                }

                if(rollResult.length > 2){
                    document.getElementById("roll_animation_separator").style.transform = `scaleX(${i/(rollResult.length - 1)})`;
                }

                mainResultSum = mainResultSum + rollResult[i];
                document.getElementById("roll_animation_element_result_left_text_main").textContent = mainResultSum;

                i == 1 && (document.getElementById("roll_animation_element_result_right_text_main").style.left = "0");

                const newSpan = document.createElement('span');
                newSpan.classList.add('roll_history_element_result_span', 'pop-in');
                newSpan.innerText = rollResult[i];
                
                document.getElementById("roll_animation_element_result_center_text_main").appendChild(newSpan);

                setTimeout(() => {
                    newSpan.classList.remove('pop-in');
                }, 300);
            }, (animationDuration - 400));

        }, (i - 1) * animationDuration);

        animationTimeouts.push(timeoutId);
    }

    triggerMainElementAnimation("animation");
}


function cancelPreviousAnimations() {
    for (let timeoutId of animationTimeouts) {
        clearTimeout(timeoutId);
    }
    
    document.getElementById("roll_animation_separator").style.transform = "scaleX(0)";
    document.getElementById("roll_animation_separator").style.opacity = "0.5";

    animationTimeouts = [];
}

const diceFaceRotations = {
    d_2: [
        { x: 0, y: 0, z: 0 },
        { x: 0, y: 180, z: 0 }
    ],
    d_4: [
        { x: 5, y: 0, z: 0 },
        { x: 115, y: 0, z: -60 },
        { x: 245, y: 0, z: 0 },
        { x: 115, y: 0, z: 60 }
    ],
    d_6: [
        { x: 180, y: 0, z: 0 },
        { x: 0, y: 90, z: 90 },
        { x: 270, y: 0, z: 180 },
        { x: 90, y: 0, z: 0 },
        { x: 180, y: 270, z: 90 },
        { x: 0, y: 0, z: 0 }
    ],
    d_8: [
        { x: 45, y: 180, z: 0 },
        { x: 45, y: 90, z: 180 },
        { x: 45, y: 270, z: 0 },
        { x: 45, y: 270, z: 180 },
        { x: 45, y: 0, z: 0 },
        { x: 45, y: 180, z: 180 },
        { x: 45, y: 90, z: 0 },
        { x: 45, y: 0, z: 180 }
    ],
    d_10: [
        { x: 5, y: -36, z: 0 },
        { x: 5, y: -36, z: 288 },
        { x: 5, y: -36, z: 216 },
        { x: 5, y: -36, z: 144 },
        { x: 5, y: -36, z: 72 },
        { x: 5, y: 144, z: 324 },
        { x: 5, y: 144, z: 252 },
        { x: 5, y: 144, z: 180 },
        { x: 5, y: 144, z: 108 },
        { x: 5, y: 144, z: 36 }
    ],
    d_12: [
        { x: 0, y: 0, z: 180 },
        { x: 121.72, y: 0, z: 0 },
        { x: 121.72, y: 288, z: 0 },
        { x: 121.72, y: 216, z: 0 },
        { x: 121.72, y: 144, z: 0 },
        { x: 121.72, y: 72, z: 0 },
        { x: -58.28, y: 216, z: 0 },
        { x: -58.28, y: 0, z: 0 },
        { x: -58.28, y: 72, z: 0 },
        { x: -58.28, y: 144, z: 0 },
        { x: -58.28, y: 288, z: 0 },
        { x: 0, y: 180, z: 0 }
    ],
    d_20: [
        { x: 100, y: 0, z: 30 },
        { x: 100, y: 0, z: -42 },
        { x: 100, y: 0, z: 244 },
        { x: 100, y: 0, z: 172 },
        { x: 100, y: 0, z: 102 },
        { x: -15, y: -25, z: 168 },
        { x: -15, y: 160, z: -130 },
        { x: -15, y: -25, z: -120 },
        { x: -15, y: 160, z: -60 },
        { x: -15, y: -25, z: -50 },
        { x: -15, y: 160, z: 10 },
        { x: -15, y: -25, z: 25 },
        { x: -15, y: 160, z: 85 },
        { x: -15, y: -25, z: 95 },
        { x: -15, y: 160, z: 155 },
        { x: -85, y: 0, z: 180 },
        { x: -85, y: 0, z: 252 },
        { x: -85, y: 0, z: -33 },
        { x: -85, y: 0, z: 39 },
        { x: -85, y: 0, z: 111 }
    ]
};

function diceRollerAnimation(faceResult, animationMode) {
    var rotationsKey;
    var rotation;

    if ([2, 4, 6, 8, 10, 12, 20].includes(diceNumber)) {
        rotationsKey = `d_${diceNumber}`;
        rotation = diceFaceRotations[rotationsKey][faceResult - 1];
    } else {
        rotationsKey = "d_custom";
        rotation = { x: 5, y: -36, z: 0 };
    }

    if (previousDiceNumber != diceNumber) {
        var prevDiceKey = [2, 4, 6, 8, 10, 12, 20].includes(previousDiceNumber) ? `d_${previousDiceNumber}` : "d_custom";
        document.getElementById(prevDiceKey).style.display = 'none';

        var currentDiceKey = [2, 4, 6, 8, 10, 12, 20].includes(diceNumber) ? `d_${diceNumber}` : "d_custom";
        document.getElementById(currentDiceKey).style.display = 'block';

        switch (diceNumber) {
            case 6:
            case 8:
            case 10:
            case 20:
                document.getElementById("dice_wrapper").style.transform = "rotateX(-65deg) rotateY(40deg)";
                break;
            case 12:
                document.getElementById("dice_wrapper").style.transform = "rotateX(-85deg) rotateY(25deg)";
                break;
            case 2:
                document.getElementById("dice_wrapper").style.transform = "rotateX(-10deg) rotateY(30deg) rotateZ(-30deg)";
                break;
            case 4:
                document.getElementById("dice_wrapper").style.transform = "rotateX(0deg) rotateY(0deg) rotateZ(0deg)";
                break;
            default:
                document.getElementById("dice_wrapper").style.transform = "rotateX(-65deg) rotateY(40deg)";
        }

        previousDiceNumber = diceNumber;
    }

    var diceElement = document.getElementById(rotationsKey);

    if (animationMode) {
        diceElement.offsetWidth;
    }

    var randomX = previousRandomX;
    var randomY = previousRandomY;

    while (randomX === previousRandomX || randomY === previousRandomY) {
        randomX = rotation.x + 360 * (1 + Math.floor(Math.random() * 2));
        randomY = rotation.y + 360 * (1 + Math.floor(Math.random() * 2));
    }

    previousRandomX = randomX;
    previousRandomY = randomY;
    
    var randomZ = rotation.z;
    
    diceElement.style.transform = `rotateX(${randomX}deg) rotateY(${randomY}deg) rotateZ(${randomZ}deg)`;

    if (![2, 4, 6, 8, 10, 12, 20].includes(diceNumber) && animationMode){
        document.getElementById("new_t_f_d_custom").textContent = `d${diceNumber}`;
        
        const diceTextElement = document.getElementById("t_f_d_custom");
        diceTextElement.style.opacity = 0.4;
    
        let intervalId = setInterval(() => {
            let randomNum = Math.floor(Math.random() * diceNumber) + 1;
            diceTextElement.textContent = randomNum;
        }, 100);
    
        setTimeout(() => {
            clearInterval(intervalId);
            diceTextElement.textContent = faceResult;
            diceTextElement.style.opacity = 1;
        }, 600);
    }
}