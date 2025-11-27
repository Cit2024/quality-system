<?php
/**
 * Essay Answer Component
 * @param int $questionId
 * @param string $label Input label
 * @param string $placeholder Placeholder text
 * @param bool $required Whether the input is required
 * @param int $maxLength Maximum character length
 */
function essayAnswerComponent(
    int $questionId,
    string $label = 'الإجابة',
    string $placeholder = 'اكتب إجابتك هنا...',
    bool $required = true,
    int $maxLength = 1000
) {
    static $style_loaded = false;

    // Escape output values
    $esc_label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $esc_placeholder = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    $inputId = 'essay-' . htmlspecialchars($questionId, ENT_QUOTES, 'UTF-8');

    // Start output buffering
    ob_start();

    // Include style only once per page
    if (!$style_loaded): ?>
    <style>
    .essay-group {
        position: relative;
        margin: 1.5rem 0;
        width: 100%;
    }
    
    .essay-textarea {
        width: 100%;
        min-height: 52px;
        height: 150px;
        padding: 1rem;
        border: 1.5px solid #9e9e9e;
        border-radius: 1rem;
        font-family: 'DINRegular', sans-serif;
        font-size: 16px;
        resize: vertical;
        transition: border-color 0.3s ease;
    }
    
    .essay-textarea:focus {
        outline: none;
        border-color: #ff6303;
        border-width: 2px;
    }
    
    .essay-label {
        position: absolute;
        right: 15px;
        top: -10px;
        background: white;
        padding: 0 0.5rem;
        color: gray;
        font-size: 0.9em;
    }
    
    .char-counter {
        text-align: left;
        font-size: 0.9em;
        color: #666;
        margin-top: 0.5rem;
    }
    
    @media (max-width: 768px) {
        .essay-textarea {
            height: 120px;
            font-size: 14px;
        }
    }
    </style>
    <?php $style_loaded = true; endif; ?>

    <div class="essay-group">
        <textarea 
            class="essay-textarea"
            name="question[<?= $questionId ?>][answer]"
            id="<?= $inputId ?>"
            placeholder="<?= $esc_placeholder ?>"
            <?= $required ? 'required' : '' ?>
            maxlength="<?= $maxLength ?>"
        ></textarea>
        <label class="essay-label" for="<?= $inputId ?>"><?= $esc_label ?></label>
        <div class="char-counter"><span>0</span>/<?= $maxLength ?></div>
    </div>

    <script>
    // Character counter functionality
    document.getElementById('<?= $inputId ?>').addEventListener('input', function(e) {
        const counter = e.target.closest('.essay-group').querySelector('.char-counter span');
        counter.textContent = e.target.value.length;
    });
    </script>

    <?php
    // Get and clean the buffer
    echo ob_get_clean();
}
?>