<?php
include 'config.php'; // Database configuration file

// Set the content type to JSON
header('Content-Type: application/json');

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $ip_address = isset($_POST['ip_address']) ? trim($_POST['ip_address']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $extension = isset($_POST['extension']) ? trim($_POST['extension']) : '';
    $computer_name = isset($_POST['computer_name']) ? trim($_POST['computer_name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $windows_version = isset($_POST['windows_version']) ? trim($_POST['windows_version']) : '';
    $office_version = isset($_POST['office_version']) ? trim($_POST['office_version']) : '';
    $antivirus = isset($_POST['antivirus']) ? trim($_POST['antivirus']) : '';
    $motherboard = isset($_POST['motherboard']) ? trim($_POST['motherboard']) : '';
    $processor = isset($_POST['processor']) ? trim($_POST['processor']) : '';
    $ram = isset($_POST['ram']) ? trim($_POST['ram']) : '';
    $disk_type = isset($_POST['disk_type']) ? trim($_POST['disk_type']) : '';
    $disk_capacity = isset($_POST['disk_capacity']) ? trim($_POST['disk_capacity']) : '';
    $graphics_card = isset($_POST['graphics_card']) ? trim($_POST['graphics_card']) : '';
    $printers = isset($_POST['printer']) ? $_POST['printer'] : [];
    $scanners = isset($_POST['scanner']) ? $_POST['scanner'] : [];

    // Basic validation
    if (empty($full_name) || empty($ip_address) || empty($department) || empty($extension) ||
        empty($computer_name) || empty($username) || empty($password) || empty($windows_version) ||
        empty($office_version) || empty($antivirus) || empty($motherboard) || empty($processor) ||
        empty($ram) || empty($disk_type) || empty($disk_capacity) || empty($graphics_card)) {
        echo json_encode([
            'success' => false,
            'message' => 'لطفاً همه فیلدهای اجباری را پر کنید.'
        ]);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // 1. Insert into contact_info
        $stmt = $conn->prepare("INSERT INTO contact_info (full_name, ip_address, department, extension) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $full_name, $ip_address, $department, $extension);
        $stmt->execute();
        $contact_id = $conn->insert_id;
        $stmt->close();

        // 2. Insert into software_info
        $stmt = $conn->prepare("INSERT INTO software_info (contact_id, computer_name, username, password, windows_version, office_version, antivirus) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $contact_id, $computer_name, $username, $password, $windows_version, $office_version, $antivirus);
        $stmt->execute();
        $stmt->close();

        // 3. Insert into hardware_info
        $stmt = $conn->prepare("INSERT INTO hardware_info (contact_id, motherboard, processor, ram, disk_type, disk_capacity, graphics_card) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $contact_id, $motherboard, $processor, $ram, $disk_type, $disk_capacity, $graphics_card);
        $stmt->execute();
        $stmt->close();

        // 4. Insert printers
        if (!empty($printers)) {
            $stmt = $conn->prepare("INSERT INTO printers (contact_id, printer_model) VALUES (?, ?)");
            foreach ($printers as $printer) {
                $stmt->bind_param("is", $contact_id, $printer);
                $stmt->execute();
            }
            $stmt->close();
        }

        // 5. Insert scanners
        if (!empty($scanners)) {
            $stmt = $conn->prepare("INSERT INTO scanners (contact_id, scanner_model) VALUES (?, ?)");
            foreach ($scanners as $scanner) {
                $stmt->bind_param("is", $contact_id, $scanner);
                $stmt->execute();
            }
            $stmt->close();
        }

        // Commit transaction
        $conn->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'اطلاعات با موفقیت ثبت شد.'
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'خطا در ثبت اطلاعات: ' . $e->getMessage()
        ]);
    }

    // Close database connection
    $conn->close();
} else {
    // If not POST, return an error response
    echo json_encode([
        'success' => false,
        'message' => 'درخواست نامعتبر است.'
    ]);
}
?>