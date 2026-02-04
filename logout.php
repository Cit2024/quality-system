<?php
// Start the session
require_once __DIR__ . '/config/session.php';

// Destroy the session securely
destroySession();

// Redirect to the login page
header("Location: login.php");

// Ensure no further code is executed after the redirect
exit();