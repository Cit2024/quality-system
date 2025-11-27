<?php
// evaluation/form-student.php
session_start();

// Error reporting for development (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '.././config/dbConnectionCit.php';
require_once '.././config/DbConnection.php';
require_once __DIR__ . '/../forms/form_constants.php';
require_once 'config/answer_processor.php';

// 1. Validate Required Fields - Get evaluation_type FIRST
$evaluation_type = isset($_POST['evaluation_type']) 
    ? htmlspecialchars(trim($_POST['evaluation_type'])) 
    : '';

// Debugging: Log received evaluation type
error_log("Received evaluation_type: $evaluation_type");

// Dynamically get allowed evaluation types for 'student'
$allowed_types = array_filter(FORM_TYPES, function($type) {
    return in_array('student', $type['allowed_targets']);
});
$allowed_type_keys = array_keys($allowed_types);

// Debugging: Log allowed types
error_log("Allowed types for student: " . implode(', ', $allowed_type_keys));

// Validate evaluation_type against allowed types
if (!in_array($evaluation_type, $allowed_type_keys)) {
    error_log("Invalid evaluation_type: $evaluation_type");
    header('Location: evaluation-thankyou.php?success=0&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
    exit();
}

// Define additional required fields conditionally
$check_evaluation_type = ['teacher_evaluation', 'course_evaluation'];
$required_fields = ['form_id', 'IDStudent', 'evaluation_type'];

if (in_array($evaluation_type, $check_evaluation_type)) {
    $required_fields = array_merge($required_fields, ['IDCourse', 'Semester', 'IDGroup']);
} else {
    $required_fields[] = 'Semester';
}

// Debugging: Log required fields
error_log("Required fields: " . implode(', ', $required_fields));

// Check required fields
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        error_log("Missing required field: $field");
        header('Location: evaluation-thankyou.php?success=0&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
        exit();
    }
}

// 2. Sanitize Inputs
$form_id = (int)$_POST['form_id'];
$IDStudent = htmlspecialchars(trim($_POST['IDStudent']));
$IDCourse = isset($_POST['IDCourse']) ? htmlspecialchars(trim($_POST['IDCourse'])) : '';
$Semester = htmlspecialchars(trim($_POST['Semester']));
$IDGroup = isset($_POST['IDGroup']) ? htmlspecialchars(trim($_POST['IDGroup'])) : '';

// Debugging: Log sanitized inputs
error_log("Sanitized inputs - FormID: $form_id, StudentID: $IDStudent, CourseID: $IDCourse, Semester: $Semester, GroupID: $IDGroup");

// 3. Verify Form Exists and Validate Target
$stmt = $con->prepare("SELECT FormTarget, FormType FROM Form WHERE ID = ? AND FormStatus = 'published'");
$stmt->bind_param("i", $form_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("Form not found or not published: $form_id");
    header('Location: evaluation-thankyou.php?success=0&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
    exit();
}

$formData = $result->fetch_assoc();
$formTarget = $formData['FormTarget'];
$formType = $formData['FormType'];

// Debugging: Log form details
error_log("Form details - Target: $formTarget, Type: $formType");

if ($formTarget !== 'student') {
    error_log("Invalid form target: $formTarget (expected student)");
    header('Location: evaluation-thankyou.php?success=0&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
    exit();
}

// 4. Check for Duplicate Submission (metadata-based) - FIXED VERSION
$stmt = $con->prepare("SELECT ID FROM EvaluationResponses 
                      WHERE FormTarget = 'student'
                      AND Metadata->>'$.student_id' = ?
                      AND Metadata->>'$.course_id' = ?
                      AND Semester = ?
                      AND FormType = ?
                      LIMIT 1");
$stmt->bind_param("ssss", $IDStudent, $IDCourse, $Semester, $formType);
$stmt->execute();
if ($stmt->fetch()) {
    error_log("Duplicate submission detected - Type: $formType, Student: $IDStudent, Course: $IDCourse, Semester: $Semester");
    header('Location: evaluation-thankyou.php?success=0&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
    exit();
}
$stmt->close();

// 5. Get Teacher ID if Teacher Evaluation
$teacher_id = null;
if ($evaluation_type === 'teacher_evaluation') {
    // Debugging: Check CIT connection
    if (!$conn_cit || $conn_cit->connect_error) {
        error_log("CIT database connection failed: " . ($conn_cit->connect_error ?? 'Unknown error'));
    }
    
    $stmt = $conn_cit->prepare("SELECT TNo FROM coursesgroups 
                          WHERE ZamanNo = ? AND MadaNo = ? AND GNo = ?");
    $stmt->bind_param("isi", $Semester, $IDCourse, $IDGroup);
    
    if (!$stmt->execute()) {
        error_log("Teacher query failed: " . $stmt->error);
        header('Location: evaluation-thankyou.php?success=0&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
        exit();
    }
    
    $stmt->bind_result($teacher_id);
    if (!$stmt->fetch()) {
        error_log("Teacher not found - Semester: $Semester, Course: $IDCourse, Group: $IDGroup");
        header('Location: evaluation-thankyou.php?success=0&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
        exit();
    }
    $stmt->close();
    
    // Debugging: Log found teacher
    error_log("Found teacher ID: $teacher_id");
}

// 6. Prepare Base Metadata
$metadata = [
    'student_id' => $IDStudent,
    'course_id' => $IDCourse,
    'group_id' => $IDGroup,
    'teacher_id' => $teacher_id,
    'ip_address' => $_SERVER['REMOTE_ADDR']
];

// Debugging: Log metadata
error_log("Metadata: " . print_r($metadata, true));

// 7. Process Responses in Transaction
$con->begin_transaction();

try {
    $stmt = $con->prepare("
        INSERT INTO EvaluationResponses (
            FormType,
            FormTarget,
            QuestionID,
            AnswerValue,
            Metadata,
            Semester
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    // Debugging: Log question count
    $questionCount = count($_POST['question'] ?? []);
    error_log("Processing $questionCount questions");

    foreach ($_POST['question'] ?? [] as $questionId => $response) {
        $questionId = filter_var($questionId, FILTER_VALIDATE_INT);
        if (!$questionId) {
            error_log("Invalid question ID: " . var_export($questionId, true));
            continue;
        }

        // Get question details
        $stmt_q = $con->prepare("SELECT TypeQuestion, Choices FROM Question WHERE ID = ?");
        $stmt_q->bind_param("i", $questionId);
        $stmt_q->execute();
        $questionData = $stmt_q->get_result()->fetch_assoc();
        $stmt_q->close();

        if (!$questionData) {
            error_log("Question not found: $questionId");
            continue;
        }

        try {
            // Use the answer processor functions
            $answerValue = processAnswerResponse($questionData, $response);
            
            if (!$answerValue) {
                error_log("Skipping invalid response for question $questionId");
                continue;
            }

            $answerJson = encodeAnswerValue($answerValue);
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);

            $stmt->bind_param("ssisss",
                $formType,
                $formTarget,
                $questionId,
                $answerJson,
                $metadataJson,
                $Semester
            );

            if (!$stmt->execute()) {
                throw new Exception("Database error: ".$stmt->error);
            }
            
        } catch (InvalidArgumentException $e) {
            error_log("Invalid answer for question $questionId: " . $e->getMessage() . " Response: " . print_r($response, true));
        } catch (JsonException $e) {
            error_log("JSON encoding error for question $questionId: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Unexpected error on question $questionId: " . $e->getMessage());
            throw $e; // Re-throw for transaction handling
        }
    }
    
    $con->commit();
    error_log("Submission successful for student $IDStudent");
    header('Location: evaluation-thankyou.php?success=1&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
    exit;
    
} catch (Exception $e) {
    $con->rollback();
    error_log("Evaluation submission failed: ".$e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    header('Location: evaluation-thankyou.php?success=0&path=' . urlencode("https://erp.cit.edu.ly/resultab/thisterm.php"));
    exit;
}