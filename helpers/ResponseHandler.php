<?php

class ResponseHandler {
    private $con;
    private $conn_cit;

    public function __construct($con, $conn_cit = null) {
        $this->con = $con;
        $this->conn_cit = $conn_cit;
    }

    public function handleSubmission($postData) {
        try {
            // 1. Basic Validation
            if (empty($postData['form_id'])) {
                throw new Exception("Form ID is missing");
            }
            $formId = (int)$postData['form_id'];

            // 2. Fetch Form Details
            $stmt = $this->con->prepare("SELECT * FROM Form WHERE ID = ? AND FormStatus = 'published'");
            $stmt->bind_param("i", $formId);
            $stmt->execute();
            $form = $stmt->get_result()->fetch_assoc();
            
            if (!$form) {
                throw new Exception("Form not found or not published");
            }

            // 3. Validate Dynamic Access Fields
            $metadata = $this->validateAndCollectMetadata($formId, $postData);

            // 4. Special Logic: Teacher Lookup for Student Evaluation
            if ($form['FormTarget'] === 'student' && 
               ($form['FormType'] === 'teacher_evaluation' || $form['FormType'] === 'course_evaluation')) {
                $metadata = $this->enrichStudentMetadata($metadata, $postData);
            }

            // 5. Check for Duplicates
            if ($this->isDuplicate($form, $metadata)) {
                throw new Exception("Duplicate submission detected");
            }

            // 6. Process and Save Answers
            $this->saveAnswers($form, $metadata, $postData['question'] ?? []);

            return ['success' => true];

        } catch (Exception $e) {
            error_log("Submission Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function validateAndCollectMetadata($formId, $postData) {
        $metadata = [];
        
        // Fetch required fields for this form
        $stmt = $this->con->prepare("SELECT * FROM FormAccessFields WHERE FormID = ? ORDER BY OrderIndex");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($field = $result->fetch_assoc()) {
            $fieldName = 'field_' . $field['ID']; // The input name in the HTML form
            
            // Check if required
            if ($field['IsRequired'] && empty($postData[$fieldName])) {
                throw new Exception("Field '{$field['Label']}' is required");
            }

            // Collect value
            if (isset($postData[$fieldName])) {
                $metadata[$field['Label']] = htmlspecialchars(trim($postData[$fieldName]));
            }
        }

        // Also collect standard fields if present (backward compatibility/standard fields)
        $standardFields = ['IDStudent', 'IDCourse', 'IDGroup', 'Semester', 'GraduationYear', 'Job', 'Specialization', 'GraduateName'];
        foreach ($standardFields as $key) {
            if (!empty($postData[$key])) {
                $metadata[$key] = htmlspecialchars(trim($postData[$key]));
            }
        }

        $metadata['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $metadata['submission_date'] = date('Y-m-d H:i:s');

        return $metadata;
    }

    private function enrichStudentMetadata($metadata, $postData) {
        // Ensure we have the CIT connection
        if (!$this->conn_cit) {
            throw new Exception("CIT Database connection required for this form type");
        }

        // Required fields for lookup
        if (empty($metadata['Semester']) || empty($metadata['IDCourse']) || empty($metadata['IDGroup'])) {
            // If they are not in metadata, check postData directly (legacy support)
            $metadata['Semester'] = $postData['Semester'] ?? null;
            $metadata['IDCourse'] = $postData['IDCourse'] ?? null;
            $metadata['IDGroup'] = $postData['IDGroup'] ?? null;
            
            if (empty($metadata['Semester']) || empty($metadata['IDCourse']) || empty($metadata['IDGroup'])) {
                 throw new Exception("Missing course details for teacher lookup");
            }
        }

        // Lookup Teacher
        $stmt = $this->conn_cit->prepare("SELECT TNo FROM coursesgroups WHERE ZamanNo = ? AND MadaNo = ? AND GNo = ?");
        $stmt->bind_param("isi", $metadata['Semester'], $metadata['IDCourse'], $metadata['IDGroup']);
        $stmt->execute();
        $stmt->bind_result($teacherId);
        
        if ($stmt->fetch()) {
            $metadata['teacher_id'] = $teacherId;
        } else {
            throw new Exception("Teacher not found for this course/group");
        }
        $stmt->close();

        return $metadata;
    }

    private function isDuplicate($form, $metadata) {
        // Define unique constraints based on form target
        if ($form['FormTarget'] === 'student') {
            // Unique per Student + Course + Semester + FormType
            $stmt = $this->con->prepare("SELECT ID FROM EvaluationResponses 
                WHERE FormType = ? 
                AND Metadata->>'$.IDStudent' = ? 
                AND Metadata->>'$.IDCourse' = ? 
                AND Semester = ? 
                LIMIT 1");
            
            $semester = $metadata['Semester'] ?? '';
            $studentId = $metadata['IDStudent'] ?? '';
            $courseId = $metadata['IDCourse'] ?? '';

            $stmt->bind_param("ssss", $form['FormType'], $studentId, $courseId, $semester);
            $stmt->execute();
            return $stmt->fetch() ? true : false;
        }
        
        // For other types, we might want to allow multiple or check other fields
        // For now, default to no duplicate check for generic forms unless specified
        return false;
    }

    private function saveAnswers($form, $metadata, $answers) {
        require_once __DIR__ . '/../evaluation/config/answer_processor.php';

        $this->con->begin_transaction();
        try {
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
            $semester = $metadata['Semester'] ?? null;

            $stmt = $this->con->prepare("INSERT INTO EvaluationResponses (FormType, FormTarget, QuestionID, AnswerValue, Metadata, Semester) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($answers as $questionId => $response) {
                $questionId = (int)$questionId;
                
                // Get Question Details
                $qStmt = $this->con->prepare("SELECT TypeQuestion, Choices FROM Question WHERE ID = ?");
                $qStmt->bind_param("i", $questionId);
                $qStmt->execute();
                $questionData = $qStmt->get_result()->fetch_assoc();
                $qStmt->close();

                if (!$questionData) continue;

                // Process Answer
                $answerValue = processAnswerResponse($questionData, $response);
                if (!$answerValue) continue;

                $answerJson = encodeAnswerValue($answerValue);

                $stmt->bind_param("ssisss", 
                    $form['FormType'], 
                    $form['FormTarget'], 
                    $questionId, 
                    $answerJson, 
                    $metadataJson, 
                    $semester
                );
                $stmt->execute();
            }

            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }
    }
}
