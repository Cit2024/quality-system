<?php
require_once __DIR__ . '/../config/session.php';

// Include the database connection
include '.././config/DbConnection.php';
require_once '../helpers/csrf.php';
require_once '../helpers/permissions.php';

// Check if the admin is logged in and has permission to create forms
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Verify admin status and refresh permissions
if (!verifyAdminStatus($con)) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Require create permission
requirePermission($con, 'isCanCreate', '../dashboard.php');

// Include the database connection
include '.././config/DbConnection.php';
require_once '../helpers/csrf.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCSRFOrDie();
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $description = mysqli_real_escape_string($con, $_POST['description']);

    // Insert the form into the database with a default status of "draft"
    $query = "INSERT INTO Form (Title, Description, FormStatus, created_by) VALUES ('$title', '$description', 'draft', {$_SESSION['admin_id']})";
    if (mysqli_query($con, $query)) {
        // Redirect to the newly created form's edit page
        $formId = mysqli_insert_id($con); // Get the ID of the newly created form
        header("Location: edit-form.php?id=$formId");
        exit();
    } else {
        $error_message = "حدث خطأ أثناء إنشاء النموذج";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Form</title>
    <link rel="stylesheet" href="../styles/forms.css">
    <link rel="stylesheet" href=".././components/ComponentsStyles.css">
    <link rel="icon" href=".././assets/icons/college.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include "../components/header.html" ?>
    <div class="create-container">
        <!-- Back Button -->
        <div class="back-button" onclick="window.location.href='../forms.php'">
            <img src=".././assets/icons/chevron-right.svg" alt="Back" />
            <span>رجوع</span>
        </div>

        <!-- Form Creation Section -->
        <div class="form-details">
            <h1>إنشاء نموذج جديد</h1>

            <!-- Display error message if any -->
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Form Creation Form -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <!-- Form Title -->
                <div class="input-group">
                    <label for="title">عنوان النموذج:</label>
                    <input type="text" id="title" name="title" value="نموذج جديد" required />
                </div>

                <!-- Form Description -->
                <div class="input-group">
                    <label for="description">وصف النموذج:</label>
                    <textarea id="description" name="description" rows="4" required>وصف افتراضي للنموذج</textarea>
                </div>

                <!-- Submit Button -->
                <button type="submit">إنشاء النموذج</button>
            </form>
        </div>
    </div>
    <?php include "../components/footer.html" ?>
</body>
</html>