<?php

require_once __DIR__ . '/exceptions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/rules/SubmissionRuleInterface.php';
require_once __DIR__ . '/rules/StudentTeacherLookupRule.php';
require_once __DIR__ . '/rules/UniqueSubmissionRule.php';

class ResponseHandler {
    private $con;
    private $conn_cit;
    private $messages = []; // Cache for system messages

    public function __construct($con, $conn_cit = null) {
        $this->con = $con;
        $this->conn_cit = $conn_cit;
    }

    public function handleSubmission($postData) {
        $this->log("--- NEW SUBMISSION ---");
        
        $this->con->begin_transaction();
        try {
            // 1. Basic Validation
            if (empty($postData['form_id'])) {
                throw new ValidationException($this->getMessage('form_id_missing', 'Form ID is missing'));
            }
            $formId = (int)$postData['form_id'];

            // 2. Fetch Form Details
            $stmt = $this->con->prepare("SELECT * FROM Form WHERE ID = ? AND FormStatus = 'published'");
            $stmt->bind_param("i", $formId);
            $stmt->execute();
            $form = $stmt->get_result()->fetch_assoc();
            
            if (!$form) {
                throw new NotFoundException($this->getMessage('form_not_found', 'Form not found or not published'));
            }

            // 3. Validate Dynamic Access Fields
            $metadata = $this->validateAndCollectMetadata($formId, $postData);
            
            // 3.1 Normalize Metadata (Critical for DB Fix)
            // Ensure schema-compatible keys exist (snake_case)
            $normalizationMap = [
                'IDStudent' => 'student_id',
                'IDCourse' => 'course_id',
                'teacher_id' => 'teacher_id'
            ];
            foreach ($normalizationMap as $old => $new) {
                if (isset($metadata[$old])) {
                    $metadata[$new] = $metadata[$old];
                }
            }

            // 4. Dynamic Rules Execution
            $rules = $this->loadRules($form['FormType'], $form['FormTarget']);
            foreach ($rules as $ruleData) {
                $className = $ruleData['RuleClass'];
                $config = json_decode($ruleData['RuleConfig'], true) ?? [];
                
                if (class_exists($className)) {
                    $rule = new $className();
                    if ($rule instanceof SubmissionRuleInterface) {
                        try {
                            $context = [
                                'con' => $this->con,
                                'conn_cit' => $this->conn_cit,
                                'form' => $form
                            ];
                            // Execute Rule
                            $resultData = $rule->execute(['metadata' => $metadata], $context, $config);
                            
                            // Update metadata if modified by rule
                            if (isset($resultData['metadata'])) {
                                $metadata = $resultData['metadata'];
                            }
                        } catch (Exception $e) {
                            // Map rule exceptions to user-friendly messages if possible
                            // For duplicates, we check the specific message key
                            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                                throw new DuplicateException($this->getMessage('submission_duplicate', 'Duplicate submission'));
                            }
                            throw $e;
                        }
                    }
                }
            }
            
            // 5. Save Answers
            $this->saveAnswers($form, $metadata, $postData['question'] ?? []);
            
            $this->con->commit();
            $this->log("Submission Successful");
            return [
                'success' => true, 
                'message' => $this->getMessage('submission_success', 'Submission successful'),
                'form_target' => $form['FormTarget'],
                'form_type' => $form['FormType']
            ];

        } catch (mysqli_sql_exception $e) {
            $this->con->rollback();
            $this->log("MySQL Error: " . $e->getMessage());
            if ($e->getCode() == 1062) {
                 return ['success' => false, 'message' => $this->getMessage('submission_duplicate', 'Duplicate submission')];
            }
            return ['success' => false, 'message' => $this->getMessage('database_error', 'Database error occurred')];
        } catch (Exception $e) {
            $this->con->rollback();
            $this->log("Exception: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function validateAndCollectMetadata($formId, $postData) {
        $metadata = [];
        
        $stmt = $this->con->prepare("SELECT * FROM FormAccessFields WHERE FormID = ? ORDER BY OrderIndex");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($field = $result->fetch_assoc()) {
            $fieldKey = !empty($field['Slug']) ? $field['Slug'] : $field['Label'];
            
            $rawValue = null;
            if (isset($postData[$fieldKey])) {
                $rawValue = $postData[$fieldKey];
            } elseif (isset($postData['field_' . $field['ID']])) {
                $rawValue = $postData['field_' . $field['ID']];
            }

            if (is_array($rawValue)) {
                $rawValue = reset($rawValue);
            }
            
            $value = ($rawValue !== null) ? trim((string)$rawValue) : null;
            
            if ($field['IsRequired'] && (is_null($value) || $value === '')) {
                $msg = str_replace('{field}', $field['Label'], $this->getMessage('field_required', "Field {$field['Label']} is required"));
                throw new Exception($msg);
            }

            if (!empty($value)) {
                $sanitizedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                
                // Numeric validation for ID fields (except Course ID)
                if ((strpos(strtolower($fieldKey), 'id') !== false || 
                    strpos(strtolower($fieldKey), '_id') !== false) && 
                    strtolower($fieldKey) !== 'idcourse') {
                    if (!ctype_digit((string)$value)) {
                         $msg = str_replace('{field}', $field['Label'], $this->getMessage('field_number_required', "Field {$field['Label']} must be a number"));
                         throw new Exception($msg);
                    }
                }

                $metadata[$fieldKey] = $sanitizedValue;
            }
        }

        $metadata['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $metadata['submission_date'] = date('Y-m-d H:i:s');
        
        return $metadata;
    }

    private function loadRules($formType, $formTarget) {
        $rules = [];
        // Fetch active rules for this form type/target, ordered by index
        $stmt = $this->con->prepare("SELECT RuleClass, RuleConfig FROM FormProcessingRules WHERE FormType = ? AND FormTarget = ? AND IsActive = 1 ORDER BY OrderIndex ASC");
        $stmt->bind_param("ss", $formType, $formTarget);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rules[] = $row;
        }
        $stmt->close();
        return $rules;
    }

    private function getMessage($key, $defaultMessage) {
        // Return cached message if available
        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        // Fetch from DB
        $stmt = $this->con->prepare("SELECT Message FROM SystemMessages WHERE `Key` = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $stmt->bind_result($message);
        
        if ($stmt->fetch()) {
            $this->messages[$key] = $message;
        } else {
            // Fallback to default if not found
            $this->messages[$key] = $defaultMessage;
        }
        $stmt->close();
        
        return $this->messages[$key];
    }
    
    private function log($message) {
        $logFile = __DIR__ . '/../logs/submission.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    private function saveAnswers($form, $metadata, $answers) {
        require_once __DIR__ . '/../evaluation/config/answer_processor.php';
        
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        $semester = $metadata['Semester'] ?? null;

        $stmt = $this->con->prepare("INSERT INTO EvaluationResponses (FormType, FormTarget, QuestionID, AnswerValue, Metadata, Semester) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($answers as $questionId => $response) {
            $questionId = (int)$questionId;
            
            $qStmt = $this->con->prepare("SELECT TypeQuestion, Choices FROM Question WHERE ID = ?");
            $qStmt->bind_param("i", $questionId);
            $qStmt->execute();
            $questionData = $qStmt->get_result()->fetch_assoc();
            $qStmt->close();

            if (!$questionData) continue;

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

