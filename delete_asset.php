<?php 
include 'config.php'; // فایل تنظیمات دیتابیس

// دریافت شناسه اموال
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// دریافت مسیر تصویر برای حذف
$query = "SELECT image_path FROM assets WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$asset = $result->fetch_assoc();
$stmt->close();

if ($asset) {
    // حذف تصویر اگه وجود داشته باشه
    if (!empty($asset['image_path']) && file_exists($asset['image_path'])) {
        unlink($asset['image_path']);
    }

    // حذف اموال از دیتابیس
    $query = "DELETE FROM assets WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>alert('اموال با موفقیت حذف شد.'); window.location.href='assets_management.php';</script>";
    } else {
        echo "<script>alert('خطا در حذف اموال: " . addslashes($conn->error) . "');</script>";
    }
    $stmt->close();
} else {
    echo "<script>alert('اموال یافت نشد.'); window.location.href='assets_management.php';</script>";
}
?>