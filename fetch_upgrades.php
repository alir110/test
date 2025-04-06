<?php
include 'config.php';
header('Content-Type: application/json');
$result = $conn->query("SELECT ci.ip_address, ci.department, hi.ram, si.windows_version FROM contact_info ci JOIN hardware_info hi ON ci.id = hi.contact_id JOIN software_info si ON ci.id = si.contact_id WHERE hi.ram = '2GB' OR si.windows_version = 'Windows 7'");
$data = [];
while ($row = $result->fetch_assoc()) {
    $suggestion = $row['ram'] === '2GB' ? 'رم به 8GB' : 'ویندوز به 10/11';
    $data[] = ['ip_address' => $row['ip_address'], 'department' => $row['department'], 'suggestion' => $suggestion];
}
echo json_encode($data);
?>