<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
include 'header.php';
include 'config.php';

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// تابع تبدیل تاریخ
function gregorian_to_jalali($gregorian_date) {
    if (empty($gregorian_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gregorian_date)) {
        return 'نامشخص';
    }
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

// تاریخ فعلی
$today = date('Y-m-d');
$filter_date_jalali = gregorian_to_jalali($today);

// باکس 1: تعداد کل سیستم‌ها و وضعیت آنلاین/آفلاین
$query = "SELECT COUNT(*) as total_systems FROM contact_info";
$result = $conn->query($query);
$total_systems = $result->fetch_assoc()['total_systems'];

$query = "SELECT 
            SUM(CASE WHEN ds.status = 'online' THEN 1 ELSE 0 END) as online_systems,
            SUM(CASE WHEN ds.status = 'offline' THEN 1 ELSE 0 END) as offline_systems
          FROM contact_info ci 
          LEFT JOIN device_status ds ON ci.ip_address = ds.ip";
$result = $conn->query($query);
$status_systems = $result->fetch_assoc();
$online_systems = $status_systems['online_systems'] ?? 0;
$offline_systems = $status_systems['offline_systems'] ?? 0;

// باکس 2: وضعیت دارایی‌ها
$query = "SELECT asset_type, COUNT(*) as count FROM assets GROUP BY asset_type";
$result = $conn->query($query);
$asset_stats = [];
while ($row = $result->fetch_assoc()) $asset_stats[$row['asset_type']] = $row['count'];

// باکس 3: سیستم‌های قدیمی (رم 2GB و ویندوز 7)
$query = "SELECT COUNT(DISTINCT ci.id) as old_systems 
          FROM contact_info ci 
          LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
          LEFT JOIN software_info si ON ci.id = si.contact_id 
          WHERE hi.ram = '2GB' AND si.windows_version = 'Windows 7'";
$result = $conn->query($query);
$old_systems = $result->fetch_assoc()['old_systems'];

// اعلان‌ها: سیستم‌های مشکل‌دار
$query = "SELECT ci.id, ci.department, ci.ip_address, si.windows_version, hi.ram, si.antivirus 
          FROM contact_info ci 
          LEFT JOIN software_info si ON ci.id = si.contact_id 
          LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
          WHERE hi.ram = '2GB' OR si.windows_version = 'Windows 7' OR si.antivirus = 'خیر'";
$result = $conn->query($query);
$notifications = [];
$problem_counts = ['win7' => 0, 'ram2gb' => 0, 'no_av' => 0];
while ($row = $result->fetch_assoc()) {
    $issues = [];
    if ($row['windows_version'] == 'Windows 7') { 
        $issues[] = 'دارای ویندوز 7'; 
        $problem_counts['win7']++; 
    }
    if ($row['ram'] == '2GB') { 
        $issues[] = 'حافظه رم 2گیگابایت'; 
        $problem_counts['ram2gb']++; 
    }
    if ($row['antivirus'] == 'خیر') { 
        $issues[] = 'بدون آنتی‌ویروس فعال'; 
        $problem_counts['no_av']++; 
    }
    $notifications[] = [
        'id' => $row['id'],
        'message' => "سیستم واحد {$row['department']} با IP {$row['ip_address']} " . implode('، ', $issues) . " می‌باشد."
    ];
}

// یادآوری‌های امروز
$query = "SELECT id, description, reminder_date FROM reminders WHERE reminder_date = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$reminders = [];
while ($row = $result->fetch_assoc()) {
    $row['date'] = gregorian_to_jalali($row['reminder_date']);
    $reminders[] = $row;
}
$stmt->close();

// یادآوری‌های نزدیک به سررسید (24 ساعت آینده)
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$query = "SELECT id, description, reminder_date FROM reminders WHERE reminder_date BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $today, $tomorrow);
$stmt->execute();
$result = $stmt->get_result();
$upcoming_reminders = [];
while ($row = $result->fetch_assoc()) {
    $row['date'] = gregorian_to_jalali($row['reminder_date']);
    $upcoming_reminders[] = $row;
}
$stmt->close();

// گزارش‌های روزانه (فیلتر تاریخ)
$filter_date = isset($_GET['report_date']) ? $_GET['report_date'] : $today;
$query = "SELECT id, description FROM daily_reports WHERE report_date = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter_date);
$stmt->execute();
$result = $stmt->get_result();
$reports = [];
while ($row = $result->fetch_assoc()) $reports[] = $row;
$stmt->close();
?>
<?php
// آخرین فعالیت‌های سیستمی
$query = "SELECT action, details, timestamp FROM system_logs ORDER BY timestamp DESC LIMIT 5";
$result = $conn->query($query);
$system_logs = [];
while ($row = $result->fetch_assoc()) $system_logs[] = $row;

// وضعیت کارها (تعداد کارها در هر وضعیت)
$task_status_counts = [
    'در دست انجام' => 0,
    'انجام شده' => 0,
    'لغو شده' => 0
];
$task_status_details = [
    'در دست انجام' => [],
    'انجام شده' => [],
    'لغو شده' => []
];

$query = "SELECT status, COUNT(*) as count FROM reminders GROUP BY status";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    if (isset($task_status_counts[$row['status']])) {
        $task_status_counts[$row['status']] = $row['count'];
    }
}

// گرفتن جزئیات کارها (3 کار آخر در هر وضعیت)
$statuses = ['در دست انجام', 'انجام شده', 'لغو شده'];
foreach ($statuses as $status) {
    $query = "SELECT id, description, reminder_date FROM reminders WHERE status = ? ORDER BY reminder_date DESC LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['date'] = gregorian_to_jalali($row['reminder_date']);
        $task_status_details[$status][] = $row;
    }
    $stmt->close();
}

// سیستم‌ها (5 تا)
$query = "SELECT ci.full_name, ci.ip_address, ci.department, ci.extension, si.computer_name, si.windows_version, hi.ram, ds.status 
          FROM contact_info ci 
          LEFT JOIN software_info si ON ci.id = si.contact_id 
          LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
          LEFT JOIN device_status ds ON ci.ip_address = ds.ip 
          ORDER BY ci.created_at DESC LIMIT 5";
$result = $conn->query($query);
$systems = [];
while ($row = $result->fetch_assoc()) $systems[] = $row;
?>

<main class="container mx-auto py-8 px-4">
    <!-- پاپ‌آپ نوتیفیکیشن -->
    <div id="notification-popup" class="fixed top-4 right-4 z-50 hidden">
        <div class="bg-white p-4 rounded-lg shadow-lg border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <p id="notification-message" class="text-sm text-gray-900"></p>
                </div>
                <button id="close-notification" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="bg-gradient-to-br from-white to-gray-50 shadow-2xl rounded-xl p-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-8 flex items-center">
            <i class="fas fa-tachometer-alt text-blue-600 mr-3"></i> داشبورد
        </h2>

        <!-- باکس‌ها -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition">
                <h3 class="text-lg font-semibold text-gray-700">تعداد سیستم‌ها</h3>
                <p class="text-2xl font-bold text-blue-600"><?php echo $total_systems; ?></p>
                <p class="text-sm text-gray-600 mt-2">آنلاین: <span class="text-green-600"><?php echo $online_systems; ?></span> | آفلاین: <span class="text-red-600"><?php echo $offline_systems; ?></span></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition">
                <h3 class="text-lg font-semibold text-gray-700">وضعیت دارایی‌ها</h3>
                <p class="text-2xl font-bold text-purple-600"><?php echo array_sum($asset_stats); ?></p>
                <p class="text-sm text-gray-600 mt-2">
                    پرینتر: <span class="text-purple-600"><?php echo $asset_stats['پرینتر'] ?? 0; ?></span> | 
                    سوییچ: <span class="text-purple-600"><?php echo $asset_stats['سوییچ'] ?? 0; ?></span>
                </p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition">
                <h3 class="text-lg font-semibold text-gray-700">سیستم‌های قدیمی</h3>
                <a href="all_systems.php?filter=needs_update" class="text-2xl font-bold text-yellow-600 hover:underline"><?php echo $old_systems; ?></a>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition">
                <h3 class="text-lg font-semibold text-gray-700">وضعیت کارها</h3>
                <div class="text-sm text-gray-600 mt-2">
                    <p>در دست انجام: <span class="text-blue-600"><?php echo $task_status_counts['در دست انجام']; ?></span></p>
                    <p>انجام شده: <span class="text-green-600"><?php echo $task_status_counts['انجام شده']; ?></span></p>
                    <p>لغو شده: <span class="text-red-600"><?php echo $task_status_counts['لغو شده']; ?></span></p>
                </div>
            </div>
        </div>

        <!-- دکمه‌ها -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <a href="index.php" class="bg-blue-500 text-white p-4 rounded-lg text-center hover:bg-blue-600 transition">افزودن سیستم جدید</a>
            <a href="index.php#search" class="bg-green-500 text-white p-4 rounded-lg text-center hover:bg-green-600 transition">جستجو تجهیزات</a>
            <a href="assets_management.php" class="bg-teal-500 text-white p-4 rounded-lg text-center hover:bg-teal-600 transition">مدیریت اموال</a>
        </div>

        <!-- یادآوری‌ها و گزارش‌ها -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">یادآوری‌ها و گزارش‌ها</h3>
            <!-- یادآوری‌های نزدیک به سررسید -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-600 mb-4">یادآوری‌های نزدیک به سررسید (24 ساعت آینده)</h4>
                <?php if (empty($upcoming_reminders)): ?>
                    <p class="text-gray-600 text-sm">هیچ یادآوری‌ای برای 24 ساعت آینده یافت نشد.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($upcoming_reminders as $reminder): ?>
                            <div class="bg-orange-50 p-4 rounded-lg shadow-sm border-l-4 border-orange-500">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-exclamation-circle text-orange-500"></i>
                                    <span class="text-sm text-gray-600"><?php echo htmlspecialchars($reminder['date']); ?></span>
                                </div>
                                <a href="task_status.php?reminder_id=<?php echo $reminder['id']; ?>" class="block text-sm text-gray-900 hover:text-orange-600"><?php echo htmlspecialchars($reminder['description']); ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">از تاریخ</label>
                    <input type="text" id="start-date" class="w-full p-2 border rounded-lg">
                    <input type="hidden" id="start-gregorian" value="<?php echo htmlspecialchars($today); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">تا تاریخ</label>
                    <input type="text" id="end-date" class="w-full p-2 border rounded-lg">
                    <input type="hidden" id="end-gregorian" value="<?php echo htmlspecialchars($today); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">فیلتر وضعیت یادآوری</label>
                    <select id="reminder-status" class="w-full p-2 border rounded-lg">
                        <option value="">همه</option>
                        <option value="در دست انجام">در دست انجام</option>
                        <option value="انجام شده">انجام شده</option>
                        <option value="لغو شده">لغو شده</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button id="filter-btn" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">فیلتر</button>
                    <button id="reset-btn" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">ریست</button>
                </div>
            </div>
            <div id="loading-message">در حال بارگذاری...</div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-4">
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-sm font-semibold text-gray-600">یادآوری‌ها</h4>
                        <a href="task_status.php" class="text-blue-600 text-sm hover:underline">مشاهده همه</a>
                    </div>
                    <div id="reminders-list">
                        <?php if (empty($reminders)): ?>
                            <p class="text-gray-600 text-sm">هیچ یادآوری‌ای برای این بازه زمانی یافت نشد.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($reminders as $reminder): ?>
                                    <div class="bg-gray-50 p-4 rounded-lg shadow-sm hover:shadow-md transition">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-calendar-alt text-blue-600"></i>
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($reminder['date']); ?></span>
                                        </div>
                                        <a href="task_status.php?reminder_id=<?php echo $reminder['id']; ?>" class="block text-sm text-gray-900 hover:text-blue-600"><?php echo htmlspecialchars($reminder['description']); ?></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-sm font-semibold text-gray-600">گزارش‌ها</h4>
                        <a href="daily_reports.php" class="text-blue-600 text-sm hover:underline">مشاهده همه</a>
                    </div>
                    <div id="reports-list">
                        <?php if (empty($reports)): ?>
                            <p class="text-gray-600 text-sm">هیچ گزارشی برای این بازه زمانی یافت نشد.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($reports as $report): ?>
                                    <div class="bg-gray-50 p-4 rounded-lg shadow-sm hover:shadow-md transition">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-calendar-alt text-blue-600"></i>
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($filter_date_jalali); ?></span>
                                        </div>
                                        <a href="daily_reports.php?filter_date=<?php echo $filter_date; ?>" class="block text-sm text-gray-900 hover:text-blue-600"><?php echo htmlspecialchars($report['description']); ?></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-lg shadow-md text-center">
                    <h4 class="text-sm font-semibold text-gray-600 mb-2">تعداد یادآوری‌ها</h4>
                    <p id="reminders-count" class="text-xl font-bold text-blue-600"><?php echo count($reminders); ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md text-center">
                    <h4 class="text-sm font-semibold text-gray-600 mb-2">تعداد گزارش‌ها</h4>
                    <p id="reports-count" class="text-xl font-bold text-green-600"><?php echo count($reports); ?></p>
                </div>
            </div>
        </div>

        <!-- وضعیت کارها -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">وضعیت کارها</h3>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <?php foreach ($statuses as $status): ?>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-600 mb-4 flex items-center gap-2">
                            <?php
                            $status_icon = '';
                            $status_color = '';
                            if ($status === 'در دست انجام') {
                                $status_icon = '<i class="fas fa-clock text-orange-500"></i>';
                                $status_color = 'text-orange-500';
                            } elseif ($status === 'انجام شده') {
                                $status_icon = '<i class="fas fa-check-circle text-green-500"></i>';
                                $status_color = 'text-green-500';
                            } elseif ($status === 'لغو شده') {
                                $status_icon = '<i class="fas fa-times-circle text-red-500"></i>';
                                $status_color = 'text-red-500';
                            }
                            echo $status_icon;
                            ?>
                            <span class="<?php echo $status_color; ?>"><?php echo htmlspecialchars($status); ?> (<?php echo $task_status_counts[$status]; ?>)</span>
                        </h4>
                        <?php if (empty($task_status_details[$status])): ?>
                            <p class="text-gray-600 text-sm">هیچ کاری در این وضعیت یافت نشد.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($task_status_details[$status] as $task): ?>
                                    <div class="bg-gray-50 p-4 rounded-lg shadow-sm hover:shadow-md transition">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-calendar-alt text-blue-600"></i>
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($task['date']); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <a href="task_status.php?reminder_id=<?php echo $task['id']; ?>" class="block text-sm text-gray-900 hover:text-blue-600"><?php echo htmlspecialchars($task['description']); ?></a>
                                            <div class="flex items-center gap-2">
                                                <span class="status-label px-2 py-1 rounded-full text-xs font-semibold <?php
                                                    if ($status === 'در دست انجام') echo 'bg-orange-100 text-orange-700';
                                                    elseif ($status === 'انجام شده') echo 'bg-green-100 text-green-700';
                                                    elseif ($status === 'لغو شده') echo 'bg-red-100 text-red-700';
                                                ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                                <div class="relative">
                                                    <button class="change-status-btn bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600 transition shadow-md" data-task-id="<?php echo $task['id']; ?>" data-current-status="<?php echo htmlspecialchars($status); ?>">
                                                        تغییر وضعیت
                                                    </button>
                                                    <div class="status-dropdown hidden absolute right-0 mt-2 w-48 bg-white border rounded-lg shadow-lg z-10">
                                                        <button class="block w-full text-right px-4 py-2 text-sm text-gray-700 hover:bg-orange-100 change-status-option" data-status="در دست انجام">در دست انجام</button>
                                                        <button class="block w-full text-right px-4 py-2 text-sm text-gray-700 hover:bg-green-100 change-status-option" data-status="انجام شده">انجام شده</button>
                                                        <button class="block w-full text-right px-4 py-2 text-sm text-gray-700 hover:bg-red-100 change-status-option" data-status="لغو شده">لغو شده</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="task_status.php" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">مشاهده همه کارها</a>
        </div>
                <!-- اعلان‌ها -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">اعلان‌ها</h3>
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-3 max-h-64 overflow-y-auto">
                    <?php if (empty($notifications)): ?>
                        <p class="text-gray-600">هیچ اعلانی وجود ندارد.</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($notifications as $note): ?>
                                <li class="text-sm text-gray-900 bg-gray-50 p-2 rounded hover:bg-gray-100 transition">
                                    <a href="view.php?id=<?php echo $note['id']; ?>" class="block"><?php echo htmlspecialchars($note['message']); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div>
                    <canvas id="problemChart" height="150"></canvas>
                </div>
            </div>
        </div>

        <!-- آخرین فعالیت‌های سیستمی -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">آخرین فعالیت‌های سیستمی</h3>
            <?php if (empty($system_logs)): ?>
                <p class="text-gray-600 text-sm">هیچ فعالیتی ثبت نشده است.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($system_logs as $log): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-clock text-gray-600"></i>
                                <span class="text-sm text-gray-600"><?php echo htmlspecialchars(gregorian_to_jalali(date('Y-m-d', strtotime($log['timestamp'])))); ?> - <?php echo date('H:i', strtotime($log['timestamp'])); ?></span>
                            </div>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($log['action']); ?>: <?php echo htmlspecialchars($log['details']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- آخرین سیستم‌ها -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">آخرین سیستم‌ها</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">نام</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">IP</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">واحد</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">داخلی</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">ویندوز</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">رم</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($systems as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['extension']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['windows_version']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['ram']); ?></td>
                                <td class="px-6 py-4 text-sm <?php echo $row['status'] === 'online' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo htmlspecialchars($row['status'] ?: 'نامشخص'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="all_systems.php" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">مشاهده همه سیستم‌ها</a>
        </div>
    </div>
</main>

<!-- استایل‌های تقویم -->
<style>
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

.persian-datepicker {
    font-family: 'Vazir', sans-serif !important;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1) !important;
    padding: 15px !important;
    animation: slideDown 0.3s ease-in-out !important;
}

.persian-datepicker .header {
    background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%) !important;
    color: #ffffff !important;
    padding: 12px !important;
    border-radius: 8px 8px 0 0 !important;
    font-weight: bold !important;
}

.persian-datepicker .header .title {
    font-size: 16px !important;
}

.persian-datepicker .header .arrow {
    color: #ffffff !important;
    font-size: 18px !important;
    cursor: pointer !important;
    transition: transform 0.2s ease, color 0.2s ease !important;
}

.persian-datepicker .header .arrow:hover {
    transform: scale(1.2) !important;
    color: #dbeafe !important;
}

.persian-datepicker .body .day {
    color: #1f2937 !important;
    font-size: 14px !important;
    padding: 8px !important;
    border-radius: 8px !important;
    transition: background 0.2s ease, color 0.2s ease !important;
}

.persian-datepicker .body .day:hover {
    background: #dbeafe !important;
    color: #1e40af !important;
    cursor: pointer !important;
}

.persian-datepicker .body .day.selected {
    background: #1e40af !important;
    color: #ffffff !important;
}

.persian-datepicker .body .day.today {
    border: 2px solid #1e40af !important;
    color: #1e40af !important;
}

.persian-datepicker .body .day.disabled {
    color: #d1d5db !important;
    cursor: not-allowed !important;
}

.persian-datepicker .footer {
    border-top: 1px solid #e2e8f0 !important;
    padding-top: 10px !important;
    margin-top: 10px !important;
}

.persian-datepicker .footer .today-btn,
.persian-datepicker .footer .clear-btn {
    background: #1e40af !important;
    color: #ffffff !important;
    padding: 8px 16px !important;
    border-radius: 8px !important;
    font-size: 14px !important;
    transition: background 0.2s ease, transform 0.2s ease !important;
}

.persian-datepicker .footer .today-btn:hover,
.persian-datepicker .footer .clear-btn:hover {
    background: #182848 !important;
    transform: translateY(-1px) !important;
}

#start-date, #end-date {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 10px !important;
    font-family: 'Vazir', sans-serif !important;
    font-size: 14px !important;
    color: #1f2937 !important;
}

#start-date:focus, #end-date:focus {
    outline: none !important;
    border-color: #1e40af !important;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1) !important;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

#loading-message {
    display: none;
    color: #1e40af;
    font-size: 14px;
    margin-bottom: 10px;
}

.change-status-btn {
    position: relative;
    transition: all 0.3s ease;
}

.change-status-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.status-dropdown { display: none; }
.status-dropdown.show { display: block; }

.status-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

@media (max-width: 640px) {
    .container { padding-left: 1rem; padding-right: 1rem; }
    .grid { grid-template-columns: 1fr !important; }
    .min-w-full { width: 100%; overflow-x: auto; display: block; }
    table { min-width: 600px; }
    .max-h-64 { max-height: 200px; }
    .lg\:col-span-3 { max-height: 200px; }
}

@media (min-width: 640px) and (max-width: 1024px) {
    .grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .grid-cols-1.lg\:grid-cols-2 { grid-template-columns: 1fr; }
}
</style>

<!-- اسکریپت‌ها -->
<script src="assets/js/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jalaali-js@1.2.6/dist/jalaali.min.js"></script>
<script src="assets/js/jquery.Bootstrap-PersianDateTimePicker.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
<link rel="stylesheet" href="assets/css/jquery.Bootstrap-PersianDateTimePicker.css">

<script>
$(document).ready(function() {
    console.log('Script started'); // تست اولیه
    console.log('jQuery:', typeof $ !== 'undefined');
    console.log('Chart.js:', typeof Chart !== 'undefined');
    console.log('jalaali.js:', typeof jalaali !== 'undefined');
    console.log('PersianDatepicker (local):', typeof $.fn.persianDatepicker !== 'undefined');
    console.log('PersianDatepicker (CDN):', typeof $.fn.pDatepicker !== 'undefined');

    const todayGregorian = new Date('<?php echo $today; ?>');
    const jalaaliDate = jalaali.toJalaali(todayGregorian.getFullYear(), todayGregorian.getMonth() + 1, todayGregorian.getDate());
    const todayJalali = jalaaliDate.jy + '/' + String(jalaaliDate.jm).padStart(2, '0') + '/' + String(jalaaliDate.jd).padStart(2, '0');
    console.log('Today (Gregorian):', '<?php echo $today; ?>', 'Jalali:', todayJalali);

    $('#start-date').val(todayJalali);
    $('#end-date').val(todayJalali);

    if (typeof Chart !== 'undefined') {
        new Chart(document.getElementById('problemChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['ویندوز 7', 'رم 2GB', 'بدون آنتی‌ویروس'],
                datasets: [{
                    data: [<?php echo $problem_counts['win7']; ?>, <?php echo $problem_counts['ram2gb']; ?>, <?php echo $problem_counts['no_av']; ?>],
                    backgroundColor: ['#EF4444', '#FBBF24', '#10B981'],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Vazir', size: 12 }, color: '#1f2937' } }
                },
                cutout: '60%'
            }
        });
    } else {
        console.error('Chart.js failed to load');
    }

    if (typeof $.fn.persianDatepicker !== 'undefined') {
        $('#start-date').persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            onShow: () => console.log('Start date picker opened'),
            onSelect: (unixDate) => {
                const date = new Date(unixDate);
                const gregorianDate = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                console.log('Start date selected:', gregorianDate);
                $('#start-gregorian').val(gregorianDate);
            }
        });
        $('#end-date').persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            onShow: () => console.log('End date picker opened'),
            onSelect: (unixDate) => {
                const date = new Date(unixDate);
                const gregorianDate = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                console.log('End date selected:', gregorianDate);
                $('#end-gregorian').val(gregorianDate);
            }
        });
    } else if (typeof $.fn.pDatepicker !== 'undefined') {
        $('#start-date').pDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            onSelect: (unixDate) => {
                const date = new Date(unixDate);
                const gregorianDate = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                $('#start-gregorian').val(gregorianDate);
            }
        });
        $('#end-date').pDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            onSelect: (unixDate) => {
                const date = new Date(unixDate);
                const gregorianDate = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                $('#end-gregorian').val(gregorianDate);
            }
        });
    } else {
        console.error('No PersianDatepicker loaded');
    }

    function fetchRemindersAndReports(startDate, endDate, status = '') {
        console.log('Fetching:', { startDate, endDate, status });
        $('#loading-message').show();
        $.ajax({
            url: 'fetch_reminders_reports.php',
            method: 'GET',
            data: { start_date: startDate, end_date: endDate, status: status },
            dataType: 'json',
            success: (data) => {
                console.log('Data received:', data);
                let remindersHtml = data.reminders.length === 0 ? 
                    '<p class="text-gray-600 text-sm">هیچ یادآوری‌ای برای این بازه زمانی یافت نشد.</p>' :
                    '<div class="space-y-4">' + data.reminders.map(reminder => 
                        `<div class="bg-gray-50 p-4 rounded-lg shadow-sm hover:shadow-md transition">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-calendar-alt text-blue-600"></i>
                                <span class="text-sm text-gray-600">${reminder.date || 'نامشخص'}</span>
                            </div>
                            <a href="task_status.php?reminder_id=${reminder.id}" class="block text-sm text-gray-900 hover:text-blue-600">${reminder.description}</a>
                        </div>`).join('') + '</div>';
                $('#reminders-list').html(remindersHtml);

                let reportsHtml = data.reports.length === 0 ? 
                    '<p class="text-gray-600 text-sm">هیچ گزارشی برای این بازه زمانی یافت نشد.</p>' :
                    '<div class="space-y-4">' + data.reports.map(report => 
                        `<div class="bg-gray-50 p-4 rounded-lg shadow-sm hover:shadow-md transition">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-calendar-alt text-blue-600"></i>
                                <span class="text-sm text-gray-600">${report.date || '<?php echo $filter_date_jalali; ?>'}</span>
                            </div>
                            <a href="daily_reports.php?filter_date=${report.date || '<?php echo $filter_date; ?>'}" class="block text-sm text-gray-900 hover:text-blue-600">${report.description}</a>
                        </div>`).join('') + '</div>';
                $('#reports-list').html(reportsHtml);

                $('#reminders-count').text(data.reminders_count || data.reports.length);
                $('#reports-count').text(data.reports_count || data.reports.length);
                $('#loading-message').hide();
            },
            error: (xhr, status, error) => {
                console.error('AJAX error:', status, error);
                alert('خطا در بارگذاری داده‌ها');
                $('#loading-message').hide();
            }
        });
    }

    function fetchNotifications() {
        $.ajax({
            url: 'fetch_notifications.php',
            method: 'GET',
            dataType: 'json',
            success: (data) => {
                if (data.new_notifications && data.new_notifications.length > 0) {
                    data.new_notifications.forEach(note => showNotification(note.message));
                }
            },
            error: (xhr, status, error) => console.error('Notifications error:', status, error),
            complete: () => setTimeout(fetchNotifications, 30000)
        });
    }

    function showNotification(message) {
        $('#notification-message').text(message);
        $('#notification-popup').removeClass('hidden').addClass('block');
        setTimeout(() => $('#notification-popup').removeClass('block').addClass('hidden'), 5000);
    }

    $('#close-notification').on('click', () => $('#notification-popup').removeClass('block').addClass('hidden'));
    $('#filter-btn').on('click', () => fetchRemindersAndReports($('#start-gregorian').val(), $('#end-gregorian').val(), $('#reminder-status').val()));
    $('#reset-btn').on('click', () => {
        $('#start-date').val(todayJalali);
        $('#end-date').val(todayJalali);
        $('#start-gregorian').val('<?php echo $today; ?>');
        $('#end-gregorian').val('<?php echo $today; ?>');
        fetchRemindersAndReports('<?php echo $today; ?>', '<?php echo $today; ?>');
    });

    fetchRemindersAndReports('<?php echo $today; ?>', '<?php echo $today; ?>');
    fetchNotifications();

    $(document).on('click', '.change-status-btn', function(e) {
        e.preventDefault();
        const dropdown = $(this).siblings('.status-dropdown');
        $('.status-dropdown').not(dropdown).removeClass('show');
        dropdown.toggleClass('show');
    });

    $(document).on('click', (e) => {
        if (!$(e.target).closest('.change-status-btn').length && !$(e.target).closest('.status-dropdown').length) {
            $('.status-dropdown').removeClass('show');
        }
    });

    $(document).on('click', '.change-status-option', function() {
        const newStatus = $(this).data('status');
        const taskId = $(this).closest('.status-dropdown').siblings('.change-status-btn').data('task-id');
        const currentStatus = $(this).closest('.status-dropdown').siblings('.change-status-btn').data('current-status');

        if (newStatus === currentStatus) {
            alert('وضعیت انتخاب‌شده با وضعیت فعلی یکسان است.');
            return;
        }

        $.ajax({
            url: 'update_task_status.php',
            method: 'POST',
            data: { task_id: taskId, new_status: newStatus },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    alert('وضعیت کار با موفقیت تغییر کرد.');
                    location.reload();
                } else {
                    alert('خطا در تغییر وضعیت: ' + response.message);
                }
            },
            error: (xhr, status, error) => {
                console.error('Status update error:', status, error);
                alert('خطا در تغییر وضعیت');
            }
        });
    });
});
</script>

<?php
ob_end_flush();
include 'footer.php';
?>