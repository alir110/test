<?php
include 'config.php';

function logNotification($message) {
    global $conn;
    
    // چک کردن اتصال به دیتابیس
    if (!$conn) {
        file_put_contents('api_log.txt', "خطا: اتصال به دیتابیس برقرار نیست\n", FILE_APPEND);
        return false;
    }

    $query = "INSERT INTO notifications (message, is_read, created_at) VALUES (?, 0, NOW())";
    $stmt = $conn->prepare($query);
    
    // چک کردن اینکه prepare موفق بوده یا نه
    if ($stmt === false) {
        file_put_contents('api_log.txt', "خطا در آماده‌سازی کوئری logNotification: " . $conn->error . "\n", FILE_APPEND);
        return false;
    }

    $stmt->bind_param("s", $message);
    if (!$stmt->execute()) {
        file_put_contents('api_log.txt', "خطا در اجرای کوئری logNotification: " . $stmt->error . "\n", FILE_APPEND);
        $stmt->close();
        return false;
    }

    file_put_contents('api_log.txt', "اعلان ثبت شد: $message\n", FILE_APPEND);
    $stmt->close();
    return true;
}
?>