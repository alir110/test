<?php
include 'config.php';
$result = $conn->query("SELECT (SELECT COUNT(*) FROM device_status WHERE status = 'online') as online, (SELECT COUNT(*) FROM device_status WHERE status = 'offline') as offline");
$data = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode($data);
?>