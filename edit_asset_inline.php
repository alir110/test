<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $field = isset($_POST['field']) ? trim($_POST['field']) : '';
    $value = isset($_POST['value']) ? trim($_POST['value']) : '';

    $allowed_fields = ['asset_type', 'asset_number', 'model', 'department'];
    if ($id <= 0 || !in_array($field, $allowed_fields) || empty($value)) {
        echo json_encode(['status' => 'error', 'message' => 'ورودی نامعتبر']);
        exit;
    }

    $query = "UPDATE assets SET $field = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $value, $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'به‌روزرسانی موفق']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطا در به‌روزرسانی']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'درخواست نامعتبر']);
}
?>