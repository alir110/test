<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('zlib.output_compression', 'Off');

function logMessage($message) {
    $log_file = 'debug_log.txt';
    $current_time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$current_time] $message\n", FILE_APPEND);
}

logMessage("Starting assets_management.php");

require 'vendor/autoload.php';
require 'jdf.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Intervention\Image\ImageManager;

try {
    $manager = new ImageManager(['driver' => 'gd']);
    logMessage("ImageManager instantiated successfully");
} catch (Exception $e) {
    logMessage("Failed to instantiate ImageManager: " . $e->getMessage());
    die("خطا در ایجاد ImageManager: " . $e->getMessage());
}

ob_start();
include 'header.php';
include 'config.php';

$message = '';
$filter_asset_type = isset($_GET['asset_type']) ? trim($_GET['asset_type']) : '';
$filter_asset_number = isset($_GET['asset_number']) ? trim($_GET['asset_number']) : '';
$filter_model = isset($_GET['model']) ? trim($_GET['model']) : '';
$filter_department = isset($_GET['department']) ? trim($_GET['department']) : '';

// ثبت اموال جدید
if (isset($_POST['add_asset'])) {
    logMessage("Processing add_asset form");
    $asset_type = trim($_POST['asset_type']);
    $asset_number = trim($_POST['asset_number']);
    $model = trim($_POST['model']);
    $department = trim($_POST['department']);
    $image_path = '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
            try {
                $img = $manager->make($image_path);
                $img->resize(1200, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                $webp_path = $upload_dir . pathinfo($image_name, PATHINFO_FILENAME) . '.webp';
                $img->save($webp_path, 80, 'webp');
                $img->save($image_path, 80);
                logMessage("Image compressed and saved: $image_path and $webp_path");
            } catch (Exception $e) {
                $message = 'خطا در فشرده‌سازی تصویر: ' . $e->getMessage();
                $image_path = '';
            }
        } else {
            $message = 'خطا در آپلود تصویر.';
        }
    }

    $query = "INSERT INTO assets (asset_type, asset_number, model, department, image_path) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssss", $asset_type, $asset_number, $model, $department, $image_path);
    if ($stmt->execute()) $message = 'اموال با موفقیت ثبت شد.';
    else $message = 'خطا در ثبت اموال: ' . $conn->error;
    $stmt->close();
}

// آپلود فایل اکسل
if (isset($_POST['upload_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $reader = new XlsxReader();
        $spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $id = $sheet->getCell('A' . $row)->getValue();
            $asset_type = $sheet->getCell('B' . $row)->getValue();
            $asset_number = $sheet->getCell('C' . $row)->getValue();
            $model = $sheet->getCell('D' . $row)->getValue();
            $department = $sheet->getCell('E' . $row)->getValue();
            $image_path = $sheet->getCell('F' . $row)->getValue();

            if (empty($asset_type) || empty($asset_number) || empty($model) || empty($department)) continue;

            $query = "SELECT id FROM assets WHERE asset_number = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $asset_number);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $query = "UPDATE assets SET asset_type = ?, model = ?, department = ?, image_path = ? WHERE asset_number = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $asset_type, $model, $department, $image_path, $asset_number);
            } else {
                $query = "INSERT INTO assets (asset_type, asset_number, model, department, image_path) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $asset_type, $asset_number, $model, $department, $image_path);
            }
            $stmt->execute();
            $stmt->close();
        }
        $message = 'داده‌ها با موفقیت از فایل اکسل وارد شدند.';
    } else {
        $message = 'خطا در آپلود فایل اکسل.';
    }
}

// دریافت لیست اموال با فیلتر
$query = "SELECT * FROM assets WHERE 1=1";
$params = [];
$types = '';
if (!empty($filter_asset_type)) {
    $query .= " AND asset_type = ?";
    $params[] = $filter_asset_type;
    $types .= 's';
}
if (!empty($filter_asset_number)) {
    $query .= " AND asset_number LIKE ?";
    $params[] = "%$filter_asset_number%";
    $types .= 's';
}
if (!empty($filter_model)) {
    $query .= " AND model LIKE ?";
    $params[] = "%$filter_model%";
    $types .= 's';
}
if (!empty($filter_department)) {
    $query .= " AND department = ?";
    $params[] = $filter_department;
    $types .= 's';
}
$query .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$assets = [];
while ($row = $result->fetch_assoc()) $assets[] = $row;
$stmt->close();

$departments = [];
$query = "SELECT DISTINCT department FROM contact_info ORDER BY department";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) $departments[] = $row['department'];

$asset_types = ['رک', 'سوییچ', 'مودم', 'پرینتر', 'اسکنر', 'لپ‌تاپ', 'کیس', 'دستگاه حضور و غیاب', 'دستگاه رزرو غذا'];

// خروجی اکسل
if (isset($_POST['export_excel'])) {
    ob_end_clean();
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'شناسه')->setCellValue('B1', 'نوع تجهیزات')->setCellValue('C1', 'شماره اموال')
          ->setCellValue('D1', 'مدل')->setCellValue('E1', 'واحد استقرار')->setCellValue('F1', 'تاریخ ثبت')
          ->setCellValue('G1', 'تاریخ به‌روزرسانی')->setCellValue('H1', 'مسیر تصویر');
    $rowNumber = 2;
    foreach ($assets as $row) {
        $sheet->setCellValue('A' . $rowNumber, $row['id'])->setCellValue('B' . $rowNumber, $row['asset_type'])
              ->setCellValue('C' . $rowNumber, $row['asset_number'])->setCellValue('D' . $rowNumber, $row['model'])
              ->setCellValue('E' . $rowNumber, $row['department'])->setCellValue('F' . $rowNumber, $row['created_at'])
              ->setCellValue('G' . $rowNumber, $row['updated_at'])->setCellValue('H' . $rowNumber, $row['image_path']);
        $rowNumber++;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="assets_list.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-100 shadow-2xl rounded-xl p-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-boxes text-blue-600 mr-2"></i> مدیریت اموال
            </h2>
            <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                <i class="fas fa-home mr-2"></i> بازگشت
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <!-- فرم فیلتر پیشرفته -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">فیلتر اموال</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">نوع تجهیزات</label>
                    <select name="asset_type" class="h-12 px-4 py-2 border rounded-lg w-full">
                        <option value="">همه</option>
                        <?php foreach ($asset_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filter_asset_type === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">شماره اموال</label>
                    <input type="text" name="asset_number" value="<?php echo htmlspecialchars($filter_asset_number); ?>" class="h-12 px-4 py-2 border rounded-lg w-full" placeholder="جستجو...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">مدل</label>
                    <input type="text" name="model" value="<?php echo htmlspecialchars($filter_model); ?>" class="h-12 px-4 py-2 border rounded-lg w-full" placeholder="جستجو...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">واحد</label>
                    <select name="department" class="h-12 px-4 py-2 border rounded-lg w-full">
                        <option value="">همه</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $filter_department === $dept ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 md:col-span-4">فیلتر</button>
            </form>
        </div>

        <!-- فرم ثبت اموال -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">ثبت اموال جدید</h3>
            <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm text-gray-700">نوع تجهیزات</label><select name="asset_type" class="h-12 px-4 py-2 border rounded-lg w-full" required><option value="">انتخاب</option><?php foreach ($asset_types as $type): ?><option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm text-gray-700">شماره اموال</label><input type="text" name="asset_number" class="h-12 px-4 py-2 border rounded-lg w-full" required></div>
                <div><label class="block text-sm text-gray-700">مدل</label><input type="text" name="model" class="h-12 px-4 py-2 border rounded-lg w-full" required></div>
                <div><label class="block text-sm text-gray-700">واحد</label><select name="department" class="h-12 px-4 py-2 border rounded-lg w-full" required><option value="">انتخاب</option><?php foreach ($departments as $dept): ?><option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm text-gray-700">تصویر</label><input type="file" name="image" accept="image/*" class="h-12 px-4 py-2 border rounded-lg w-full"></div>
                <button type="submit" name="add_asset" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 md:col-span-2"><i class="fas fa-plus mr-2"></i> ثبت</button>
            </form>
        </div>

        <!-- فرم آپلود اکسل -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">آپلود فایل اکسل</h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div><label class="block text-sm text-gray-700">فایل اکسل</label><input type="file" name="excel_file" accept=".xlsx" class="h-12 px-4 py-2 border rounded-lg w-full" required></div>
                <button type="submit" name="upload_excel" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600"><i class="fas fa-upload mr-2"></i> آپلود</button>
            </form>
        </div>

        <!-- جدول اموال -->
        <div class="overflow-x-auto">
            <form method="POST"><button type="submit" name="export_excel" class="mb-4 bg-teal-500 text-white px-4 py-2 rounded-lg hover:bg-teal-600"><i class="fas fa-file-excel mr-2"></i> خروجی اکسل</button></form>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">شماره</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">نوع</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">شماره اموال</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">مدل</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">واحد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">تصویر</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">تاریخ ثبت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="assets-table">
                    <?php $index = 1; foreach ($assets as $row): ?>
                        <tr data-id="<?php echo $row['id']; ?>">
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo $index++; ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 editable" data-field="asset_type"><?php echo htmlspecialchars($row['asset_type']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 editable" data-field="asset_number"><?php echo htmlspecialchars($row['asset_number']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 editable" data-field="model"><?php echo htmlspecialchars($row['model']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 editable" data-field="department"><?php echo htmlspecialchars($row['department']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php if (!empty($row['image_path']) && file_exists($row['image_path'])): ?>
                                    <a href="#" onclick="openLightbox('<?php echo htmlspecialchars(file_exists("uploads/" . pathinfo($row['image_path'], PATHINFO_FILENAME) . '.webp') ? "uploads/" . pathinfo($row['image_path'], PATHINFO_FILENAME) . '.webp' : $row['image_path']); ?>'); return false;">
                                        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="تصویر" class="w-16 h-16 object-cover rounded cursor-pointer">
                                    </a>
                                <?php else: ?>
                                    بدون تصویر
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars(jdate('Y/m/d H:i:s', strtotime($row['created_at']))); ?></td>
                            <td class="px-6 py-4 text-sm flex space-x-2 space-x-reverse">
                                <a href="view_asset.php?id=<?php echo $row['id']; ?>" target="_blank" class="bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600">نمایش</a>
                                <button class="edit-btn bg-green-500 text-white px-3 py-1 rounded-lg hover:bg-green-600">ذخیره</button>
                                <a href="delete_asset.php?id=<?php echo $row['id']; ?>" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600" onclick="return confirm('مطمئن هستید؟');">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- لایت‌باکس -->
<div id="lightbox" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
    <div class="relative bg-white rounded-lg p-4 max-w-3xl w-full mx-4">
        <img id="lightbox-image" src="" alt="تصویر بزرگ" class="w-full max-h-[80vh] object-contain rounded-md">
        <button onclick="closeLightbox()" class="absolute top-2 right-2 text-gray-600 text-3xl hover:text-red-500">×</button>
    </div>
</div>

<script>
// لایت‌باکس
function openLightbox(imageSrc) {
    const lightbox = document.getElementById('lightbox');
    const image = document.getElementById('lightbox-image');
    image.src = imageSrc;
    lightbox.classList.remove('hidden');
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    const image = document.getElementById('lightbox-image');
    image.src = '';
    lightbox.classList.add('hidden');
}

document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});

// ویرایش سریع
document.querySelectorAll('.editable').forEach(cell => {
    cell.addEventListener('click', function() {
        if (this.querySelector('input')) return;
        const original = this.textContent;
        const field = this.dataset.field;
        const input = document.createElement('input');
        input.type = 'text';
        input.value = original;
        input.className = 'w-full px-2 py-1 border rounded';
        this.innerHTML = '';
        this.appendChild(input);
        input.focus();

        input.addEventListener('blur', saveEdit);
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') saveEdit.call(this);
        });

        function saveEdit() {
            const newValue = this.value;
            const id = this.closest('tr').dataset.id;
            fetch('edit_asset_inline.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&field=${field}&value=${encodeURIComponent(newValue)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    cell.textContent = newValue;
                } else {
                    alert('خطا: ' + data.message);
                    cell.textContent = original;
                }
            })
            .catch(() => {
                alert('خطا در ارتباط با سرور');
                cell.textContent = original;
            });
        }
    });
});
</script>

<style>
.table-text { color: #111827 !important; }
</style>

<?php
logMessage("Finished rendering assets_management.php");
if (ob_get_level() > 0) ob_end_flush();
include 'footer.php';
?>