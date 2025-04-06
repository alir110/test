<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';

    if (empty($title) || !in_array($type, ['سیستم‌ها', 'تیکت‌ها', 'وظایف'])) {
        echo json_encode(['status' => 'error', 'message' => 'فیلدهای الزامی را پر کنید.']);
        exit;
    }

    // فرض: تولید فایل گزارش (اینجا فقط یه نمونه ساده است، باید با کتابخونه‌ای مثل TCPDF پیاده‌سازی بشه)
    $file_path = "reports/" . time() . "_" . str_replace(' ', '_', $title) . ".pdf";
    // فرض: فایل PDF تولید شده و ذخیره شده
    try {
        $query = "INSERT INTO reports (title, type, file_path) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $title, $type, $file_path);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'گزارش با موفقیت تولید شد.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'خطا در تولید گزارش: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'درخواست نامعتبر است.']);
}
?>