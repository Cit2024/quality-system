<?php
session_start();

// Include database connections
require_once 'config/dbConnectionCit.php'; // For tprofiles table
require_once 'config/DbConnection.php';    // For admin table

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $error_message = "";

    // Admin authentication with prepared statement
    $adminQuery = "SELECT * FROM Admin WHERE username = ? AND is_deleted = 0";
    $stmt = mysqli_prepare($con, $adminQuery);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $adminResult = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($adminResult) == 1) {
        $admin = mysqli_fetch_assoc($adminResult);

        // Verify password (plaintext comparison - INSECURE, needs hashing)
        if ($password === $admin['password']) {
            $_SESSION['admin_id'] = $admin['ID'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['password'] = $admin['password'];
            $_SESSION['permissions'] = [
                'isCanCreate' => (bool)$admin['isCanCreate'],
                'isCanDelete' => (bool)$admin['isCanDelete'],
                'isCanUpdate' => (bool)$admin['isCanUpdate'],
                'isCanRead' => (bool)$admin['isCanRead'],
                'isCanGetAnalysis' => (bool)$admin['isCanGetAnalysis']
            ];
            header("Location: dashboard.php");
            exit();
        }
    }

    // Teacher authentication with prepared statement
    $teacherQuery = "SELECT * FROM teachers_evaluation WHERE username  = ? AND password = ? ";
    $stmt = mysqli_prepare($conn_cit, $teacherQuery);
    mysqli_stmt_bind_param($stmt, "ss", $username, $password);
    mysqli_stmt_execute($stmt);
    $teacherResult = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($teacherResult) == 1) {
        $teacher = mysqli_fetch_assoc($teacherResult);

        // Verify password (plaintext comparison)
        $_SESSION['teacher_id'] = $teacher['id'];
        $_SESSION['teacher_name'] = $teacher['name'];
        $_SESSION['username'] = $username;
        $_SESSION['password'] = $teacher['password']; // Add this line
        
        header("Location: teacher_dashboard.php");
        exit();
    }

    $error_message = "اسم المستخدم أو كلمة المرور غير صحيحة";
    // Delay to prevent brute-force attacks
    sleep(2);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="styles/login.css">
    <link rel="icon" href="./assets/icons/college.png">
</head>

<body>
    <div class="root">
        <div class="login-container">
            <div>
                <img src="./assets/icons/college.png" alt="college" style="width: auto; height: 80px; object-fit: cover;" />
                <h2>تسجيل الدخول</h2>
            </div>
            <form action="login.php" method="POST" class="form">
                <div class="input-group">
                    <label for="username">: اسم المستخدم</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="input-group">
                    <label for="password">:كلمة المرور</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">تسجيل</button>
            </form>
            <?php if (isset($error_message)): ?>
                <p style="color: red; text-align: center;"><?php echo $error_message; ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>