<?php
include 'config.php'; // فایل تنظیمات دیتابیس

// دریافت فیلترها از درخواست ایجکس
$filter_department = isset($_GET['filter_department']) ? trim($_GET['filter_department']) : '';
$filter_asset_type = isset($_GET['filter_asset_type']) ? trim($_GET['filter_asset_type']) : '';

// ساخت کوئری برای دریافت اموال
$query = "SELECT * FROM assets WHERE 1=1";
$params = [];
$types = '';

if (!empty($filter_department)) {
    $query .= " AND department = ?";
    $params[] = $filter_department;
    $types .= 's';
}
if (!empty($filter_asset_type)) {
    $query .= " AND asset_type = ?";
    $params[] = $filter_asset_type;
    $types .= 's';
}

$query .= " ORDER BY created_at DESC";
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

// تولید HTML جدول
if (empty($results)) {
    echo '<p class="text-gray-600 mb-4">هیچ رکوردی یافت نشد.</p>';
} else {
    echo '<table class="min-w-full divide-y divide-gray-200">';
    echo '<thead class="bg-gradient-to-r from-gray-100 to-gray-200">';
    echo '<tr>';
    echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">شماره</th>';
    echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">نوع اموال</th>';
    echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">نام اموال</th>';
    echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">واحد</th>';
    echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">محل استقرار</th>';
    echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">وضعیت</th>';
    echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">تاریخ ثبت</th>';
    echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">عملیات</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody class="bg-white divide-y divide-gray-200">';
    $index = 1;
    foreach ($results as $row) {
        echo '<tr>';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm table-text">' . $index++ . '</td>';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['asset_type']) . '</td>';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['asset_name']) . '</td>';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['department']) . '</td>';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['location']) . '</td>';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['status']) . '</td>';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm table-text">' . htmlspecialchars($row['created_at']) . '</td>';
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex space-x-2 space-x-reverse">';
        echo '<a href="view_asset.php?id=' . $row['id'] . '" target="_blank" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-3 py-1 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1">نمایش</a>';
        echo '<a href="edit_asset.php?id=' . $row['id'] . '" target="_blank" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-3 py-1 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1">ویرایش</a>';
        echo '<a href="delete_asset.php?id=' . $row['id'] . '" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 py-1 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1" onclick="return confirm(\'آیا مطمئن هستید که می‌خواهید این رکورد را حذف کنید؟\');">حذف</a>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}
?>

<style>
/* استایل اختصاصی برای متن‌های جدول */
.table-text {
    color: #111827 !important; /* text-gray-900 */
}
</style>