<?php

require_once __DIR__ . '/SubmissionRuleInterface.php';

class StudentTeacherLookupRule implements SubmissionRuleInterface {
    
    public function execute(array $data, array $context, array $config): array {
        $metadata = $data['metadata'];
        $conn_cit = $context['conn_cit'];
        
        if (!$conn_cit) {
            throw new Exception("Database connection 'conn_cit' is required for StudentTeacherLookupRule");
        }

        // Configuration
        $requiredFields = $config['required_fields'] ?? ['Semester', 'IDCourse', 'IDGroup'];
        $targetField = $config['target_field'] ?? 'teacher_id';
        
        // Validate required fields exist in metadata
        foreach ($requiredFields as $field) {
            if (empty($metadata[$field])) {
                throw new Exception("Missing required field for teacher lookup: $field");
            }
        }

        $semester = $metadata['Semester'];
        $course = $metadata['IDCourse'];
        $group = $metadata['IDGroup'];

        // 1. Primary Lookup (Current Semester)
        $stmt = $conn_cit->prepare("SELECT TNo FROM coursesgroups WHERE ZamanNo = ? AND MadaNo = ? AND GNo = ?");
        $stmt->bind_param("isi", $semester, $course, $group);
        $stmt->execute();
        $stmt->bind_result($teacherId);
        
        $found = false;
        if ($stmt->fetch()) {
            $metadata[$targetField] = $teacherId;
            $found = true;
        } 
        $stmt->close();

        // 2. Heuristic Lookup (Previous Semesters)
        if (!$found) {
            $stmt = $conn_cit->prepare("
                SELECT TNo FROM coursesgroups 
                WHERE MadaNo = ? AND GNo = ? AND ZamanNo < ? 
                ORDER BY ZamanNo DESC LIMIT 1
            ");
            $stmt->bind_param("sii", $course, $group, $semester);
            $stmt->execute();
            $stmt->bind_result($lastTeacherId);
            if ($stmt->fetch()) {
                $metadata[$targetField] = $lastTeacherId;
                $found = true;
            }
            $stmt->close();
        }

        if (!$found) {
            // Check context/config for strict mode or fallback behavior
            // For now, consistent with previous logic: throw error unless specifically handled
            throw new Exception("Teacher assignment not found for this course.");
        }

        $data['metadata'] = $metadata;
        return $data;
    }
}
