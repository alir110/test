<?php
include 'config.php'; // فایل تنظیمات دیتابیس

// دریافت واحد انتخاب‌شده از درخواست ایجکس
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

// ساخت کوئری برای دریافت سیستم‌ها
$query = "SELECT ci.id, ci.full_name, ci.ip_address, ci.department, ci.extension, 
                 si.computer_name, si.username, si.windows_version, si.office_version, si.antivirus 
          FROM contact_info ci 
          LEFT JOIN software_info si ON ci.id = si.contact_id 
          WHERE 1=1";
$params = [];
$types = '';

if ($filter === 'department' && !empty($department)) {
    $query .= " AND ci.department = ?";
    $params[] = $department;
    $types .= 's';
} elseif ($filter === 'needs_update') {
    $query .= " AND (si.windows_version IN ('Windows 7', 'Windows 8.1') OR si.office_version IN ('Office 2010', 'Office 2013', 'Office 2016'))";
}

$query .= " ORDER BY ci.created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$results = [];
while ($row = $result->fetch_assoc()) {
    $results[] = $row;
}
$stmt->close();

// تولید HTML برای دسکتاپ و موبایل
if (empty($results)) {
    echo '<p class="text-gray-600 mb-4 text-sm md:text-base">هیچ رکوردی یافت نشد.</p>';
} else {
    // جدول برای دسکتاپ
    echo '<div class="hidden md:block">';
    echo '<table class="min-w-full divide-y divide-gray-200">';
    echo '<thead class="bg-gradient-to-r from-gray-100 to-gray-200">';
    echo '<tr>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">شماره</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">نام و نام خانوادگی</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">IP</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">واحد</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">داخلی</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">نام کامپیوتر</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">نام کاربری</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">نسخه ویندوز</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">نسخه آفیس</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">آنتی‌ویروس</th>';
    echo '<th class="px-4 md:px-6 py-2 md:py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">عملیات</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody class="bg-white divide-y divide-gray-200">';
    $index = 1;
    foreach ($results as $row) {
        echo '<tr>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . $index++ . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['full_name']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['ip_address']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['department']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['extension']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['computer_name']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['username']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['windows_version']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['office_version']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['antivirus']) . '</td>';
        echo '<td class="px-4 md:px-6 py-3 md:py-4 whitespace-nowrap text-sm font-medium flex space-x-2 space-x-reverse">';
        echo '<a href="view.php?id=' . $row['id'] . '" target="_blank" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-2 md:px-3 py-1 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1 text-xs md:text-sm">نمایش</a>';
        echo '<a href="edit.php?id=' . $row['id'] . '" target="_blank" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-2 md:px-3 py-1 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1 text-xs md:text-sm">ویرایش</a>';
        echo '<a href="delete.php?id=' . $row['id'] . '" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-2 md:px-3 py-1 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1 text-xs md:text-sm" onclick="return confirm(\'آیا مطمئن هستید که می‌خواهید این رکورد را حذف کنید؟\');">حذف</a>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';

    // کارت‌ها برای موبایل
    echo '<div class="md:hidden space-y-4">';
    $index = 1;
    foreach ($results as $row) {
        echo '<div class="mobile-card bg-gray-50 rounded-lg p-4 shadow-md">';
        echo '<div class="grid grid-cols-1 gap-2 text-sm">';
        echo '<p><strong>شماره:</strong> ' . $index++ . '</p>';
        echo '<p><strong>نام:</strong> ' . htmlspecialchars($row['full_name']) . '</p>';
        echo '<p><strong>IP:</strong> ' . htmlspecialchars($row['ip_address']) . '</p>';
        echo '<p><strong>واحد:</strong> ' . htmlspecialchars($row['department']) . '</p>';
        echo '<p><strong>داخلی:</strong> ' . htmlspecialchars($row['extension']) . '</p>';
        echo '<p><strong>نام کامپیوتر:</strong> ' . htmlspecialchars($row['computer_name']) . '</p>';
        echo '<p><strong>نام کاربری:</strong> ' . htmlspecialchars($row['username']) . '</p>';
        echo '<p><strong>ویندوز:</strong> ' . htmlspecialchars($row['windows_version']) . '</p>';
        echo '<p><strong>آفیس:</strong> ' . htmlspecialchars($row['office_version']) . '</p>';
        echo '<p><strong>آنتی‌ویروس:</strong> ' . htmlspecialchars($row['antivirus']) . '</p>';
        echo '</div>';
        echo '<div class="mt-3 flex flex-col space-y-2">';
        echo '<a href="view.php?id=' . $row['id'] . '" target="_blank" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-3 py-1 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 shadow-sm hover:shadow-md text-center text-sm">نمایش</a>';
        echo '<a href="edit.php?id=' . $row['id'] . '" target="_blank" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-3 py-1 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-sm hover:shadow-md text-center text-sm">ویرایش</a>';
        echo '<a href="delete.php?id=' . $row['id'] . '" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 py-1 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-sm hover:shadow-md text-center text-sm" onclick="return confirm(\'آیا مطمئن هستید که می‌خواهید این رکورد را حذف کنید؟\');">حذف</a>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}
?>

<style>
/* استایل اختصاصی برای متن‌های جدول و کارت */
.table-text {
    color: #111827 !important; /* text-gray-900 */
}
</style>