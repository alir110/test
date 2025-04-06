<?php
// تنظیمات دیتابیس
$host = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'kcf6.alir110.ir') ? 'localhost' : '45.156.184.24';
$user = "uwzbjynn_KCF_1404";
$pass = "~*)kJk+]ry+y";
$db = "uwzbjynn_banafsheh";

// اتصال به دیتابیس
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    $error = "اتصال به دیتابیس ناموفق: " . $conn->connect_error;
    file_put_contents('error_log.txt', $error . "\n", FILE_APPEND);
    die($error);
}
$conn->set_charset("utf8mb4");
?>