<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تنظیم لاگ خطاها
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

// تابع برای ثبت پیام‌های دیباگ
function logMessage($message) {
    $log_file = 'debug_log.txt';
    $current_time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$current_time] $message\n", FILE_APPEND);
}

logMessage("Starting daily_reports.php");

ob_start();
include 'header.php';
include 'config.php';

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;

// متغیر برای پیام‌ها
$message = '';
$filter_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';
$filter_description = isset($_GET['filter_description']) ? trim($_GET['filter_description']) : '';

// توابع تبدیل تاریخ
function jalali_to_gregorian($jalali_date) {
    if (empty($jalali_date) || !is_string($jalali_date)) {
        return date('Y-m-d');
    }
    $jalali_date = explode('/', $jalali_date);
    if (count($jalali_date) != 3) {
        return date('Y-m-d');
    }
    $j_y = (int)$jalali_date[0];
    $j_m = (int)$jalali_date[1];
    $j_d = (int)$jalali_date[2];

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

    return sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
}

function gregorian_to_jalali($gregorian_date) {
    if (empty($gregorian_date)) return 'نامشخص';
    $date = explode('-', $gregorian_date);
    $gy = (int)$date[0];
    $gm = (int)$date[1];
    $gd = (int)$date[2];

    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $gy -= 1600;
    $gm -= 1;
    $gd -= 1;

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

// ثبت گزارش‌ها
if (isset($_POST['save_reports'])) {
    $report_date = isset($_POST['report_date']) ? trim($_POST['report_date']) : '';
    $descriptions = isset($_POST['descriptions']) ? $_POST['descriptions'] : [];

    if (empty($report_date)) {
        $message = 'خطا: تاریخ گزارش الزامی است.';
    } else {
        $report_date_gregorian = jalali_to_gregorian($report_date);
        if (empty($descriptions)) {
            $message = 'خطا: حداقل یک توضیحات الزامی است.';
        } else {
            foreach ($descriptions as $description) {
                if (!empty($description)) {
                    $query = "INSERT INTO daily_reports (report_date, description) VALUES (?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $report_date_gregorian, $description);
                    if ($stmt->execute()) {
                        $message = 'گزارش‌ها با موفقیت ثبت شدند.';
                    } else {
                        $message = 'خطا در ثبت گزارش: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// دریافت گزارش‌ها با فیلتر
$query = "SELECT * FROM daily_reports WHERE 1=1";
$params = [];
$types = '';
if (!empty($filter_date)) {
    $filter_date_gregorian = jalali_to_gregorian($filter_date);
    $query .= " AND report_date = ?";
    $params[] = $filter_date_gregorian;
    $types .= 's';
}
if (!empty($filter_description)) {
    $query .= " AND description LIKE ?";
    $params[] = "%$filter_description%";
    $types .= 's';
}
$query .= " ORDER BY report_date DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$reports = [];
while ($row = $result->fetch_assoc()) $reports[] = $row;
$stmt->close();

// محاسبه آمار برای نمودار
$report_counts = [];
foreach ($reports as $report) {
    $date = gregorian_to_jalali($report['report_date']);
    $report_counts[$date] = ($report_counts[$date] ?? 0) + 1;
}
// خروجی اکسل
if (isset($_POST['export_excel'])) {
    logMessage("Exporting to Excel");
    try {
        // مطمئن می‌شیم هیچ خروجی ناخواسته‌ای وجود نداره
        if (ob_get_length()) {
            ob_end_clean();
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'تاریخ')->setCellValue('B1', 'توضیحات')->setCellValue('C1', 'تاریخ ثبت');
        $rowNumber = 2;
        foreach ($reports as $row) {
            $sheet->setCellValue('A' . $rowNumber, gregorian_to_jalali($row['report_date']))
                  ->setCellValue('B' . $rowNumber, $row['description'])
                  ->setCellValue('C' . $rowNumber, $row['created_at']);
            $rowNumber++;
        }

        // تنظیم هدرها برای دانلود
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="daily_reports.xlsx"');
        header('Cache-Control: max-age=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        logMessage("Excel export failed: " . $e->getMessage());
        $message = "خطا در تولید فایل اکسل: " . $e->getMessage();
    }
}

// خروجی PDF
if (isset($_POST['export_pdf'])) {
    logMessage("Exporting to PDF");
    try {
        // مطمئن می‌شیم هیچ خروجی ناخواسته‌ای وجود نداره
        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your System');
        $pdf->SetTitle('Daily Reports');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);

        $html = '<h1 style="text-align: center;">گزارش‌های روزانه</h1>';
        $html .= '<table border="1" cellpadding="5">';
        $html .= '<tr style="background-color: #f0f0f0;"><th>تاریخ</th><th>توضیحات</th><th>تاریخ ثبت</th></tr>';
        foreach ($reports as $row) {
            $html .= '<tr>';
            $html .= '<td>' . gregorian_to_jalali($row['report_date']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['description']) . '</td>';
            $html .= '<td>' . $row['created_at'] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('daily_reports.pdf', 'D');
        exit;
    } catch (Exception $e) {
        logMessage("PDF export failed: " . $e->getMessage());
        $message = "خطا در تولید فایل PDF: " . $e->getMessage();
    }
}

// تاریخ فعلی برای استفاده در جاوااسکریپت
$today = date('Y-m-d');
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-50 shadow-2xl rounded-xl p-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-file-alt text-blue-600 mr-3"></i> گزارش‌های روزانه
            </h2>
            <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center transition-all">
                <i class="fas fa-home mr-2"></i> بازگشت
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <!-- فرم ثبت گزارش -->
        <div class="mb-8 bg-blue-50 p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">ثبت گزارش جدید</h3>
            <form method="POST" class="space-y-4">
                <div class="flex flex-col md:flex-row md:space-x-4 md:space-x-reverse">
                    <div class="flex-1">
                        <label for="report_date" class="block text-sm font-medium text-gray-700">تاریخ (شمسی)</label>
                        <input type="text" id="report_date" name="report_date" class="h-10 px-4 py-2 border rounded-lg w-full focus:ring focus:ring-blue-200" data-jdp required>
                    </div>
                </div>
                <div id="descriptions-container">
                    <div class="description-entry mb-2 flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">توضیحات</label>
                            <textarea name="descriptions[]" class="h-20 px-4 py-2 border rounded-lg w-full focus:ring focus:ring-blue-200" required></textarea>
                        </div>
                        <button type="button" onclick="removeDescription(this)" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 flex items-center">
                            <i class="fas fa-trash mr-2"></i> حذف
                        </button>
                    </div>
                </div>
                <button type="button" onclick="addDescription()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 flex items-center">
                    <i class="fas fa-plus mr-2"></i> افزودن توضیحات
                </button>
                <button type="submit" name="save_reports" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 flex items-center">
                    <i class="fas fa-save mr-2"></i> ثبت گزارش
                </button>
            </form>
        </div>

        <!-- فرم فیلتر -->
        <div class="mb-8 bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">فیلتر گزارش‌ها</h3>
            <form id="filter-form" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">تاریخ (شمسی)</label>
                    <input type="text" id="filter-date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" class="h-10 px-4 py-2 border rounded-lg w-full focus:ring focus:ring-blue-200" data-jdp placeholder="مثال: 1404/01/01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">توضیحات</label>
                    <input type="text" id="filter-description" name="filter_description" value="<?php echo htmlspecialchars($filter_description); ?>" class="h-10 px-4 py-2 border rounded-lg w-full focus:ring focus:ring-blue-200" placeholder="جستجو...">
                </div>
                <div class="flex items-end">
                    <button type="button" onclick="updateReports()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">فیلتر</button>
                </div>
            </form>
        </div>

        <!-- نمودار تعداد گزارش‌ها -->
        <div class="mb-8 bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-4 text-center">تعداد گزارش‌ها بر اساس تاریخ</h3>
            <div class="chart-container">
                <canvas id="reportChart"></canvas>
            </div>
        </div>

        <!-- جدول گزارش‌ها -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <form method="POST" class="mb-4 flex space-x-2 space-x-reverse">
                <button type="submit" name="export_excel" class="bg-teal-500 text-white px-4 py-2 rounded-lg hover:bg-teal-600 flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> خروجی اکسل
                </button>
                <button type="submit" name="export_pdf" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> خروجی PDF
                </button>
            </form>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">توضیحات</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">تاریخ ثبت</th>
                    </tr>
                </thead>
                <tbody id="reports-table" class="divide-y divide-gray-200">
                    <?php foreach ($reports as $row): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-sm text-gray-900 font-medium"><?php echo gregorian_to_jalali($row['report_date']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['description']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<!-- اسکریپت‌ها و استایل‌ها -->
<script src="assets/js/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="assets/css/jalalidatepicker.min.css">
<script src="assets/js/jalalidatepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// دیباگ برای بررسی لود اسکریپت‌ها
console.log("jQuery loaded:", typeof jQuery !== "undefined");
console.log("Jalali Datepicker loaded:", typeof jalaliDatepicker !== "undefined");
console.log("Chart.js loaded:", typeof Chart !== "undefined");

// تنظیم تاریخ شمسی با jalaliDatepicker
jalaliDatepicker.startWatch({
    separator: '/',
    minDate: "attr",
    maxDate: "attr",
    changeMonth: true,
    changeYear: true,
    showTodayBtn: true,
    showEmptyBtn: true,
});

// تنظیم مقدار اولیه فیلدها
const todayJalali = '<?php echo gregorian_to_jalali($today); ?>';
$('#report_date').val(todayJalali);
if ($('#filter-date').val() === '') {
    $('#filter-date').val(todayJalali);
}

// تابع افزودن توضیحات با دکمه حذف
function addDescription() {
    const container = document.getElementById('descriptions-container');
    const entry = document.createElement('div');
    entry.className = 'description-entry mb-2 flex items-end gap-2';
    entry.innerHTML = `
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700">توضیحات</label>
            <textarea name="descriptions[]" class="h-20 px-4 py-2 border rounded-lg w-full focus:ring focus:ring-blue-200" required></textarea>
        </div>
        <button type="button" onclick="removeDescription(this)" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 flex items-center">
            <i class="fas fa-trash mr-2"></i> حذف
        </button>
    `;
    container.appendChild(entry);
}

// تابع حذف توضیحات
function removeDescription(button) {
    const container = document.getElementById('descriptions-container');
    const entries = container.getElementsByClassName('description-entry');
    if (entries.length > 1) {
        button.parentElement.remove();
    } else {
        alert('حداقل یک توضیحات باید باقی بماند.');
    }
}

// تابع به‌روزرسانی گزارش‌ها
function updateReports() {
    const filterDate = document.getElementById('filter-date').value;
    const filterDescription = document.getElementById('filter-description').value;
    const url = `fetch_reports.php?filter_date=${encodeURIComponent(filterDate)}&filter_description=${encodeURIComponent(filterDescription)}`;
    fetch(url)
        .then(response => response.text())
        .then(data => {
            document.getElementById('reports-table').innerHTML = data;
        })
        .catch(error => console.error('Error fetching reports:', error));
}

// رویداد تغییر فیلترها
document.getElementById('filter-date').addEventListener('change', updateReports);
document.getElementById('filter-description').addEventListener('input', updateReports);

// نمودار تعداد گزارش‌ها
if (typeof Chart !== 'undefined') {
    const reportCtx = document.getElementById('reportChart').getContext('2d');
    new Chart(reportCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($report_counts)); ?>,
            datasets: [{
                label: 'تعداد گزارش‌ها',
                data: <?php echo json_encode(array_values($report_counts)); ?>,
                backgroundColor: '#3B82F6',
                borderColor: '#2563EB',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 12, family: "'Vazir', sans-serif" } } },
                tooltip: { backgroundColor: '#1F2937', titleFont: { size: 14 }, bodyFont: { size: 12 } }
            },
            animation: { duration: 1000, easing: 'easeOutQuart' },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Vazir', sans-serif" } } },
                x: { ticks: { font: { family: "'Vazir', sans-serif" } } }
            }
        }
    });
} else {
    console.error("Chart.js is not loaded properly.");
}
</script>

<style>
/* استایل‌های اصلی صفحه */
.table-text { color: #111827 !important; }
.shadow-md { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
.hover\:shadow-lg:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
.transition { transition: all 0.2s ease-in-out; }
.chart-container { max-width: 600px; max-height: 300px; margin: 0 auto; }
table { font-family: 'Vazir', sans-serif; }
thead { background-color: #EFF6FF !important; }
tbody tr { border-bottom: 1px solid #E5E7EB; }
td, th { padding: 12px 16px !important; }

/* استایل‌های فونت */
@font-face {
    font-family: 'Vazir';
    src: url('assets/fonts/Vazir-Regular.woff2') format('woff2');
    font-weight: normal;
    font-style: normal;
}

@font-face {
    font-family: 'Vazir';
    src: url('assets/fonts/Vazir-Bold.woff2') format('woff2');
    font-weight: bold;
    font-style: normal;
}

/* استایل ورودی تاریخ */
input[data-jdp] {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 10px !important;
    font-family: 'Vazir', sans-serif !important;
    font-size: 14px !important;
    color: #1f2937 !important;
}

input[data-jdp]:focus {
    outline: none !important;
    border-color: #1e40af !important;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1) !important;
}
</style>

<?php
ob_end_flush();
include 'footer.php';
?>