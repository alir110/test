<?php
// فعال کردن نمایش خطاها برای دیباگ (در محیط توسعه)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'header.php';
include 'config.php'; // فایل تنظیمات دیتابیس

// بررسی وجود ID در URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('شناسه نامعتبر است.'); window.location.href='search.php';</script>";
    exit;
}

$id = (int)$_GET['id'];

// دریافت پارامترهای جستجو از URL برای بازگشت به صفحه جستجو
$search_params = [];
foreach (['ip', 'name', 'extension', 'department'] as $param) {
    if (isset($_GET[$param]) && !empty($_GET[$param])) {
        $search_params[$param] = trim($_GET[$param]);
    }
}
$back_url = 'search.php';
if (!empty($search_params)) {
    $back_url .= '?' . http_build_query($search_params);
}

// بررسی اینکه رکورد وجود دارد یا خیر
$query = "SELECT id FROM contact_info WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "<script>alert('رکورد مورد نظر یافت نشد.'); window.location.href='$back_url';</script>";
    $stmt->close();
    exit;
}
$stmt->close();

// شروع تراکنش برای حذف
$conn->begin_transaction();

try {
    // 1. حذف از جدول printers
    $stmt = $conn->prepare("DELETE FROM printers WHERE contact_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 2. حذف از جدول scanners
    $stmt = $conn->prepare("DELETE FROM scanners WHERE contact_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 3. حذف از جدول hardware_info
    $stmt = $conn->prepare("DELETE FROM hardware_info WHERE contact_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 4. حذف از جدول software_info
    $stmt = $conn->prepare("DELETE FROM software_info WHERE contact_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 5. حذف از جدول contact_info
    $stmt = $conn->prepare("DELETE FROM contact_info WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // تأیید تراکنش
    $conn->commit();

    // پیام موفقیت و بازگشت به صفحه جستجو
    echo "<script>alert('رکورد با موفقیت حذف شد.'); window.location.href='$back_url';</script>";
} catch (Exception $e) {
    // در صورت خطا، تراکنش را لغو می‌کنیم
    $conn->rollback();
    echo "<script>alert('خطا در حذف رکورد: " . addslashes($e->getMessage()) . "'); window.location.href='$back_url';</script>";
}

// بستن اتصال به دیتابیس
$conn->close();
?>

<?php include 'footer.php'; ?>