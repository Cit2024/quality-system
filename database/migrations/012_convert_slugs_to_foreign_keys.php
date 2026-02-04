<?php
require_once __DIR__ . '/../../config/DbConnection.php';

echo "Starting migration: Converting string slugs to foreign keys...\n";

try {
    // Step 1: Add new FK columns to Form table
    echo "Step 1: Adding FormTypeID and EvaluatorTypeID columns...\n";
    
    $checkCol = $con->query("SHOW COLUMNS FROM Form LIKE 'FormTypeID'");
    if ($checkCol->num_rows == 0) {
        $sql = "ALTER TABLE Form ADD COLUMN FormTypeID INT(11) NULL AFTER FormType";
        if (!$con->query($sql)) {
            throw new Exception("Failed to add FormTypeID column: " . $con->error);
        }
        echo "  ✓ Added FormTypeID column\n";
    } else {
        echo "  - FormTypeID column already exists\n";
    }
    
    $checkCol = $con->query("SHOW COLUMNS FROM Form LIKE 'EvaluatorTypeID'");
    if ($checkCol->num_rows == 0) {
        $sql = "ALTER TABLE Form ADD COLUMN EvaluatorTypeID INT(11) NULL AFTER FormTarget";
        if (!$con->query($sql)) {
            throw new Exception("Failed to add EvaluatorTypeID column: " . $con->error);
        }
        echo "  ✓ Added EvaluatorTypeID column\n";
    } else {
        echo "  - EvaluatorTypeID column already exists\n";
    }

    // Step 2: Populate FormTypeID from FormType slug
    echo "\nStep 2: Populating FormTypeID from FormType slugs...\n";
    $sql = "UPDATE Form f
            INNER JOIN FormTypes ft ON f.FormType = ft.Slug
            SET f.FormTypeID = ft.ID
            WHERE f.FormType IS NOT NULL";
    if (!$con->query($sql)) {
        throw new Exception("Failed to populate FormTypeID: " . $con->error);
    }
    $affected = $con->affected_rows;
    echo "  ✓ Updated $affected rows\n";

    // Step 3: Populate EvaluatorTypeID from FormTarget slug
    echo "\nStep 3: Populating EvaluatorTypeID from FormTarget slugs...\n";
    $sql = "UPDATE Form f
            INNER JOIN EvaluatorTypes et ON f.FormTarget = et.Slug
            SET f.EvaluatorTypeID = et.ID
            WHERE f.FormTarget IS NOT NULL";
    if (!$con->query($sql)) {
        throw new Exception("Failed to populate EvaluatorTypeID: " . $con->error);
    }
    $affected = $con->affected_rows;
    echo "  ✓ Updated $affected rows\n";

    // Step 4: Add foreign key constraints
    echo "\nStep 4: Adding foreign key constraints...\n";
    
    // Check if FK already exists before adding
    $checkFK = $con->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'Form' 
                            AND CONSTRAINT_NAME = 'fk_form_formtype'");
    if ($checkFK->num_rows == 0) {
        $sql = "ALTER TABLE Form 
                ADD CONSTRAINT fk_form_formtype 
                FOREIGN KEY (FormTypeID) REFERENCES FormTypes(ID) ON DELETE SET NULL";
        if (!$con->query($sql)) {
            throw new Exception("Failed to add FormTypeID FK: " . $con->error);
        }
        echo "  ✓ Added FormTypeID foreign key constraint\n";
    } else {
        echo "  - FormTypeID FK already exists\n";
    }
    
    $checkFK = $con->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'Form' 
                            AND CONSTRAINT_NAME = 'fk_form_evaluatortype'");
    if ($checkFK->num_rows == 0) {
        $sql = "ALTER TABLE Form 
                ADD CONSTRAINT fk_form_evaluatortype 
                FOREIGN KEY (EvaluatorTypeID) REFERENCES EvaluatorTypes(ID) ON DELETE SET NULL";
        if (!$con->query($sql)) {
            throw new Exception("Failed to add EvaluatorTypeID FK: " . $con->error);
        }
        echo "  ✓ Added EvaluatorTypeID foreign key constraint\n";
    } else {
        echo "  - EvaluatorTypeID FK already exists\n";
    }

    // Step 5: Add indexes for performance
    echo "\nStep 5: Adding indexes...\n";
    
    $checkIdx = $con->query("SHOW INDEX FROM Form WHERE Key_name = 'idx_form_type_id'");
    if ($checkIdx->num_rows == 0) {
        $sql = "CREATE INDEX idx_form_type_id ON Form(FormTypeID)";
        if (!$con->query($sql)) {
            throw new Exception("Failed to add FormTypeID index: " . $con->error);
        }
        echo "  ✓ Added FormTypeID index\n";
    } else {
        echo "  - FormTypeID index already exists\n";
    }
    
    $checkIdx = $con->query("SHOW INDEX FROM Form WHERE Key_name = 'idx_evaluator_type_id'");
    if ($checkIdx->num_rows == 0) {
        $sql = "CREATE INDEX idx_evaluator_type_id ON Form(EvaluatorTypeID)";
        if (!$con->query($sql)) {
            throw new Exception("Failed to add EvaluatorTypeID index: " . $con->error);
        }
        echo "  ✓ Added EvaluatorTypeID index\n";
    } else {
        echo "  - EvaluatorTypeID index already exists\n";
    }

    echo "\n✅ Migration 012 completed successfully!\n";
    echo "\nNOTE: The old FormType and FormTarget columns are kept for backward compatibility.\n";
    echo "After verifying all code works with the new FK columns, you can drop them in a future migration.\n";

} catch (Exception $e) {
    die("\n❌ Migration failed: " . $e->getMessage() . "\n");
}
?>
