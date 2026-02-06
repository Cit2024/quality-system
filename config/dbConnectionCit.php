<?php
require_once __DIR__ . '/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../.env');

if (file_exists(__DIR__ . '/local_config.php')) {
    include_once __DIR__ . '/local_config.php';
}

$host = getenv('CIT_DB_HOST') ?: (getenv('DB_HOST') ?: (defined('CIT_DB_HOST') ? CIT_DB_HOST : "cit.edu.ly"));
$port = getenv('CIT_DB_PORT') ?: (getenv('DB_PORT') ?: (defined('CIT_DB_PORT') ? CIT_DB_PORT : 3306));
$username = getenv('CIT_DB_USER') ?: (getenv('DB_USER') ?: (defined('CIT_DB_USER') ? CIT_DB_USER : "citcoder_Citgate"));
$password = getenv('CIT_DB_PASS') ?: (getenv('DB_PASS') ?: (defined('CIT_DB_PASS') ? CIT_DB_PASS : "Cit9078563412"));
$dbname = getenv('CIT_DB_NAME') ?: (getenv('DB_NAME') ?: (defined('CIT_DB_NAME') ? CIT_DB_NAME : "citcoder_Citgate"));

//Creating a connection
$conn_cit = mysqli_connect($host, $username, $password, $dbname, $port);

if (mysqli_connect_errno()) {
  echo "لا بمكن الاتصال بقاعدة البيانات" . mysqli_connect_error();
}

mysqli_set_charset($conn_cit, 'utf8');
