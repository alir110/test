<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
include 'header.php';
include 'config.php'; // فایل تنظیمات دیتابیس

// تعریف ثابت‌ها برای دسته‌بندی‌ها و جداول
define('TABLE_CONTACT_INFO', 'contact_info');
define('TABLE_SOFTWARE_INFO', 'software_info');
define('TABLE_HARDWARE_INFO', 'hardware_info');
define('TABLE_PRINTERS', 'printers');
define('TABLE_SCANNERS', 'scanners');
define('CATEGORY_WINDOWS_VERSION', 'windows_version');
define('CATEGORY_OFFICE_VERSION', 'office_version');
define('CATEGORY_ANTIVIRUS', 'antivirus');
define('CATEGORY_MOTHERBOARD', 'motherboard');
define('CATEGORY_PROCESSOR', 'processor');
define('CATEGORY_RAM', 'ram');
define('CATEGORY_DISK_TYPE', 'disk_type');
define('CATEGORY_DISK_CAPACITY_SSD', 'disk_capacity_ssd');
define('CATEGORY_DISK_CAPACITY_HDD', 'disk_capacity_hdd');
define('CATEGORY_GRAPHICS_CARD', 'graphics_card');
define('CATEGORY_PRINTER', 'printer');
define('CATEGORY_SCANNER', 'scanner');

// تابع برای لود گزینه‌ها از دیتابیس
function getDropdownOptions($conn, $category) {
    try {
        $options = [];
        $query = "SELECT value FROM dropdown_options WHERE category = ? ORDER BY value";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("خطا در آماده‌سازی کوئری: " . $conn->error);
        }
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $options[] = $row['value'];
        }
        $stmt->close();
        return $options;
    } catch (Exception $e) {
        error_log("Error in getDropdownOptions for category $category: " . $e->getMessage());
        return [];
    }
}

// لود گزینه‌ها از دیتابیس (با کش کردن در سشن)
session_start();
function loadOptionsWithCache($conn, $category) {
    $cache_key = 'dropdown_' . $category;
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = getDropdownOptions($conn, $category);
    }
    return $_SESSION[$cache_key];
}

$windows_versions = loadOptionsWithCache($conn, CATEGORY_WINDOWS_VERSION);
$office_versions = loadOptionsWithCache($conn, CATEGORY_OFFICE_VERSION);
$antivirus_options = loadOptionsWithCache($conn, CATEGORY_ANTIVIRUS);
$motherboards = loadOptionsWithCache($conn, CATEGORY_MOTHERBOARD);
$processors = loadOptionsWithCache($conn, CATEGORY_PROCESSOR);
$rams = loadOptionsWithCache($conn, CATEGORY_RAM);
$disk_types = loadOptionsWithCache($conn, CATEGORY_DISK_TYPE);
$disk_capacities_ssd = loadOptionsWithCache($conn, CATEGORY_DISK_CAPACITY_SSD);
$disk_capacities_hdd = loadOptionsWithCache($conn, CATEGORY_DISK_CAPACITY_HDD);
$graphics_cards = loadOptionsWithCache($conn, CATEGORY_GRAPHICS_CARD);
$printers = loadOptionsWithCache($conn, CATEGORY_PRINTER);
$scanners = loadOptionsWithCache($conn, CATEGORY_SCANNER);

// تولید توکن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// نمایش پیام‌های سشن (موفقیت یا خطا)
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<main class="container mx-auto py-8 px-4">
    <!-- نمایش پیام‌های موفقیت یا خطا -->
    <div id="form-messages" class="mb-6">
        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- ستون سمت چپ: جستجو در پایگاه داده -->
        <section class="md:col-span-1">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                    <i class="fas fa-search text-blue-600"></i> جستجو در پایگاه داده
                </h2>
                <form action="search.php" method="GET">
                    <!-- جستجو بر اساس IP -->
                    <div class="mb-4">
                        <label for="search_ip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">جستجو بر اساس IP</label>
                        <div class="relative">
                            <input type="text" id="search_ip" name="ip" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" placeholder="192.168.1.1">
                            <i class="fas fa-network-wired absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- جستجو بر اساس نام و نام خانوادگی -->
                    <div class="mb-4">
                        <label for="search_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">جستجو بر اساس نام و نام خانوادگی</label>
                        <div class="relative">
                            <input type="text" id="search_name" name="name" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" placeholder="نام و نام خانوادگی">
                            <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- جستجو بر اساس داخلی -->
                    <div class="mb-4">
                        <label for="search_extension" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">جستجو بر اساس داخلی</label>
                        <div class="relative">
                            <input type="text" id="search_extension" name="extension" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" placeholder="شماره داخلی">
                            <i class="fas fa-phone absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- جستجو بر اساس واحد -->
                    <div class="mb-4">
                        <label for="search_department" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">جستجو بر اساس واحد</label>
                        <div class="relative">
                            <input type="text" id="search_department" name="department" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" placeholder="واحد">
                            <i class="fas fa-building absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- دکمه جستجو -->
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition-colors duration-300 flex items-center justify-center gap-2">
                        <i class="fas fa-search"></i> جستجو
                    </button>
                </form>
            </div>
        </section>

        <!-- ستون سمت راست: فرم اصلی -->
        <section class="md:col-span-3">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                    <i class="fas fa-desktop text-blue-600"></i> فرم ثبت سیستم
                </h2>
                <form id="main-form" action="submit.php" method="POST">
                    <!-- توکن CSRF -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <!-- بخش‌های تاشو -->
                    <div class="space-y-4">
                        <!-- بخش IP و اطلاعات تماس -->
                        <div class="border rounded-lg">
                            <button type="button" class="w-full flex justify-between items-center p-4 text-left bg-gray-100 dark:bg-gray-700 rounded-t-lg focus:outline-none toggle-section">
                                <span class="text-lg font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                    <i class="fas fa-address-card text-blue-600"></i> IP و اطلاعات تماس
                                </span>
                                <i class="fas fa-chevron-down text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <div class="section-content p-4 hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-field-container">
                                        <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نام و نام خانوادگی</label>
                                        <div class="relative">
                                            <input type="text" id="full_name" name="full_name" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" required maxlength="100">
                                            <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً نام و نام خانوادگی را وارد کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="ip_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP کامپیوتر</label>
                                        <div class="relative">
                                            <input type="text" id="ip_address" name="ip_address" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" required pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" title="لطفاً یک آدرس IP معتبر وارد کنید (مثلاً 192.168.1.1)">
                                            <i class="fas fa-network-wired absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً یک آدرس IP معتبر وارد کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="department" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">واحد</label>
                                        <div class="relative">
                                            <input type="text" id="department" name="department" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" required maxlength="50">
                                            <i class="fas fa-building absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً واحد را وارد کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="extension" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">داخلی</label>
                                        <div class="relative">
                                            <input type="text" id="extension" name="extension" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" required pattern="^\d+$" title="لطفاً فقط عدد وارد کنید" maxlength="10">
                                            <i class="fas fa-phone absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً فقط عدد وارد کنید.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- بخش اطلاعات نرم‌افزاری -->
                        <div class="border rounded-lg">
                            <button type="button" class="w-full flex justify-between items-center p-4 text-left bg-gray-100 dark:bg-gray-700 rounded-t-lg focus:outline-none toggle-section">
                                <span class="text-lg font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                    <i class="fas fa-code text-blue-600"></i> اطلاعات نرم‌افزاری
                                </span>
                                <i class="fas fa-chevron-down text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <div class="section-content p-4 hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-field-container">
                                        <label for="computer_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نام کامپیوتر</label>
                                        <div class="relative">
                                            <input type="text" id="computer_name" name="computer_name" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" required maxlength="50">
                                            <i class="fas fa-desktop absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً نام کامپیوتر را وارد کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نام کاربری</label>
                                        <div class="relative">
                                            <input type="text" id="username" name="username" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" required maxlength="50">
                                            <i class="fas fa-user-circle absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً نام کاربری را وارد کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">رمز ورود</label>
                                        <div class="relative">
                                            <input type="text" id="password" name="password" class="mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400 pl-10" required maxlength="50">
                                            <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً رمز ورود را وارد کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="windows_version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نسخه ویندوز</label>
                                        <div class="relative">
                                            <select id="windows_version" name="windows_version" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($windows_versions as $version): ?>
                                                    <option value="<?php echo htmlspecialchars($version); ?>"><?php echo htmlspecialchars($version); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fab fa-windows absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً نسخه ویندوز را انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="office_version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نسخه آفیس</label>
                                        <div class="relative">
                                            <select id="office_version" name="office_version" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($office_versions as $version): ?>
                                                    <option value="<?php echo htmlspecialchars($version); ?>"><?php echo htmlspecialchars($version); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-file-word absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً نسخه آفیس را انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="antivirus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">آنتی‌ویروس</label>
                                        <div class="relative">
                                            <select id="antivirus" name="antivirus" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($antivirus_options as $option): ?>
                                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-shield-alt absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً آنتی‌ویروس را انتخاب کنید.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- بخش اطلاعات سخت‌افزاری -->
                        <div class="border rounded-lg">
                            <button type="button" class="w-full flex justify-between items-center p-4 text-left bg-gray-100 dark:bg-gray-700 rounded-t-lg focus:outline-none toggle-section">
                                <span class="text-lg font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                    <i class="fas fa-microchip text-blue-600"></i> اطلاعات سخت‌افزاری
                                </span>
                                <i class="fas fa-chevron-down text-gray-600 dark:text-gray-300"></i>
                            </button>
                            <div class="section-content p-4 hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-field-container">
                                        <label for="motherboard" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">مادربرد</label>
                                        <div class="relative">
                                            <select id="motherboard" name="motherboard" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($motherboards as $motherboard): ?>
                                                    <option value="<?php echo htmlspecialchars($motherboard); ?>"><?php echo htmlspecialchars($motherboard); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-microchip absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً مادربرد را انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="processor" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">پردازنده</label>
                                        <div class="relative">
                                            <select id="processor" name="processor" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($processors as $processor): ?>
                                                    <option value="<?php echo htmlspecialchars($processor); ?>"><?php echo htmlspecialchars($processor); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-microchip absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً پردازنده را انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="ram" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">حافظه رم</label>
                                        <div class="relative">
                                            <select id="ram" name="ram" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($rams as $ram): ?>
                                                    <option value="<?php echo htmlspecialchars($ram); ?>"><?php echo htmlspecialchars($ram); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-memory absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً حافظه رم را انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="disk_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نوع هارد دیسک</label>
                                        <div class="relative">
                                            <select id="disk_type" name="disk_type" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($disk_types as $disk_type): ?>
                                                    <option value="<?php echo htmlspecialchars($disk_type); ?>"><?php echo htmlspecialchars($disk_type); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-hdd absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً نوع هارد دیسک را انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="disk_capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ظرفیت هارد دیسک</label>
                                        <div class="relative">
                                            <select id="disk_capacity" name="disk_capacity" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                            </select>
                                            <i class="fas fa-hdd absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً ظرفیت هارد دیسک را انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="graphics_card" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">کارت گرافیک</label>
                                        <div class="relative">
                                            <select id="graphics_card" name="graphics_card" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" required>
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($graphics_cards as $graphics_card): ?>
                                                    <option value="<?php echo htmlspecialchars($graphics_card); ?>"><?php echo htmlspecialchars($graphics_card); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-desktop absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-red-500 text-xs mt-1 hidden error-message">لطفاً کارت گرافیک را انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="printer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">پرینتر</label>
                                        <div class="relative">
                                            <select id="printer" name="printer[]" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700A
 dark:border-gray-600 dark:text-gray-100 pr-10" multiple>
                                                <?php foreach ($printers as $printer): ?>
                                                    <option value="<?php echo htmlspecialchars($printer); ?>"><?php echo htmlspecialchars($printer); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-print absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-gray-500 text-xs mt-1">می‌توانید چند پرینتر انتخاب کنید.</p>
                                    </div>
                                    <div class="form-field-container">
                                        <label for="scanner" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">اسکنر</label>
                                        <div class="relative">
                                            <select id="scanner" name="scanner[]" class="select2 mt-1 block w-full h-12 px-4 py-2 text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 pr-10" multiple>
                                                <?php foreach ($scanners as $scanner): ?>
                                                    <option value="<?php echo htmlspecialchars($scanner); ?>"><?php echo htmlspecialchars($scanner); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fas fa-scanner absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                        <p class="text-gray-500 text-xs mt-1">می‌توانید چند اسکنر انتخاب کنید.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- دکمه ارسال -->
                    <div class="text-center mt-6">
                        <button type="submit" class="w-1/2 md:w-1/4 bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition-colors duration-300 flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i> ثبت اطلاعات
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<!-- اسکریپت‌های مورد نیاز -->
<link href="assets/css/select2.min.css" rel="stylesheet">
<script src="assets/js/jquery-3.7.1.min.js"></script>
<script src="assets/js/select2.min.js"></script>
<link href="assets/css/styles.css" rel="stylesheet">

<script>
$(document).ready(function() {
    // مقداردهی اولیه Select2
    $('.select2').select2({
        placeholder: "انتخاب کنید",
        allowClear: true,
        dir: "rtl",
        dropdownCssClass: "dark:select2-dropdown--dark",
        selectionCssClass: "dark:select2-selection--dark",
        maximumSelectionLength: 3, // حداکثر 3 گزینه برای انتخاب چندگانه
        language: {
            maximumSelected: function(args) {
                return "شما فقط می‌توانید " + args.maximum + " گزینه انتخاب کنید";
            },
            noResults: function() {
                return "نتیجه‌ای یافت نشد";
            }
        }
    });

    // مدیریت تاشوها با افکت fade
    $('.toggle-section').on('click', function() {
        const content = $(this).siblings('.section-content');
        const icon = $(this).find('i.fa-chevron-down, i.fa-chevron-up');
        if (content.is(':visible')) {
            content.fadeOut(300); // افکت fade برای بستن
        } else {
            content.fadeIn(300); // افکت fade برای باز کردن
        }
        icon.toggleClass('fa-chevron-down fa-chevron-up');
    });

    // باز شدن خودکار تاشوها
    // بعد از وارد کردن "داخلی" (3 رقم)، تاشوی "IP و اطلاعات تماس" بسته و "اطلاعات نرم‌افزاری" باز شود
    $('#extension').on('input', function() {
        const value = $(this).val().trim();
        const pattern = $(this).attr('pattern');
        if (value && new RegExp(pattern).test(value) && value.length === 3) { // شرط 3 رقم
            const contactSection = $(this).closest('.section-content');
            const contactToggle = contactSection.siblings('.toggle-section');
            const softwareSection = contactSection.parent().next().find('.section-content');
            const softwareToggle = softwareSection.siblings('.toggle-section');

            // تاخیر 500 میلی‌ثانیه قبل از تغییر تاشوها
            setTimeout(function() {
                // بستن تاشوی "IP و اطلاعات تماس"
                contactSection.fadeOut(300);
                contactToggle.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');

                // باز کردن تاشوی "اطلاعات نرم‌افزاری"
                softwareSection.fadeIn(300);
                softwareToggle.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
            }, 500);
        }
    });

    // بعد از انتخاب "آنتی‌ویروس"، تاشوی "اطلاعات نرم‌افزاری" بسته و "اطلاعات سخت‌افزاری" باز شود
    $('#antivirus').on('change', function() {
        const value = $(this).val();
        if (value) { // اگر مقداری انتخاب شده باشد
            const softwareSection = $(this).closest('.section-content');
            const softwareToggle = softwareSection.siblings('.toggle-section');
            const hardwareSection = softwareSection.parent().next().find('.section-content');
            const hardwareToggle = hardwareSection.siblings('.toggle-section');

            // تاخیر 500 میلی‌ثانیه قبل از تغییر تاشوها
            setTimeout(function() {
                // بستن تاشوی "اطلاعات نرم‌افزاری"
                softwareSection.fadeOut(300);
                softwareToggle.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');

                // باز کردن تاشوی "اطلاعات سخت‌افزاری"
                hardwareSection.fadeIn(300);
                hardwareToggle.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
            }, 500);
        }
    });

    // مدیریت ظرفیت هارد دیسک بر اساس نوع هارد
    function updateDiskCapacityOptions(diskType) {
        const diskCapacity = $('#disk_capacity');
        diskCapacity.empty().append('<option value="">انتخاب کنید</option>');

        const capacities = diskType === 'SSD' ? 
            <?php echo json_encode($disk_capacities_ssd); ?> : 
            <?php echo json_encode($disk_capacities_hdd); ?>;

        capacities.forEach(function(capacity) {
            diskCapacity.append('<option value="' + capacity + '">' + capacity + '</option>');
        });

        diskCapacity.trigger('change');
    }

    $('#disk_type').on('change', function() {
        const diskType = $(this).val();
        updateDiskCapacityOptions(diskType);
    });

    // اعتبارسنجی بلادرنگ
    function validateField(field) {
        const isMultiple = field.prop('multiple'); // چک کردن اینکه آیا فیلد چندگانه است
        let value = field.val();
        const errorMessage = field.siblings('.error-message');
        const isRequired = field.prop('required');
        const pattern = field.attr('pattern');

        // برای منوهای چندگانه (مثل پرینتر و اسکنر)
        if (isMultiple) {
            value = value || []; // اگر null باشد، آرایه خالی برگردان
            if (isRequired && value.length === 0) {
                errorMessage.text('لطفاً حداقل یک گزینه انتخاب کنید.');
                errorMessage.removeClass('hidden');
                field.addClass('border-red-500');
                return false;
            }
        } else {
            // برای فیلدهای معمولی
            value = value ? value.toString().trim() : ''; // تبدیل به رشته و trim
            if (isRequired && !value) {
                errorMessage.text(field.attr('id') === 'extension' ? 'لطفاً فقط عدد وارد کنید.' : 'لطفاً این فیلد را پر کنید.');
                errorMessage.removeClass('hidden');
                field.addClass('border-red-500');
                return false;
            }

            if (pattern && value && !new RegExp(pattern).test(value)) {
                errorMessage.text(field.attr('title') || 'لطفاً مقدار معتبر وارد کنید.');
                errorMessage.removeClass('hidden');
                field.addClass('border-red-500');
                return false;
            }
        }

        errorMessage.addClass('hidden');
        field.removeClass('border-red-500').addClass('border-green-500');
        return true;
    }

    // اعتبارسنجی هنگام تغییر مقدار فیلدها
    $('#main-form input, #main-form select').on('input change', function() {
        validateField($(this));
    });

    // ارسال فرم با AJAX
    $('#main-form').on('submit', function(e) {
        e.preventDefault();

        // اعتبارسنجی همه فیلدها قبل از ارسال
        let isValid = true;
        $('#main-form input[required], #main-form select[required]').each(function() {
            if (!validateField($(this))) {
                isValid = false;
            }
        });

        if (!isValid) {
            $('#form-messages').html('<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">لطفاً همه فیلدهای اجباری را به درستی پر کنید.</div>');
            return;
        }

        const formData = $(this).serialize();
        $.ajax({
            url: 'submit.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                $('#form-messages').html('<div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg">در حال ارسال...</div>');
            },
            success: function(response) {
                if (response.success) {
                    $('#form-messages').html('<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">' + response.message + '</div>');
                    $('#main-form')[0].reset();
                    $('.select2').val(null).trigger('change');
                    $('.section-content').fadeOut(300);
                    $('.toggle-section i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                } else {
                    $('#form-messages').html('<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#form-messages').html('<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">خطا در ارسال فرم: ' + error + '</div>');
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>