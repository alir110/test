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

logMessage("Starting search.php");

include 'header.php'; 
include 'config.php'; // فایل تنظیمات دیتابیس

// متغیر برای ذخیره نتایج جستجو
$results = [];
$search_performed = false;
$no_params_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';
    $name = isset($_GET['name']) ? trim($_GET['name']) : '';
    $extension = isset($_GET['extension']) ? trim($_GET['extension']) : '';
    $department = isset($_GET['department']) ? trim($_GET['department']) : '';

    // بررسی اینکه آیا حداقل یکی از فیلدها پر شده
    if (!empty($ip) || !empty($name) || !empty($extension) || !empty($department)) {
        $search_performed = true;
        logMessage("Search performed with params - IP: $ip, Name: $name, Extension: $extension, Department: $department");

        // ساخت کوئری جستجو
        $query = "SELECT DISTINCT ci.id, ci.full_name, ci.ip_address, ci.department, ci.extension, si.username 
                  FROM contact_info ci 
                  LEFT JOIN software_info si ON ci.id = si.contact_id 
                  WHERE 1=1";
        $params = [];
        $types = '';

        if (!empty($ip)) {
            $query .= " AND ci.ip_address LIKE ?";
            $params[] = "%$ip%";
            $types .= 's';
        }
        if (!empty($name)) {
            $query .= " AND ci.full_name LIKE ?";
            $params[] = "%$name%";
            $types .= 's';
        }
        if (!empty($extension)) {
            $query .= " AND ci.extension LIKE ?";
            $params[] = "%$extension%";
            $types .= 's';
        }
        if (!empty($department)) {
            $query .= " AND ci.department LIKE ?";
            $params[] = "%$department%";
            $types .= 's';
        }

        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
        logMessage("Search query executed. Found " . count($results) . " results.");
    } else {
        $no_params_message = 'لطفاً حداقل یکی از فیلدها را برای جستجو پر کنید.';
        logMessage("No search parameters provided.");
    }
}

// ذخیره پارامترهای جستجو برای استفاده در لینک‌ها
$search_params = [];
if (!empty($ip)) $search_params['ip'] = $ip;
if (!empty($name)) $search_params['name'] = $name;
if (!empty($extension)) $search_params['extension'] = $extension;
if (!empty($department)) $search_params['department'] = $department;
?>

<main class="container mx-auto py-4 px-2 md:py-8 md:px-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6">
        <!-- ستون سمت چپ: فرم جستجو -->
        <section class="md:col-span-1">
            <div class="bg-white shadow-lg rounded-lg p-4 md:p-6 transition-all duration-300 hover:shadow-xl">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-3 md:mb-4">جستجو در پایگاه داده</h2>
                <form action="search.php" method="GET">
                    <!-- جستجو بر اساس IP -->
                    <div class="mb-3 md:mb-4">
                        <label for="search_ip" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">جستجو بر اساس IP</label>
                        <input type="text" id="search_ip" name="ip" value="<?php echo isset($_GET['ip']) ? htmlspecialchars($_GET['ip']) : ''; ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" placeholder="192.168.1.1">
                    </div>

                    <!-- جستجو بر اساس نام و نام خانوادگی -->
                    <div class="mb-3 md:mb-4">
                        <label for="search_name" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">جستجو بر اساس نام و نام خانوادگی</label>
                        <input type="text" id="search_name" name="name" value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" placeholder="نام و نام خانوادگی">
                    </div>

                    <!-- جستجو بر اساس داخلی -->
                    <div class="mb-3 md:mb-4">
                        <label for="search_extension" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">جستجو بر اساس داخلی</label>
                        <input type="text" id="search_extension" name="extension" value="<?php echo isset($_GET['extension']) ? htmlspecialchars($_GET['extension']) : ''; ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" placeholder="شماره داخلی">
                    </div>

                    <!-- جستجو بر اساس واحد -->
                    <div class="mb-3 md:mb-4">
                        <label for="search_department" class="block text-xs md:text-sm font-medium text-gray-700 mb-1">جستجو بر اساس واحد</label>
                        <input type="text" id="search_department" name="department" value="<?php echo isset($_GET['department']) ? htmlspecialchars($_GET['department']) : ''; ?>" class="mt-1 block w-full h-10 md:h-12 px-3 md:px-4 py-2 text-sm md:text-base border border-gray-300 rounded-md focus:ring focus:ring-blue-300 transition-all duration-300" placeholder="واحد">
                    </div>

                    <!-- دکمه جستجو -->
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-2 rounded-md hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-md hover:shadow-lg text-sm md:text-base">جستجو</button>
                </form>
            </div>
        </section>

        <!-- ستون سمت راست: نتایج جستجو -->
        <section class="md:col-span-3">
            <div class="bg-white shadow-lg rounded-lg p-4 md:p-6 transition-all duration-300 hover:shadow-xl">
                <?php if (!empty($no_params_message)): ?>
                    <p class="text-gray-600 text-sm md:text-base"><?php echo htmlspecialchars($no_params_message); ?></p>
                <?php elseif ($search_performed): ?>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-3 md:mb-4">نتایج جستجو</h2>
                    <?php if (empty($results)): ?>
                        <p class="text-gray-600 text-sm md:text-base">هیچ رکوردی یافت نشد.</p>
                    <?php else: ?>
                        <!-- نمایش جدول در دسکتاپ -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">نام و نام خانوادگی</th>
                                        <th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                                        <th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">واحد</th>
                                        <th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">داخلی</th>
                                        <?php if (!empty($ip)): ?>
                                            <th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">نام کاربری</th>
                                        <?php endif; ?>
                                        <th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text"><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                            <td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text"><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text"><?php echo htmlspecialchars($row['extension']); ?></td>
                                            <?php if (!empty($ip)): ?>
                                                <td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text"><?php echo htmlspecialchars($row['username']); ?></td>
                                            <?php endif; ?>
                                            <td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm font-medium flex space-x-2 space-x-reverse">
                                                <a href="view.php?id=<?php echo $row['id']; ?>&<?php echo http_build_query($search_params); ?>" target="_blank" class="bg-blue-600 text-white px-2 md:px-3 py-1 rounded-md hover:bg-blue-700 transition-all duration-300 shadow-sm hover:shadow-md text-xs md:text-sm">نمایش</a>
                                                <a href="edit.php?id=<?php echo $row['id']; ?>" target="_blank" class="bg-green-600 text-white px-2 md:px-3 py-1 rounded-md hover:bg-green-700 transition-all duration-300 shadow-sm hover:shadow-md text-xs md:text-sm">ویرایش</a>
                                                <a href="delete.php?id=<?php echo $row['id']; ?>" class="bg-red-600 text-white px-2 md:px-3 py-1 rounded-md hover:bg-red-700 transition-all duration-300 shadow-sm hover:shadow-md text-xs md:text-sm" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این رکورد را حذف کنید؟');">حذف</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- نمایش کارت‌ها در موبایل -->
                        <div class="md:hidden space-y-4">
                            <?php foreach ($results as $row): ?>
                                <div class="bg-gray-50 rounded-lg p-4 shadow-md">
                                    <div class="grid grid-cols-1 gap-2 text-sm">
                                        <p><strong>نام:</strong> <?php echo htmlspecialchars($row['full_name']); ?></p>
                                        <p><strong>IP:</strong> <?php echo htmlspecialchars($row['ip_address']); ?></p>
                                        <p><strong>واحد:</strong> <?php echo htmlspecialchars($row['department']); ?></p>
                                        <p><strong>داخلی:</strong> <?php echo htmlspecialchars($row['extension']); ?></p>
                                        <?php if (!empty($ip)): ?>
                                            <p><strong>نام کاربری:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3 flex flex-col space-y-2">
                                        <a href="view.php?id=<?php echo $row['id']; ?>&<?php echo http_build_query($search_params); ?>" target="_blank" class="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 transition-all duration-300 shadow-sm hover:shadow-md text-center text-sm">نمایش</a>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" target="_blank" class="bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700 transition-all duration-300 shadow-sm hover:shadow-md text-center text-sm">ویرایش</a>
                                        <a href="delete.php?id=<?php echo $row['id']; ?>" class="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 transition-all duration-300 shadow-sm hover:shadow-md text-center text-sm" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این رکورد را حذف کنید؟');">حذف</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<style>
/* استایل اختصاصی برای متن‌های جدول و کارت */
.table-text {
    color: #111827 !important; /* text-gray-900 */
}
</style>

<?php 
logMessage("Finished rendering search.php");
include 'footer.php'; 
?>