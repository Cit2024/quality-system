<?php
$host = "cit.edu.ly";
$username  = "citcoder_Citgate";
$password = "Cit9078563412";
$dbname = "citcoder_Citgate";

//Creating a connection
$conn_cit = mysqli_connect($host, $username, $password, $dbname);

if (mysqli_connect_errno()) {
  echo "لا بمكن الاتصال بقاعدة البيانات" . mysqli_connect_error();
}

mysqli_set_charset($conn_cit, 'utf8');
