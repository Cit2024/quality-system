<?php
// pages/members.php
$currentPage = 'members'; // Set the current page for active link highlighting
session_start();
include './components/header.php';

// Include the database connection
require_once 'config/dbConnectionCit.php';
require_once 'config/DbConnection.php';

require_once 'helpers/database.php';


// Fetch all admins
$admins = fetchData($con, "SELECT * FROM Admin WHERE is_deleted = 0") ?: [];

// Fetch all teachers accounts
$teachers = fetchData($conn_cit, "SELECT * FROM  teachers_evaluation ") ?: [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأعضاء</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/members.css">
</head>

<body>
    <!-- =========================== Main Content ======================== -->
    <div class="main">
        <!-- ==================== Top Bar ==================== -->
        <div class="top-bar">
            <div class="toggle-button">
                <i class="fa-solid fa-bars"></i>
            </div>
            <div class="semester-info">
                <span><?php echo $semester['ZamanName']; ?></span>
            </div>
            <div class="user-profile">
                <img src="./assets/icons/circle-user-round.svg" alt="صورة المستخدم">
            </div>
        </div>

        <?php if ($_SESSION['username'] === "DrGabriel" && $_SESSION['password'] === "VRWZK1UG") : ?>
            <!-- ============================ Button Add new Admin ====================== -->
            <div class="add-admin-button-container">
                <div class="add-admin-button" onclick="window.location.href = './members/create-admin.php';">
                    <p class="add-admin-button-text">إضافة مستخدم</p>
                    <img src="./assets/icons/user-plus.svg" alt="إضافة">
                </div>
            </div>
            <div class="container">
                <!-- ==================== Admin Table ==================== -->
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>اسم المستخدم</th>
                                <th>كلمة المرور</th>
                                <th>الصلاحيات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin) : ?>
                                <?php if (!$admin['is_deleted']) : ?>
                                    <tr>
                                        <!-- Username -->
                                        <td>
                                            <div class="username-container">
                                                <span class="username"><?php echo htmlspecialchars($admin['username']); ?></span>
                                                <button class="copy-button" onclick="copyToClipboard('<?php echo htmlspecialchars($admin['username']); ?>')">
                                                    <img src="./assets/icons/copy.svg" alt="نسخ">
                                                </button>
                                            </div>
                                        </td>

                                        <!-- Password -->
                                        <td>
                                            <div class="password-container">
                                                <input type="password" class="password-input" value="<?php echo htmlspecialchars($admin['password']); ?>" readonly>
                                                <button class="toggle-password" onclick="togglePasswordVisibility(this)">
                                                    <img src="./assets/icons/eye-closed.svg" alt="إظهار كلمة المرور">
                                                </button>
                                                <button class="copy-button" onclick="copyToClipboard('<?php echo htmlspecialchars($admin['password']); ?>')">
                                                    <img src="./assets/icons/copy.svg" alt="نسخ">
                                                </button>
                                            </div>
                                        </td>

                                        <!-- Permissions -->
                                        <td>
                                            <div class="permissions-container">
                                                <label>
                                                    <input type="checkbox" name="canCreate" <?php echo $admin['isCanCreate'] ? 'checked' : ''; ?> onchange="updatePermission(<?php echo $admin['ID']; ?>, 'isCanCreate', this.checked)">
                                                    إنشاء نماذج
                                                </label>
                                                <label>
                                                    <input type="checkbox" name="canDelete" <?php echo $admin['isCanDelete'] ? 'checked' : ''; ?> onchange="updatePermission(<?php echo $admin['ID']; ?>, 'isCanDelete', this.checked)">
                                                    حذف نماذج
                                                </label>
                                                <label>
                                                    <input type="checkbox" name="canUpdate" <?php echo $admin['isCanUpdate'] ? 'checked' : ''; ?> onchange="updatePermission(<?php echo $admin['ID']; ?>, 'isCanUpdate', this.checked)">
                                                    تعديل النماذج
                                                </label>
                                                <label>
                                                    <input type="checkbox" name="canRead" <?php echo $admin['isCanRead'] ? 'checked' : ''; ?> disabled>
                                                    الإطلاع علي نماذج
                                                </label>
                                                <label>
                                                    <input type="checkbox" name="canGetAnalysis" <?php echo $admin['isCanGetAnalysis'] ? 'checked' : ''; ?> onchange="updatePermission(<?php echo $admin['ID']; ?>, 'isCanGetAnalysis', this.checked)">
                                                    الإطلاع علي الإحصائيات
                                                </label>
                                            </div>
                                        </td>

                                        <!-- Delete Button -->
                                        <td>
                                            <?php if ($admin['username'] !== "DrGabriel") : ?>
                                                <button class="delete-button" onclick="deleteAdmin(<?php echo $admin['ID']; ?>)">
                                                    <img src="./assets/icons/trash.svg" alt="حذف">
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- ==================== Teachers Table ==================== -->
                <div class="table-container">
                    <h2 class="table-title">قائمة المعلمين</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>اسم المستخدم</th>
                                <th>كلمة المرور</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher) : ?>
                                <tr>
                                    <!-- Name -->
                                    <td>
                                        <div class="name-container">
                                            <span class="name"><?php echo htmlspecialchars($teacher['name']); ?></span>
                                        </div>
                                    </td>
                                    <!-- Username -->
                                    <td>
                                        <div class="username-container">
                                            <span class="username"><?php echo htmlspecialchars($teacher['username']); ?></span>
                                            <button class="copy-button" onclick="copyToClipboard('<?php echo htmlspecialchars($teacher['username']); ?>')">
                                                <img src="./assets/icons/copy.svg" alt="نسخ">
                                            </button>
                                        </div>
                                    </td>
                                    <!-- Password -->
                                    <td>
                                        <div class="password-container">
                                            <input type="password" class="password-input" value="<?php echo htmlspecialchars($teacher['password']); ?>" readonly>
                                            <button class="toggle-password" onclick="togglePasswordVisibility(this)">
                                                <img src="./assets/icons/eye-closed.svg" alt="إظهار كلمة المرور">
                                            </button>
                                            <button class="copy-button" onclick="copyToClipboard('<?php echo htmlspecialchars($teacher['password']); ?>')">
                                                <img src="./assets/icons/copy.svg" alt="نسخ">
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="copy-button" onclick="copyUsernameAndPassword('<?php echo htmlspecialchars($teacher['name']); ?>', '<?php echo htmlspecialchars($teacher['username']); ?>', '<?php echo htmlspecialchars($teacher['password']); ?>')">
                                            <img src="./assets/icons/copy.svg" alt="نسخ">
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else : ?>
            <div class="no-permission">
                <p>لا تمتلك الصلاحية لعرض هذه الصفحة</p>
                <a href="./login.php">تسجيل كمشرف مصلح له</a>
            </div>
        <?php endif; ?>

        <?php include './components/footer.php'; ?>
    </div>

    <!-- ==================== JavaScript ==================== -->
    <script src="./scripts/members.js"></script>
</body>

</html>