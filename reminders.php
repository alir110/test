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

logMessage("Starting reminders.php");

// شامل کردن فایل‌های مورد نیاز
include 'header.php'; 
include 'config.php'; // فایل تنظیمات دیتابیس
require 'vendor/autoload.php'; // برای PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// متغیر برای ذخیره پیام‌ها
$message = '';
$filter_message = '';

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

// ذخیره یادآوری‌ها
if (isset($_POST['save_reminders'])) {
    $reminder_date = jalaliToGregorian($_POST['reminder_date']);
    $descriptions = $_POST['descriptions'];

    foreach ($descriptions as $description) {
        if (!empty($description)) {
            $query = "INSERT INTO reminders (reminder_date, description, status) VALUES (?, ?, 'در دست انجام')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $reminder_date, $description);
            $stmt->execute();
            $stmt->close();
        }
    }
    $message = 'یادآوری‌ها با موفقیت ذخیره شدند.';
    logMessage("Reminders saved successfully for date: $reminder_date");
}

// فیلتر یادآوری‌ها با بازه تاریخ
$reminders = [];
$start_date = '';
$end_date = '';
if (isset($_GET['filter_reminders'])) {
    $start_date = jalaliToGregorian($_GET['start_date']);
    $end_date = jalaliToGregorian($_GET['end_date']);

    $query = "SELECT * FROM reminders WHERE reminder_date BETWEEN ? AND ? ORDER BY reminder_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reminders[] = $row;
    }
    $stmt->close();

    if (count($reminders) > 0) {
        $filter_message = 'تعداد ' . count($reminders) . ' یادآوری یافت شد.';
    } else {
        $filter_message = 'در تاریخ انتخاب شده یادآوری ثبت نشده است.';
    }
}

// خروجی اکسل
if (isset($_POST['export_excel']) && !empty($reminders)) {
    logMessage("Exporting reminders to Excel");
    ob_end_clean();

    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // تنظیم هدرهای جدول
        $sheet->setCellValue('A1', 'تاریخ');
        $sheet->setCellValue('B1', 'شرح کار');
        $sheet->setCellValue('C1', 'وضعیت');
        $sheet->setCellValue('D1', 'تاریخ ثبت');

        // پر کردن داده‌ها
        $rowNumber = 2;
        foreach ($reminders as $reminder) {
            $sheet->setCellValue('A' . $rowNumber, gregorian_to_jalali($reminder['reminder_date']));
            $sheet->setCellValue('B' . $rowNumber, $reminder['description']);
            $sheet->setCellValue('C' . $rowNumber, $reminder['status']);
            $sheet->setCellValue('D' . $rowNumber, $reminder['created_at']);
            $rowNumber++;
        }

        // تنظیم هدر برای دانلود فایل
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="reminders.xlsx"');
        header('Cache-Control: max-age=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        ob_end_clean();
        echo "<script>alert('خطا در تولید فایل اکسل: " . addslashes($e->getMessage()) . "');</script>";
        logMessage("Failed to export Excel: " . $e->getMessage());
    }
}
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-100 shadow-2xl rounded-xl p-8 transition-all duration-300">
        <!-- هدر صفحه -->
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-bell text-blue-600 mr-2"></i>
                ثبت یادآوری
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

        <!-- فرم ثبت یادآوری -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">ثبت یادآوری</h3>
            <form method="POST" id="reminderForm">
                <div class="flex flex-col md:flex-row md:space-x-4 md:space-x-reverse mb-4">
                    <div class="flex-1">
                        <label for="reminder_date" class="block text-sm font-medium text-gray-700">تاریخ</label>
                        <input type="text" id="reminder_date" name="reminder_date" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" data-jdp required>
                    </div>
                    <div class="flex-1">
                        <label for="description_1" class="block text-sm font-medium text-gray-700">شرح کار</label>
                        <div class="flex items-center space-x-2 space-x-reverse">
                            <input type="text" id="description_1" name="descriptions[]" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" required>
                            <button type="button" onclick="removeReminder(1)" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 py-1 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1 flex items-center">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div id="additionalDescriptions"></div>
                <div class="flex space-x-4 space-x-reverse">
                    <button type="button" onclick="addNewReminder()" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        افزودن کار جدید
                    </button>
                    <button type="submit" name="save_reminders" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        ذخیره
                    </button>
                </div>
            </form>
        </div>

        <!-- فرم فیلتر با بازه تاریخ -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">فیلتر یادآوری‌ها با بازه تاریخ</h3>
            <form method="GET" class="flex flex-col md:flex-row md:space-x-4 md:space-x-reverse">
                <div class="flex-1">
                    <label for="start_date" class="block text-sm font-medium text-gray-700">از تاریخ</label>
                    <input type="text" id="start_date" name="start_date" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" data-jdp required>
                </div>
                <div class="flex-1">
                    <label for="end_date" class="block text-sm font-medium text-gray-700">تا تاریخ</label>
                    <input type="text" id="end_date" name="end_date" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" data-jdp required>
                </div>
                <div class="flex-1 mt-4 md:mt-0">
                    <button type="submit" name="filter_reminders" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center h-12 mt-6">
                        <i class="fas fa-search mr-2"></i>
                        بررسی گزارش
                    </button>
                </div>
            </form>
        </div>

        <!-- پیام فیلتر -->
        <?php if (!empty($filter_message)): ?>
            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($filter_message); ?></p>
            <?php if (count($reminders) > 0): ?>
                <form method="POST">
                    <button type="submit" name="export_excel" class="bg-gradient-to-r from-teal-500 to-teal-600 text-white px-4 py-2 rounded-lg hover:from-teal-600 hover:to-teal-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                        <i class="fas fa-file-excel mr-2"></i>
                        خروجی اکسل
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- اسکریپت‌های مورد نیاز -->
<link href="assets/css/jalalidatepicker.min.css" rel="stylesheet">
<script src="assets/js/jquery-3.7.1.min.js"></script>
<script src="assets/js/jalalidatepicker.min.js"></script>
<script>
    // تنظیم تاریخ شمسی
    jalaliDatepicker.startWatch({
        separator: '/',
        minDate: "attr",
        maxDate: "attr",
        changeMonth: true,
        changeYear: true,
        showTodayBtn: true,
        showEmptyBtn: true,
    });

    // متغیر برای شمارش یادآوری‌ها
    let reminderCount = 1;

    // تابع برای اضافه کردن یادآوری جدید
    function addNewReminder() {
        reminderCount++;
        const newField = `
            <div class="flex flex-col md:flex-row md:space-x-4 md:space-x-reverse mb-4" id="reminder_${reminderCount}">
                <div class="flex-1">
                    <label for="description_${reminderCount}" class="block text-sm font-medium text-gray-700">شرح کار</label>
                    <div class="flex items-center space-x-2 space-x-reverse">
                        <input type="text" id="description_${reminderCount}" name="descriptions[]" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" required>
                        <button type="button" onclick="removeReminder(${reminderCount})" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 py-1 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1 flex items-center">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('additionalDescriptions').insertAdjacentHTML('beforeend', newField);
    }

    // تابع برای حذف یادآوری
    function removeReminder(index) {
        const reminderDiv = document.getElementById('reminder_' + index);
        if (reminderDiv) {
            reminderDiv.remove();
        }
    }
</script>

<style>
/* استایل اختصاصی برای متن‌های جدول */
.table-text {
    color: #111827 !important; /* text-gray-900 */
}
</style>

<?php 
logMessage("Finished rendering reminders.php");
if (ob_get_level() > 0) {
    ob_end_flush();
}
include 'footer.php'; 
?>