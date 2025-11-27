<?php
// includes/header.php

// Check if either admin or teacher is logged in
$isAdmin = isset($_SESSION['admin_id']);
$isTeacher = isset($_SESSION['teacher_id']);

if (!$isAdmin && !$isTeacher) {
    header("Location: login.php");
    exit();
}

// Include the database connection
include 'config/dbConnectionCit.php';

// Get current semester with proper error handling
$sql = "SELECT ZamanNo, ZamanName FROM `zaman` WHERE ZamanNo = (SELECT MAX(ZamanNo) FROM zaman)";
$result = mysqli_query($conn_cit, $sql);

if (!$result) {
    die("Database query failed: " . mysqli_error($conn_cit));
}

$semester = mysqli_fetch_assoc($result) ?? ['ZamanNo' => 0, 'ZamanName' => 'No Active Semester'];

// Function to execute a prepared statement and return the result
function executePreparedStatement($con, $query, $param)
{
    $stmt = mysqli_prepare($con, $query);
    if (!$stmt) {
        die("Failed to prepare the SQL statement: " . mysqli_error($con));
    }
    mysqli_stmt_bind_param($stmt, "i", $param);
    if (!mysqli_stmt_execute($stmt)) {
        die("Failed to execute the SQL statement: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_bind_result($stmt, $result);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="./assets/icons/college.png">
    <link rel="stylesheet" href="./styles/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <?php if ($isAdmin) : ?>
            <?php include 'navigation.php'; ?>
        <?php else : ?>
            <?php include 'teacher_navigation.php'; ?>
        <?php endif; ?>