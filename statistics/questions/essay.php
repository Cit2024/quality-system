<?php
/**
 * Displays essay question responses
 * @param array $questionData {
 *     @type string $question  Question text
 *     @type array  $responses [
 *          ['content' => string, 'timestamp' => date, 'metadata' => array]
 *     ]
 * }
 */
 
 // Include shared header
require_once __DIR__ . '/../analytics/shared/header.php';

// Validate and initialize variables
$questionData = $questionData ?? [];
$titleParts = split_arabic_english($questionData['question'] ?? '');

?>
<div class="question-container essay-question" data-question-type="essay">
    <h3 class="question-title">
        <div class="arabic-text"><?= htmlspecialchars($titleParts['arabic']) ?></div>
        <?php if (!empty($titleParts['english'])): ?>
            <div class="english-text"><?= htmlspecialchars($titleParts['english']) ?></div>
        <?php endif; ?>
    </h3>
    
    <?php if (!empty($questionData['responses'])): ?>
        <div class="response-list">
            <?php foreach ($questionData['responses'] as $response): ?>
                <div class="response-item">
                    <div class="response-content">
                        <?= nl2br(htmlspecialchars($response['content'])) ?>
                    </div>
                    
                    <div class="response-meta">
                        <span class="date">
                            <?= date('M j, Y H:i', strtotime($response['timestamp'])) ?>
                        </span>
                        <?php if (!empty($response['metadata'])): ?>
                            <div class="metadata-details">
                                <?php foreach ((array)$response['metadata'] as $metaItem): ?>
                                    <?php if (is_array($metaItem) && isset($metaItem['label'], $metaItem['value'])): ?>
                                        <div class="meta-item">
                                            <span class="meta-label"><?= htmlspecialchars($metaItem['label']) ?></span>
                                            <span class="meta-value"><?= htmlspecialchars($metaItem['value']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-responses">No responses yet</div>
    <?php endif; ?>
</div>

<style>
.essay-question .response-list {
    margin-top: 1rem;
}

.response-content {
    line-height: 1.6;
    color: #2c3e50;
}

.metadata-details {
    margin-top: 0.8rem;
    border-top: 1px dashed #eee;
    padding-top: 0.8rem;
}

.meta-item {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.3rem;
    font-size: 0.9rem;
}

.meta-label {
    font-weight: 600;
    color: #2c3e50;
    min-width: 80px;
}

.response-item {
    padding: 1rem;
    margin-bottom: 1rem;
    background: white;
    border: 1px solid #eee;
    border-radius: 6px;
}


.response-meta {
    margin-top: 0.8rem;
    font-size: 0.85rem;
    color: #7f8c8d;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.no-responses {
    padding: 2rem;
    text-align: center;
    color: #95a5a6;
}
</style>