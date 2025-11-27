<?php

session_start();

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
    <style>
        .message-box img {
            filter: 
                <?php echo $success ? 'brightness(0) saturate(100%) invert(56%) sepia(91%) saturate(340%) hue-rotate(81deg) brightness(95%) contrast(86%)' 
                                       : 'brightness(0) saturate(100%) invert(37%) sepia(93%) saturate(748%) hue-rotate(331deg) brightness(97%) contrast(89%)'; ?>;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="message-box">
            <?php if ($success): ?>
                <img src="../assets/icons/circle-check-solid.svg" alt="Success" width="100">
                <h1 style="color: #4CAF50;">تم التقييم بنجاح</h1>
                <p>شكراً لك على مشاركتك في التقييم</p>
            <?php else: ?>
                <img src="../assets/icons/triangle-exclamation-solid.svg" alt="Error" width="100">
                <h1 style="color: #F44336;">حدث خطأ</h1>
                <p>لم يتم حفظ التقييم، يرجى المحاولة مرة أخرى</p>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($return_path) ?>" class="submit-button" style="text-decoration: none;">حسناً </a>
        </div>
    </div>
</body>
</html>