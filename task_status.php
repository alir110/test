<?php 
// فعال کردن نمایش خطاها برای دیباگ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// غیرفعال کردن فشرده‌سازی خروجی
ini_set('zlib.output_compression', 'Off');

// تنظیم لاگ خطاها
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

// تابع برای ثبت پیام‌های دیباگ
function logMessage($message) {
    $log_file = 'debug_log.txt';
    $current_time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$current_time] $message\n", FILE_APPEND);
}

logMessage("Starting task_status.php");

// شامل کردن فایل‌های مورد نیاز
include 'header.php'; 
include 'config.php'; // فایل تنظیمات دیتابیس

// متغیر برای ذخیره پیام‌ها
$message = '';

// توابع تبدیل تاریخ شمسی به میلادی (بدون نیاز به jdf.php)
function jalali_to_jd($j_y, $j_m, $j_d) {
    $j_y = (int)$j_y;
    $j_m = (int)$j_m;
    $j_d = (int)$j_d;

    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $jy = $j_y - 979;
    $jm = $j_m - 1;
    $jd = $j_d - 1;

    $j_day_no = 365 * $jy + (int)($jy / 33) * 8 + (int)(($jy % 33 + 3) / 4);
    for ($i = 0; $i < $jm; ++$i) {
        $j_day_no += $j_days_in_month[$i];
    }

    $j_day_no += $jd;
    $g_day_no = $j_day_no + 79;

    $gy = 1600 + 400 * (int)($g_day_no / 146097);
    $g_day_no = $g_day_no % 146097;

    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * (int)($g_day_no / 36524);
        $g_day_no = $g_day_no % 36524;

        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = false;
        }
    }

    $gy += 4 * (int)($g_day_no / 1461);
    $g_day_no %= 1461;

    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += (int)($g_day_no / 365);
        $g_day_no = $g_day_no % 365;
    }

    for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++) {
        $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
    }

    $gm = $i + 1;
    $gd = $g_day_no + 1;

    return [$gy, $gm, $gd];
}

function jdtogregorian($jd) {
    $gregorian = jalali_to_jd($jd[0], $jd[1], $jd[2]);
    return sprintf("%04d/%02d/%02d", $gregorian[0], $gregorian[1], $gregorian[2]);
}

// تبدیل تاریخ شمسی به میلادی
function jalaliToGregorian($jalali_date) {
    if (empty($jalali_date) || !is_string($jalali_date)) {
        return date('Y-m-d'); // تاریخ امروز به فرمت میلادی
    }
    $jalali_date = explode('/', $jalali_date);
    if (count($jalali_date) != 3) {
        return date('Y-m-d'); // تاریخ امروز به فرمت میلادی
    }
    $year = $jalali_date[0];
    $month = $jalali_date[1];
    $day = $jalali_date[2];
    $gregorian = jalali_to_jd($year, $month, $day);
    return sprintf("%04d-%02d-%02d", $gregorian[0], $gregorian[1], $gregorian[2]);
}

// تبدیل تاریخ میلادی به شمسی
function gregorian_to_jalali($gregorian_date) {
    $date = explode('-', $gregorian_date);
    $year = $date[0];
    $month = $date[1];
    $day = $date[2];
    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $gy = $year - 1600;
    $gm = $month - 1;
    $gd = $day - 1;

    $g_day_no = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);

    for ($i = 0; $i < $gm; ++$i) {
        $g_day_no += $g_days_in_month[$i];
    }
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
        $g_day_no++;
    }
    $g_day_no += $gd;

    $j_day_no = $g_day_no - 79;

    $j_np = (int)($j_day_no / 12053);
    $j_day_no %= 12053;

    $jy = 979 + 33 * $j_np + 4 * (int)($j_day_no / 1461);
    $j_day_no %= 1461;

    if ($j_day_no >= 366) {
        $jy += (int)(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }

    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
        $j_day_no -= $j_days_in_month[$i];
    }
    $jm = $i + 1;
    $jd = $j_day_no + 1;

    return sprintf("%04d/%02d/%02d", $jy, $jm, $jd);
}

// به‌روزرسانی وضعیت کار
if (isset($_GET['update_status'])) {
    $reminder_id = (int)$_GET['reminder_id'];
    $status = $_GET['status'];

    $query = "UPDATE reminders SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $reminder_id);
    $stmt->execute();
    $stmt->close();

    $message = 'وضعیت کار با موفقیت به‌روزرسانی شد.';
    logMessage("Task status updated: ID $reminder_id, Status: $status");
}

// دریافت همه یادآوری‌ها
$query = "SELECT * FROM reminders ORDER BY reminder_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$reminders = [];
while ($row = $result->fetch_assoc()) {
    $reminders[] = $row;
}
$stmt->close();
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-100 shadow-2xl rounded-xl p-8 transition-all duration-300">
        <!-- هدر صفحه -->
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-tasks text-blue-600 mr-2"></i>
                وضعیت انجام کار
            </h2>
            <a href="index.php" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                <i class="fas fa-home mr-2"></i>
                بازگشت به صفحه اصلی
            </a>
        </div>

        <!-- پیام‌ها -->
        <?php if (!empty($message)): ?>
            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <!-- نمایش یادآوری‌ها -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-gray-100 to-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">شرح کار</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($reminders as $reminder): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm table-text"><?php echo htmlspecialchars(gregorian_to_jalali($reminder['reminder_date'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm table-text"><?php echo htmlspecialchars($reminder['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm table-text"><?php echo htmlspecialchars($reminder['status']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex space-x-2 space-x-reverse">
                                <a href="?update_status=1&reminder_id=<?php echo $reminder['id']; ?>&status=در دست انجام" class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-3 py-1 rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1">در دست انجام</a>
                                <a href="?update_status=1&reminder_id=<?php echo $reminder['id']; ?>&status=انجام شده" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-3 py-1 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1">انجام شده</a>
                                <a href="?update_status=1&reminder_id=<?php echo $reminder['id']; ?>&status=لغو شده" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 py-1 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1">لغو شده</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
/* استایل اختصاصی برای متن‌های جدول */
.table-text {
    color: #111827 !important; /* text-gray-900 */
}
</style>

<?php 
logMessage("Finished rendering task_status.php");
if (ob_get_level() > 0) {
    ob_end_flush();
}
include 'footer.php'; 
?>