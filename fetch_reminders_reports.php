<?php
include 'config.php';

// توابع تبدیل تاریخ
function jalali_to_gregorian($jalali_date) {
    if (empty($jalali_date) || !is_string($jalali_date)) {
        return date('Y-m-d');
    }
    $jalali_date = explode('/', $jalali_date);
    if (count($jalali_date) != 3) {
        return date('Y-m-d');
    }
    $j_y = (int)$jalali_date[0];
    $j_m = (int)$jalali_date[1];
    $j_d = (int)$jalali_date[2];

    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $jy = $j_y - 979;
    $jm = $j_m - 1;
    $jd = $j_d - 1;

    $j_day_no = 365 * $jy + (int)($jy / 33) * 8 + (int)(($jy % 33 + 3) / 4);
    for ($i = 0; $i < $jm; ++$i) {
        $j_day_no += $j_days_in_month[$i];
    }
    $j_day_no += $jd;
    $g_day_no = $j_day_no + 79;

    $gy = 1600 + 400 * (int)($g_day_no / 146097);
    $g_day_no = $g_day_no % 146097;

    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * (int)($g_day_no / 36524);
        $g_day_no = $g_day_no % 36524;

        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = false;
        }
    }

    $gy += 4 * (int)($g_day_no / 1461);
    $g_day_no %= 1461;

    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += (int)($g_day_no / 365);
        $g_day_no = $g_day_no % 365;
    }

    for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++) {
        $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
    }
    $gm = $i + 1;
    $gd = $g_day_no + 1;

    return sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
}

function gregorian_to_jalali($gregorian_date) {
    if (empty($gregorian_date)) return 'نامشخص';
    $date = explode('-', $gregorian_date);
    $gy = (int)$date[0];
    $gm = (int)$date[1];
    $gd = (int)$date[2];

    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $gy -= 1600;
    $gm -= 1;
    $gd -= 1;

    $g_day_no = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);
    for ($i = 0; $i < $gm; ++$i) {
        $g_day_no += $g_days_in_month[$i];
    }
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
        $g_day_no++;
    }
    $g_day_no += $gd;

    $j_day_no = $g_day_no - 79;
    $j_np = (int)($j_day_no / 12053);
    $j_day_no %= 12053;

    $jy = 979 + 33 * $j_np + 4 * (int)($j_day_no / 1461);
    $j_day_no %= 1461;

    if ($j_day_no >= 366) {
        $jy += (int)(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }

    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
        $j_day_no -= $j_days_in_month[$i];
    }
    $jm = $i + 1;
    $jd = $j_day_no + 1;

    return sprintf("%04d/%02d/%02d", $jy, $jm, $jd);
}

// دریافت بازه تاریخ
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : '';

// دیباگ تاریخ‌ها
error_log("Start Date: $start_date, End Date: $end_date, Status: $status");

// یادآوری‌ها
$query = "SELECT id, description, reminder_date FROM reminders WHERE reminder_date BETWEEN ? AND ?";
if (!empty($status)) {
    $query .= " AND status = ?";
}
$stmt = $conn->prepare($query);
if (!empty($status)) {
    $stmt->bind_param("sss", $start_date, $end_date, $status);
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
}
$stmt->execute();
$result = $stmt->get_result();
$reminders = [];
while ($row = $result->fetch_assoc()) {
    $row['date'] = gregorian_to_jalali($row['reminder_date']);
    $reminders[] = $row;
}
$stmt->close();

// گزارش‌ها
$query = "SELECT id, description, report_date FROM daily_reports WHERE report_date BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$reports = [];
while ($row = $result->fetch_assoc()) {
    $row['date'] = gregorian_to_jalali($row['report_date']);
    $reports[] = $row;
}
$stmt->close();

// داده‌های برای تعداد
$reminders_count = count($reminders);
$reports_count = count($reports);

// خروجی JSON
echo json_encode([
    'reminders' => $reminders,
    'reports' => $reports,
    'reminders_count' => $reminders_count,
    'reports_count' => $reports_count,
    'labels' => [] // برای سازگاری با کد قبلی
]);
?>