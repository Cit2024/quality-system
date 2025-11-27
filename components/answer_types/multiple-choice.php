<?php
// Get options from database
$stmt = $con->prepare("SELECT Choices FROM Question WHERE ID = ?");
$stmt->bind_param("i", $question['ID']);
$stmt->execute();
$result = $stmt->get_result();
$choices = json_decode($result->fetch_assoc()['Choices'] ?? '[]', true);
?>

<div class="multiple-choice-container" data-question-id="<?= $question['ID'] ?>">
    <div class="options-list">
        <?php foreach ($choices as $index => $option): 
            $escaped_option = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
            $option_id = "question-{$question['ID']}-option-{$index}";
        ?>
            <div class="option-item">
                <input type="radio" 
                       name="question[<?= $question['ID'] ?>][answer]"
                       id="<?= $option_id ?>"
                       value="<?= $escaped_option ?>"
                       required>
                <label for="<?= $option_id ?>">
                    <?= $escaped_option ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.multiple-choice-container {
    margin: 1.5rem 0;
}

.options-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.option-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.option-item:hover {
    border-color: #ff6303;
    background: #fff5f0;
}

.option-item input[type="radio"] {
    width: 18px;
    height: 18px;
    accent-color: #ff6303;
}

.option-item label {
    flex: 1;
    cursor: pointer;
    font-family: 'DINRegular', sans-serif;
    font-size: 16px;
}
</style>