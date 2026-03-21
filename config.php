<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP mặc định không có password
define('DB_NAME', 'phone_store');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Set charset tiếng Việt
$conn->set_charset("utf8mb4");
?>