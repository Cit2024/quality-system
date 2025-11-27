<?php
/**
 * Star Rating Component
 * @param int $questionId
 */
?>
<style>
    /* Evaluation */
    .evaluation {
        width: 100%;
        justify-content: center;
        display: flex;
        align-items: center;
        padding: 25px 50px 35px;
    }
    
    .evaluation .stars {
        display: flex;
        align-items: center;
        gap: 25px;
    }
    
    .stars i {
        color: #e6e6e6;
        font-size: 35px;
        cursor: pointer;
        transition: color 0.3s ease;
    }
    
    .stars i.active {
        color: #ff9c1a;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .evaluation {
            padding: 18px 0px;
        }
    
        .evaluation .stars {
            gap: 20px;
        }
        
        .stars i {
            font-size: 30px;
        }
    }
    
    @media (max-width: 480px) {
        .evaluation {
            padding: 10px 0px;
        }
            
        .evaluation .stars {
            gap: 15px;
        }
            
        .stars i {
            font-size: 28px;
        }
        
    }

</style>

<div class="evaluation">
    <div class="stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="fa-solid fa-star" data-value="<?= $i ?>"></i>
        <?php endfor; ?>
    </div>
<input type="hidden" 
       name="question[<?= $questionId ?>][rating]"
       id="question[<?= $questionId ?>][rating]" 
       value="0" />
</div>