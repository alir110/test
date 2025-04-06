<?php 
// فعال کردن نمایش خطاها برای دیباگ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('zlib.output_compression', 'Off');

// تابع برای ثبت پیام‌های دیباگ
function logMessage($message) {
    $log_file = 'debug_log.txt';
    $current_time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$current_time] $message\n", FILE_APPEND);
}

logMessage("Starting view_asset.php");

// بارگذاری فایل‌های مورد نیاز
require 'jdf.php';
include 'header.php'; 
include 'config.php';

// شروع بافر خروجی
ob_start();

// دریافت ID اموال
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) {
    logMessage("Invalid asset ID: $id");
    echo "<p class='text-gray-600 mb-4'>شناسه اموال نامعتبر است.</p>";
    include 'footer.php';
    exit;
}

// دریافت اطلاعات اموال
$query = "SELECT * FROM assets WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$asset = $result->fetch_assoc();
$stmt->close();

if (!$asset) {
    logMessage("Asset not found for ID: $id");
    echo "<p class='text-gray-600 mb-4'>اموال یافت نشد.</p>";
    include 'footer.php';
    exit;
}

// بررسی وجود تصویر و نسخه WebP
$has_image = !empty($asset['image_path']) && file_exists($asset['image_path']);
$webp_path = '';
if ($has_image) {
    $webp_path = pathinfo($asset['image_path'], PATHINFO_DIRNAME) . '/' . pathinfo($asset['image_path'], PATHINFO_FILENAME) . '.webp';
    $has_webp = file_exists($webp_path);
} else {
    $has_webp = false;
}
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-100 shadow-2xl rounded-xl p-8 transition-all duration-300">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-eye text-blue-600 mr-2"></i>
                نمایش جزئیات اموال
            </h2>
            <a href="assets_management.php" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                <i class="fas fa-arrow-right mr-2"></i>
                بازگشت به مدیریت اموال
            </a>
        </div>

        <div class="space-y-4">
            <div class="flex items-center">
                <span class="font-semibold text-gray-700 w-40">شناسه:</span>
                <span class="text-gray-600"><?php echo htmlspecialchars($asset['id']); ?></span>
            </div>
            <div class="flex items-center">
                <span class="font-semibold text-gray-700 w-40">نوع تجهیزات:</span>
                <span class="text-gray-600"><?php echo htmlspecialchars($asset['asset_type']); ?></span>
            </div>
            <div class="flex items-center">
                <span class="font-semibold text-gray-700 w-40">شماره اموال:</span>
                <span class="text-gray-600"><?php echo htmlspecialchars($asset['asset_number']); ?></span>
            </div>
            <div class="flex items-center">
                <span class="font-semibold text-gray-700 w-40">مدل:</span>
                <span class="text-gray-600"><?php echo htmlspecialchars($asset['model']); ?></span>
            </div>
            <div class="flex items-center">
                <span class="font-semibold text-gray-700 w-40">واحد استقرار:</span>
                <span class="text-gray-600"><?php echo htmlspecialchars($asset['department']); ?></span>
            </div>
            <div class="flex items-center">
                <span class="font-semibold text-gray-700 w-40">تصویر:</span>
                <?php if ($has_image): ?>
                    <a href="#" onclick="openLightbox('<?php echo htmlspecialchars($has_webp ? $webp_path : $asset['image_path']); ?>'); return false;">
                        <picture>
                            <?php if ($has_webp): ?>
                                <source srcset="<?php echo htmlspecialchars($webp_path); ?>" type="image/webp">
                            <?php endif; ?>
                            <img src="<?php echo htmlspecialchars($asset['image_path']); ?>" alt="تصویر اموال" class="w-32 h-32 object-cover rounded cursor-pointer border hover:scale-105 transition-transform">
                        </picture>
                    </a>
                <?php else: ?>
                    <span class="text-gray-600">بدون تصویر</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center">
                <span class="font-semibold text-gray-700 w-40">تاریخ ثبت:</span>
                <span class="text-gray-600">
                    <?php
                    $created_at = $asset['created_at'];
                    if (!empty($created_at)) {
                        $timestamp = strtotime($created_at);
                        echo htmlspecialchars(jdate('Y/m/d H:i:s', $timestamp));
                    } else {
                        echo 'نامشخص';
                    }
                    ?>
                </span>
            </div>
            <div class="flex items-center">
                <span class="font-semibold text-gray-700 w-40">تاریخ به‌روزرسانی:</span>
                <span class="text-gray-600">
                    <?php
                    $updated_at = $asset['updated_at'];
                    if (!empty($updated_at)) {
                        $timestamp = strtotime($updated_at);
                        echo htmlspecialchars(jdate('Y/m/d H:i:s', $timestamp));
                    } else {
                        echo 'نامشخص';
                    }
                    ?>
                </span>
            </div>
        </div>
    </div>
</main>

<!-- لایت‌باکس -->
<div id="view-asset-lightbox" class="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="relative bg-white rounded-lg shadow-2xl p-4 max-w-3xl w-full mx-4">
        <img id="view-asset-lightbox-image" src="" alt="تصویر بزرگ" class="w-full max-h-[80vh] object-contain rounded-md">
        <button onclick="closeLightbox()" class="absolute top-2 right-2 text-gray-600 text-3xl hover:text-red-500 transition duration-200">×</button>
    </div>
</div>

<script>
function openLightbox(imageSrc) {
    const lightbox = document.getElementById('view-asset-lightbox');
    const image = document.getElementById('view-asset-lightbox-image');

    if (imageSrc) {
        image.src = imageSrc;
        lightbox.classList.remove('hidden');
    }
}

function closeLightbox() {
    const lightbox = document.getElementById('view-asset-lightbox');
    const image = document.getElementById('view-asset-lightbox-image');

    image.src = '';
    lightbox.classList.add('hidden');
}

// بستن با کلیک روی فضای خالی
document.getElementById('view-asset-lightbox').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLightbox();
    }
});

// اطمینان از مخفی بودن هنگام بارگذاری
document.addEventListener('DOMContentLoaded', function() {
    const lightbox = document.getElementById('view-asset-lightbox');
    lightbox.classList.add('hidden');
});
</script>

<?php 
logMessage("Finished rendering view_asset.php");
if (ob_get_level() > 0) {
    ob_end_flush();
}
include 'footer.php'; 
?>