<?php 
include 'header.php'; 
include 'config.php'; // فایل تنظیمات دیتابیس

// دریافت شناسه اموال
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// دریافت اطلاعات اموال
$query = "SELECT * FROM assets WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$asset = $result->fetch_assoc();
$stmt->close();

if (!$asset) {
    echo "<p class='text-gray-600 mb-4'>اموال یافت نشد.</p>";
    include 'footer.php';
    exit;
}

// دریافت لیست واحدها
$departments = [];
$query = "SELECT DISTINCT department FROM contact_info ORDER BY department";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// لیست انواع تجهیزات
$asset_types = ['رک', 'سوییچ', 'مودم', 'پرینتر', 'اسکنر', 'لپ‌تاپ', 'کیس'];

// حذف تصویر
if (isset($_POST['delete_image'])) {
    if (!empty($asset['image_path']) && file_exists($asset['image_path'])) {
        unlink($asset['image_path']); // حذف فایل تصویر
        $query = "UPDATE assets SET image_path = '' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        // به‌روزرسانی اطلاعات اموال
        $query = "SELECT * FROM assets WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $asset = $result->fetch_assoc();
        $stmt->close();
        echo "<script>alert('تصویر با موفقیت حذف شد.');</script>";
    }
}

// به‌روزرسانی اموال
if (isset($_POST['update_asset'])) {
    $asset_type = trim($_POST['asset_type']);
    $asset_number = trim($_POST['asset_number']);
    $model = trim($_POST['model']);
    $department = trim($_POST['department']);
    $image_path = $asset['image_path'];

    // آپلود تصویر جدید
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
            // تصویر با موفقیت آپلود شد
            // حذف تصویر قدیمی اگه وجود داشته باشه
            if (!empty($asset['image_path']) && file_exists($asset['image_path'])) {
                unlink($asset['image_path']);
            }
        } else {
            $message = 'خطا در آپلود تصویر.';
        }
    }

    // به‌روزرسانی اموال توی دیتابیس
    $query = "UPDATE assets SET asset_type = ?, asset_number = ?, model = ?, department = ?, image_path = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssi", $asset_type, $asset_number, $model, $department, $image_path, $id);
    if ($stmt->execute()) {
        echo "<script>alert('اموال با موفقیت به‌روزرسانی شد.'); window.location.href='assets_management.php';</script>";
    } else {
        echo "<script>alert('خطا در به‌روزرسانی اموال: " . addslashes($conn->error) . "');</script>";
    }
    $stmt->close();
}
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-100 shadow-2xl rounded-xl p-8 transition-all duration-300">
        <!-- هدر صفحه -->
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-edit text-blue-600 mr-2"></i>
                ویرایش اموال
            </h2>
            <a href="assets_management.php" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                <i class="fas fa-arrow-right mr-2"></i>
                بازگشت به مدیریت اموال
            </a>
        </div>

        <!-- فرم ویرایش اموال -->
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div class="flex flex-col md:flex-row md:space-x-4 md:space-x-reverse">
                <div class="flex-1">
                    <label for="asset_type" class="block text-sm font-medium text-gray-700">نوع تجهیزات</label>
                    <select id="asset_type" name="asset_type" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" required>
                        <option value="">انتخاب نوع تجهیزات</option>
                        <?php foreach ($asset_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $asset['asset_type'] === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <label for="asset_number" class="block text-sm font-medium text-gray-700">شماره اموال</label>
                    <input type="text" id="asset_number" name="asset_number" value="<?php echo htmlspecialchars($asset['asset_number']); ?>" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" required>
                </div>
            </div>
            <div class="flex flex-col md:flex-row md:space-x-4 md:space-x-reverse">
                <div class="flex-1">
                    <label for="model" class="block text-sm font-medium text-gray-700">مدل</label>
                    <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($asset['model']); ?>" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" required>
                </div>
                <div class="flex-1">
                    <label for="department" class="block text-sm font-medium text-gray-700">واحد استقرار</label>
                    <select id="department" name="department" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full" required>
                        <option value="">انتخاب واحد</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $asset['department'] === $dept ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label for="image" class="block text-sm font-medium text-gray-700">آپلود تصویر جدید (اختیاری)</label>
                <input type="file" id="image" name="image" accept="image/*" class="h-12 px-4 py-2 text-base border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all duration-300 shadow-sm hover:shadow-md w-full">
                <?php if (!empty($asset['image_path'])): ?>
                    <p class="text-gray-600 mt-2">تصویر فعلی:</p>
                    <img src="<?php echo htmlspecialchars($asset['image_path']); ?>" alt="تصویر اموال" class="w-32 h-32 object-cover rounded mt-2">
                    <button type="submit" name="delete_image" class="mt-2 bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-sm hover:shadow-md transform hover:-translate-y-1 flex items-center">
                        <i class="fas fa-trash mr-2"></i>
                        حذف تصویر
                    </button>
                <?php endif; ?>
            </div>
            <button type="submit" name="update_asset" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center">
                <i class="fas fa-save mr-2"></i>
                به‌روزرسانی اموال
            </button>
        </form>
    </div>
</main>

<style>
/* استایل اختصاصی برای متن‌های جدول */
.table-text {
    color: #111827 !important; /* text-gray-900 */
}
</style>

<?php 
ob_end_flush();
include 'footer.php'; 
?>