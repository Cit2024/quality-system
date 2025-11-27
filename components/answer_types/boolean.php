<?php
/**
 * Boolean Answer Component
 * @param int $questionId
 */
?>
<style>
    
    /* True/False Buttons Container */
    .true-false-buttons {
        width: 100%;
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }
    
    /* Base Button Styles */
    .true-false-buttons button {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 8px;
        border-radius: 8px;
        font-family: 'DINRegular', sans-serif;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: transparent;
    }
    
    /* Agree Button (Default State) */
    .agree-button {
        color: #4CAF50;
        border: #4CAF50 solid 2px;
        transition: all 0.3s ease;
    }
    
    /* Disagree Button (Default State) */
    .disagree-button {
        color: #F44336;
        border: #F44336 solid 2px;
        transition: all 0.3s ease;
    }
    
    .agree-button:hover {
        background-color: #4CAF50;
        color: white;
    }
    
    .disagree-button:hover {
        background-color: #F44336;
        color: white;
    }
    
    /* Agree Button (Active State) */
    .agree-button.active {
        background-color: #4CAF50;
        color: white;
    }
    
    /* Disagree Button (Active State) */
    .disagree-button.active {
        background-color: #F44336;
        color: white;
    }
    
    /* Button Icons */
    .true-false-buttons .option-icon {
        font-size: 18px;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .true-false-buttons {
            gap: 15px;
        }
    
        .true-false-buttons button {
            padding: 6px 8px;
            font-size: 14px;
            border-width: 1px;
        }
    
        .true-false-buttons .option-icon {
            font-size: 16px;
        }
    }
    
    @media (max-width: 480px) {
        .true-false-buttons {
            gap: 10px;
        }
    
        .true-false-buttons button {
            padding: 6px;
            font-size: 12px;
        }
    
        .true-false-buttons .option-icon {
            font-size: 14px;
        }
    }
    
</style>

<div class="true-false-buttons">
    <button type="button" class="agree-button boolean-option--true" data-question="<?= $questionId ?>">
        <span class="option-icon">✔</span>
        <span class="text">موافق</span>
    </button>
    <button type="button" class="disagree-button boolean-option--false" data-question="<?= $questionId ?>">
        <span class="option-icon">✖</span>
        <span class="text">غير موافق</span>
    </button>
    <input type="hidden" 
           name="question[<?= $questionId ?>][answer]"
           id="question-<?= $questionId ?>-answer" 
           value="">
</div>