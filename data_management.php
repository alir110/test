<?php 
// فعال کردن نمایش خطاها برای دیباگ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// غیرفعال کردن فشرده‌سازی خروجی
ini_set('zlib.output_compression', 'Off');

// تنظیم لاگ خطاها
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt'); // فایل لاگ خطاها

// تابع برای ثبت پیام‌های دیباگ
function logMessage($message) {
    $log_file = 'debug_log.txt';
    $current_time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$current_time] $message\n", FILE_APPEND);
}

logMessage("Starting data_management.php");

// بارگذاری کتابخونه PhpSpreadsheet
logMessage("Requiring vendor/autoload.php");
require 'vendor/autoload.php'; // مسیر اصلاح‌شده

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

logMessage("PhpSpreadsheet loaded successfully");

// شروع بافر خروجی
ob_start();

try {
    logMessage("Including header.php");
    include 'header.php'; 
    logMessage("header.php included successfully");

    logMessage("Including config.php");
    include 'config.php'; // فایل تنظیمات دیتابیس
    logMessage("config.php included successfully");

    // متغیر برای ذخیره پیام‌ها
    $message = '';

    // تابع برای لود گزینه‌ها از دیتابیس (مشابه فرم اصلی)
    function getDropdownOptions($conn, $category) {
        $options = [];
        $query = "SELECT value FROM dropdown_options WHERE category = ? ORDER BY value";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $options[] = $row['value'];
        }
        $stmt->close();
        return $options;
    }

    // لود گزینه‌ها از دیتابیس برای منوهای کشویی
    $windows_versions = getDropdownOptions($conn, 'windows_version');
    $office_versions = getDropdownOptions($conn, 'office_version');
    $antivirus_options = getDropdownOptions($conn, 'antivirus');
    $motherboards = getDropdownOptions($conn, 'motherboard');
    $processors = getDropdownOptions($conn, 'processor');
    $rams = getDropdownOptions($conn, 'ram');
    $disk_types = getDropdownOptions($conn, 'disk_type');
    $disk_capacities_ssd = getDropdownOptions($conn, 'disk_capacity_ssd');
    $disk_capacities_hdd = getDropdownOptions($conn, 'disk_capacity_hdd');
    $graphics_cards = getDropdownOptions($conn, 'graphics_card');
    $printers = getDropdownOptions($conn, 'printer');
    $scanners = getDropdownOptions($conn, 'scanner');

    // دریافت لیست واحدها برای منوی کشویی
    logMessage("Fetching departments from database");
    $departments = [];
    $query = "SELECT DISTINCT department FROM contact_info ORDER BY department";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
    logMessage("Departments fetched successfully");

    // آپلود فایل اکسل برای به‌روزرسانی یا اضافه کردن داده‌ها
    if (isset($_POST['upload_excel'])) {
        logMessage("Processing upload_excel form");
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            for ($row = 2; $row <= $highestRow; $row++) {
                // IP و اطلاعات تماس
                $full_name = $sheet->getCell('A' . $row)->getValue();
                $ip_address = $sheet->getCell('B' . $row)->getValue();
                $department = $sheet->getCell('C' . $row)->getValue();
                $extension = $sheet->getCell('D' . $row)->getValue();

                // اطلاعات نرم‌افزاری
                $computer_name = $sheet->getCell('E' . $row)->getValue();
                $username = $sheet->getCell('F' . $row)->getValue();
                $password = $sheet->getCell('G' . $row)->getValue();
                $windows_version = $sheet->getCell('H' . $row)->getValue();
                $office_version = $sheet->getCell('I' . $row)->getValue();
                $antivirus = $sheet->getCell('J' . $row)->getValue();

                // اطلاعات سخت‌افزاری
                $motherboard = $sheet->getCell('K' . $row)->getValue();
                $processor = $sheet->getCell('L' . $row)->getValue();
                $ram = $sheet->getCell('M' . $row)->getValue();
                $disk_type = $sheet->getCell('N' . $row)->getValue();
                $disk_capacity = $sheet->getCell('O' . $row)->getValue();
                $graphics_card = $sheet->getCell('P' . $row)->getValue();

                // پرینترها و اسکنرها
                $printers_input = $sheet->getCell('Q' . $row)->getValue();
                $scanners_input = $sheet->getCell('R' . $row)->getValue();

                // رد کردن ردیف‌های خالی
                if (empty($full_name) || empty($ip_address) || empty($department) || empty($extension) || empty($computer_name)) {
                    continue;
                }

                // بررسی اینکه آیا سیستم با این IP وجود داره یا نه
                $query = "SELECT id FROM contact_info WHERE ip_address = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $ip_address);
                $stmt->execute();
                $result = $stmt->get_result();
                $contact = $result->fetch_assoc();

                if ($result->num_rows > 0) {
                    // به‌روزرسانی اطلاعات تماس
                    $contact_id = $contact['id'];
                    $query = "UPDATE contact_info SET full_name = ?, ip_address = ?, department = ?, extension = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssi", $full_name, $ip_address, $department, $extension, $contact_id);
                    $stmt->execute();

                    // به‌روزرسانی اطلاعات نرم‌افزاری
                    $query = "UPDATE software_info SET computer_name = ?, username = ?, password = ?, windows_version = ?, office_version = ?, antivirus = ? WHERE contact_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssssi", $computer_name, $username, $password, $windows_version, $office_version, $antivirus, $contact_id);
                    $stmt->execute();

                    // به‌روزرسانی اطلاعات سخت‌افزاری
                    $query = "UPDATE hardware_info SET motherboard = ?, processor = ?, ram = ?, disk_type = ?, disk_capacity = ?, graphics_card = ? WHERE contact_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssssi", $motherboard, $processor, $ram, $disk_type, $disk_capacity, $graphics_card, $contact_id);
                    $stmt->execute();

                    // حذف پرینترها و اسکنرهای قدیمی
                    $query = "DELETE FROM printers WHERE contact_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $contact_id);
                    $stmt->execute();

                    $query = "DELETE FROM scanners WHERE contact_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $contact_id);
                    $stmt->execute();

                    // اضافه کردن پرینترهای جدید
                    if (!empty($printers_input)) {
                        $printers_array = explode(',', $printers_input);
                        foreach ($printers_array as $printer) {
                            $printer = trim($printer);
                            if (!empty($printer)) {
                                $query = "INSERT INTO printers (contact_id, printer_model) VALUES (?, ?)";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("is", $contact_id, $printer);
                                $stmt->execute();
                            }
                        }
                    }

                    // اضافه کردن اسکنرهای جدید
                    if (!empty($scanners_input)) {
                        $scanners_array = explode(',', $scanners_input);
                        foreach ($scanners_array as $scanner) {
                            $scanner = trim($scanner);
                            if (!empty($scanner)) {
                                $query = "INSERT INTO scanners (contact_id, scanner_model) VALUES (?, ?)";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("is", $contact_id, $scanner);
                                $stmt->execute();
                            }
                        }
                    }
                } else {
                    // اضافه کردن سیستم جدید
                    $query = "INSERT INTO contact_info (full_name, ip_address, department, extension) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssss", $full_name, $ip_address, $department, $extension);
                    $stmt->execute();
                    $contact_id = $conn->insert_id;

                    // اضافه کردن اطلاعات نرم‌افزاری
                    $query = "INSERT INTO software_info (contact_id, computer_name, username, password, windows_version, office_version, antivirus) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("issssss", $contact_id, $computer_name, $username, $password, $windows_version, $office_version, $antivirus);
                    $stmt->execute();

                    // اضافه کردن اطلاعات سخت‌افزاری
                    $query = "INSERT INTO hardware_info (contact_id, motherboard, processor, ram, disk_type, disk_capacity, graphics_card) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("issssss", $contact_id, $motherboard, $processor, $ram, $disk_type, $disk_capacity, $graphics_card);
                    $stmt->execute();

                    // اضافه کردن پرینترها
                    if (!empty($printers_input)) {
                        $printers_array = explode(',', $printers_input);
                        foreach ($printers_array as $printer) {
                            $printer = trim($printer);
                            if (!empty($printer)) {
                                $query = "INSERT INTO printers (contact_id, printer_model) VALUES (?, ?)";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("is", $contact_id, $printer);
                                $stmt->execute();
                            }
                        }
                    }

                    // اضافه کردن اسکنرها
                    if (!empty($scanners_input)) {
                        $scanners_array = explode(',', $scanners_input);
                        foreach ($scanners_array as $scanner) {
                            $scanner = trim($scanner);
                            if (!empty($scanner)) {
                                $query = "INSERT INTO scanners (contact_id, scanner_model) VALUES (?, ?)";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("is", $contact_id, $scanner);
                                $stmt->execute();
                            }
                        }
                    }
                }
                $stmt->close();
            }
            $message = 'داده‌ها با موفقیت از فایل اکسل وارد شدند.';
            logMessage("Excel data imported successfully");
        } else {
            $message = 'خطا در آپلود فایل اکسل.';
            logMessage("Failed to upload Excel file");
        }
    }

    // دریافت داده‌ها برای خروجی اکسل
    logMessage("Fetching data for export");
    $query = "SELECT ci.id, ci.full_name, ci.ip_address, ci.department, ci.extension, 
                     si.computer_name, si.username, si.password, si.windows_version, si.office_version, si.antivirus, 
                     hi.motherboard, hi.processor, hi.ram, hi.disk_type, hi.disk_capacity, hi.graphics_card 
              FROM contact_info ci 
              LEFT JOIN software_info si ON ci.id = si.contact_id 
              LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
              ORDER BY ci.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $systems = [];
    while ($row = $result->fetch_assoc()) {
        $systems[] = $row;
    }
    $stmt->close();

    // دریافت پرینترها و اسکنرها برای هر سیستم
    foreach ($systems as &$system) {
        $id = $system['id'];

        // دریافت پرینترها
        $printers = [];
        $query = "SELECT printer_model FROM printers WHERE contact_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $printers[] = $row['printer_model'];
        }
        $stmt->close();
        $system['printers'] = implode(', ', $printers);

        // دریافت اسکنرها
        $scanners = [];
        $query = "SELECT scanner_model FROM scanners WHERE contact_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $scanners[] = $row['scanner_model'];
        }
        $stmt->close();
        $system['scanners'] = implode(', ', $scanners);
    }
    unset($system);
    logMessage("Data fetched successfully for export");

    // خروجی اکسل
    if (isset($_POST['export_excel'])) {
        logMessage("Exporting data to Excel");
        // پاک کردن بافر خروجی برای جلوگیری از خروجی ناخواسته
        ob_end_clean();

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // تنظیم هدرهای جدول (فقط فیلدهای واردشده توسط کاربر)
            $sheet->setCellValue('A1', 'نام و نام خانوادگی');
            $sheet->setCellValue('B1', 'IP کامپیوتر');
            $sheet->setCellValue('C1', 'واحد');
            $sheet->setCellValue('D1', 'داخلی');
            $sheet->setCellValue('E1', 'نام کامپیوتر');
            $sheet->setCellValue('F1', 'نام کاربری');
            $sheet->setCellValue('G1', 'رمز ورود');
            $sheet->setCellValue('H1', 'نسخه ویندوز');
            $sheet->setCellValue('I1', 'نسخه آفیس');
            $sheet->setCellValue('J1', 'آنتی‌ویروس');
            $sheet->setCellValue('K1', 'مادربرد');
            $sheet->setCellValue('L1', 'پردازنده');
            $sheet->setCellValue('M1', 'حافظه رم');
            $sheet->setCellValue('N1', 'نوع هارد دیسک');
            $sheet->setCellValue('O1', 'ظرفیت هارد دیسک');
            $sheet->setCellValue('P1', 'کارت گرافیک');
            $sheet->setCellValue('Q1', 'پرینترها');
            $sheet->setCellValue('R1', 'اسکنرها');

            // پر کردن داده‌ها
            $rowNumber = 2;
            foreach ($systems as $row) {
                $sheet->setCellValue('A' . $rowNumber, $row['full_name']);
                $sheet->setCellValue('B' . $rowNumber, $row['ip_address']);
                $sheet->setCellValue('C' . $rowNumber, $row['department']);
                $sheet->setCellValue('D' . $rowNumber, $row['extension']);
                $sheet->setCellValue('E' . $rowNumber, $row['computer_name']);
                $sheet->setCellValue('F' . $rowNumber, $row['username']);
                $sheet->setCellValue('G' . $rowNumber, $row['password']);
                $sheet->setCellValue('H' . $rowNumber, $row['windows_version']);
                $sheet->setCellValue('I' . $rowNumber, $row['office_version']);
                $sheet->setCellValue('J' . $rowNumber, $row['antivirus']);
                $sheet->setCellValue('K' . $rowNumber, $row['motherboard']);
                $sheet->setCellValue('L' . $rowNumber, $row['processor']);
                $sheet->setCellValue('M' . $rowNumber, $row['ram']);
                $sheet->setCellValue('N' . $rowNumber, $row['disk_type']);
                $sheet->setCellValue('O' . $rowNumber, $row['disk_capacity']);
                $sheet->setCellValue('P' . $rowNumber, $row['graphics_card']);
                $sheet->setCellValue('Q' . $rowNumber, $row['printers']);
                $sheet->setCellValue('R' . $rowNumber, $row['scanners']);
                $rowNumber++;
            }

            // اضافه کردن منوهای کشویی
            // منوی کشویی برای "واحد"
            $departments_list = implode(',', $departments);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('C' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $departments_list . '"');
            }

            // منوی کشویی برای "نسخه ویندوز"
            $windows_versions_list = implode(',', $windows_versions);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('H' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $windows_versions_list . '"');
            }

            // منوی کشویی برای "نسخه آفیس"
            $office_versions_list = implode(',', $office_versions);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('I' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $office_versions_list . '"');
            }

            // منوی کشویی برای "آنتی‌ویروس"
            $antivirus_list = implode(',', $antivirus_options);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('J' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $antivirus_list . '"');
            }

            // منوی کشویی برای "مادربرد"
            $motherboards_list = implode(',', $motherboards);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('K' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $motherboards_list . '"');
            }

            // منوی کشویی برای "پردازنده"
            $processors_list = implode(',', $processors);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('L' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $processors_list . '"');
            }

            // منوی کشویی برای "حافظه رم"
            $rams_list = implode(',', $rams);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('M' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $rams_list . '"');
            }

            // منوی کشویی برای "نوع هارد دیسک"
            $disk_types_list = implode(',', $disk_types);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('N' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $disk_types_list . '"');
            }

            // منوی کشویی برای "ظرفیت هارد دیسک" (ترکیب SSD و HDD)
            $disk_capacities = array_merge($disk_capacities_ssd, $disk_capacities_hdd);
            $disk_capacities_list = implode(',', $disk_capacities);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('O' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $disk_capacities_list . '"');
            }

            // منوی کشویی برای "کارت گرافیک"
            $graphics_cards_list = implode(',', $graphics_cards);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('P' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $graphics_cards_list . '"');
            }

            // منوی کشویی برای "پرینترها"
            $printers_list = implode(',', $printers);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('Q' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $printers_list . '"');
            }

            // منوی کشویی برای "اسکنرها"
            $scanners_list = implode(',', $scanners);
            for ($row = 2; $row < $rowNumber; $row++) {
                $validation = $sheet->getCell('R' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $scanners_list . '"');
            }

            // تنظیم هدر برای دانلود فایل
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="data_export.xlsx"');
            header('Cache-Control: max-age=0');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            // در صورت بروز خطا، بافر رو پاک می‌کنیم و خطا رو نمایش می‌دیم
            ob_end_clean();
            echo "<script>alert('خطا در تولید فایل اکسل: " . addslashes($e->getMessage()) . "');</script>";
            logMessage("Failed to export Excel: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    logMessage("Exception caught: " . $e->getMessage());
    echo "خطا: " . $e->getMessage();
}
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-100 shadow-2xl rounded-xl p-8 transition-all duration-300">
        <!-- هدر صفحه -->
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-database text-blue-600 mr-2"></i>
                مدیریت داده‌ها
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

        <!-- دکمه دانلود فایل اکسل -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">خروجی داده‌ها</h3>
            <form method="POST">
                <button type="submit" name="export_excel" class="bg-gradient-to-r from-teal-500 to-teal-600 text-white px-4 py-2 rounded-lg hover:from-teal-600 hover:to-teal-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                    <i class="fas fa-file-excel mr-2"></i>
                    دانلود فایل اکسل
                </button>
            </form>
        </div>

        <!-- فرم آپلود فایل اکسل -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">آپلود فایل اکسل</h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="excel_file" class="block text-sm font-medium text-gray-700">فایل اکسل</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" required>
                </div>
                <button type="submit" name="upload_excel" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                    <i class="fas fa-upload mr-2"></i>
                    آپلود و وارد کردن
                </button>
            </form>
        </div>
    </div>
</main>

<style>
/* استایل اختصاصی برای متن‌ها */
.table-text {
    color: #111827 !important; /* text-gray-900 */
}
</style>

<?php 
logMessage("Finished rendering data_management.php");
if (ob_get_level() > 0) {
    ob_end_flush();
}
include 'footer.php'; 
?>