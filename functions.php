<?php
// توابع مشترک برای استفاده در صفحات مختلف

// تابع برای دریافت و نمایش یادآوری‌ها
function displayReminders($conn, $limit = 5) {
    // توابع تبدیل تاریخ شمسی به میلادی
    function jalali_to_jd($j_y, $j_m, $j_d) {
        $j_y = (int)$j_y;
        $j_m = (int)$j_m;
        $j_d = (int)$j_d;

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

        return [$gy, $gm, $gd];
    }

    function jdtogregorian($jd) {
        $gregorian = jalali_to_jd($jd[0], $jd[1], $jd[2]);
        return sprintf("%04d/%02d/%02d", $gregorian[0], $gregorian[1], $gregorian[2]);
    }

    function jalaliToGregorian($jalali_date) {
        if (empty($jalali_date) || !is_string($jalali_date)) {
            return date('Y-m-d'); // تاریخ امروز به فرمت میلادی
        }
        $jalali_date = explode('/', $jalali_date);
        if (count($jalali_date) != 3) {
            return date('Y-m-d'); // تاریخ امروز به فرمت میلادی
        }
        $year = $jalali_date[0];
        $month = $jalali_date[1];
        $day = $jalali_date[2];
        $gregorian = jalali_to_jd($year, $month, $day);
        return sprintf("%04d-%02d-%02d", $gregorian[0], $gregorian[1], $gregorian[2]);
    }

    function gregorian_to_jalali($gregorian_date) {
        $date = explode('-', $gregorian_date);
        $year = $date[0];
        $month = $date[1];
        $day = $date[2];
        $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $gy = $year - 1600;
        $gm = $month - 1;
        $gd = $day - 1;

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

    // دریافت یادآوری‌ها
    $query = "SELECT * FROM reminders ORDER BY reminder_date DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $reminders = [];
    while ($row = $result->fetch_assoc()) {
        $reminders[] = $row;
    }
    $stmt->close();

    // نمایش یادآوری‌ها
    if (count($reminders) > 0) {
        echo '<div class="reminders-widget">';
        echo '<h3 class="text-lg font-semibold text-gray-800 mb-4">یادآوری‌ها</h3>';
        echo '<ul class="space-y-2">';
        foreach ($reminders as $reminder) {
            echo '<li class="flex justify-between items-center p-2 bg-gray-50 rounded-lg">';
            echo '<div>';
            echo '<span class="text-sm font-medium text-gray-700">' . htmlspecialchars($reminder['description']) . '</span>';
            echo '<span class="block text-xs text-gray-500">' . htmlspecialchars(gregorian_to_jalali($reminder['reminder_date'])) . '</span>';
            echo '</div>';
            echo '<span class="text-sm font-medium ' . ($reminder['status'] == 'انجام شده' ? 'text-green-600' : ($reminder['status'] == 'لغو شده' ? 'text-red-600' : 'text-yellow-600')) . '">' . htmlspecialchars($reminder['status']) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-600">هیچ یادآوری‌ای ثبت نشده است.</p>';
    }
}