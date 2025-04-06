<?php
// اسکریپت برای فشرده‌سازی تصاویر قدیمی

// فعال کردن نمایش خطاها برای دیباگ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تابع برای ثبت پیام‌های دیباگ
function logMessage($message) {
    $log_file = 'debug_log.txt';
    $current_time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$current_time] $message\n", FILE_APPEND);
}

logMessage("Starting compress_existing_images.php");

// بارگذاری کتابخونه Intervention Image
try {
    require 'vendor/autoload.php';
    logMessage("vendor/autoload.php loaded successfully");
} catch (Exception $e) {
    logMessage("Failed to load vendor/autoload.php: " . $e->getMessage());
    die("خطا در بارگذاری autoload: " . $e->getMessage());
}

// استفاده از Intervention Image
use Intervention\Image\ImageManager;

// پوشه آپلود
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    die("پوشه uploads/ وجود ندارد.");
}

// ایجاد یک نمونه از ImageManager
try {
    $manager = new ImageManager(['driver' => 'gd']);
    logMessage("ImageManager instantiated successfully");
} catch (Exception $e) {
    logMessage("Failed to instantiate ImageManager: " . $e->getMessage());
    die("خطا در ایجاد ImageManager: " . $e->getMessage());
}

// گرفتن لیست تصاویر (فقط فرمت‌های غیر WebP)
$files = glob($upload_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
$processed = 0;
$skipped = 0;

foreach ($files as $file) {
    $filename = basename($file);
    $webp_file = $upload_dir . pathinfo($filename, PATHINFO_FILENAME) . '.webp';

    // اگه نسخه WebP وجود داره، از این تصویر رد شو
    if (file_exists($webp_file)) {
        logMessage("Skipping $filename - WebP version already exists.");
        $skipped++;
        continue;
    }

    try {
        // فشرده‌سازی تصویر
        $img = $manager->make($file);

        // تغییر اندازه تصویر
        $img->resize(1200, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        // ذخیره نسخه WebP
        $img->save($webp_file, 80, 'webp');

        // فشرده‌سازی تصویر اصلی
        $img->save($file, 80);

        logMessage("Compressed $filename and created $webp_file");
        $processed++;
    } catch (Exception $e) {
        logMessage("Error compressing $filename: " . $e->getMessage());
    }
}

echo "فشرده‌سازی تصاویر قدیمی به پایان رسید.<br>";
echo "تعداد تصاویر فشرده‌شده: $processed<br>";
echo "تعداد تصاویر ردشده (به دلیل وجود نسخه WebP): $skipped<br>";
logMessage("Finished compressing existing images. Processed: $processed, Skipped: $skipped");
?>