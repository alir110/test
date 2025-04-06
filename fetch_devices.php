<?php
include 'config.php';

$search_ip = isset($_GET['search_ip']) ? trim($_GET['search_ip']) : '';

$query = "SELECT ip, status, timestamp, cpu, ram, disk, windows_active, office_version, antivirus, last_login 
          FROM device_status 
          WHERE ip LIKE ?";
$stmt = $conn->prepare($query);
$search_term = "%$search_ip%";
$stmt->bind_param("s", $search_term);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "<tr class='hover:bg-gray-50 transition'>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['ip']) . "</td>";
    echo "<td class='px-6 py-4 text-sm " . ($row['status'] === 'online' ? 'text-green-600' : 'text-red-600') . "'>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars(date('Y-m-d H:i:s', strtotime($row['timestamp']))) . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['cpu'] ?: 'نامشخص') . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['ram'] ?: 'نامشخص') . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['disk'] ?: 'نامشخص') . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['windows_active'] ?: 'نامشخص') . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['office_version'] ?: 'نامشخص') . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['antivirus'] ?: 'نامشخص') . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['last_login'] ?: 'نامشخص') . "</td>";
    echo "</tr>";
}
$stmt->close();
?>