<?php
include 'config.php';
header('Content-Type: application/json');
$result = $conn->query("SELECT ci.department, ds.ip, ds.status FROM device_status ds JOIN contact_info ci ON ds.ip = ci.ip_address");
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = ['x' => $row['department'], 'y' => $row['ip'], 'status' => $row['status']];
}
echo json_encode($data);
?>