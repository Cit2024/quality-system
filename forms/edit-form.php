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
                       <div class="form-target-dropdown">
                          <select id="form-target-select">
                            <?php foreach (FORM_TARGETS as $key => $target): ?>
                              <option value="<?= $key ?>" 
                                      <?= $key === $form['FormTarget'] ? 'selected' : '' ?>>
                                <img src="../<?= $target['icon'] ?>" alt="<?= $key ?>"/>
                                <?= $target['name'] ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                    </div>

                    <!-- Form Type Control -->
                    <div class="form-status-row">
                        <span>نوع التقييم : </span>
                        <div class="form-type-dropdown">
                          <select id="form-type-select" data-current-type="<?= $form['FormType'] ?>">
                            <?php foreach (FORM_TYPES as $key => $type): ?>
                              <option value="<?= $key ?>" 
                                      data-targets="<?= implode(',', $type['allowed_targets']) ?>"
                                      <?= $key === $form['FormType'] ? 'selected' : '' ?>>
                                <img src="../<?= $type['icon'] ?>" alt="<?= $key ?>"/>
                                <?= $type['name'] ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                    </div>

                </div>

                <!-- button download form -->
                <button class="download-form-button" data-printthis-ignore 
                    data-form-id="<?php echo $form['ID']; ?>"
                    data-form-title="<?php echo htmlspecialchars($form['Title'], ENT_QUOTES, 'UTF-8'); ?>">
                <img src=".././assets/icons/file-down.svg" alt="download" />
                تنزيل النموذج
            </button>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/printThis/1.15.0/printThis.min.js"></script>
    <script src="../scripts/forms.js"></script>
    <script>
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
</body>
        const TYPE_QUESTION = <?php echo json_encode(TYPE_QUESTION); ?>;
    </script>
    
    <script src="../scripts/lib/utils.js"></script>
    <script src="../scripts/forms.js"></script>

</body>

</html>