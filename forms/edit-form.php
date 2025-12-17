<?php
// ./forms/edit-form.php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: .././login.php");
    exit();
}

// Include the database connection
include '.././config/DbConnection.php';
include 'form_constants.php'; 

// Handle API Requests (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_access_data') {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'Invalid request'];
        
        try {
            $formId = intval($_GET['id']);
            if ($formId <= 0) throw new Exception('Invalid Form ID');
            
            // Get Password
            $stmt = $con->prepare("SELECT password FROM Form WHERE ID = ?");
            $stmt->bind_param('i', $formId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $password = $res['password'] ?? '';
            $stmt->close();
            
            // Get Fields
            $fields = [];
            $stmt = $con->prepare("SELECT ID, Label, Slug, FieldType, IsRequired FROM FormAccessFields WHERE FormID = ? ORDER BY OrderIndex ASC");
            $stmt->bind_param('i', $formId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $fields[] = $row;
            }
            $stmt->close();
            
            $response = [
                'success' => true,
                'password' => $password,
                'fields' => $fields
            ];
            
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        
        echo json_encode($response);
        exit();
    }
}

// Handle API Requests (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        $action = $_POST['action'];
        
        // --- Type Management ---
        if ($action === 'create_type') {
            $category = $_POST['category'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $icon = trim($_POST['icon'] ?? '');
            
            if (empty($name) || empty($slug) || empty($icon)) {
                throw new Exception('جميع الحقول مطلوبة');
            }
            
            $table = $category === 'target' ? 'EvaluatorTypes' : 'FormTypes';
            
            // Check slug
            $stmt = $con->prepare("SELECT ID FROM $table WHERE Slug = ?");
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) throw new Exception('المعرف موجود بالفعل');
            $stmt->close();
            
            // Insert
            $stmt = $con->prepare("INSERT INTO $table (Name, Slug, Icon) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $name, $slug, $icon);
            if (!$stmt->execute()) throw new Exception('فشل الإضافة');
            $newId = $con->insert_id;
            $stmt->close();
            
            // For FormTypes, handle allowed targets
            if ($category !== 'target' && !empty($_POST['allowed_targets'])) {
                $stmt = $con->prepare("INSERT INTO FormType_EvaluatorType (FormTypeID, EvaluatorTypeID) VALUES (?, ?)");
                foreach ($_POST['allowed_targets'] as $evalId) {
                    $stmt->bind_param('ii', $newId, $evalId);
                    $stmt->execute();
                }
                $stmt->close();
            }
            
            $response = ['success' => true, 'message' => 'تم الإضافة بنجاح'];
            
        } elseif ($action === 'delete_type') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $category = $input['category'] ?? '';
            
            if ($id <= 0) throw new Exception('معرف غير صحيح');
            
            $table = $category === 'target' ? 'EvaluatorTypes' : 'FormTypes';
            $col = $category === 'target' ? 'EvaluatorTypeID' : 'FormTypeID';
            
            // Check usage
            $stmt = $con->prepare("SELECT COUNT(*) as count FROM Form WHERE $col = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            $stmt->close();
            
            if ($count > 0) throw new Exception("لا يمكن الحذف لأنه مستخدم في $count نموذج");
            
            // Delete relationships first if needed
            if ($category !== 'target') {
                $stmt = $con->prepare("DELETE FROM FormType_EvaluatorType WHERE FormTypeID = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $con->prepare("DELETE FROM FormType_EvaluatorType WHERE EvaluatorTypeID = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            
            $stmt = $con->prepare("DELETE FROM $table WHERE ID = ?");
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) throw new Exception('فشل الحذف');
            $stmt->close();
            
            $response = ['success' => true, 'message' => 'تم الحذف بنجاح'];
            
        } 
        // --- Access Settings (Unified) ---
        elseif ($action === 'save_access_settings') {
            $formId = intval($_POST['form_id']);
            $password = trim($_POST['password'] ?? '');
            $fields = json_decode($_POST['fields'], true);
            
            $con->begin_transaction();
            
            try {
                // 1. Update Password
                $sql = "UPDATE Form SET password = ? WHERE ID = ?";
                $val = empty($password) ? null : $password;
                $stmt = $con->prepare($sql);
                $stmt->bind_param('si', $val, $formId);
                if (!$stmt->execute()) throw new Exception('فشل تحديث كلمة المرور');
                $stmt->close();
                
                // 2. Update Fields
                $stmt = $con->prepare("DELETE FROM FormAccessFields WHERE FormID = ?");
                $stmt->bind_param('i', $formId);
                $stmt->execute();
                $stmt->close();
                
                if (!empty($fields)) {
                    $stmt = $con->prepare("INSERT INTO FormAccessFields (FormID, Label, Slug, FieldType, IsRequired, OrderIndex) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($fields as $index => $field) {
                        $label = trim($field['Label']);
                        $slug = trim($field['Slug'] ?? '');
                        $type = $field['FieldType'];
                        $required = isset($field['IsRequired']) ? intval($field['IsRequired']) : 0;
                        $order = $index;
                        
                        if (!empty($label)) {
                            $stmt->bind_param('isssii', $formId, $label, $slug, $type, $required, $order);
                            $stmt->execute();
                        }
                    }
                    $stmt->close();
                }
                
                $con->commit();

                // Re-generate the evaluation link
                $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
                
                $stmt = $con->prepare("SELECT FormType, FormTarget FROM Form WHERE ID = ?");
                $stmt->bind_param('i', $formId);
                $stmt->execute();
                $fResult = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $evalLink = "";
                if ($fResult) {
                    $evalLink = $baseUrl . "/evaluation-form.php?evaluation=" . urlencode($fResult['FormType']) . 
                               "&Evaluator=" . urlencode($fResult['FormTarget']);
                               
                    $accessFieldsQuery = "SELECT Slug FROM FormAccessFields WHERE FormID = " . intval($formId) . " ORDER BY OrderIndex ASC";
                    $accessFieldsRes = mysqli_query($con, $accessFieldsQuery);
                    while($af = mysqli_fetch_assoc($accessFieldsRes)) {
                        if(!empty($af['Slug'])) {
                            $slug = $af['Slug'];
                            $evalLink .= "&" . urlencode($slug) . "={" . $slug . "}";
                        }
                    }
                }

                $response = ['success' => true, 'message' => 'تم حفظ الإعدادات', 'eval_link' => $evalLink];
                
            } catch (Exception $e) {
                $con->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
} 

// Get the form ID from the URL
$formId = $_GET['id'];

// Fetch the form details
$stmt = $con->prepare("SELECT * FROM Form WHERE ID = ?");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();

// Fetch sections for the form
$sections_query = "SELECT * FROM Section WHERE IDForm = $formId";
$sections_result = mysqli_query($con, $sections_query);

// Fetch Access Fields
$fields_query = "SELECT * FROM FormAccessFields WHERE FormID = $formId ORDER BY OrderIndex ASC";
$fields_result = mysqli_query($con, $fields_query);

if (!$sections_result) {
    die("Database error: " . mysqli_error($con));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل النموذج</title>
    <link rel="stylesheet" href="../styles/forms.css">
    <link rel="stylesheet" href="../components/ComponentsStyles.css">
    <link rel="icon" href=".././assets/icons/college.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     
    <style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        overflow: auto;
    }

    .modal.active {
        display: block !important;
    }

    .modal-content,
    .access-modal-content {
        background: white;
        margin: 5% auto;
        padding: 30px;
        border-radius: 8px;
        width: 90%;
        max-width: 600px;
        position: relative;
        max-height: 80vh;
        overflow-y: auto;
        direction: rtl;
    }

    .close {
        position: absolute;
        left: 15px;
        top: 15px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #666;
        line-height: 1;
        z-index: 1;
    }

    .close:hover {
        color: #000;
    }

    .icon-selector {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
        max-height: 150px;
        overflow-y: auto;
        border: 1px solid #ddd;
        padding: 10px;
        border-radius: 4px;
        background: #f9f9f9;
    }

    .icon-option {
        cursor: pointer;
        padding: 8px;
        text-align: center;
        border: 2px solid transparent;
        border-radius: 4px;
        transition: all 0.3s;
        background: white;
    }

    .icon-option:hover {
        background: #f5f5f5;
        border-color: #ddd;
    }

    .icon-option img {
        width: 24px;
        height: 24px;
        display: block;
        margin: 0 auto;
    }

    .fields-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }

    .fields-table th,
    .fields-table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: right;
    }

    .fields-table th {
        background: #f5f5f5;
        font-weight: bold;
    }

    .fields-table input[type="text"],
    .fields-table select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: inherit;
    }

    .btn-add-row,
    .btn-remove-row {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-add-row {
        background: #4CAF50;
        color: white;
        margin-top: 10px;
    }

    .btn-add-row:hover {
        background: #45a049;
    }

    .btn-remove-row {
        background: #f44336;
        color: white;
        padding: 5px 10px;
    }

    .btn-remove-row:hover {
        background: #da190b;
    }

    .modal-footer {
        margin-top: 20px;
        text-align: left;
        border-top: 1px solid #eee;
        padding-top: 15px;
        display: flex;
        gap: 10px;
        justify-content: flex-start;
    }

    .btn-cancel,
    .btn-save-access {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-cancel {
        background: #666;
        color: white;
    }

    .btn-cancel:hover {
        background: #555;
    }

    .btn-save-access {
        background: #2196F3;
        color: white;
    }

    .btn-save-access:hover {
        background: #0b7dda;
    }

    .access-section {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }

    .access-section:last-of-type {
        border-bottom: none;
    }

    .access-section h3 {
        margin-bottom: 15px;
        color: #333;
    }

    .access-input-group {
        margin-bottom: 15px;
    }

    .access-input-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }

    .access-input-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: inherit;
    }

    .access-input-group small {
        display: block;
        margin-top: 5px;
        color: #666;
        font-size: 12px;
    }

    /* Custom Select Styles */
    .custom-select-wrapper {
        position: relative;
        display: inline-block;
        min-width: 200px;
    }

    .custom-select-trigger {
        padding: 8px 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: white;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .custom-select-trigger:hover {
        border-color: #2196F3;
    }

    .custom-select-options {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        left: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 5px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .custom-select-wrapper.open .custom-select-options {
        display: block;
    }

    .custom-option {
        padding: 10px 15px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }

    .custom-option:hover {
        background: #f5f5f5;
    }

    .custom-option.selected {
        background: #e3f2fd;
    }

    .btn-delete-option {
        background: transparent;
        border: none;
        color: #f44336;
        cursor: pointer;
        padding: 5px;
        transition: color 0.3s;
    }

    .btn-delete-option:hover {
        color: #da190b;
    }

    .custom-option-add {
        padding: 10px 15px;
        cursor: pointer;
        border-top: 1px solid #ddd;
        color: #2196F3;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s;
    }

    .custom-option-add:hover {
        background: #f5f5f5;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
    }

    .form-group input[type="text"],
    .form-group input[type="password"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: inherit;
        font-size: 14px;
    }

    .form-group button[type="submit"] {
        width: 100%;
        background: #4CAF50;
        color: white;
        border: none;
        padding: 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        transition: all 0.3s;
    }

    .form-group button[type="submit"]:hover {
        background: #45a049;
    }

    .access-settings-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #2196F3;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }

    .access-settings-btn:hover {
        background: #0b7dda;
    }
    </style>
</head>

<body>
    <?php include "../components/header.html"; ?>
    <div class="view-container">
        <!-- Back Button -->
        <div class="back-button" onclick="window.location.href='../forms.php'" data-printthis-ignore>
            <img src=".././assets/icons/chevron-right.svg" alt="Back" />
            <span>رجوع</span>
        </div>

        <!-- Form Details -->
        <div class="form-details" data-form-id="<?php echo $form['ID']; ?>">
            <!-- Editable Form Title -->
            <h1>
                <p class="editable" data-id="<?php echo $form['ID']; ?>" data-field="Title"><?php echo $form['Title']; ?></p>
                <span>#<?php echo $form['ID']; ?></span>
            </h1>
            <!-- Editable Form Description -->
            <p class="editable" data-id="<?php echo $form['ID']; ?>" data-field="Description"><?php echo $form['Description']; ?></p>

            <div class="form-row-active-button">
                <div class="control-buttons">
                    <!-- Form Status and Flip Switch Row -->
                    <div class="form-status-row">
                        <!-- Custom Flip Switch -->
                        <label class="switch" data-printthis-ignore>
                            <input type="checkbox" id="form-status-toggle" <?php echo $form['FormStatus'] === 'published' ? 'checked' : ''; ?>>
                            <div class="slider">
                                <div class="circle">
                                    <img src=".././assets/icons/cross.svg" class="cross" alt="Cross Icon" />
                                    <img src=".././assets/icons/checkmark.svg" class="checkmark" alt="Checkmark Icon" />
                                </div>
                            </div>
                        </label>

                        <!-- Form Status -->
                        <div class="form-status">
                            <?php if ($form['FormStatus'] === 'published'): ?>
                                <img src=".././assets/icons/badge-check.svg" alt="Published" />
                                <span>منشور</span>
                            <?php else: ?>
                                <img src=".././assets/icons/badge-alert.svg" alt="Draft" />
                                <span>مسودة</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Form Target Control -->
                    <div class="form-status-row">
                        <span>المُقيِّم : </span>
                        <div class="custom-select-wrapper form-target-control" id="target-select-wrapper">
                            <div class="custom-select-trigger js-custom-select-trigger" data-type="target">
                                <span id="target-selected-text"><?= FORM_TARGETS[$form['FormTarget']]['name'] ?? 'اختر' ?></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-options" id="target-options">
                                <?php foreach (FORM_TARGETS as $key => $target): ?>
                                    <div class="custom-option js-custom-option <?= $key === $form['FormTarget'] ? 'selected' : '' ?>" 
                                         data-type="target" 
                                         data-value="<?= $key ?>" 
                                         data-db-id="<?= $target['id'] ?>" 
                                         data-name="<?= $target['name'] ?>">
                                        <span><?= $target['name'] ?></span>
                                        <button type="button" class="btn-delete-option js-delete-type" data-category="target" data-db-id="<?= $target['id'] ?>" title="حذف">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                                <div class="custom-option-add js-open-type-modal" data-category="target">
                                    <i class="fa-solid fa-plus"></i> إضافة جديد
                                </div>
                            </div>
                            <input type="hidden" id="form-target-select" value="<?= $form['FormTarget'] ?>">
                        </div>
                    </div>

                    <!-- Form Type Control -->
                    <div class="form-status-row">
                        <span>نوع التقييم : </span>
                        <div class="custom-select-wrapper form-type-control" id="type-select-wrapper">
                            <div class="custom-select-trigger js-custom-select-trigger" data-type="type">
                                <span id="type-selected-text"><?= FORM_TYPES[$form['FormType']]['name'] ?? 'اختر' ?></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-options" id="type-options">
                                <?php foreach (FORM_TYPES as $key => $type): ?>
                                    <div class="custom-option js-custom-option <?= $key === $form['FormType'] ? 'selected' : '' ?>" 
                                         data-type="type"
                                         data-value="<?= $key ?>" 
                                         data-db-id="<?= $type['id'] ?>" 
                                         data-targets="<?= implode(',', $type['allowed_targets']) ?>"
                                         data-name="<?= $type['name'] ?>">
                                        <span><?= $type['name'] ?></span>
                                        <button type="button" class="btn-delete-option js-delete-type" data-category="type" data-db-id="<?= $type['id'] ?>" title="حذف">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                                <div class="custom-option-add js-open-type-modal" data-category="type">
                                    <i class="fa-solid fa-plus"></i> إضافة جديد
                                </div>
                            </div>
                            <input type="hidden" id="form-type-select" value="<?= $form['FormType'] ?>" data-current-type="<?= $form['FormType'] ?>">
                        </div>
                    </div>

                </div> <!-- Close control-buttons -->

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="access-settings-btn js-open-access-modal">
                        <i class="fa-solid fa-lock"></i>
                        إعدادات الوصول
                    </button>
                    <button class="download-form-button" data-printthis-ignore 
                        data-form-id="<?php echo $form['ID']; ?>"
                        data-form-title="<?php echo htmlspecialchars($form['Title'], ENT_QUOTES, 'UTF-8'); ?>">
                        <img src=".././assets/icons/file-down.svg" alt="download" />
                        تنزيل النموذج
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Evaluation Link Section -->
        <?php if ($form['FormStatus'] === 'published'): ?>
        <div class="evaluation-link-section">
            <div class="link-header">
                <i class="fa-solid fa-link"></i>
                <h3>رابط التقييم للمشاركة</h3>
            </div>
            <div class="link-container">
                <?php 
                $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
                $evalLink = $baseUrl . "/evaluation-form.php?evaluation=" . urlencode($form['FormType']) . 
                           "&Evaluator=" . urlencode($form['FormTarget']);
                $accessFieldsQuery = "SELECT Slug FROM FormAccessFields WHERE FormID = " . intval($form['ID']) . " ORDER BY OrderIndex ASC";
                $accessFieldsRes = mysqli_query($con, $accessFieldsQuery);
                while($af = mysqli_fetch_assoc($accessFieldsRes)) {
                    if(!empty($af['Slug'])) {
                        $slug = $af['Slug'];
                        $evalLink .= "&" . urlencode($slug) . "={" . $slug . "}";
                    }
                }
                ?>
                <input type="text" class="evaluation-link-input" value="<?php echo htmlspecialchars($evalLink); ?>" readonly>
                <button class="copy-link-btn" title="نسخ الرابط">
                    <i class="fa-solid fa-copy"></i>
                    <span>نسخ</span>
                </button>
            </div>
            <p class="link-note">
                <i class="fa-solid fa-info-circle"></i>
                هذا الرابط يمكن مشاركته مع المقيمين للمشاركة في التقييم. 
                <br>ملاحظة: سيتم استبدال المتغيرات ما بين الأقواس <?php 
                $accessFieldsRes = mysqli_query($con, $accessFieldsQuery); 
                while($af = mysqli_fetch_assoc($accessFieldsRes)) {
                    if(!empty($af['Slug'])) {
                        $slug = $af['Slug'];
                        echo "{" . htmlspecialchars($slug) . "}" . " ";
                    }
                }?> بالقيم الفعلية عند المشاركة.
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Form Note -->
        <div class="form-note">
            <span>
                * ملاحظة التظهر للمقييم
            </span>
            <?php if (!empty($form['note'])) : ?>
                <pre class="editable-note">
                    <?php echo $form['note']; ?>
                </pre>
            <?php else : ?>
                <div class="add-note-button">
                    <img src="../assets/icons/plus-circle.svg" alt="Add Note" />
                    إضافة ملاحظة
                </div>
            <?php endif; ?>
        </div>

        <!-- List of Sections -->
        <div class="sections-container">
            <?php while ($section = mysqli_fetch_assoc($sections_result)): ?>
                <div class="section">
                    <!-- Section Header -->
                    <div class="section-header">
                        <!-- Delete Button -->
                        <span class="delete-section" data-section-id="<?php echo $section['ID']; ?>" data-printthis-ignore>
                            <img src=".././assets/icons/trash.svg" alt="Delete Section" />
                        </span>

                        <!-- Editable Section Title -->
                        <h2 class="editable-section" data-id="<?php echo $section['ID']; ?>" data-field="title"><?php echo $section['title']; ?></h2>

                        <!-- Copy Plus Button -->
                        <span class="copy-plus" data-section-id="<?php echo $section['ID']; ?>" data-printthis-ignore>
                            <img src=".././assets/icons/plus-circle.svg" alt="Add Default Question" />
                        </span>

                        <!-- Chevron Icon -->
                        <span class="chevron" data-printthis-ignore>
                            <img src=".././assets/icons/chevron-down.svg" alt="Chevron down icon" />
                        </span>
                    </div>

                    <!-- Fetch and display questions for this section -->
                    <?php
                    $sectionId = $section['ID'];
                    $questions_query = "SELECT * FROM Question WHERE IDSection = $sectionId";
                    $questions_result = mysqli_query($con, $questions_query);
                    ?>
                    <!-- Questions Container -->
                    <div class="questions-container">
                        <?php while ($question = mysqli_fetch_assoc($questions_result)): ?>
                            <!-- Editable Question -->
                            <div class="question">
                                <!-- Question Title and (?) Mark Row -->
                                <div class="question-title-row">
                                    <!-- Delete Button -->
                                    <span class="delete-question" data-question-id="<?php echo $question['ID']; ?>" data-printthis-ignore>
                                        <img src=".././assets/icons/trash.svg" alt="Delete Question" />
                                    </span>
                                    <!-- Question Title -->
                                    <p class="editable-question" data-id="<?php echo $question['ID']; ?>" data-field="TitleQuestion"><?php echo $question['TitleQuestion']; ?></p>
                                    <!-- (?) Mark -->
                                    <span>?</span>
                                </div>
                                <!-- Question Type -->
                                <div class="question-type" data-id="<?php echo $question['ID']; ?>" data-type="<?php echo $question['TypeQuestion']; ?>">
                                    <?php if ($question['TypeQuestion'] === 'true_false'): ?>
                                        <img src=".././assets/icons/square-check.svg" alt="True/False" />
                                        <span>صح/خطأ</span>
                                    <?php elseif ($question['TypeQuestion'] === 'evaluation'): ?>
                                        <img src=".././assets/icons/star.svg" alt="Evaluation" />
                                        <span>تقييم</span>
                                    <?php elseif ($question['TypeQuestion'] === 'essay'):  ?>
                                        <img src=".././assets/icons/quote.svg" alt="Essay" />
                                        <span>مقالي</span>
                                    <?php endif; ?>
                                    <?php if ($question['TypeQuestion'] === 'multiple_choice'): ?>
                                        <img src=".././assets/icons/list-check.svg" alt="Multiple choice" />
                                        <span>اختيار من متعدد</span>
                                    <?php endif; ?>
                                </div>
                               <?php if ($question['TypeQuestion'] === 'multiple_choice') : 
                                    $options = json_decode($question['Choices'] ?? '[]', true);
                                    $options = is_array($options) ? $options : [];
                                ?>
                                <div class="options-section">
                                    <label>خيارات الإجابة:</label>
                                    <div class="options-list">
                                        <?php foreach ($options as $opt) : ?>
                                            <div class="option-item" data-option="<?= htmlspecialchars($opt) ?>">
                                                <button class="remove-option" data-printthis-ignore><i class="fa-solid fa-trash"></i></button>
                                                <p class="option-value"><?= htmlspecialchars($opt) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="add-option" data-printthis-ignore><i class="fa-solid fa-circle-plus"></i> إضافة خيار</button>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endwhile; ?>
            <!-- Add Section Button -->
            <div class="add-section-button" data-form-id="<?php echo $form['ID']; ?>" data-printthis-ignore>
                <img src=".././assets/icons/copy-plus.svg" alt="Add Section" />
                <span>إضافة قسم</span>
            </div>
        </div>
    </div>
    <?php include "../components/footer.html"; ?>

    <!-- Type Management Modal -->
    <div id="typeModal" class="modal">
        <div class="modal-content">
            <span class="close js-close-type-modal">&times;</span>
            <h2 id="modalTitle">إضافة عنصر جديد</h2>
            <form id="typeForm">
                <input type="hidden" id="typeCategory" name="category">
                
                <div class="form-group">
                    <label>الاسم (بالعربية)</label>
                    <input type="text" id="typeName" name="name" required placeholder="مثال: طالب">
                </div>
                
                <div class="form-group">
                    <label>المعرف (Slug - إنجليزي)</label>
                    <input type="text" id="typeSlug" name="slug" required placeholder="example_slug" style="direction: ltr;">
                </div>

                <div class="form-group" id="allowedTargetsContainer" style="display: none;">
                    <label>أنواع المقيمين المسموح لهم (اختياري)</label>
                    <div id="allowedTargetsList" style="display: flex; flex-wrap: wrap; gap: 10px; max-height: 100px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                        <!-- Checkboxes will be injected here -->
                    </div>
                </div>

                <div class="form-group">
                    <label>الأيقونة</label>
                    <div class="icon-selector">
                        <!-- Icons will be injected here -->
                    </div>
                    <input type="hidden" id="typeIcon" name="icon" required>
                </div>

                <div class="form-group">
                    <button type="submit">إضافة</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Access Control Modal -->
    <div id="accessControlModal" class="modal">
        <div class="access-modal-content">
            <span class="close js-close-access-modal">&times;</span>
            <h2>إعدادات الوصول</h2>
            
            <!-- Section 1: Password -->
            <div class="access-section">
                <h3>1. كلمة المرور</h3>
                <div class="access-input-group">
                    <label>كلمة مرور النموذج (اختياري)</label>
                    <input type="password" id="access-form-password" placeholder="اتركه فارغاً للوصول العام">
                    <small>إذا تم تعيين كلمة مرور، سيطلب من المستخدم إدخالها قبل عرض النموذج.</small>
                </div>
            </div>

            <!-- Section 2: Registration Fields -->
            <div class="access-section">
                <h3>2. بيانات التسجيل المطلوبة</h3>
                <p style="margin-bottom: 10px; color: #666; font-size: 14px;">حدد البيانات التي يجب على المستخدم إدخالها قبل البدء.</p>
                
                <table class="fields-table">
                    <thead>
                        <tr>
                            <th>اسم الحقل (العربية)</th>
                            <th>المعرف (URL Slug)</th>
                            <th style="width: 150px;">النوع</th>
                            <th style="width: 100px;">إجباري؟</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="access-fields-tbody">
                        <!-- Rows will be added here dynamically -->
                    </tbody>
                </table>
                
                <button type="button" class="btn-add-row js-add-access-row">
                    <i class="fa-solid fa-plus"></i> إضافة حقل جديد
                </button>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel js-close-access-modal">إلغاء</button>
                <button type="button" class="btn-save-access js-save-access-settings">حفظ التغييرات</button>
            </div>
        </div>
    </div>

    <?php include "components/footer.php"; ?>

    <!-- Scripts -->
    <script>
        // Define configuration FIRST before any other scripts
        window.formConfig = {
            id: <?php echo $form['ID']; ?>,
            password: "<?php echo htmlspecialchars($form['password'] ?? '', ENT_QUOTES); ?>"
        };
        
        // Define TYPE_QUESTION for question type modal
        window.TYPE_QUESTION = {
            'multiple_choice': {
                name: 'إختيار من متعدد',
                icon: 'assets/icons/list-check.svg'
            },
            'true_false': {
                name: 'صح/خطأ',
                icon: 'assets/icons/square-check.svg'
            },
            'evaluation': {
                name: 'تقييم',
                icon: 'assets/icons/star.svg'
            },
            'essay': {
                name: 'مقالي',
                icon: 'assets/icons/quote.svg'
            }
        };
        
        console.log("Form Config initialized:", window.formConfig);
        console.log("TYPE_QUESTION initialized:", window.TYPE_QUESTION);
    </script>
    <script src="../scripts/forms.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/printThis/1.15.0/printThis.min.js"></script>
</body>

</html>