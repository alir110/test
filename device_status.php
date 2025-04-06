<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
include 'header.php';
include 'config.php';

$query = "SELECT ip, status, timestamp, cpu, ram, disk, windows_active, office_version, antivirus, last_login 
          FROM device_status 
          ORDER BY timestamp DESC";
$result = $conn->query($query);
$devices = [];
while ($row = $result->fetch_assoc()) $devices[] = $row;

$status_stats = ['online' => 0, 'offline' => 0];
foreach ($devices as $device) {
    if ($device['status'] === 'online') $status_stats['online']++;
    else $status_stats['offline']++;
}
?>

<main class="container mx-auto py-8 px-4">
    <div class="bg-gradient-to-br from-white to-gray-50 shadow-2xl rounded-xl p-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-server text-blue-600 mr-3"></i> مانیتورینگ دستگاه‌ها
            </h2>
            <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center transition-all">
                <i class="fas fa-home mr-2"></i> بازگشت
            </a>
        </div>

        <!-- هدر با اطلاعات کلی -->
        <div class="mb-8 bg-blue-50 p-6 rounded-lg shadow-md flex justify-between items-center">
            <div>
                <h3 class="text-xl font-semibold text-gray-700">وضعیت کلی</h3>
                <p class="text-gray-600">تعداد کل دستگاه‌ها: <span class="font-bold"><?php echo count($devices); ?></span></p>
                <p class="text-gray-600">دستگاه‌های آنلاین: <span class="font-bold text-green-600"><?php echo $status_stats['online']; ?></span></p>
                <p class="text-gray-600">دستگاه‌های آفلاین: <span class="font-bold text-red-600"><?php echo $status_stats['offline']; ?></span></p>
            </div>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- جدول دستگاه‌ها -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <p id="loading-message" class="text-gray-600 mb-4 hidden">در حال بارگذاری...</p>
            <div id="record-count" class="text-gray-600 mb-4">تعداد دستگاه‌ها: <?php echo count($devices); ?></div>
            <input type="text" id="search-ip" class="mb-4 px-4 py-2 border rounded-lg w-full md:w-1/3 focus:ring focus:ring-blue-200 transition-all" placeholder="جستجو بر اساس IP...">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">IP</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">آخرین به‌روزرسانی</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">CPU</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">RAM</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">دیسک</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">ویندوز فعال</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">نسخه آفیس</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">آنتی‌ویروس</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase">آخرین ورود</th>
                    </tr>
                </thead>
                <tbody id="devices-table" class="divide-y divide-gray-200">
                    <?php foreach ($devices as $device): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($device['ip']); ?></td>
                            <td class="px-6 py-4 text-sm <?php echo $device['status'] === 'online' ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                <i class="fas fa-circle mr-2 <?php echo $device['status'] === 'online' ? 'text-green-600' : 'text-red-600'; ?>"></i>
                                <?php echo htmlspecialchars($device['status']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($device['timestamp']))); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($device['cpu'] ?: 'نامشخص'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($device['ram'] ?: 'نامشخص'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($device['disk'] ?: 'نامشخص'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($device['windows_active'] ?: 'نامشخص'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($device['office_version'] ?: 'نامشخص'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($device['antivirus'] ?: 'نامشخص'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($device['last_login'] ?: 'نامشخص'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function updateDevices() {
    const searchIp = document.getElementById('search-ip').value;
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `fetch_devices.php?search_ip=${encodeURIComponent(searchIp)}`, true);
    document.getElementById('loading-message').classList.remove('hidden');
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            document.getElementById('devices-table').innerHTML = xhr.responseText;
            const rows = document.querySelectorAll('#devices-table tr').length;
            document.getElementById('record-count').innerHTML = 'تعداد دستگاه‌ها: ' + rows;
            document.getElementById('loading-message').classList.add('hidden');
        }
    };
    xhr.send();
}

setInterval(updateDevices, 5000); // هر 5 ثانیه به‌روزرسانی
document.getElementById('search-ip').addEventListener('input', updateDevices);

// نمودار وضعیت
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['آنلاین', 'آفلاین'],
        datasets: [{ 
            data: [<?php echo $status_stats['online']; ?>, <?php echo $status_stats['offline']; ?>], 
            backgroundColor: ['#34D399', '#F87171'], 
            borderWidth: 1,
            shadowOffsetX: 3,
            shadowOffsetY: 3,
            shadowBlur: 10,
            shadowColor: 'rgba(0, 0, 0, 0.1)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { position: 'top', labels: { font: { size: 12, family: "'Vazir', sans-serif" } } },
            tooltip: { backgroundColor: '#1F2937', titleFont: { size: 14 }, bodyFont: { size: 12 } }
        },
        animation: { duration: 1000, easing: 'easeOutQuart' },
        cutout: '70%'
    }
});

window.onload = updateDevices;
</script>

<style>
.table-text { color: #111827 !important; }
.shadow-md { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
.hover\:shadow-lg:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
.transition { transition: all 0.2s ease-in-out; }
.chart-container { max-width: 200px; max-height: 200px; margin: 0 auto; }
table { font-family: 'Vazir', sans-serif; }
thead { background-color: #EFF6FF !important; }
tbody tr { border-bottom: 1px solid #E5E7EB; }
td, th { padding: 12px 16px !important; }
</style>

<?php
ob_end_flush();
include 'footer.php';
?>