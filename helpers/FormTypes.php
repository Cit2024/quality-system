<?php

class FormTypes {
    
    private static $formTypes = null;
    private static $formTargets = null;

    const TYPE_QUESTION = [
        'true_false' => [
            'name' => 'صح/خطأ',
            'icon' => 'fa-solid fa-square-check'
        ],
        'evaluation' => [
            'name' => 'تقييم',
            'icon' => 'fa-solid fa-star'
        ],
        'multiple_choice' => [
            'name' => 'إختيار من متعدد',
            'icon' => 'fa-solid fa-list-check'
        ],
        'essay' => [
            'name' => 'مقالي',
            'icon' => 'fa-solid fa-quote-left'
        ]
    ];

    public static function getFormTypes($con) {
        if (self::$formTypes === null) {
            self::$formTypes = [];
            $query = "SELECT ID, Slug, Name, Icon FROM FormTypes";
            $result = $con->query($query);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    self::$formTypes[$row['Slug']] = [
                        'id' => $row['ID'],
                        'name' => $row['Name'],
                        'icon' => $row['Icon'],
                        'allowed_targets' => []
                    ];
                }
            }
                
            // Fetch allowed targets for each form type from FormType_EvaluatorType table if it exists
            // This is a many-to-many relationship
            // Check if table exists first
            $tableCheck = $con->query("SHOW TABLES LIKE 'FormType_EvaluatorType'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                foreach (self::$formTypes as $slug => &$formType) {
                    $query = "SELECT et.Slug 
                              FROM FormTypes ft
                              JOIN FormType_EvaluatorType fte ON ft.ID = fte.FormTypeID
                              JOIN EvaluatorTypes et ON fte.EvaluatorTypeID = et.ID
                              WHERE ft.Slug = ?";
                    $stmt = $con->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param('s', $slug);
                        $stmt->execute();
                        $result2 = $stmt->get_result();
                        while ($row = $result2->fetch_assoc()) {
                            $formType['allowed_targets'][] = $row['Slug'];
                        }
                        $stmt->close();
                    }
                }
            } else {
                // Fallback: if table doesn't exist, allow all targets for all types
                $allTargets = array_keys(self::getFormTargets($con));
                foreach (self::$formTypes as $slug => &$formType) {
                    $formType['allowed_targets'] = $allTargets;
                }
            }
        }
        return self::$formTypes;
    }

    public static function getFormTargets($con) {
        if (self::$formTargets === null) {
            self::$formTargets = [];
            $query = "SELECT Slug, Name, Icon FROM EvaluatorTypes";
            $result = $con->query($query);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    self::$formTargets[$row['Slug']] = [
                        'name' => $row['Name'],
                        'icon' => $row['Icon']
                    ];
                }
            }
        }
        return self::$formTargets;
    }
}
