<?php
session_start();

// Xóa toàn bộ session
$_SESSION = [];
session_destroy();

// Chuyển về trang đăng nhập
header('Location: ../auth/login.php');
exit;
?>