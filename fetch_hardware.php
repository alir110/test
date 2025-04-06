<?php
include 'config.php';
header('Content-Type: application/json');
$type = isset($_GET['type']) ? $_GET['type'] : 'cpu';
$ram_2gb = $conn->query("SELECT COUNT(*) as count FROM hardware_info WHERE ram = '2GB'")->fetch_assoc()['count'];
$ram_4gb = $conn->query("SELECT COUNT(*) as count FROM hardware_info WHERE ram = '4GB'")->fetch_assoc()['count'];
$ram_8gb = $conn->query("SELECT COUNT(*) as count FROM hardware_info WHERE ram = '8GB'")->fetch_assoc()['count'];
$ram_16gb = $conn->query("SELECT COUNT(*) as count FROM hardware_info WHERE ram = '16GB'")->fetch_assoc()['count'];
$ssd_count = $conn->query("SELECT COUNT(*) as count FROM hardware_info WHERE disk_type = 'SSD'")->fetch_assoc()['count'];
$hdd_count = $conn->query("SELECT COUNT(*) as count FROM hardware_info WHERE disk_type = 'HDD'")->fetch_assoc()['count'];
$hybrid_count = $conn->query("SELECT COUNT(*) as count FROM hardware_info WHERE disk_type = 'Hybrid'")->fetch_assoc()['count'];
$cpu_query = "SELECT processor, COUNT(*) as count FROM hardware_info GROUP BY processor ORDER BY count DESC LIMIT 3";
$cpu_result = $conn->query($cpu_query);
$labels = [];
$counts = [];
if ($cpu_result->num_rows > 0) {
    while ($row = $cpu_result->fetch_assoc()) {
        $labels[] = $row['processor'];
        $counts[] = $row['count'];
    }
} else {
    $labels = ['داده‌ای یافت نشد'];
    $counts = [0];
}
echo json_encode([
    'labels' => $labels,
    'counts' => $counts,
    'ram_2gb' => $ram_2gb,
    'ram_4gb' => $ram_4gb,
    'ram_8gb' => $ram_8gb,
    'ram_16gb' => $ram_16gb,
    'ssd_count' => $ssd_count,
    'hdd_count' => $hdd_count,
    'hybrid_count' => $hybrid_count
]);
?>