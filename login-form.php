<?php
require_once __DIR__ . '/config/session.php';
require_once 'config/DbConnection.php';
require_once 'helpers/csrf.php';
require_once 'helpers/error_handler.php';

try {

$formId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$evaluator = $_GET['Evaluator'] ?? '';
$evaluation = $_GET['evaluation'] ?? '';

if (!$formId) {
    throw new ValidationException("Invalid Form ID");
}

// Fetch Form Details (Password)
$stmt = $con->prepare("SELECT Title, password FROM Form WHERE ID = ?");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$form) {
    throw new NotFoundException("Form not found");
}

// Fetch Access Fields
$stmt = $con->prepare("SELECT * FROM FormAccessFields WHERE FormID = ? ORDER BY OrderIndex ASC");
$stmt->bind_param("i", $formId);
$stmt->execute();
$fields_result = $stmt->get_result();
$fields = [];
while ($row = $fields_result->fetch_assoc()) {
    $fields[] = $row;
}
$stmt->close();

// Handle Submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();
    $valid = true;
    $redirectParams = [];
    
    // 1. Check Password
    if (!empty($form['password'])) {
        $inputPassword = $_POST['form_password'] ?? '';
        if ($inputPassword !== $form['password']) {
            $valid = false;
            $error = 'كلمة المرور غير صحيحة';
        }
    }
    
    // 2. Check Fields
    if ($valid) {
        foreach ($fields as $field) {
            $val = trim($_POST['field_' . $field['ID']] ?? '');
            if ($field['IsRequired'] && empty($val)) {
                $valid = false;
                $error = 'جميع الحقول المطلوبة يجب ملؤها';
                break;
            }
            // Add to redirect params
            // We use the Slug as the key for the URL param to ensure it matches what evaluation-form expects
            $paramKey = !empty($field['Slug']) ? $field['Slug'] : $field['Label'];
            $redirectParams[$paramKey] = $val;
        }
    }
    
    if ($valid) {
        // Store field values in session using Slug naming
        foreach ($fields as $field) {
            $val = trim($_POST['field_' . $field['ID']] ?? '');
            $slug = !empty($field['Slug']) ? $field['Slug'] : $field['Label'];
            
            // Store in session for evaluation-form to find
            if (!empty($val)) {
                $_SESSION[$slug] = $val;
            }
        }
        
        // Build Redirect URL
        $params = [
            'evaluation' => $evaluation,
            'Evaluator' => $evaluator,
        ];
        
        // Merge with existing GET params and collected redirect params
        foreach ($fields as $field) {
            $val = trim($_POST['field_' . $field['ID']] ?? '');
            $slug = !empty($field['Slug']) ? $field['Slug'] : $field['Label'];
            if (!empty($val)) {
                $params[$slug] = $val;
            }
        }
        $params = array_merge($_GET, $params);
        
        // Remove internal params
        unset($params['id']); 
        
        // Mark as authorized
        $_SESSION['form_auth_' . $formId] = true;
        
        $queryString = http_build_query($params);
        header("Location: evaluation-form.php?" . $queryString);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['Title']) ?> - تسجيل الدخول</title>
    <link rel="stylesheet" href="styles/forms.css">
    <link rel="stylesheet" href="components/ComponentsStyles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Almarai', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            z-index: 0;
        }
        
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Logo Container */
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease-out 0.2s backwards;
        }
        
        .logo-container img {
            max-width: 100px;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }
        
        .logo-container img:hover {
            transform: scale(1.05);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Title */
        h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
            color: #2c3e50;
            font-weight: 600;
            animation: fadeIn 0.8s ease-out 0.3s backwards;
        }
        
        /* Error Message */
        .error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(238, 90, 111, 0.3);
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        /* Form Group */
        .form-group {
            margin-bottom: 24px;
            animation: fadeIn 0.8s ease-out 0.4s backwards;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .form-group label .required {
            color: #e74c3c;
            margin-left: 4px;
        }
        
        /* Input Container */
        .input-container {
            position: relative;
        }
        
        .input-container i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            transition: color 0.3s ease;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 45px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            color: #2c3e50;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input:focus + i {
            color: #667eea;
        }
        
        .form-group input::placeholder {
            color: #95a5a6;
        }
        
        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out 0.5s backwards;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e0e6ed;
            color: #7f8c8d;
            font-size: 13px;
            animation: fadeIn 0.8s ease-out 0.6s backwards;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- College Logo -->
        <div class="logo-container">
            <img src="assets/icons/college.png" alt="شعار الكلية">
        </div>
        
        <h2><?= htmlspecialchars($form['Title']) ?></h2>
        
        <?php if($error): ?>
            <div class="error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php if(!empty($form['password'])): ?>
                <div class="form-group">
                    <label>
                        كلمة المرور
                        <span class="required">*</span>
                    </label>
                    <div class="input-container">
                        <input type="password" name="form_password" required placeholder="أدخل كلمة المرور">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>
            <?php endif; ?>
            
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <?php foreach($fields as $field): ?>
                <div class="form-group">
                    <label>
                        <?= htmlspecialchars($field['Label']) ?>
                        <?php if($field['IsRequired']): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                    <div class="input-container">
                        <input type="<?= $field['FieldType'] ?>" 
                               name="field_<?= $field['ID'] ?>" 
                               value="<?= isset($field['Slug']) && isset($_GET[$field['Slug']]) ? htmlspecialchars($_GET[$field['Slug']]) : '' ?>"
                               placeholder="أدخل <?= htmlspecialchars($field['Label']) ?>"
                               <?= $field['IsRequired'] ? 'required' : '' ?>>
                        <i class="fa-solid fa-<?= $field['FieldType'] === 'email' ? 'envelope' : 'user' ?>"></i>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-arrow-left" style="margin-left: 8px;"></i>
                دخول
            </button>
        </form>
        
        <div class="login-footer">
            <i class="fa-solid fa-shield-halved"></i>
            محمي وآمن
        </div>
    </div>
</body>
</html>
<?php
} catch (Exception $e) {
    handleException($e);
}
?>
