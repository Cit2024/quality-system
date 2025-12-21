<?php
session_start();
require_once 'config/DbConnection.php';

$formId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$evaluator = $_GET['Evaluator'] ?? '';
$evaluation = $_GET['evaluation'] ?? '';

if (!$formId) {
    die("Invalid Form ID");
}

// Fetch Form Details (Password)
$stmt = $con->prepare("SELECT Title, password FROM Form WHERE ID = ?");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$form) {
    die("Form not found");
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
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-submit {
            width: 100%;
            padding: 10px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-submit:hover {
            background: #1976D2;
        }
        .error {
            color: red;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 style="text-align: center; margin-bottom: 20px;"><?= htmlspecialchars($form['Title']) ?></h2>
        
        <?php if($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php if(!empty($form['password'])): ?>
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <input type="password" name="form_password" required>
                </div>
            <?php endif; ?>

            <?php foreach($fields as $field): ?>
                <div class="form-group">
                    <label>
                        <?= htmlspecialchars($field['Label']) ?>
                        <?php if($field['IsRequired']): ?>
                            <span style="color: red;">*</span>
                        <?php endif; ?>
                    </label>
                    <input type="<?= $field['FieldType'] ?>" 
                           name="field_<?= $field['ID'] ?>" 
                           value="<?= isset($field['Slug']) && isset($_GET[$field['Slug']]) ? htmlspecialchars($_GET[$field['Slug']]) : '' ?>"
                           <?= $field['IsRequired'] ? 'required' : '' ?>>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-submit">دخول</button>
        </form>
    </div>
</body>
</html>
