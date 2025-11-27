<?php
// evaluation/form-alumni.php
session_start();
require_once '.././config/DbConnection.php';
require_once 'config/answer_processor.php';

// 1. Validate required field
if (empty($_POST['evaluation_type'])) {
    die("حقل نوع التقييم مطلوب");
}

// 2. Sanitize Inputs
$formType = htmlspecialchars(trim($_POST['evaluation_type'])); // Lowercase variable
$graduationYear = htmlspecialchars(trim($_POST['GraduationYear']));
$graduateName = !empty($_POST['GraduateName']) 
    ? htmlspecialchars(trim($_POST['GraduateName'])) 
    : null;

// 3. Verify Form Exists and Validate Target
$stmt = $con->prepare("SELECT * FROM Form WHERE FormType = ? AND FormTarget = 'alumni' AND FormStatus = 'published'");
$stmt->bind_param("s", $formType);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: evaluation-thankyou.php?success=0");
    exit();
}

// Prepare metadata
$metadata = [
    'role' => 'alumni',
    'graduation_year' => $graduationYear, 
    'graduate_name' => $graduateName,
    'job' => !empty($_POST['Job']) ? htmlspecialchars(trim($_POST['Job'])) : null,
    'specialization' => !empty($_POST['Specialization']) ? htmlspecialchars(trim($_POST['Specialization'])) : null
];

// Validate at least one question answered
if (empty($_POST['question']) || !is_array($_POST['question']) || count($_POST['question']) < 1) {
    die("الرجاء الإجابة على الأقل على سؤال واحد");
}

// Start transaction
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

    foreach ($_POST['question'] as $questionID => $response) {
        // Validate QuestionID
        $cleanQID = filter_var($questionID, FILTER_VALIDATE_INT);
        if (!$cleanQID) continue;

        // Get question details from database
        $stmt_q = $con->prepare("SELECT TypeQuestion, Choices FROM Question WHERE ID = ?");
        $stmt_q->bind_param("i", $cleanQID);
        $stmt_q->execute();
        $questionData = $stmt_q->get_result()->fetch_assoc();
        $stmt_q->close();

        if (!$questionData) continue;

        try {
            // Use the shared processor
            $answerValue = processAnswerResponse($questionData, $response);
            
            if (!$answerValue) continue; // Skip invalid responses

            // Prepare parameters
            $formTarget = 'alumni';
            $answerJson = encodeAnswerValue($answerValue);
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
            $semester = $_POST['semester'] ?? null;

            // Bind parameters
            $stmt->bind_param("ssisss",
                $formType,
                $formTarget,
                $cleanQID,
                $answerJson,
                $metadataJson,
                $semester
            );
        
        if (!$stmt->execute()) {
                throw new Exception("Failed to save answer for question $cleanQID: " . $stmt->error);
            }
            
        } catch (InvalidArgumentException $e) {
            error_log("Invalid answer for question $cleanQID: " . $e->getMessage());
            continue;
        } catch (JsonException $e) {
            error_log("JSON error for question $cleanQID: " . $e->getMessage());
            continue;
        }
    }


    $con->commit();
    header('Location: evaluation-thankyou.php?success=1'); 
    exit;
    
} catch (Exception $e) {
    $con->rollback();
    error_log("Alumni Evaluation Error: " . $e->getMessage());
    header('Location: evaluation-thankyou.php?success=0');
    exit;
}