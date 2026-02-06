<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../helpers/icons.php';

$success = isset($_GET['success']) && $_GET['success'] == 1;

$return_path = filter_var(
    isset($_GET['path']) ? urldecode($_GET['path']) : '',
    FILTER_VALIDATE_URL
) ?: "https://www.google.com/";

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/college.png">
    <title><?php echo $success ? 'شكراً لك' : 'حدث خطأ'; ?></title>
    <link rel="stylesheet" href="../styles/evaluation-form.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message-box i {
            font-size: 100px;
            margin-bottom: 20px;
            display: block;
        }
        .message-box i.fa-circle-check {
            color: #4CAF50;
        }
        .message-box i.fa-triangle-exclamation {
            color: #F44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="message-box">
            <?php if ($success): ?>
                <?php echo iconCheck(); ?>
                <h1 style="color: #4CAF50;">تم التقييم بنجاح</h1>
                <p>شكراً لك على مشاركتك في التقييم</p>
            <?php else: ?>
                <?php echo iconWarning(); ?>
                <h1 style="color: #F44336;">حدث خطأ</h1>
                <p>لم يتم حفظ التقييم، يرجى المحاولة مرة أخرى</p>
                <?php if (isset($_GET['error'])): ?>
                    <p style="color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 0.9em;">
                        تفاصيل الخطأ: <?php echo htmlspecialchars($_GET['error']); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($return_path) ?>" class="submit-button" style="text-decoration: none;">حسناً </a>
        </div>
    </div>
</body>
</html>