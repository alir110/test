<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo "خطا: شناسه سیستم نامعتبر است.";
        exit;
    }

    try {
        $query = "SELECT ci.*, si.*, hi.* 
                 FROM contact_info ci 
                 LEFT JOIN software_info si ON ci.id = si.contact_id 
                 LEFT JOIN hardware_info hi ON ci.id = hi.contact_id 
                 WHERE ci.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $system = $result->fetch_assoc();
        $stmt->close();

        if ($system) {
            echo "<p><strong>نام و نام خانوادگی:</strong> " . htmlspecialchars($system['full_name']) . "</p>";
            echo "<p><strong>IP کامپیوتر:</strong> " . htmlspecialchars($system['ip_address']) . "</p>";
            echo "<p><strong>واحد:</strong> " . htmlspecialchars($system['department']) . "</p>";
            echo "<p><strong>داخلی:</strong> " . htmlspecialchars($system['extension']) . "</p>";
            echo "<p><strong>نام کامپیوتر:</strong> " . htmlspecialchars($system['computer_name']) . "</p>";
            echo "<p><strong>نسخه ویندوز:</strong> " . htmlspecialchars($system['windows_version']) . "</p>";
            echo "<p><strong>آنتی‌ویروس:</strong> " . htmlspecialchars($system['antivirus']) . "</p>";
            echo "<p><strong>رم:</strong> " . htmlspecialchars($system['ram']) . "</p>";
        } else {
            echo "سیستم مورد نظر یافت نشد.";
        }
    } catch (Exception $e) {
        echo "خطا در بارگذاری جزئیات سیستم: " . $e->getMessage();
    }
} else {
    echo "درخواست نامعتبر است.";
}
?>