<?php
include 'config.php';
header('Content-Type: application/json');
$q = isset($_GET['q']) ? $_GET['q'] : '';
$results = [];
if ($q) {
    $query = "SELECT full_name as value, 'سیستم' as type FROM contact_info WHERE full_name LIKE ? 
              UNION SELECT asset_number as value, 'اموال' as type FROM assets WHERE asset_number LIKE ?";
    $stmt = $conn->prepare($query);
    $like = "%$q%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $results[] = $row;
    $stmt->close();
}
echo json_encode($results);
?>