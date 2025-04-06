<?php
include 'config.php';

header('Content-Type: application/json');

$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';

$valid_statuses = ['در دست انجام', 'انجام شده', 'لغو شده'];

if ($task_id <= 0 || !in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است.']);
    exit;
}

try {
    $query = "UPDATE reminders SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $new_status, $task_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'وضعیت با موفقیت تغییر کرد.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هیچ تغییری اعمال نشد.']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطا در تغییر وضعیت: ' . $e->getMessage()]);
}
?>