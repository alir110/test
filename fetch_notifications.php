<?php
include 'config.php';

// فرض می‌کنیم یه جدول notifications داریم که اعلان‌های جدید رو ذخیره می‌کنه
// این جدول می‌تونه شامل ستون‌های id, message, created_at, is_read باشه

// گرفتن اعلان‌های جدید (خوانده‌نشده)
$query = "SELECT message FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);
$new_notifications = [];
while ($row = $result->fetch_assoc()) {
    $new_notifications[] = $row;
}

// علامت‌گذاری اعلان‌ها به‌عنوان خوانده‌شده
$query = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
$conn->query($query);

// خروجی JSON
echo json_encode([
    'new_notifications' => $new_notifications
]);
?>