<?php 
include 'header.php'; 
include 'config.php'; // فایل تنظیمات دیتابیس

// بررسی وجود ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('شناسه نامعتبر است.'); window.location.href='search.php';</script>";
    exit;
}

$id = (int)$_GET['id'];

// دریافت اطلاعات از دیتابیس
$query = "SELECT ci.*, si.*, hi.* 
          FROM contact_info ci 
          LEFT JOIN software_info si ON ci.id = si.contact_id 
          LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
          WHERE ci.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if (!$record) {
    echo "<script>alert('رکورد مورد نظر یافت نشد.'); window.location.href='search.php';</script>";
    exit;
}

// دریافت پرینترها
$selected_printers = [];
$query = "SELECT printer_model FROM printers WHERE contact_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $selected_printers[] = $row['printer_model'];
}
$stmt->close();

// دریافت اسکنرها
$selected_scanners = [];
$query = "SELECT scanner_model FROM scanners WHERE contact_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $selected_scanners[] = $row['scanner_model'];
}
$stmt->close();

// لود گزینه‌ها از دیتابیس
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

// پردازش فرم ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $ip_address = isset($_POST['ip_address']) ? trim($_POST['ip_address']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $extension = isset($_POST['extension']) ? trim($_POST['extension']) : '';
    $computer_name = isset($_POST['computer_name']) ? trim($_POST['computer_name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $windows_version = isset($_POST['windows_version']) ? trim($_POST['windows_version']) : '';
    $office_version = isset($_POST['office_version']) ? trim($_POST['office_version']) : '';
    $antivirus = isset($_POST['antivirus']) ? trim($_POST['antivirus']) : '';
    $motherboard = isset($_POST['motherboard']) ? trim($_POST['motherboard']) : '';
    $processor = isset($_POST['processor']) ? trim($_POST['processor']) : '';
    $ram = isset($_POST['ram']) ? trim($_POST['ram']) : '';
    $disk_type = isset($_POST['disk_type']) ? trim($_POST['disk_type']) : '';
    $disk_capacity = isset($_POST['disk_capacity']) ? trim($_POST['disk_capacity']) : '';
    $graphics_card = isset($_POST['graphics_card']) ? trim($_POST['graphics_card']) : '';
    $printers = isset($_POST['printer']) ? $_POST['printer'] : [];
    $scanners = isset($_POST['scanner']) ? $_POST['scanner'] : [];

    // اعتبارسنجی اولیه
    if (empty($full_name) || empty($ip_address) || empty($department) || empty($extension) ||
        empty($computer_name) || empty($username) || empty($password) || empty($windows_version) ||
        empty($office_version) || empty($antivirus) || empty($motherboard) || empty($processor) ||
        empty($ram) || empty($disk_type) || empty($disk_capacity) || empty($graphics_card)) {
        echo "<script>alert('لطفاً همه فیلدهای اجباری را پر کنید.'); window.location.href='edit.php?id=$id';</script>";
        exit;
    }

    // شروع تراکنش
    $conn->begin_transaction();

    try {
        // 1. بروزرسانی اطلاعات تماس
        $stmt = $conn->prepare("UPDATE contact_info SET full_name = ?, ip_address = ?, department = ?, extension = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $ip_address, $department, $extension, $id);
        $stmt->execute();
        $stmt->close();

        // 2. بروزرسانی اطلاعات نرم‌افزاری
        $stmt = $conn->prepare("UPDATE software_info SET computer_name = ?, username = ?, password = ?, windows_version = ?, office_version = ?, antivirus = ? WHERE contact_id = ?");
        $stmt->bind_param("ssssssi", $computer_name, $username, $password, $windows_version, $office_version, $antivirus, $id);
        $stmt->execute();
        $stmt->close();

        // 3. بروزرسانی اطلاعات سخت‌افزاری
        $stmt = $conn->prepare("UPDATE hardware_info SET motherboard = ?, processor = ?, ram = ?, disk_type = ?, disk_capacity = ?, graphics_card = ? WHERE contact_id = ?");
        $stmt->bind_param("ssssssi", $motherboard, $processor, $ram, $disk_type, $disk_capacity, $graphics_card, $id);
        $stmt->execute();
        $stmt->close();

        // 4. حذف پرینترهای قبلی و ثبت پرینترهای جدید
        $stmt = $conn->prepare("DELETE FROM printers WHERE contact_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        if (!empty($printers)) {
            $stmt = $conn->prepare("INSERT INTO printers (contact_id, printer_model) VALUES (?, ?)");
            foreach ($printers as $printer) {
                $stmt->bind_param("is", $id, $printer);
                $stmt->execute();
            }
            $stmt->close();
        }

        // 5. حذف اسکنرهای قبلی و ثبت اسکنرهای جدید
        $stmt = $conn->prepare("DELETE FROM scanners WHERE contact_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        if (!empty($scanners)) {
            $stmt = $conn->prepare("INSERT INTO scanners (contact_id, scanner_model) VALUES (?, ?)");
            foreach ($scanners as $scanner) {
                $stmt->bind_param("is", $id, $scanner);
                $stmt->execute();
            }
            $stmt->close();
        }

        // تأیید تراکنش
        $conn->commit();

        // هدایت به صفحه نمایش اطلاعات
        echo "<script>alert('اطلاعات با موفقیت بروزرسانی شد.'); window.location.href='view.php?id=$id';</script>";
    } catch (Exception $e) {
        // در صورت بروز خطا، تراکنش رو لغو می‌کنیم
        $conn->rollback();
        echo "<script>alert('خطا در بروزرسانی اطلاعات: " . addslashes($e->getMessage()) . "'); window.location.href='edit.php?id=$id';</script>";
    }

    $conn->close();
}
?>

<main class="container mx-auto py-4 px-2 md:py-8 md:px-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6">
        <!-- ستون سمت چپ: فرم جستجو -->
        <section class="md:col-span-1">
            <div class="bg-white shadow-lg rounded-lg p-4 md:p-6">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-3 md:mb-4">جستجو در پایگاه داده</h2>
                <form action="search.php" method="GET">
                    <!-- جستجو بر اساس IP -->
                    <div class="mb-3 md:mb-4">
                        <label for="search_ip" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">جستجو بر اساس IP</label>
                        <input type="text" id="search_ip" name="ip" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" placeholder="192.168.1.1">
                    </div>

                    <!-- جستجو بر اساس نام و نام خانوادگی -->
                    <div class="mb-3 md:mb-4">
                        <label for="search_name" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">جستجو بر اساس نام و نام خانوادگی</label>
                        <input type="text" id="search_name" name="name" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" placeholder="نام و نام خانوادگی">
                    </div>

                    <!-- جستجو بر اساس داخلی -->
                    <div class="mb-3 md:mb-4">
                        <label for="search_extension" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">جستجو بر اساس داخلی</label>
                        <input type="text" id="search_extension" name="extension" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" placeholder="شماره داخلی">
                    </div>

                    <!-- جستجو بر اساس واحد -->
                    <div class="mb-3 md:mb-4">
                        <label for="search_department" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">جستجو بر اساس واحد</label>
                        <input type="text" id="search_department" name="department" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" placeholder="واحد">
                    </div>

                    <!-- دکمه جستجو -->
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition-colors duration-300 text-sm md:text-base">جستجو</button>
                </form>
            </div>
        </section>

        <!-- ستون سمت راست: فرم ویرایش -->
        <section class="md:col-span-3">
            <div class="bg-white shadow-lg rounded-lg p-4 md:p-6">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-3 md:mb-4">ویرایش اطلاعات</h2>
                <form action="edit.php?id=<?php echo $id; ?>" method="POST">
                    <!-- بخش IP و اطلاعات تماس -->
                    <div class="mb-4 md:mb-6">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2 md:mb-3">IP و اطلاعات تماس</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                            <div>
                                <label for="full_name" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">نام و نام خانوادگی</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($record['full_name']); ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="ip_address" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">IP کامپیوتر</label>
                                <input type="text" id="ip_address" name="ip_address" value="<?php echo htmlspecialchars($record['ip_address']); ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="department" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">واحد</label>
                                <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($record['department']); ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="extension" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">داخلی</label>
                                <input type="text" id="extension" name="extension" value="<?php echo htmlspecialchars($record['extension']); ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                            </div>
                        </div>
                    </div>

                    <!-- بخش اطلاعات نرم‌افزاری -->
                    <div class="mb-4 md:mb-6">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2 md:mb-3">اطلاعات نرم‌افزاری</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                            <div>
                                <label for="computer_name" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">نام کامپیوتر</label>
                                <input type="text" id="computer_name" name="computer_name" value="<?php echo htmlspecialchars($record['computer_name']); ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="username" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">نام کاربری</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($record['username']); ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="password" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">رمز ورود</label>
                                <input type="text" id="password" name="password" value="<?php echo htmlspecialchars($record['password']); ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="windows_version" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">نسخه ویندوز</label>
                                <select id="windows_version" name="windows_version" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($windows_versions as $version): ?>
                                        <option value="<?php echo htmlspecialchars($version); ?>" <?php echo $record['windows_version'] === $version ? 'selected' : ''; ?>><?php echo htmlspecialchars($version); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="office_version" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">نسخه آفیس</label>
                                <select id="office_version" name="office_version" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($office_versions as $version): ?>
                                        <option value="<?php echo htmlspecialchars($version); ?>" <?php echo $record['office_version'] === $version ? 'selected' : ''; ?>><?php echo htmlspecialchars($version); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="antivirus" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">آنتی‌ویروس</label>
                                <select id="antivirus" name="antivirus" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($antivirus_options as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $record['antivirus'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- بخش اطلاعات سخت‌افزاری -->
                    <div class="mb-4 md:mb-6">
                        <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-2 md:mb-3">اطلاعات سخت‌افزاری</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                            <div>
                                <label for="motherboard" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">مادربرد</label>
                                <select id="motherboard" name="motherboard" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($motherboards as $motherboard): ?>
                                        <option value="<?php echo htmlspecialchars($motherboard); ?>" <?php echo $record['motherboard'] === $motherboard ? 'selected' : ''; ?>><?php echo htmlspecialchars($motherboard); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="processor" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">پردازنده</label>
                                <select id="processor" name="processor" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($processors as $processor): ?>
                                        <option value="<?php echo htmlspecialchars($processor); ?>" <?php echo $record['processor'] === $processor ? 'selected' : ''; ?>><?php echo htmlspecialchars($processor); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="ram" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">حافظه رم</label>
                                <select id="ram" name="ram" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($rams as $ram): ?>
                                        <option value="<?php echo htmlspecialchars($ram); ?>" <?php echo $record['ram'] === $ram ? 'selected' : ''; ?>><?php echo htmlspecialchars($ram); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="disk_type" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">نوع هارد دیسک</label>
                                <select id="disk_type" name="disk_type" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($disk_types as $disk_type): ?>
                                        <option value="<?php echo htmlspecialchars($disk_type); ?>" <?php echo $record['disk_type'] === $disk_type ? 'selected' : ''; ?>><?php echo htmlspecialchars($disk_type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="disk_capacity" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">ظرفیت هارد دیسک</label>
                                <select id="disk_capacity" name="disk_capacity" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php
                                    $disk_capacities = $record['disk_type'] === 'SSD' ? $disk_capacities_ssd : $disk_capacities_hdd;
                                    foreach ($disk_capacities as $capacity): ?>
                                        <option value="<?php echo htmlspecialchars($capacity); ?>" <?php echo $record['disk_capacity'] === $capacity ? 'selected' : ''; ?>><?php echo htmlspecialchars($capacity); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="graphics_card" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">کارت گرافیک</label>
                                <select id="graphics_card" name="graphics_card" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" required>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($graphics_cards as $graphics_card): ?>
                                        <option value="<?php echo htmlspecialchars($graphics_card); ?>" <?php echo $record['graphics_card'] === $graphics_card ? 'selected' : ''; ?>><?php echo htmlspecialchars($graphics_card); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="printer" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">پرینتر</label>
                                <select id="printer" name="printer[]" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" multiple>
                                    <?php foreach ($printers as $printer): ?>
                                        <option value="<?php echo htmlspecialchars($printer); ?>" <?php echo in_array($printer, $selected_printers) ? 'selected' : ''; ?>><?php echo htmlspecialchars($printer); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="scanner" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">اسکنر</label>
                                <select id="scanner" name="scanner[]" class="select2 mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" multiple>
                                    <?php foreach ($scanners as $scanner): ?>
                                        <option value="<?php echo htmlspecialchars($scanner); ?>" <?php echo in_array($scanner, $selected_scanners) ? 'selected' : ''; ?>><?php echo htmlspecialchars($scanner); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- دکمه ارسال -->
                    <div class="text-center">
                        <button type="submit" class="w-full md:w-1/4 bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition-colors duration-300 text-sm md:text-base">بروزرسانی اطلاعات</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<!-- اسکریپت‌های مورد نیاز -->
<link href="assets/css/select2.min.css" rel="stylesheet">
<script src="assets/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // مقداردهی اولیه Select2
        $('.select2').select2({
            placeholder: "انتخاب کنید",
            allowClear: true,
            dir: "rtl"
        });

        // مدیریت ظرفیت هارد دیسک بر اساس نوع هارد
        $('#disk_type').on('change', function() {
            var diskType = $(this).val();
            var diskCapacity = $('#disk_capacity');
            diskCapacity.empty().append('<option value="">انتخاب کنید</option>');

            if (diskType === 'SSD') {
                <?php foreach ($disk_capacities_ssd as $capacity): ?>
                    diskCapacity.append('<option value="<?php echo htmlspecialchars($capacity); ?>" <?php echo $record['disk_capacity'] === $capacity ? 'selected' : ''; ?>><?php echo htmlspecialchars($capacity); ?></option>');
                <?php endforeach; ?>
            } else if (diskType === 'HDD') {
                <?php foreach ($disk_capacities_hdd as $capacity): ?>
                    diskCapacity.append('<option value="<?php echo htmlspecialchars($capacity); ?>" <?php echo $record['disk_capacity'] === $capacity ? 'selected' : ''; ?>><?php echo htmlspecialchars($capacity); ?></option>');
                <?php endforeach; ?>
            }
            // بروزرسانی Select2 بعد از تغییر گزینه‌ها
            diskCapacity.trigger('change');
        });
    });
</script>

<style>
/* استایل‌های Select2 برای هماهنگی با فیلدهای متنی */
.select2-container--default .select2-selection--single,
.select2-container--default .select2-selection--multiple {
    height: 40px !important; /* موبایل */
    padding: 6px 12px !important;
    line-height: 28px !important;
    border: 1px solid #d1d5db !important;
    border-radius: 6px !important;
    font-size: 14px !important;
}
@media (min-width: 768px) {
    .select2-container--default .select2-selection--single,
    .select2-container--default .select2-selection--multiple {
        height: 48px !important; /* دسکتاپ */
        padding: 8px 16px !important;
        line-height: 32px !important;
        font-size: 16px !important;
    }
}
.select2-container--default .select2-selection--single .select2-selection__rendered,
.select2-container--default .select2-selection--multiple .select2-selection__rendered {
    line-height: 28px !important;
    padding: 0 !important;
}
@media (min-width: 768px) {
    .select2-container--default .select2-selection--single .select2-selection__rendered,
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        line-height: 32px !important;
    }
}
.select2-container--default .select2-selection--single .select2-selection__arrow,
.select2-container--default .select2-selection--multiple .select2-selection__arrow {
    height: 40px !important;
}
@media (min-width: 768px) {
    .select2-container--default .select2-selection--single .select2-selection__arrow,
    .select2-container--default .select2-selection--multiple .select2-selection__arrow {
        height: 48px !important;
    }
}

/* استایل‌های Select2 چندگانه */
.select2-container--default .select2-selection--multiple {
    min-height: 40px !important;
    max-height: 40px !important;
    overflow-y: auto !important;
}
@media (min-width: 768px) {
    .select2-container--default .select2-selection--multiple {
        min-height: 48px !important;
        max-height: 48px !important;
    }
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #e5e7eb !important;
    border: 1px solid #d1d5db !important;
    color: #374151 !important;
    margin-top: 4px !important;
    margin-bottom: 4px !important;
    padding: 2px 6px !important;
    font-size: 12px !important;
}
@media (min-width: 768px) {
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        margin-top: 6px !important;
        margin-bottom: 6px !important;
        font-size: 14px !important;
    }
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: #6b7280 !important;
    margin-right: 4px !important;
}
</style>

<?php include 'footer.php'; ?>