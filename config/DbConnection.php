<?php
if (file_exists(__DIR__ . '/local_config.php')) {
    include_once __DIR__ . '/local_config.php';
}

$host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : "cit.edu.ly");
$port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306);
$username = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : "citcoder_Citgate");
$password = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : "Cit9078563412");
$dbname = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : "citcoder_Quality");

// Create a database connection
$con = mysqli_connect($host, $username, $password, $dbname, $port);

if (mysqli_connect_errno()) {
    echo "لا يمكن الاتصال بقاعدة البيانات: " . mysqli_connect_error();
}

mysqli_set_charset($con, 'utf8');
?>