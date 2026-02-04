<?php
$host = "cit.edu.ly"; // Correct hostname
$port = 3306; // MySQL default port (optional, only if using a non-default port)
$username = "citcoder_Citgate"; // Your MySQL username
$password = "Cit9078563412"; // Your MySQL password
$dbname = "citcoder_Quality"; // Your database name

// Create a database connection
$con = mysqli_connect($host, $username, $password, $dbname, $port);

if (mysqli_connect_errno()) {
    echo "لا يمكن الاتصال بقاعدة البيانات: " . mysqli_connect_error();
}

mysqli_set_charset($con, 'utf8');
?>