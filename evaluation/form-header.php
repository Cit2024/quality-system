<?php
// quality-system/evaluation/form-header.php
if (!$form_exists) return;

require_once __DIR__ . "/../helpers/units.php";

// Always check if $row exists and contains KidNo
    if(isset($IDStudent)) {
        $GPA = calculateCumulativeGPA($IDStudent) ?? ['gpa' => 0];
    }

$formTypeName = FORM_TYPES[$TypeEvaluation]['name'];
$evaluatorName = FORM_TARGETS[$Evaluator]['name'];

?>

<div class="form-header">
    <?php if($Evaluator === 'student'): ?>
        <div class="student-header">
            <div class="meta-section">
                <?php if(isset($course_info)): ?>
                    <h3><?= htmlspecialchars($course_info['MadaName'] ?? '') ?> (<?= htmlspecialchars($IDCourse) ?>)</h3>
                <?php endif; ?>
                
                <div class="meta-grid">
                    <?php if(isset($semester_name)): ?>
                        <p><strong>الفصل الدراسي:</strong> <?= htmlspecialchars($semester_name['ZamanName'] ?? '') ?></p>
                    <?php endif; ?>

                    <?php if(isset($teacher_name)): ?>
                        <p><strong>مدرس المقرر:</strong> <?= htmlspecialchars($teacher_name['name'] ?? '') ?></p>
                    <?php endif; ?>

                    <?php if(isset($GPA) && !empty($GPA['gpa']) && $GPA['gpa'] > 0): ?>
                        <p><strong>المعدل التراكمي:</strong> <?= $GPA['gpa'] ?>%</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php elseif($Evaluator === 'alumni'): ?>
        <div class="alumni-header">
            <?php
                // Display alumni information from session (populated via form-login.php)
                $graduationYear = $_SESSION['GraduationYear'] ?? '';
                $job = $_SESSION['Job'] ?? '';
                $specialization = $_SESSION['Specialization'] ?? '';
                $graduateName = $_SESSION['GraduateName'] ?? '';
            ?>
            <?php if ($graduationYear): ?>
                <p><strong>سنة التخرج:</strong> <?= htmlspecialchars($graduationYear) ?></p>
            <?php endif; ?>
            <?php if ($job): ?>
                <p><strong>الوظيفة الحالية:</strong> <?= htmlspecialchars($job) ?></p>
            <?php endif; ?>
            <?php if ($specialization): ?>
                <p><strong>التخصص:</strong> <?= htmlspecialchars($specialization) ?></p>
            <?php endif; ?>
            <?php if ($graduateName): ?>
                <p><strong>اسم الخريج:</strong> <?= htmlspecialchars($graduateName) ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="default-header">
            <h2><?= $formTypeName ?></h2>
            <p class="evaluator-role"><?= $evaluatorName ?></p>
        </div>
    <?php endif; ?>
</div>
