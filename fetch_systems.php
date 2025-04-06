<?php
include 'config.php';

// دریافت پارامترها
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$windows_version = isset($_GET['windows_version']) ? trim($_GET['windows_version']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// ساخت کوئری
$query = "SELECT ci.id, ci.full_name, ci.ip_address, ci.department, ci.extension, 
                 si.computer_name, si.windows_version, hi.ram, ds.status 
          FROM contact_info ci 
          LEFT JOIN software_info si ON ci.id = si.contact_id 
          LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
          LEFT JOIN device_status ds ON ci.ip_address = ds.ip 
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
$query .= " ORDER BY ci.created_at DESC";

// اجرای کوئری
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// بررسی تعداد نتایج
$rows = $result->num_rows;
if ($rows === 0) {
    echo "<tr><td colspan='9' class='px-6 py-4 text-sm text-gray-900 text-center'>هیچ سیستمی یافت نشد.</td></tr>";
} else {
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr class='hover:bg-gray-50 transition'>";
        echo "<td class='px-6 py-4 text-sm text-gray-900'>{$index}</td>";
        echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['ip_address']) . "</td>";
        echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['department']) . "</td>";
        echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['extension']) . "</td>";
        echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['computer_name']) . "</td>";
        echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['windows_version']) . "</td>";
        echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['ram']) . "</td>";
        echo "<td class='px-6 py-4 text-sm " . ($row['status'] === 'online' ? 'text-green-600' : 'text-red-600') . "'>" . htmlspecialchars($row['status'] ?: 'نامشخص') . "</td>";
        echo "</tr>";
        $index++;
    }
}

$stmt->close();
?>