<?php
// pages/members/create-admin.php
session_start();

$currentPage = 'members'; // Set the current page for active link highlighting

// Include the database connection
include '.././config/DbConnection.php';


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate a random 6-character password (letters and numbers)
    $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);

    // Prepare the SQL query
    $username = 'cit_' . $_POST['username']; // Prefix the username with "cit_"
    $isCanCreate = isset($_POST['canCreate']) ? 1 : 0;
    $isCanDelete = isset($_POST['canDelete']) ? 1 : 0;
    $isCanUpdate = isset($_POST['canUpdate']) ? 1 : 0;
    $isCanRead = 1; // Fixed permission
    $isCanGetAnalysis = isset($_POST['canGetAnalysis']) ? 1 : 0;

    $query = "INSERT INTO Admin (username, password, isCanCreate, isCanDelete, isCanUpdate, isCanRead, isCanGetAnalysis) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'ssiiiii', $username, $password, $isCanCreate, $isCanDelete, $isCanUpdate, $isCanRead, $isCanGetAnalysis);

    if (mysqli_stmt_execute($stmt)) {
        $successMessage = "تم إنشاء المستخدم بنجاح!";
        $generatedUsername = $username;
        $generatedPassword = $password;
    } else {
        $errorMessage = "فشل إنشاء المستخدم. يرجى المحاولة مرة أخرى.";
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء مستخدم جديد</title>
    <link rel="stylesheet" href="../styles/members.css">
    <link rel="icon" href=".././assets/icons/college.png">

</head>

<body>
    <!-- =========================== Main Content ======================== -->
    <div class="main">
        <!-- Back Button -->
        <div class="back-button" onclick="window.location.href='../members.php'">
            <span>رجوع</span>
            <img src=".././assets/icons/chevron-right.svg" alt="Back" />
        </div>

        <!-- ==================== Create Admin Form ==================== -->
        <div class="form-container">
            <h1>إنشاء مستخدم جديد</h1>

            <?php if (isset($successMessage)) : ?>
                <div class="success-message">
                    <button onclick="copyCredentials('<?php echo $generatedUsername; ?>', '<?php echo $generatedPassword; ?>')">
                        نسخ بيانات الاعتماد
                    </button>
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($errorMessage)) : ?>
                <div class="error-message">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="container-input">

                    <!-- Username -->
                    <div class="group-input">
                        <label for="username">:اسم المستخدم</label>
                        <div class="username-input">
                            <span>cit_</span>
                            <input type="text" id="username" name="username" placeholder="Admin.."  required>
                            <button type="button" class="copy-button" onclick="copyUsername()">
                                <img src=".././assets/icons/copy.svg" alt="Copy Username">
                            </button>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="group-input">
                        <label for="password">:كلمة المرور</label>
                        <div class="password-input-container">
                            <input type="text" id="password" name="password" class="password-input" value="<?php echo isset($generatedPassword) ? $generatedPassword : ''; ?>" readonly>
                            <button type="button" class="regenerate-password-button" onclick="regeneratePassword()">
                                <img src=".././assets/icons/rotate-ccw.svg" alt="Regenerate Password">
                            </button>
                            <button type="button" class="copy-button" onclick="copyPassword()">
                                <img src=".././assets/icons/copy.svg" alt="Copy Password">
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Permissions -->
                <div class="permissions-group">
                    <label>:الصلاحيات</label>
                    <div class="permissions-container">
                        <label>
                            <input type="checkbox" name="canCreate">
                            إنشاء نماذج
                        </label>
                        <label>
                            <input type="checkbox" name="canDelete">
                            حذف نماذج
                        </label>
                        <label>
                            <input type="checkbox" name="canUpdate">
                            تعديل النماذج
                        </label>
                        <label>
                            <input type="checkbox" name="canRead" disabled checked>
                            الإطلاع علي نماذج
                        </label>
                        <label>
                            <input type="checkbox" name="canGetAnalysis">
                            الإطلاع علي الإحصائيات
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="submit-button">إنشاء مستخدم</button>
            </form>
        </div>
    </div>
    <script src=".././scripts/members.js"></script>
</body>

</html>