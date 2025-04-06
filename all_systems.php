<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
include 'header.php';
include 'config.php';
include 'jdf.php'; // برای تبدیل تاریخ به شمسی

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// برای خروجی PDF
require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$windows_version = isset($_GET['windows_version']) ? trim($_GET['windows_version']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$departments = [];
$query = "SELECT DISTINCT department FROM contact_info ORDER BY department";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) $departments[] = $row['department'];

$windows_versions = ['Windows 7', 'Windows 8.1', 'Windows 10', 'Windows 11'];

// دریافت سیستم‌ها با همه فیلدها
$query = "SELECT ci.id, ci.full_name, ci.ip_address, ci.department, ci.extension, ci.created_at,
                 si.computer_name, si.username, si.password, si.windows_version, si.office_version, si.antivirus,
                 hi.motherboard, hi.processor, hi.ram, hi.disk_type, hi.disk_capacity, hi.graphics_card,
                 ds.status, ds.command_status,
                 GROUP_CONCAT(DISTINCT p.printer_model) AS printers,
                 GROUP_CONCAT(DISTINCT s.scanner_model) AS scanners
          FROM contact_info ci 
          LEFT JOIN software_info si ON ci.id = si.contact_id 
          LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
          LEFT JOIN device_status ds ON ci.ip_address = ds.ip 
          LEFT JOIN printers p ON ci.id = p.contact_id 
          LEFT JOIN scanners s ON ci.id = s.contact_id 
          WHERE 1=1";
$params = [];
$types = '';
if ($filter === 'department' && !empty($department)) {
    $query .= " AND ci.department = ?";
    $params[] = $department;
    $types .= 's';
}
if (!empty($windows_version)) {
    $query .= " AND si.windows_version = ?";
    $params[] = $windows_version;
    $types .= 's';
}
if (!empty($status)) {
    $query .= " AND ds.status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($filter === 'needs_update') {
    $query .= " AND (hi.ram = '2GB' OR (hi.ram = '2GB' AND si.windows_version = 'Windows 7'))";
}
$query .= " GROUP BY ci.id ORDER BY ci.created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$systems = [];
while ($row = $result->fetch_assoc()) {
    // تبدیل تاریخ میلادی به شمسی
    if ($row['created_at']) {
        $row['created_at'] = gregorian_to_jalali(date('Y', strtotime($row['created_at'])), date('m', strtotime($row['created_at'])), date('d', strtotime($row['created_at'])), '/');
    }
    $systems[] = $row;
}
$stmt->close();

// داده برای نمودارها
$windows_stats = array_fill_keys($windows_versions, 0);
$ram_stats = ['2GB' => 0, '4GB' => 0, '8GB' => 0, '16GB' => 0, '32GB' => 0];
$status_stats = ['online' => 0, 'offline' => 0];
foreach ($systems as $system) {
    if (isset($windows_stats[$system['windows_version']])) $windows_stats[$system['windows_version']]++;
    if (isset($ram_stats[$system['ram']])) $ram_stats[$system['ram']]++;
    if ($system['status'] === 'online') $status_stats['online']++;
    elseif ($system['status'] === 'offline') $status_stats['offline']++;
}

// خروجی اکسل با همه اطلاعات (بدون وضعیت، وضعیت دستور، و تاریخ ثبت)
if (isset($_POST['export_excel'])) {
    ob_end_clean();

    $export_filter = isset($_POST['export_filter']) ? trim($_POST['export_filter']) : 'all';
    $export_department = isset($_POST['export_department']) ? trim($_POST['export_department']) : '';
    $export_windows = isset($_POST['export_windows_version']) ? trim($_POST['export_windows_version']) : '';
    $export_status = isset($_POST['export_status']) ? trim($_POST['export_status']) : '';

    $query = "SELECT ci.id, ci.full_name, ci.ip_address, ci.department, ci.extension, ci.created_at,
                     si.computer_name, si.username, si.password, si.windows_version, si.office_version, si.antivirus,
                     hi.motherboard, hi.processor, hi.ram, hi.disk_type, hi.disk_capacity, hi.graphics_card,
                     ds.status, ds.command_status,
                     GROUP_CONCAT(DISTINCT p.printer_model) AS printers,
                     GROUP_CONCAT(DISTINCT s.scanner_model) AS scanners
              FROM contact_info ci 
              LEFT JOIN software_info si ON ci.id = si.contact_id 
              LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
              LEFT JOIN device_status ds ON ci.ip_address = ds.ip 
              LEFT JOIN printers p ON ci.id = p.contact_id 
              LEFT JOIN scanners s ON ci.id = s.contact_id 
              WHERE 1=1";
    $params = [];
    $types = '';
    if ($export_filter === 'department' && !empty($export_department)) {
        $query .= " AND ci.department = ?";
        $params[] = $export_department;
        $types .= 's';
    }
    if (!empty($export_windows)) {
        $query .= " AND si.windows_version = ?";
        $params[] = $export_windows;
        $types .= 's';
    }
    if (!empty($export_status)) {
        $query .= " AND ds.status = ?";
        $params[] = $export_status;
        $types .= 's';
    }
    if ($export_filter === 'needs_update') {
        $query .= " AND (hi.ram = '2GB' OR (hi.ram = '2GB' AND si.windows_version = 'Windows 7'))";
    }
    $query .= " GROUP BY ci.id ORDER BY ci.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $export_systems = [];
    while ($row = $result->fetch_assoc()) {
        // تبدیل تاریخ میلادی به شمسی
        if ($row['created_at']) {
            $row['created_at'] = gregorian_to_jalali(date('Y', strtotime($row['created_at'])), date('m', strtotime($row['created_at'])), date('d', strtotime($row['created_at'])), '/');
        }
        $export_systems[] = $row;
    }
    $stmt->close();

    // تولید فایل اکسل با همه فیلدها (بدون وضعیت، وضعیت دستور، و تاریخ ثبت)
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'شماره')
          ->setCellValue('B1', 'نام و نام خانوادگی')
          ->setCellValue('C1', 'IP')
          ->setCellValue('D1', 'واحد')
          ->setCellValue('E1', 'داخلی')
          ->setCellValue('F1', 'نام کامپیوتر')
          ->setCellValue('G1', 'نام کاربری')
          ->setCellValue('H1', 'رمز')
          ->setCellValue('I1', 'نسخه ویندوز')
          ->setCellValue('J1', 'نسخه آفیس')
          ->setCellValue('K1', 'آنتی‌ویروس')
          ->setCellValue('L1', 'مادربرد')
          ->setCellValue('M1', 'پردازنده')
          ->setCellValue('N1', 'رم')
          ->setCellValue('O1', 'نوع دیسک')
          ->setCellValue('P1', 'ظرفیت دیسک')
          ->setCellValue('Q1', 'کارت گرافیک')
          ->setCellValue('R1', 'پرینترها')
          ->setCellValue('S1', 'اسکنرها');

    $rowNumber = 2;
    $index = 1;
    foreach ($export_systems as $row) {
        $sheet->setCellValue('A' . $rowNumber, $index++)
              ->setCellValue('B' . $rowNumber, $row['full_name'] ?? 'نامشخص')
              ->setCellValue('C' . $rowNumber, $row['ip_address'] ?? 'نامشخص')
              ->setCellValue('D' . $rowNumber, $row['department'] ?? 'نامشخص')
              ->setCellValue('E' . $rowNumber, $row['extension'] ?? 'نامشخص')
              ->setCellValue('F' . $rowNumber, $row['computer_name'] ?? 'نامشخص')
              ->setCellValue('G' . $rowNumber, $row['username'] ?? 'نامشخص')
              ->setCellValue('H' . $rowNumber, $row['password'] ?? 'نامشخص')
              ->setCellValue('I' . $rowNumber, $row['windows_version'] ?? 'نامشخص')
              ->setCellValue('J' . $rowNumber, $row['office_version'] ?? 'نامشخص')
              ->setCellValue('K' . $rowNumber, $row['antivirus'] ?? 'نامشخص')
              ->setCellValue('L' . $rowNumber, $row['motherboard'] ?? 'نامشخص')
              ->setCellValue('M' . $rowNumber, $row['processor'] ?? 'نامشخص')
              ->setCellValue('N' . $rowNumber, $row['ram'] ?? 'نامشخص')
              ->setCellValue('O' . $rowNumber, $row['disk_type'] ?? 'نامشخص')
              ->setCellValue('P' . $rowNumber, $row['disk_capacity'] ?? 'نامشخص')
              ->setCellValue('Q' . $rowNumber, $row['graphics_card'] ?? 'نامشخص')
              ->setCellValue('R' . $rowNumber, $row['printers'] ?? 'نامشخص')
              ->setCellValue('S' . $rowNumber, $row['scanners'] ?? 'نامشخص');
        $rowNumber++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="systems_list_full.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// خروجی PDF با همه اطلاعات (بدون وضعیت، وضعیت دستور، و تاریخ ثبت)
if (isset($_POST['export_pdf'])) {
    ob_end_clean();

    $export_filter = isset($_POST['export_filter']) ? trim($_POST['export_filter']) : 'all';
    $export_department = isset($_POST['export_department']) ? trim($_POST['export_department']) : '';
    $export_windows = isset($_POST['export_windows_version']) ? trim($_POST['export_windows_version']) : '';
    $export_status = isset($_POST['export_status']) ? trim($_POST['export_status']) : '';

    $query = "SELECT ci.id, ci.full_name, ci.ip_address, ci.department, ci.extension, ci.created_at,
                     si.computer_name, si.username, si.password, si.windows_version, si.office_version, si.antivirus,
                     hi.motherboard, hi.processor, hi.ram, hi.disk_type, hi.disk_capacity, hi.graphics_card,
                     ds.status, ds.command_status,
                     GROUP_CONCAT(DISTINCT p.printer_model) AS printers,
                     GROUP_CONCAT(DISTINCT s.scanner_model) AS scanners
              FROM contact_info ci 
              LEFT JOIN software_info si ON ci.id = si.contact_id 
              LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
              LEFT JOIN device_status ds ON ci.ip_address = ds.ip 
              LEFT JOIN printers p ON ci.id = p.contact_id 
              LEFT JOIN scanners s ON ci.id = s.contact_id 
              WHERE 1=1";
    $params = [];
    $types = '';
    if ($export_filter === 'department' && !empty($export_department)) {
        $query .= " AND ci.department = ?";
        $params[] = $export_department;
        $types .= 's';
    }
    if (!empty($export_windows)) {
        $query .= " AND si.windows_version = ?";
        $params[] = $export_windows;
        $types .= 's';
    }
    if (!empty($export_status)) {
        $query .= " AND ds.status = ?";
        $params[] = $export_status;
        $types .= 's';
    }
    if ($export_filter === 'needs_update') {
        $query .= " AND (hi.ram = '2GB' OR (hi.ram = '2GB' AND si.windows_version = 'Windows 7'))";
    }
    $query .= " GROUP BY ci.id ORDER BY ci.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $export_systems = [];
    while ($row = $result->fetch_assoc()) {
        $export_systems[] = $row;
    }
    $stmt->close();

    // تولید فایل PDF
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('System Management');
    $pdf->SetTitle('Systems List');
    $pdf->SetSubject('Systems List PDF Export');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setFontSubsetting(true);

    // تنظیم فونت dejavusans (فونت پیش‌فرض TCPDF)
    $pdf->SetFont('dejavusans', '', 8);

    // اضافه کردن صفحه
    $pdf->AddPage();

    // عنوان
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, 'لیست سیستم‌ها', 0, 1, 'C');
    $pdf->Ln(5);

    // تولید جدول به صورت HTML
    $html = '<table border="1" cellpadding="5" style="font-size: 8pt; text-align: center;">';
    $html .= '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    $html .= '<th width="30">شماره</th>';
    $html .= '<th width="60"><span dir="rtl">نام</span></th>';
    $html .= '<th width="45"><span dir="ltr">IP</span></th>';
    $html .= '<th width="45"><span dir="rtl">واحد</span></th>';
    $html .= '<th width="30"><span dir="rtl">داخلی</span></th>';
    $html .= '<th width="45"><span dir="ltr">کامپیوتر</span></th>';
    $html .= '<th width="45"><span dir="ltr">نام کاربری</span></th>';
    $html .= '<th width="45"><span dir="ltr">رمز</span></th>';
    $html .= '<th width="45"><span dir="ltr">ویندوز</span></th>';
    $html .= '<th width="45"><span dir="ltr">آفیس</span></th>';
    $html .= '<th width="45"><span dir="rtl">آنتی‌ویروس</span></th>';
    $html .= '<th width="45"><span dir="ltr">مادربرد</span></th>';
    $html .= '<th width="45"><span dir="ltr">پردازنده</span></th>';
    $html .= '<th width="30"><span dir="ltr">رم</span></th>';
    $html .= '<th width="30"><span dir="rtl">نوع دیسک</span></th>';
    $html .= '<th width="30"><span dir="ltr">ظرفیت دیسک</span></th>';
    $html .= '<th width="45"><span dir="ltr">کارت گرافیک</span></th>';
    $html .= '<th width="60"><span dir="rtl">پرینترها</span></th>';
    $html .= '<th width="60"><span dir="rtl">اسکنرها</span></th>';
    $html .= '</tr>';

    $index = 1;
    foreach ($export_systems as $row) {
        $html .= '<tr>';
        $html .= '<td width="30">' . $index++ . '</td>';
        $html .= '<td width="60"><span dir="rtl">' . htmlspecialchars($row['full_name'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['ip_address'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="rtl">' . htmlspecialchars($row['department'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="30"><span dir="rtl">' . htmlspecialchars($row['extension'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['computer_name'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['username'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['password'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['windows_version'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['office_version'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="rtl">' . htmlspecialchars($row['antivirus'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['motherboard'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['processor'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="30"><span dir="ltr">' . htmlspecialchars($row['ram'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="30"><span dir="rtl">' . htmlspecialchars($row['disk_type'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="30"><span dir="ltr">' . htmlspecialchars($row['disk_capacity'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="45"><span dir="ltr">' . htmlspecialchars($row['graphics_card'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="60"><span dir="rtl">' . htmlspecialchars($row['printers'] ?? 'نامشخص') . '</span></td>';
        $html .= '<td width="60"><span dir="rtl">' . htmlspecialchars($row['scanners'] ?? 'نامشخص') . '</span></td>';
        $html .= '</tr>';
    }
    $html .= '</table>';

    // رندر جدول HTML
    $pdf->writeHTML($html, true, false, true, false, '');

    // خروجی PDF
    $pdf->Output('systems_list_full.pdf', 'D');
    exit;
}
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-50 shadow-2xl rounded-xl p-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-desktop text-blue-600 mr-3"></i> داشبورد سیستم‌ها
            </h2>
            <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                <i class="fas fa-home mr-2"></i> بازگشت
            </a>
        </div>

        <!-- فرم فیلتر -->
        <div class="mb-8 bg-white p-6 rounded-lg shadow-md">
            <form id="filter-form" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">واحد</label>
                    <select id="department-filter" name="department" class="h-10 px-3 py-2 border rounded-lg w-full focus:ring focus:ring-blue-200">
                        <option value="">همه</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ویندوز</label>
                    <select id="windows-filter" name="windows_version" class="h-10 px-3 py-2 border rounded-lg w-full focus:ring focus:ring-blue-200">
                        <option value="">همه</option>
                        <?php foreach ($windows_versions as $ver): ?>
                            <option value="<?php echo htmlspecialchars($ver); ?>" <?php echo $windows_version === $ver ? 'selected' : ''; ?>><?php echo htmlspecialchars($ver); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">وضعیت</label>
                    <select id="status-filter" name="status" class="h-10 px-3 py-2 border rounded-lg w-full focus:ring focus:ring-blue-200">
                        <option value="">همه</option>
                        <option value="online" <?php echo $status === 'online' ? 'selected' : ''; ?>>آنلاین</option>
                        <option value="offline" <?php echo $status === 'offline' ? 'selected' : ''; ?>>آفلاین</option>
                    </select>
                </div>
                <div class="flex items-end space-x-2 space-x-reverse">
                    <button type="button" id="all-btn" onclick="updateSystems('all')" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 <?php echo $filter === 'all' ? 'ring-2 ring-blue-300' : ''; ?>">همه</button>
                    <button type="button" id="needs-update-btn" onclick="updateSystems('needs_update')" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 <?php echo $filter === 'needs_update' ? 'ring-2 ring-red-300' : ''; ?>">نیاز به آپدیت</button>
                </div>
            </form>
        </div>

        <!-- نمودارها -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 text-center">توزیع نسخه‌های ویندوز</h3>
                <canvas id="windowsChart" height="150"></canvas>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 text-center">توزیع رم</h3>
                <canvas id="ramChart" height="150"></canvas>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 text-center">وضعیت دستگاه‌ها</h3>
                <canvas id="statusChart" height="150"></canvas>
            </div>
        </div>

        <!-- جدول سیستم‌ها -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <p id="loading-message" class="text-gray-600 mb-4 hidden">در حال بارگذاری...</p>
            <div id="record-count" class="text-gray-600 mb-4"></div>
            <form id="excel-form" method="POST" class="inline-block">
                <input type="hidden" name="export_filter" id="export-filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="hidden" name="export_department" id="export-department" value="<?php echo htmlspecialchars($department); ?>">
                <input type="hidden" name="export_windows_version" id="export-windows-version" value="<?php echo htmlspecialchars($windows_version); ?>">
                <input type="hidden" name="export_status" id="export-status" value="<?php echo htmlspecialchars($status); ?>">
                <button type="submit" name="export_excel" class="mb-4 bg-teal-500 text-white px-4 py-2 rounded-lg hover:bg-teal-600"><i class="fas fa-file-excel mr-2"></i> خروجی اکسل</button>
            </form>
            <form id="pdf-form" method="POST" class="inline-block">
                <input type="hidden" name="export_filter" id="export-filter-pdf" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="hidden" name="export_department" id="export-department-pdf" value="<?php echo htmlspecialchars($department); ?>">
                <input type="hidden" name="export_windows_version" id="export-windows-version-pdf" value="<?php echo htmlspecialchars($windows_version); ?>">
                <input type="hidden" name="export_status" id="export-status-pdf" value="<?php echo htmlspecialchars($status); ?>">
                <button type="submit" name="export_pdf" class="mb-4 bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600"><i class="fas fa-file-pdf mr-2"></i> خروجی PDF</button>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">شماره</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">نام</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">IP</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">واحد</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">داخلی</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">کامپیوتر</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">ویندوز</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">رم</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody id="systems-table" class="divide-y divide-gray-200">
                        <?php $index = 1; foreach ($systems as $row): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo $index++; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['full_name'] ?? 'نامشخص'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['ip_address'] ?? 'نامشخص'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['department'] ?? 'نامشخص'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['extension'] ?? 'نامشخص'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['computer_name'] ?? 'نامشخص'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['windows_version'] ?? 'نامشخص'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['ram'] ?? 'نامشخص'); ?></td>
                                <td class="px-6 py-4 text-sm <?php echo ($row['status'] === 'online') ? 'text-green-600' : 'text-red-600'; ?>"><?php echo htmlspecialchars($row['status'] ?? 'نامشخص'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentFilter = '<?php echo $filter; ?>';

function updateSystems(filter) {
    const department = document.getElementById('department-filter').value;
    const windows = document.getElementById('windows-filter').value;
    const status = document.getElementById('status-filter').value;

    currentFilter = filter;
    if (department && filter !== 'needs_update') {
        currentFilter = 'department';
    }

    const url = `fetch_systems.php?filter=${currentFilter}&department=${encodeURIComponent(department)}&windows_version=${encodeURIComponent(windows)}&status=${encodeURIComponent(status)}`;
    console.log('Fetching URL:', url);
    const xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    document.getElementById('loading-message').classList.remove('hidden');
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            if (xhr.status == 200) {
                document.getElementById('systems-table').innerHTML = xhr.responseText;
                const rows = document.querySelectorAll('#systems-table tr').length;
                document.getElementById('record-count').innerHTML = 'تعداد سیستم‌ها: ' + rows;

                // به‌روزرسانی فیلدهای مخفی برای اکسل و PDF
                document.getElementById('export-filter').value = currentFilter;
                document.getElementById('export-department').value = department;
                document.getElementById('export-windows-version').value = windows;
                document.getElementById('export-status').value = status;

                document.getElementById('export-filter-pdf').value = currentFilter;
                document.getElementById('export-department-pdf').value = department;
                document.getElementById('export-windows-version-pdf').value = windows;
                document.getElementById('export-status-pdf').value = status;
            } else {
                console.error('Error fetching data:', xhr.status, xhr.responseText);
            }
            document.getElementById('loading-message').classList.add('hidden');
            updateButtonStyles();
        }
    };
    xhr.send();
}

function updateButtonStyles() {
    document.getElementById('all-btn').classList.remove('ring-2', 'ring-blue-300');
    document.getElementById('needs-update-btn').classList.remove('ring-2', 'ring-red-300');
    if (currentFilter === 'all') document.getElementById('all-btn').classList.add('ring-2', 'ring-blue-300');
    else if (currentFilter === 'needs_update') document.getElementById('needs-update-btn').classList.add('ring-2', 'ring-red-300');
}

setInterval(() => {
    updateSystems(currentFilter);
}, 30000);

['department-filter', 'windows-filter', 'status-filter'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        const department = document.getElementById('department-filter').value;
        updateSystems(department ? 'department' : 'all');
    });
});

const chartOptions = {
    responsive: true,
    plugins: {
        legend: { position: 'top', labels: { font: { size: 12 } } },
        tooltip: { backgroundColor: '#1F2937', titleFont: { size: 14 }, bodyFont: { size: 12 } }
    },
    animation: { duration: 1000, easing: 'easeOutQuart' }
};

const windowsCtx = document.getElementById('windowsChart').getContext('2d');
new Chart(windowsCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($windows_stats)); ?>,
        datasets: [{ data: <?php echo json_encode(array_values($windows_stats)); ?>, backgroundColor: ['#EF4444', '#FBBF24', '#10B981', '#3B82F6'], borderWidth: 2 }]
    },
    options: { ...chartOptions, cutout: '60%' }
});

const ramCtx = document.getElementById('ramChart').getContext('2d');
new Chart(ramCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_keys($ram_stats)); ?>,
        datasets: [{ label: 'تعداد سیستم‌ها', data: <?php echo json_encode(array_values($ram_stats)); ?>, backgroundColor: '#8B5CF6', borderColor: '#7C3AED', borderWidth: 1 }]
    },
    options: { ...chartOptions, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['آنلاین', 'آفلاین'],
        datasets: [{ data: [<?php echo $status_stats['online']; ?>, <?php echo $status_stats['offline']; ?>], backgroundColor: ['#10B981', '#EF4444'], borderWidth: 2 }]
    },
    options: { ...chartOptions, cutout: '60%' }
});

window.onload = () => {
    const initialDepartment = '<?php echo $department; ?>';
    updateSystems(initialDepartment ? 'department' : '<?php echo $filter; ?>');
};
</script>

<style>
.table-text { color: #111827 !important; }
.shadow-md { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
.hover\:shadow-lg:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
.transition { transition: all 0.2s ease-in-out; }
</style>

<?php
ob_end_flush();
include 'footer.php';
?>