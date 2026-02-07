<?php

require_once __DIR__ . '/SubmissionRuleInterface.php';

class UniqueSubmissionRule implements SubmissionRuleInterface {
    
    public function execute(array $data, array $context, array $config): array {
        $con = $context['con'];
        $formType = $context['form']['FormType'];
        $metadata = $data['metadata'];
        $semester = $metadata['Semester'] ?? null;
        
        // Config: keys to check for uniqueness
        // e.g. ["student_id", "course_id"] or ["IDStudent", "IDCourse"]
        // We need to map config keys to metadata values
        $uniqueKeys = $config['unique_keys'] ?? [];
        
        // Build the query dynamically?
        // Current logic: SELECT 1 FROM EvaluationResponses WHERE FormType=? AND student_id=? AND course_id=? AND Semester=?
        
        // We use the new schema columns: student_id, course_id
        // Metadata should have these normalized keys now (handled by ResponseHandler before rules)
        
        $sql = "SELECT 1 FROM EvaluationResponses WHERE FormType = ? AND Semester = ?";
        $params = [$formType, $semester];
        $types = "ss";

        foreach ($uniqueKeys as $key) {
            if (!isset($metadata[$key])) {
                // If a key required for uniqueness is missing, we can't check. 
                // Should we throw error? Yes for strictness.
                throw new Exception("Missing key for uniqueness check: $key");
            }
            
            // Map key to DB column
            // We assume column names match metadata keys for simplicity (student_id -> student_id)
            // AND we assume the columns exist in DB (fixed by migration 017)
            $sql .= " AND $key = ?";
            $params[] = $metadata[$key];
            $types .= "s";
        }
        
        $sql .= " LIMIT 1 FOR UPDATE";
        
        $stmt = $con->prepare($sql);
        if (!$stmt) {
             throw new Exception("UniqueSubmissionRule DB Error: " . $con->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row();
        $stmt->close();
        
        if ($exists) {
            throw new Exception("Duplicate submission detected.");
        }

        return $data;
    }
}
