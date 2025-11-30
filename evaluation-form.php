<?php

session_start();
require_once 'config/dbConnectionCit.php';
require_once 'config/DbConnection.php';
require_once 'helpers/units.php';
require_once 'components/answer_types/floating-input.php';
require_once 'forms/form_constants.php';


// Validate required parameters
if (!isset($_GET['evaluation'], $_GET['Evaluator'])) {
    header("HTTP/1.1 400 Bad Request");
    die("Missing required parameters");
}

// Retrieve and sanitize the data from the URL or Session
$Semester = $_SESSION['Semester'] ?? (!empty($_GET['Semester']) ? htmlspecialchars(trim($_GET['Semester'])) : null);
$IDStudent = $_SESSION['IDStudent'] ?? (!empty($_GET['IDStudent']) ? htmlspecialchars(trim($_GET['IDStudent'])) : null);
$IDCourse = $_SESSION['IDCourse'] ?? (!empty($_GET['IDCourse']) ? htmlspecialchars(trim($_GET['IDCourse'])) : null);
$IDGroup = $_SESSION['IDGroup'] ?? (!empty($_GET['IDGroup']) ? htmlspecialchars(trim($_GET['IDGroup'])) : null);

$Evaluator = htmlspecialchars(trim($_GET['Evaluator']));
$TypeEvaluation = strtolower(trim($_GET['evaluation']));

// Validate against constants
if (!array_key_exists($Evaluator, FORM_TARGETS)) {
    header("HTTP/1.1 400 Bad Request");
    die("Invalid evaluator type");
}

if (!array_key_exists($TypeEvaluation, FORM_TYPES)) {
    header("HTTP/1.1 400 Bad Request");
    die("Invalid evaluation type");
}


// Initialize form variables
$form_exists = false;
$form_message = '';
$form_by_type = [];
$sections = [];

if ($Evaluator == "student") {
    // Get student information
    $stmt = $conn_cit->prepare("SELECT KidNo, KesmNo FROM sprofiles WHERE KidNo = ?");
    $stmt->bind_param("s", $IDStudent);
    $stmt->execute();
    $stmt->bind_result($kidNo, $kesmNo);
    $student_info = [];
    if ($stmt->fetch()) {
        $student_info = [
            'KidNo' => $kidNo,
            'KesmNo' => $kesmNo,
        ];
    }
    $stmt->free_result();
    $stmt->close();
    
    if (empty($student_info)) {
        die("Error: Student not found.");
    }
    
    // Get department name
    $IDDepartment = $student_info['KesmNo'];
    $stmt = $conn_cit->prepare("SELECT did, Depname, dname FROM divitions WHERE KesmNo = ?");
    $stmt->bind_param("s", $IDDepartment);
    $stmt->execute();
    $stmt->bind_result($divId, $depName, $dName);
    $deparment_student_name = [];
    if ($stmt->fetch()) {
        $deparment_student_name = [
            'did' => $divId,
            'Depname' => $depName,
            'dname' => $dName,
        ];
    }
    $stmt->close();
    
    // Get course information
    $stmt = $conn_cit->prepare("SELECT MadaName, MadaNo FROM mawad WHERE MadaNo = ?");
    $stmt->bind_param("s", $IDCourse);
    $stmt->execute();
    $stmt->bind_result($MadaName, $MadaNo);
    $course_info = [];
    if ($stmt->fetch()) {
        $course_info = [
            'MadaNo' => $MadaNo,
            'MadaName' => $MadaName,
        ];
    }
    $stmt->close();
    
    // Get semester name
    $stmt = $conn_cit->prepare("SELECT ZamanName FROM zaman WHERE ZamanNo = ?");
    $stmt->bind_param("s", $Semester);
    $stmt->execute();
    $stmt->bind_result($ZamanName);
    $semester_name = [];
    if ($stmt->fetch()) {
        $semester_name = [
            'ZamanName' => $ZamanName
        ];
    }
    $stmt->close();
    
    // Get teacher ID
    $stmt = $conn_cit->prepare("SELECT TNo FROM coursesgroups WHERE ZamanNo = ? AND MadaNo = ? AND GNo = ?");
    $stmt->bind_param("sss", $Semester, $IDCourse, $IDGroup);
    $stmt->execute();
    $stmt->bind_result($TNo);
    $teacher_id_result = [];
    if ($stmt->fetch()) {
        $teacher_id_result = [
            'TNo' => $TNo
        ];
    }
    $stmt->close();
    
    $teacher_id = $teacher_id_result['TNo'] ?? null;
    
    // Get teacher name
    if ($teacher_id) {
        $stmt = $conn_cit->prepare("SELECT name FROM regteacher WHERE id = ?");
        $stmt->bind_param("s", $teacher_id);
        $stmt->execute();
        $stmt->bind_result($name);
        $teacher_name = [];
        if ($stmt->fetch()) {
            $teacher_name = [
                'name' => $name
            ];
        }
        $stmt->close();
    }
}

// Dynamic form query using prepared statements
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
    $stmt->close();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// --- Authorization Check ---
if ($form_exists) {
    $formId = $form_by_type['ID'];
    
    // Check if form has password or access fields
    $requiresAuth = false;
    if (!empty($form_by_type['password'])) {
        $requiresAuth = true;
    } else {
        // Check for access fields
        $stmt = $con->prepare("SELECT COUNT(*) as count FROM FormAccessFields WHERE FormID = ?");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res['count'] > 0) {
            $requiresAuth = true;
        }
        $stmt->close();
    }
    
    if ($requiresAuth) {
        // Check if authorized in session
        if (!isset($_SESSION['form_auth_' . $formId])) {
            // Redirect to login
            $query = http_build_query($_GET);
            // Add ID to query if not present (it might not be in GET for evaluation-form, as it uses type/target)
            // But wait, evaluation-form finds the form by type/target.
            // We need to pass the ID to login-form.php
            header("Location: login-form.php?id=$formId&" . $query);
            exit();
        }
    }
}
// ---------------------------
    
// If form exists, get its sections and questions
if ($form_exists) {
    $IDForm = (int)$form_by_type['ID'];
    
    // Fetch sections for the form
    $stmt = $con->prepare("SELECT ID, title FROM Section WHERE IDForm = ?");
    if (!$stmt) {
        die("Database error: " . $con->error);
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
                    <?php if (!empty($form_by_type['note'])) : ?>
                        <div class="form-note">
                            *ملاحظة:
                            <p><?php echo formatBilingualText($form_by_type['note']); ?></p>
                        </div>
                    <?php endif; ?>
        <input type="hidden" name="form_id" value="<?php echo $form_by_type['ID']; ?>">
        <input type="hidden" name="evaluation_type" value="<?php echo $TypeEvaluation; ?>">
        
        <!-- Pass Context Data -->
        <?php if (isset($Semester) && $Semester): ?><input type="hidden" name="Semester" value="<?= htmlspecialchars($Semester) ?>"><?php endif; ?>
        <?php if (isset($IDStudent) && $IDStudent): ?><input type="hidden" name="IDStudent" value="<?= htmlspecialchars($IDStudent) ?>"><?php endif; ?>
        <?php if (isset($IDCourse) && $IDCourse): ?><input type="hidden" name="IDCourse" value="<?= htmlspecialchars($IDCourse) ?>"><?php endif; ?>
        <?php if (isset($IDGroup) && $IDGroup): ?><input type="hidden" name="IDGroup" value="<?= htmlspecialchars($IDGroup) ?>"><?php endif; ?>
        
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
                <img src="./assets/icons/no-data.svg" alt="No form available">
                <h2><?php echo htmlspecialchars($form_message); ?></h2>
                <p>الرجاء التواصل مع المسؤولين</p>
            </div>
        <?php endif; ?>
    </div>
    <script src="./scripts/evaluation-form.js"></script>
</body>
</html>