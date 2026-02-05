<?php

require_once __DIR__ . '/exceptions.php';
require_once __DIR__ . '/../config/constants.php';

class ResponseHandler {
    private $con;
    private $conn_cit;

    public function __construct($con, $conn_cit = null) {
        $this->con = $con;
        $this->conn_cit = $conn_cit;
    }

    public function handleSubmission($postData) {
        $this->con->begin_transaction();
        try {
            // 1. Basic Validation
            if (empty($postData['form_id'])) {
                throw new ValidationException("Form ID is missing");
            }
            $formId = (int)$postData['form_id'];

            // 2. Fetch Form Details
            $stmt = $this->con->prepare("SELECT * FROM Form WHERE ID = ? AND FormStatus = 'published'");
            $stmt->bind_param("i", $formId);
            $stmt->execute();
            $form = $stmt->get_result()->fetch_assoc();
            
            if (!$form) {
                throw new NotFoundException("Form not found or not published");
            }

            // 3. Validate Dynamic Access Fields
            $metadata = $this->validateAndCollectMetadata($formId, $postData);

            // 4. Special Logic: Teacher Lookup for Student Evaluation
            if ($form['FormTarget'] === 'student' && 
               ($form['FormType'] === 'teacher_evaluation' || $form['FormType'] === 'course_evaluation')) {
                $metadata = $this->enrichStudentMetadata($metadata, $postData);
            }

            // 5. Check for Duplicates (Now uses FOR UPDATE inside transaction)
            if ($this->isDuplicate($form, $metadata)) {
                $this->con->rollback();
                throw new DuplicateException("لقد قمت بإرسال هذا التقييم مسبقاً");
            }

            // 6. Process and Save Answers
            $this->saveAnswers($form, $metadata, $postData['question'] ?? []);
            
            $this->con->commit();
            return ['success' => true];

        } catch (mysqli_sql_exception $e) {
            $this->con->rollback();
            // Check for duplicate entry error (1062)
            if ($e->getCode() == 1062) {
                 return ['success' => false, 'message' => "لقد قمت بإرسال هذا التقييم مسبقاً"];
            }
            error_log("Database Error: " . $e->getMessage());
            return ['success' => false, 'message' => "Database error occurred"];
        } catch (Exception $e) {
            $this->con->rollback();
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
            // Use Slug if available, fallback to Label
            $fieldKey = !empty($field['Slug']) ? $field['Slug'] : $field['Label'];
            
            // Try multiple possible input names:
            // 1. Direct slug name (from evaluation-form hidden inputs)
            // 2. field_ID pattern (from login-form inputs)
            $value = null;
            if (isset($postData[$fieldKey])) {
                $value = trim($postData[$fieldKey]);
            } elseif (isset($postData['field_' . $field['ID']])) {
                $value = trim($postData['field_' . $field['ID']]);
            }
            
            // Check if required
            if ($field['IsRequired'] && (is_null($value) || $value === '')) {
                throw new Exception("Field '{$field['Label']}' is required");
            }

            // Sanitization and Validation
            if (!empty($value)) {
                // Sanitize all string inputs
                $sanitizedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                
                // Specific validation for ID fields (except Course ID which can be alphanumeric)
                if ((strpos(strtolower($fieldKey), 'id') !== false || 
                    strpos(strtolower($fieldKey), '_id') !== false) && 
                    strtolower($fieldKey) !== 'idcourse') {
                    if (!ctype_digit((string)$value)) {
                        throw new Exception("Field '{$field['Label']}' must be a valid number");
                    }
                }

                $metadata[$fieldKey] = $sanitizedValue;
            }
        }

        // Add System Metadata
        $metadata['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $metadata['submission_date'] = date('Y-m-d H:i:s');
        
        // Validate total JSON size
        if (strlen(json_encode($metadata)) > METADATA_SIZE_LIMIT) {
             throw new Exception("Metadata size exceeds limit");
        }

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
            // We verify if ANY answer exists for this combination
            $stmt = $this->con->prepare("SELECT 1 FROM EvaluationResponses 
                WHERE FormType = ? 
                AND IDStudent = ? 
                AND IDCourse = ? 
                AND Semester = ? 
                LIMIT 1 FOR UPDATE");
            
            $semester = $metadata['Semester'] ?? '';
            $studentId = $metadata['IDStudent'] ?? '';
            $courseId = $metadata['IDCourse'] ?? '';

            $stmt->bind_param("ssss", $form['FormType'], $studentId, $courseId, $semester);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->close();
            
            return $result ? true : false;
        }
        
        return false;
    }

    private function saveAnswers($form, $metadata, $answers) {
        require_once __DIR__ . '/../evaluation/config/answer_processor.php';

        // Transaction is now handled by the caller (handleSubmission)
        
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        $semester = $metadata['Semester'] ?? null;

        $stmt = $this->con->prepare("INSERT INTO EvaluationResponses (FormType, FormTarget, QuestionID, AnswerValue, Metadata, Semester) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($answers as $questionId => $response) {
            $questionId = (int)$questionId;
            
            // Get Question Details
            // Note: Optimally, we should fetch all question details in one go, but keeping this logic for now
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
        $stmt->close();
    }
}
