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

// دریافت پارامترهای جستجو از URL
$search_params = [];
foreach (['ip', 'name', 'extension', 'department'] as $param) {
    if (isset($_GET[$param]) && !empty($_GET[$param])) {
        $search_params[$param] = $_GET[$param];
    }
}

// ساخت URL بازگشت به صفحه نتایج
$back_url = 'search.php';
if (!empty($search_params)) {
    $back_url .= '?' . http_build_query($search_params);
}
?>

<main class="container mx-auto py-4 px-2 md:py-8 md:px-4">
    <div class="bg-gradient-to-br from-white to-gray-100 shadow-2xl rounded-xl p-4 md:p-8 transition-all duration-300">
        <!-- هدر صفحه -->
        <div class="flex flex-col md:flex-row items-center justify-between mb-6 md:mb-8 space-y-4 md:space-y-0">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                نمایش اطلاعات
            </h2>
            <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-2 md:space-x-reverse w-full md:w-auto">
                <a href="<?php echo htmlspecialchars($back_url); ?>" class="bg-gradient-to-r from-gray-600 to-gray-700 text-white px-3 py-2 rounded-lg hover:from-gray-700 hover:to-gray-800 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center justify-center text-sm md:text-base">
                    <i class="fas fa-arrow-right mr-2"></i>
                    برگشت به صفحه نتایج
                </a>
                <a href="edit.php?id=<?php echo $id; ?>" target="_blank" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-3 py-2 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center justify-center text-sm md:text-base">
                    <i class="fas fa-edit mr-2"></i>
                    ویرایش اطلاعات
                </a>
                <a href="index.php" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-3 py-2 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center justify-center text-sm md:text-base">
                    <i class="fas fa-home mr-2"></i>
                    بازگشت به صفحه اصلی
                </a>
            </div>
        </div>

        <!-- اطلاعات تماس -->
        <div class="mb-6">
            <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-address-card text-blue-600 mr-2"></i>
                IP و اطلاعات تماس
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">نام و نام خانوادگی</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['full_name']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">IP کامپیوتر</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['ip_address']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">واحد</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['department']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">داخلی</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['extension']); ?></p>
                </div>
            </div>
        </div>

        <!-- اطلاعات نرم‌افزاری -->
        <div class="mb-6">
            <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-laptop-code text-blue-600 mr-2"></i>
                اطلاعات نرم‌افزاری
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">نام کامپیوتر</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['computer_name']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">نام کاربری</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['username']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">رمز ورود</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['password']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">نسخه ویندوز</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['windows_version']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">نسخه آفیس</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['office_version']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">آنتی‌ویروس</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['antivirus']); ?></p>
                </div>
            </div>
        </div>

        <!-- اطلاعات سخت‌افزاری -->
        <div class="mb-6">
            <h3 class="text-base md:text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-microchip text-blue-600 mr-2"></i>
                اطلاعات سخت‌افزاری
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">مادربرد</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['motherboard']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">پردازنده</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['processor']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">حافظه رم</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['ram']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">نوع هارد دیسک</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['disk_type']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">ظرفیت هارد دیسک</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['disk_capacity']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">کارت گرافیک</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars($record['graphics_card']); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">پرینتر</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars(implode(', ', $printers)); ?></p>
                </div>
                <div class="bg-white shadow-md rounded-lg p-3 md:p-4 transition-all duration-300 hover:shadow-lg">
                    <label class="block text-xs md:text-sm font-medium text-gray-700">اسکنر</label>
                    <p class="mt-1 view-text text-sm md:text-base"><?php echo htmlspecialchars(implode(', ', $scanners)); ?></p>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
/* استایل اختصاصی برای متن‌های مقادیر */
.view-text {
    color: #111827 !important; /* text-gray-900 */
}
</style>

<?php include 'footer.php'; ?>