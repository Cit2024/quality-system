<?php

require_once __DIR__ . '/config/session.php';
require_once 'config/dbConnectionCit.php';
require_once 'config/DbConnection.php';
require_once 'helpers/units.php';
require_once 'helpers/csrf.php';
require_once 'helpers/error_handler.php';
require_once 'components/answer_types/floating-input.php';

// Wrap the entire page logic in error handling if needed, 
// or at least handle critical setup errors.
try {


// Validate required parameters
if (!isset($_GET['evaluation'], $_GET['Evaluator'])) {
    throw new ValidationException("Missing required parameters");
}

// Validate against Database Types
// Check Evaluator Type
$stmt = $con->prepare("SELECT ID FROM EvaluatorTypes WHERE Slug = ?");
$stmt->bind_param("s", $Evaluator);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    throw new NotFoundException("Invalid evaluator type");
}
$stmt->close();

// Check Form Type
$stmt = $con->prepare("SELECT ID FROM FormTypes WHERE Slug = ?");
$stmt->bind_param("s", $TypeEvaluation);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    throw new NotFoundException("Invalid evaluation type");
}
$stmt->close();

// Initialize form variables
$form_exists = false;
$form_message = '';
$form_by_type = [];
$sections = [];

// 1. Fetch Form First to know what fields are required
try {
    $stmt = $con->prepare("
        SELECT ID, note, FormTarget, password 
        FROM Form 
        WHERE FormStatus = 'published' 
        AND FormType = ? 
        AND FormTarget = ?
    ");
    
    $stmt->bind_param("ss", $TypeEvaluation, $Evaluator);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $form_by_type = $result->fetch_assoc();
        $form_exists = true;
    } else {
        $form_message = FORM_TYPES[$TypeEvaluation]['not_found'] ?? "No form available";
    }
    
    $stmt->close();

} catch (Exception $e) {
    throw new DatabaseException("Database error: " . $e->getMessage());
}

// 2. Dynamic Parameter Validation
if ($form_exists) {
    $formId = $form_by_type['ID'];
    
    // Fetch Access Fields for this form
    $stmt = $con->prepare("SELECT Label, Slug, IsRequired, FieldType FROM FormAccessFields WHERE FormID = ? ORDER BY OrderIndex ASC");
    $stmt->bind_param("i", $formId);
    $stmt->execute();
    $fieldsResult = $stmt->get_result();
    
    $missingParams = [];
    $redirectNeeded = false;
    $access_fields = [];
    
    while ($field = $fieldsResult->fetch_assoc()) {
        $access_fields[] = $field;
        $slug = $field['Slug'];
        $isRequired = $field['IsRequired'];
        
        // Check if value exists in GET or SESSION
        // We use the Slug as the variable name (e.g., if Slug is 'IDStudent', we look for $_GET['IDStudent'])
        $value = null;
        if (!empty($_GET[$slug])) {
            $value = htmlspecialchars(trim($_GET[$slug]));
        } elseif (isset($_SESSION[$slug])) {
            $value = $_SESSION[$slug];
        }
        
        // Dynamically assign variable with the name of the Slug
        // This ensures backward compatibility if existing code uses variables like $IDStudent
        // We capitalize the first letter to match convention if needed, strictly speaking, PHP variables are case sensitive.
        // The user's legacy code uses $Semester, $IDStudent (PascalCase).
        // We should assume the Slug in DB matches the variable name expected by the legacy code (e.g. "IDStudent").
        if ($slug) {
            $$slug = $value;
        }

        if ($isRequired && empty($value)) {
            $missingParams[] = $field['Label'];
            $redirectNeeded = true;
        }
    }
    $stmt->close();
    
    // Also check for Form Password (if set) and authentication session
    $hasPassword = !empty($form_by_type['password']);
    $isAuthenticated = isset($_SESSION['form_auth_' . $formId]);

    if (($redirectNeeded || ($hasPassword && !$isAuthenticated))) {
        // Redirect to login-form.php
        $query = http_build_query($_GET);
        header("Location: login-form.php?id=$formId&" . $query);
        exit();
    }
}
    
// If form exists, get its sections and questions
if ($form_exists) {
    $IDForm = (int)$form_by_type['ID'];
    
    // Fetch sections for the form
    $stmt = $con->prepare("SELECT ID, title FROM Section WHERE IDForm = ?");
    if (!$stmt) {
        throw new DatabaseException("Database error: " . $con->error);
    }
    
    $stmt->bind_param("i", $IDForm);
    $stmt->execute();
    $stmt->bind_result($sectionId, $title);
    
    $sections = [];
    while ($stmt->fetch()) {
        $sections[] = [
            'ID' => $sectionId,
            'title' => $title
        ];
    }
    $stmt->close();
    
    // If no sections found, treat as no form available
    if (empty($sections)) {
        $form_exists = false;
        $form_message = "النموذج الموجود لا يحتوي على أي أقسام";
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نموذج التقييم</title>
    <link rel="icon" href="./assets/icons/college.png">
    <link rel="stylesheet" href="./styles/evaluation-form.css">
    <link rel="stylesheet" href="./components/ComponentsStyles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header>
        <div class="logo-contnet">
            <img src="./assets/icons/Industrial-Technology-College-Logo-Arabic-For-the-big-screen.svg" alt="Industrial Technology College Logo" />
        </div>
        <div class="title">
            <p>وزارة الصناعة و المعادن</p>
            <p>كلية التنقية الصناعية - مصراتة</p>
        </div>
        <div></div>
    </header>

    <?php include "components/separator.html" ?>

    <div class="container">
        <?php if ($form_exists): ?>
            <form action="<?php
                    // Define form handlers for each target
                    $formHandlers = [
                        'student' => './evaluation/form-student.php',
                        'teacher' => './evaluation/form-teacher.php',
                        'admin' => './evaluation/form-admin.php',
                        'alumni' => './evaluation/form-alumni.php',
                        'employer' => './evaluation/form-employer.php'
                    ];
                    
                    // Get clean form target value
                    $formTarget = htmlspecialchars($form_by_type['FormTarget'] ?? '');
                    
                    // Select appropriate handler or fallback
                    echo isset($formHandlers[$formTarget]) 
                        ? $formHandlers[$formTarget]
                        : './evaluation/form-default.php';
                    ?>" method="POST">
                      <!-- Form Header -->
                      <?php require_once 'evaluation/form-header.php'; ?>
                      <!-- Form Body -->
                      <div class="form-body">
                      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php if (!empty($form_by_type['note'])) : ?>
                        <div class="form-note">
                            *ملاحظة:
                            <p><?php echo formatBilingualText($form_by_type['note']); ?></p>
                        </div>
                    <?php endif; ?>
        <input type="hidden" name="form_id" value="<?php echo $form_by_type['ID']; ?>">
        <input type="hidden" name="evaluation_type" value="<?php echo $TypeEvaluation; ?>">
        
        <!-- Pass Context Data -->
        <!-- Pass Context Data (Dynamic) -->
        <?php foreach ($access_fields as $field): 
            $slug = $field['Slug'];
            if ($slug && isset($$slug) && $$slug): ?>
                <input type="hidden" name="<?= htmlspecialchars($slug) ?>" value="<?= htmlspecialchars($$slug) ?>">
            <?php endif; 
        endforeach; ?>
        
        <!-- Pass Session Data as Hidden Fields for ResponseHandler -->
        <?php 
        // If logged in via login-form.php, pass those values too
        if (isset($_SESSION['form_auth_' . $formId])) {
            foreach ($_SESSION as $key => $val) {
                if (strpos($key, 'field_') === 0 && !empty($val)) {
                    echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '">';
                }
            }
        }
        ?>
                        
                        <?php foreach ($sections as $section): ?>
                            <div class="evaluation-section">
                                <p class="section-title"><?php echo htmlspecialchars($section['title']); ?></p>
                                
                                <?php
                                $sectionId = $section['ID'];
                                $stmt = $con->prepare("SELECT ID, TitleQuestion, TypeQuestion FROM Question WHERE IDSection = ?");
                                $stmt->bind_param("i", $sectionId);
                                $stmt->execute();
                                $stmt->bind_result($questionId, $titleQuestion, $typeQuestion);
                                $questions = [];
                                while ($stmt->fetch()) {
                                    $questions[] = [
                                        'ID' => $questionId,
                                        'TitleQuestion' => $titleQuestion,
                                        'TypeQuestion' => $typeQuestion
                                    ];
                                }
                                $stmt->close();
                                ?>
                                
                                <?php foreach ($questions as $question): ?>
                                    <?php $questionId = $question['ID']; // Define $questionId here ?>
                                    <div class="evaluation-question" 
                                        data-question-id="<?php echo $question['ID']; ?>"
                                        data-question-type="<?= $question['TypeQuestion'] ?>"
                                    >
                                        <div class="question-header">
                                            <p class="question-title"><?php echo formatBilingualText($question['TitleQuestion']); ?></p>
                                            <span>?</span>
                                        </div>
    
                                        <?php 
                                            $componentFile = match($question['TypeQuestion']) {
                                                'multiple_choice' => 'multiple-choice.php',
                                                'evaluation' => 'evaluation.php',
                                                'true_false' => 'boolean.php',
                                                'essay' => 'essay.php',
                                                default => null
                                            };
                                            
                                            if ($componentFile) {
                                                include __DIR__ . "/components/answer_types/{$componentFile}";
                                            }
                                            ?>
                                        <p class="error-message" style="color: red; display: none;">
                                            <?php match($question['TypeQuestion']) {
                                                'evaluation' => 'الرجاء اختيار تقييم.',
                                                'true_false' => 'الرجاء اختيار موافق أو غير موافق.',
                                                'essay' => 'الرجاء كتابة إجابة.',
                                                default => 'يجب اختيار خيار واحد على الأقل.'
                                            }; ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="submit-button">إرسال التقييم</button>
                    </div>
                </form>
        <?php else: ?>
            <div class="no-form-message">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 3rem; color: #e67e22; margin-bottom: 1rem;"></i>
                <h2><?php echo htmlspecialchars($form_message); ?></h2>
                <p>الرجاء التواصل مع المسؤولين</p>
            </div>
        <?php endif; ?>
    </div>
    <script src="./scripts/evaluation-form.js"></script>
</body>
</html>
<?php
} catch (Exception $e) {
    handleException($e);
}
?>
