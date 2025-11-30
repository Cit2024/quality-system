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
            $stmt = $con->prepare("SELECT ID, Label, FieldType, IsRequired FROM FormAccessFields WHERE FormID = ? ORDER BY OrderIndex ASC");
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
            $category = $input['category'] ?? ''; // 'target' or 'type'
            
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
            
            // Delete relationships first if needed (FormType_EvaluatorType)
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
                // Strategy: Delete all and re-insert. Simple and effective for this scale.
                $stmt = $con->prepare("DELETE FROM FormAccessFields WHERE FormID = ?");
                $stmt->bind_param('i', $formId);
                $stmt->execute();
                $stmt->close();
                
                if (!empty($fields)) {
                    $stmt = $con->prepare("INSERT INTO FormAccessFields (FormID, Label, FieldType, IsRequired, OrderIndex) VALUES (?, ?, ?, ?, ?)");
                    foreach ($fields as $index => $field) {
                        $label = trim($field['Label']);
                        $type = $field['FieldType'];
                        $required = isset($field['IsRequired']) ? intval($field['IsRequired']) : 0;
                        $order = $index;
                        
                        if (!empty($label)) {
                            $stmt->bind_param('issii', $formId, $label, $type, $required, $order);
                            $stmt->execute();
                        }
                    }
                    $stmt->close();
                }
                
                $con->commit();
                $response = ['success' => true, 'message' => 'تم حفظ الإعدادات'];
                
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

    <!-- ================== font awesome =================== -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                            <div class="custom-select-trigger" onclick="toggleCustomSelect('target')">
                                <span id="target-selected-text"><?= FORM_TARGETS[$form['FormTarget']]['name'] ?? 'اختر' ?></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-options" id="target-options">
                                <?php foreach (FORM_TARGETS as $key => $target): ?>
                                    <div class="custom-option <?= $key === $form['FormTarget'] ? 'selected' : '' ?>" data-value="<?= $key ?>" data-db-id="<?= $target['id'] ?>" onclick="selectOption('target', '<?= $key ?>', '<?= $target['name'] ?>', this)">
                                        <span><?= $target['name'] ?></span>
                                        <button type="button" class="btn-delete-option" onclick="event.stopPropagation(); deleteType('target', '<?= $target['id'] ?>', '<?= $key ?>')" title="حذف">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                                <div class="custom-option-add" onclick="openTypeModal('target')">
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
                            <div class="custom-select-trigger" onclick="toggleCustomSelect('type')">
                                <span id="type-selected-text"><?= FORM_TYPES[$form['FormType']]['name'] ?? 'اختر' ?></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-options" id="type-options">
                                <?php foreach (FORM_TYPES as $key => $type): ?>
                                    <div class="custom-option <?= $key === $form['FormType'] ? 'selected' : '' ?>" 
                                         data-value="<?= $key ?>" 
                                         data-db-id="<?= $type['id'] ?>" 
                                         data-targets="<?= implode(',', $type['allowed_targets']) ?>"
                                         onclick="selectOption('type', '<?= $key ?>', '<?= $type['name'] ?>', this)">
                                        <span><?= $type['name'] ?></span>
                                        <button type="button" class="btn-delete-option" onclick="event.stopPropagation(); deleteType('type', '<?= $type['id'] ?>', '<?= $key ?>')" title="حذف">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                                <div class="custom-option-add" onclick="openTypeModal('type')">
                                    <i class="fa-solid fa-plus"></i> إضافة جديد
                                </div>
                            </div>
                            <input type="hidden" id="form-type-select" value="<?= $form['FormType'] ?>" data-current-type="<?= $form['FormType'] ?>">
                        </div>
                    </div>

                    </div> <!-- Close control-buttons -->

                    <div style="display: flex; gap: 10px;">
                        <button class="access-settings-btn" onclick="openAccessModal()">
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
                // Add additional parameters based on form target
                if ($form['FormTarget'] === 'student') {
                    $evalLink .= "&Semester={Semester}&IDStudent={IDStudent}";
                    if (in_array($form['FormType'], ['teacher_evaluation', 'course_evaluation'])) {
                        $evalLink .= "&IDCourse={IDCourse}&IDGroup={IDGroup}";
                    }
                }
                ?>
                <input type="text" class="evaluation-link-input" value="<?php echo htmlspecialchars($evalLink); ?>" readonly>
                <button class="copy-link-btn" onclick="copyEvaluationLink(this)" title="نسخ الرابط">
                    <i class="fa-solid fa-copy"></i>
                    <span>نسخ</span>
                </button>
            </div>
            <p class="link-note">
                <i class="fa-solid fa-info-circle"></i>
                هذا الرابط يمكن مشاركته مع المقيمين للمشاركة في التقييم. 
                <?php if ($form['FormTarget'] === 'student'): ?>
                    <br>ملاحظة: يجب استبدال المتغيرات {Semester}, {IDStudent}, {IDCourse}, {IDGroup} بالقيم الفعلية.
                <?php endif; ?>
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
    <div id="typeModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background: white; margin: 10% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; position: relative;">
            <span class="close" onclick="closeTypeModal()" style="position: absolute; left: 15px; top: 10px; font-size: 24px; cursor: pointer;">&times;</span>
            <h2 id="modalTitle" style="margin-bottom: 20px;">إضافة عنصر جديد</h2>
            <form id="typeForm">
                <input type="hidden" id="typeCategory" name="category"> <!-- 'target' or 'type' -->
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">الاسم (بالعربية)</label>
                    <input type="text" id="typeName" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">المعرف (Slug - إنجليزي)</label>
                    <input type="text" id="typeSlug" name="slug" required placeholder="example_slug" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">الأيقونة</label>
                    <div class="icon-selector" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                        <!-- Icons will be injected here -->
                    </div>
                    <input type="hidden" id="typeIcon" name="icon" required>
                </div>

                    <select name="type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="text">نص (Text)</option>
                        <option value="number">رقم (Number)</option>
                        <option value="email">بريد إلكتروني (Email)</option>
                        <option value="date">تاريخ (Date)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="required" value="1">
                        حقل إجباري
                    </label>
                </div>

                <button type="submit" style="width: 100%; background: #4CAF50; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer;">إضافة</button>
            </form>
        </div>
    </div>

    <!-- Access Control Modal -->
    <div id="accessControlModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
        <div class="access-modal-content">
            <span class="close" onclick="closeAccessModal()" style="float: left; font-size: 24px; cursor: pointer;">&times;</span>
            <h2 style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">إعدادات الوصول</h2>
            
            <!-- Section 1: Password -->
            <div class="access-section">
                <h3>1. كلمة المرور</h3>
                <div class="access-input-group">
                    <label>كلمة مرور النموذج (اختياري)</label>
                    <input type="password" id="access-form-password" placeholder="اتركه فارغاً للوصول العام">
                    <small style="color: #666;">إذا تم تعيين كلمة مرور، سيطلب من المستخدم إدخالها قبل عرض النموذج.</small>
                </div>
            </div>

            <!-- Section 2: Registration Fields -->
            <div class="access-section">
                <h3>2. بيانات التسجيل المطلوبة</h3>
                <p style="margin-bottom: 10px; color: #666; font-size: 14px;">حدد البيانات التي يجب على المستخدم إدخالها قبل البدء.</p>
                
                <table class="fields-table">
                    <thead>
                        <tr>
                            <th>اسم الحقل</th>
                            <th style="width: 150px;">النوع</th>
                            <th style="width: 100px;">إجباري؟</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="access-fields-tbody">
                        <!-- Rows will be added here dynamically -->
                    </tbody>
                </table>
                
                <button type="button" class="btn-add-row" onclick="addAccessFieldRow()">
                    <i class="fa-solid fa-plus"></i> إضافة حقل جديد
                </button>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAccessModal()">إلغاء</button>
                <button type="button" class="btn-save-access" onclick="saveAccessSettings()">حفظ التغييرات</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/printThis/1.15.0/printThis.min.js"></script>
    <script src="../scripts/forms.js"></script>
    <script>
    // Access Control Modal Functions
    let currentAccessFields = [];

    function openAccessModal() {
        // Fetch current password
        const currentPassword = "<?php echo htmlspecialchars($form['password'] ?? '', ENT_QUOTES); ?>"; // Initial value from PHP
        // Note: For dynamic updates without reload, we might need to store this in a global var or data attribute
        // For now, we use the PHP value. If user saved previously via AJAX, we should update this.
        // Better: Read from a hidden input or data attribute on the page if we want it to persist without reload.
        // Let's rely on the page state.
        
        document.getElementById('access-form-password').value = currentPassword;

        // Fetch current fields
        // We can parse them from the PHP-generated list if it existed, but we removed it.
        // So we should fetch them via AJAX or store them in a JS variable on load.
        // Let's fetch them via AJAX to be sure.
        
        const formId = <?php echo $form['ID']; ?>;
        
        // Show loading state?
        const tbody = document.getElementById('access-fields-tbody');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">جاري التحميل...</td></tr>';
        document.getElementById('accessControlModal').style.display = 'block';

        fetch(`edit-form.php?id=${formId}&action=get_access_data`) // We need to implement this action!
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('access-form-password').value = data.password;
                    currentAccessFields = data.fields;
                    renderAccessFields();
                } else {
                    alert('فشل تحميل البيانات');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">خطأ في التحميل</td></tr>';
            });
    }

    function closeAccessModal() {
        document.getElementById('accessControlModal').style.display = 'none';
    }

    function renderAccessFields() {
        const tbody = document.getElementById('access-fields-tbody');
        tbody.innerHTML = '';
        
        currentAccessFields.forEach((field, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" value="${field.Label}" onchange="updateField(${index}, 'Label', this.value)" placeholder="مثال: الاسم الثلاثي"></td>
                <td>
                    <select onchange="updateField(${index}, 'FieldType', this.value)">
                        <option value="text" ${field.FieldType === 'text' ? 'selected' : ''}>نص</option>
                        <option value="number" ${field.FieldType === 'number' ? 'selected' : ''}>رقم</option>
                        <option value="email" ${field.FieldType === 'email' ? 'selected' : ''}>بريد إلكتروني</option>
                        <option value="date" ${field.FieldType === 'date' ? 'selected' : ''}>تاريخ</option>
                        <option value="password" ${field.FieldType === 'password' ? 'selected' : ''}>كلمة مرور</option>
                    </select>
                </td>
                <td style="text-align: center;">
                    <input type="checkbox" ${field.IsRequired == 1 ? 'checked' : ''} onchange="updateField(${index}, 'IsRequired', this.checked ? 1 : 0)" style="width: auto;">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-remove-row" onclick="removeAccessFieldRow(${index})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    function addAccessFieldRow() {
        currentAccessFields.push({
            ID: null, // New field
            Label: '',
            FieldType: 'text',
            IsRequired: 1
        });
        renderAccessFields();
    }

    function removeAccessFieldRow(index) {
        currentAccessFields.splice(index, 1);
        renderAccessFields();
    }

    function updateField(index, key, value) {
        currentAccessFields[index][key] = value;
    }

    function saveAccessSettings() {
        const password = document.getElementById('access-form-password').value;
        const formId = <?php echo $form['ID']; ?>;
        
        // Validate fields
        for (let field of currentAccessFields) {
            if (!field.Label.trim()) {
                alert('يرجى إدخال اسم لجميع الحقول');
                return;
            }
        }

        const formData = new FormData();
        formData.append('action', 'save_access_settings');
        formData.append('form_id', formId);
        formData.append('password', password);
        formData.append('fields', JSON.stringify(currentAccessFields));

        fetch('edit-form.php?id=' + formId, { // Post to same file
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('تم حفظ الإعدادات بنجاح');
                closeAccessModal();
                // Optionally reload or update UI indicators
            } else {
                alert('حدث خطأ: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال');
        });
    }

    // Function to copy evaluation link
    function copyEvaluationLink(button) {
        const input = button.parentElement.querySelector('.evaluation-link-input');
        input.select();
        document.execCommand('copy');
        
        // Visual feedback
        const originalHtml = button.innerHTML;
        button.classList.add('copied');
        button.innerHTML = '<i class="fa-solid fa-check"></i> <span>تم النسخ</span>';
        
        setTimeout(() => {
            button.classList.remove('copied');
            button.innerHTML = originalHtml;
        }, 2000);
    }
    </script>
    <script>
        const TYPE_QUESTION = <?php echo json_encode(TYPE_QUESTION); ?>;
    </script>
    
    <script src="../scripts/lib/utils.js"></script>
    <script src="../scripts/forms.js"></script>
    <script>
        // Type Management Logic
        const iconsList = [
            'assets/icons/college.png',
            'assets/icons/user.svg',
            'assets/icons/users.svg',
            'assets/icons/star.svg',
            'assets/icons/file-text.svg',
            'assets/icons/clipboard.svg',
            'assets/icons/check-circle.svg',
            'assets/icons/calendar.svg'
        ];

        function openTypeModal(category) {
            document.getElementById('typeCategory').value = category;
            document.getElementById('modalTitle').textContent = category === 'target' ? 'إضافة مقيّم جديد' : 'إضافة نوع تقييم جديد';
            document.getElementById('typeForm').reset();
            
            // Populate icons
            const iconContainer = document.querySelector('.icon-selector');
            iconContainer.innerHTML = '';
            iconsList.forEach(icon => {
                const div = document.createElement('div');
                div.className = 'icon-option';
                div.style.cursor = 'pointer';
                div.style.padding = '5px';
                div.style.textAlign = 'center';
                div.style.border = '1px solid transparent';
                div.innerHTML = `<img src="../${icon}" style="width: 24px; height: 24px;">`;
                div.onclick = function() {
                    document.querySelectorAll('.icon-option').forEach(el => el.style.borderColor = 'transparent');
                    div.style.borderColor = '#2196F3';
                    document.getElementById('typeIcon').value = icon;
                };
                iconContainer.appendChild(div);
            });
            
            document.getElementById('typeModal').style.display = 'block';
        }

        function closeTypeModal() {
            document.getElementById('typeModal').style.display = 'none';
        }

        document.getElementById('typeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const category = document.getElementById('typeCategory').value;
            const formData = new FormData(this);
            formData.append('action', 'create_type');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('تم الإضافة بنجاح');
                    location.reload(); 
                } else {
                    alert('خطأ: ' + data.message);
                }
            })
            .catch(err => alert('حدث خطأ في الاتصال'));
        });

        // Custom Select Logic
        function toggleCustomSelect(type) {
            const options = document.getElementById(type + '-options');
            const isOpen = options.classList.contains('open');
            
            // Close all other selects
            document.querySelectorAll('.custom-select-options').forEach(el => el.classList.remove('open'));
            
            if (!isOpen) {
                options.classList.add('open');
            }
        }

        function selectOption(type, value, name, element) {
            // Update hidden input
            const inputId = type === 'target' ? 'form-target-select' : 'form-type-select';
            document.getElementById(inputId).value = value;
            
            // Update display text
            document.getElementById(type + '-selected-text').textContent = name;
            
            // Update selected class
            const wrapper = document.getElementById(type + '-select-wrapper');
            wrapper.querySelectorAll('.custom-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            
            // Close dropdown
            document.getElementById(type + '-options').classList.remove('open');
            
            // Trigger change event if needed (for saving)
            updateFormSetting(type === 'target' ? 'FormTarget' : 'FormType', value);
        }

        // Close selects when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.custom-select-wrapper')) {
                document.querySelectorAll('.custom-select-options').forEach(el => el.classList.remove('open'));
            }
        });

        // Type Management Logic
        const iconsList = [
            'assets/icons/college.png',
            'assets/icons/user.svg',
            'assets/icons/users.svg',
            'assets/icons/star.svg',
            'assets/icons/file-text.svg',
            'assets/icons/clipboard.svg',
            'assets/icons/check-circle.svg',
            'assets/icons/calendar.svg'
        ];

        function openTypeModal(category) {
            document.getElementById('typeCategory').value = category;
            document.getElementById('modalTitle').textContent = category === 'target' ? 'إضافة مقيّم جديد' : 'إضافة نوع تقييم جديد';
            document.getElementById('typeForm').reset();
            
            // Populate icons
            const iconContainer = document.querySelector('.icon-selector');
            iconContainer.innerHTML = '';
            iconsList.forEach(icon => {
                const div = document.createElement('div');
                div.className = 'icon-option';
                div.style.cursor = 'pointer';
                div.style.padding = '5px';
                div.style.textAlign = 'center';
                div.style.border = '1px solid transparent';
                div.innerHTML = `<img src="../${icon}" style="width: 24px; height: 24px;">`;
                div.onclick = function() {
                    document.querySelectorAll('.icon-option').forEach(el => el.style.borderColor = 'transparent');
                    div.style.borderColor = '#2196F3';
                    document.getElementById('typeIcon').value = icon;
                };
                iconContainer.appendChild(div);
            });
            
            document.getElementById('typeModal').style.display = 'block';
        }

        function closeTypeModal() {
            document.getElementById('typeModal').style.display = 'none';
        }

        document.getElementById('typeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const category = document.getElementById('typeCategory').value;
            const formData = new FormData(this);
            formData.append('action', 'create_type');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('تم الإضافة بنجاح');
                    location.reload(); 
                } else {
                    alert('خطأ: ' + data.message);
                }
            })
            .catch(err => alert('حدث خطأ في الاتصال'));
        });

        function deleteType(category, id, slug) {
            if (!id) {
                alert('لا يمكن حذف هذا العنصر');
                return;
            }

            if (!confirm('هل أنت متأكد من الحذف؟')) return;

            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    action: 'delete_type',
                    id: id,
                    category: category
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('تم الحذف بنجاح');
                    location.reload();
                } else {
                    alert('خطأ: ' + data.message);
                }
            });
        }

        // Password Update
        function updatePassword() {
            const password = document.getElementById('form-password').value;
            const formData = new FormData();
            formData.append('action', 'update_password');
            formData.append('form_id', <?= $formId ?>);
            formData.append('password', password);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('تم حفظ كلمة المرور');
                } else {
                    alert('خطأ: ' + data.message);
                }
            })
            .catch(err => alert('حدث خطأ'));
        }

        // Field Modal Logic
        function openFieldModal() {
            document.getElementById('fieldModal').style.display = 'block';
            document.getElementById('new-field-label').value = '';
            document.getElementById('new-field-type').value = 'text';
            document.getElementById('new-field-required').checked = false;
        }

        function addField() {
            const label = document.getElementById('new-field-label').value;
            const type = document.getElementById('new-field-type').value;
            const required = document.getElementById('new-field-required').checked ? 1 : 0;
            
            if (!label) {
                alert('يرجى إدخال اسم الحقل');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_field');
            formData.append('form_id', <?= $formId ?>);
            formData.append('label', label);
            formData.append('type', type);
            formData.append('required', required);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('خطأ: ' + data.message);
                }
            });
        }

        // Delete Field
        function deleteField(id) {
            if(!confirm('حذف هذا الحقل؟')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_field');
            formData.append('field_id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('خطأ: ' + data.message);
                }
            });
        }
    </script>

</body>

</html>
