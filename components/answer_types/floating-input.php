<?php
/**
 * Floating Label Input Component (Alternative Version)
 * 
 * @param string $label Text for the floating label
 * @param string $name Input name attribute
 * @param string $type Input type (text|email|password etc.)
 * @param bool $required Whether the input is required
 * @param string $autocomplete Autocomplete behavior
 */
function floatingInputComponent(
    string $label,
    string $name,
    string $type = 'text',
    bool $required = true,
    string $autocomplete = 'off'
) {
    static $style_loaded = false;

    // Escape output values
    $esc_label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $esc_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $esc_type = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    $esc_autocomplete = htmlspecialchars($autocomplete, ENT_QUOTES, 'UTF-8');

    // Start output buffering
    ob_start();

    // Include style only once per page
    if (!$style_loaded): ?>
    <style>
    .input-group {
        position: relative;
        margin: 1rem 0;
    }
    .input {
        border: solid 1.5px #9e9e9e;
        border-radius: 1rem;
        background: none;
        padding: 1rem;
        font-size: 1rem;
        color: #333333;
        transition: border 150ms cubic-bezier(0.4,0,0.2,1);
        width: 100%;
    }
    .user-label {
        position: absolute;
        right: 15px;
        color: #b4b4b4;
        pointer-events: none;
        transform: translateY(1rem);
        transition: 150ms cubic-bezier(0.4,0,0.2,1);
        background: transparent;
    }
    .input:focus, .input:valid {
        outline: none;
        border: 1.5px solid #ff6303;
    }
    .input:focus ~ .user-label, .input:valid ~ .user-label {
        transform: translateY(-55%) scale(0.9);
        background-color: #ffffff;
        padding: 0 .3em;
        color: #ff6303;
    }
    </style>
    <?php $style_loaded = true; endif; ?>

    <div class="input-group">
        <input 
            type="<?= $esc_type ?>"
            name="<?= $esc_name ?>"
            class="input"
            autocomplete="<?= $esc_autocomplete ?>"
            <?= $required ? 'required' : '' ?>
        >
        <label class="user-label"><?= $esc_label ?></label>
    </div>

    <?php
    // Get and clean the buffer
    echo ob_get_clean();
}
?>