<?php
include 'config.php';

// تبدیل تاریخ شمسی به میلادی
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

// تبدیل تاریخ میلادی به شمسی
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

$filter_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';
$filter_description = isset($_GET['filter_description']) ? trim($_GET['filter_description']) : '';

$query = "SELECT * FROM daily_reports WHERE 1=1";
$params = [];
$types = '';
if (!empty($filter_date)) {
    $filter_date_gregorian = jalali_to_gregorian($filter_date);
    $query .= " AND report_date = ?";
    $params[] = $filter_date_gregorian;
    $types .= 's';
}
if (!empty($filter_description)) {
    $query .= " AND description LIKE ?";
    $params[] = "%$filter_description%";
    $types .= 's';
}
$query .= " ORDER BY report_date DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "<tr class='hover:bg-gray-50 transition'>";
    echo "<td class='px-6 py-4 text-sm text-gray-900 font-medium'>" . gregorian_to_jalali($row['report_date']) . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['description']) . "</td>";
    echo "<td class='px-6 py-4 text-sm text-gray-900'>" . htmlspecialchars($row['created_at']) . "</td>";
    echo "</tr>";
}
$stmt->close();
?>