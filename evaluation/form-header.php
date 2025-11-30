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
            <?php if($TypeEvaluation === 'course_evaluation'): ?>
                <div class="course-meta">
                    <h3><?= htmlspecialchars($course_info['MadaName'] ?? '') ?></h3>
                    <p>رمز المقرر: <?= htmlspecialchars($IDCourse) ?></p>
                    <p>الفصل الدارسي: <?= htmlspecialchars($semester_name['ZamanName'] ?? '') ?></p>
                </div>
            <?php elseif($TypeEvaluation === 'teacher_evaluation'): ?>
                <div class="teacher-info">
                    <h3>مدرس المقرر: <?= htmlspecialchars($teacher_name['name'] ?? '') ?></h3>
                    <p>المعدل التراكمي: <?= $GPA['gpa'] ?? '0' ?>%</p>
                </div>
            <?php endif; ?>
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
