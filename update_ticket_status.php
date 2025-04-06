<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($id <= 0 || !in_array($status, ['باز', 'در حال انجام', 'بسته‌شده'])) {
        echo json_encode(['status' => 'error', 'message' => 'ورودی‌ها نامعتبر هستند.']);
        exit;
    }

    try {
        $query = "UPDATE tickets SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'وضعیت تیکت به‌روزرسانی شد.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'خطا در به‌روزرسانی وضعیت تیکت: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'درخواست نامعتبر است.']);
}
?>