<?php
include 'config.php';

$dept = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';
$win = $_GET['windows'] ?? '';
$ram = $_GET['ram'] ?? '';
$antivirus = $_GET['antivirus'] ?? '';

$query = "SELECT ci.full_name, ci.ip_address, ci.department, ci.extension, si.computer_name, si.windows_version, si.antivirus, hi.ram, ds.status 
          FROM contact_info ci 
          LEFT JOIN software_info si ON ci.id = si.contact_id 
          LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
          LEFT JOIN device_status ds ON ci.ip_address = ds.ip 
          WHERE 1=1";
$params = [];
$types = '';

if ($dept) { $query .= " AND ci.department = ?"; $params[] = $dept; $types .= 's'; }
if ($status) { $query .= " AND ds.status = ?"; $params[] = $status; $types .= 's'; }
if ($win) { $query .= " AND si.windows_version = ?"; $params[] = $win; $types .= 's'; }
if ($ram) { $query .= " AND hi.ram = ?"; $params[] = $ram; $types .= 's'; }
if ($antivirus) { $query .= " AND si.antivirus = ?"; $params[] = $antivirus; $types .= 's'; }

$query .= " ORDER BY ci.created_at DESC LIMIT 5";
$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td class='p-2'>" . htmlspecialchars($row['full_name'] ?? 'نامشخص') . "</td>
                <td class='p-2'>" . htmlspecialchars($row['ip_address'] ?? 'نامشخص') . "</td>
                <td class='p-2'>" . htmlspecialchars($row['department'] ?? 'نامشخص') . "</td>
                <td class='p-2'>" . htmlspecialchars($row['extension'] ?? 'نامشخص') . "</td>
                <td class='p-2'>" . htmlspecialchars($row['computer_name'] ?? 'نامشخص') . "</td>
                <td class='p-2'>" . htmlspecialchars($row['windows_version'] ?? 'نامشخص') . "</td>
                <td class='p-2'>" . htmlspecialchars($row['antivirus'] ?? 'نامشخص') . "</td>
                <td class='p-2'>" . htmlspecialchars($row['ram'] ?? 'نامشخص') . "</td>
                <td class='p-2 status-" . ($row['status'] ?? 'unknown') . "'>" . ($row['status'] ?? 'نامشخص') . "</td>
                <td class='p-2 flex space-x-2'>
                    <button class='btn text-xs bg-green-500' onclick=\"controlDevice('restart', '" . htmlspecialchars($row['ip_address'] ?? '') . "')\">ری‌استارت</button>
                    <button class='btn text-xs bg-red-500' onclick=\"controlDevice('shutdown', '" . htmlspecialchars($row['ip_address'] ?? '') . "')\">خاموش</button>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='10' class='p-2 text-center'>سیستمی یافت نشد</td></tr>";
}
$stmt->close();
?>