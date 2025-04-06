<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تجهیزات IT موسسه خیریه کهریزک</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <!-- Custom Fonts -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('assets/fonts/Vazir-Regular.woff2') format('woff2');
        }
        @font-face {
            font-family: 'Vazir';
            font-weight: bold;
            src: url('assets/fonts/Vazir-Bold.woff2') format('woff2');
        }
        body {
            font-family: 'Vazir', sans-serif;
        }
        /* استایل هدر */
        header {
            background: linear-gradient(to right, #1e3a8a, #4b5563);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        /* استایل سایدبار */
        #sidebar {
            transition: transform 0.3s ease-in-out;
            transform: translateX(100%);
            z-index: 50;
            background: linear-gradient(to bottom, #ffffff, #f1f5f9);
            box-shadow: -4px 0 10px rgba(0, 0, 0, 0.1);
            width: 16rem; /* عرض ثابت */
        }
        #sidebar.open {
            transform: translateX(0);
        }
        #overlay {
            z-index: 40;
            display: none;
        }
        #overlay.active {
            display: block;
        }
        /* ریسپانسیو کردن سایدبار در موبایل */
        @media (max-width: 768px) {
            #sidebar {
                width: 100%; /* در موبایل تمام‌صفحه */
            }
        }
        /* افکت هاور روی آیتم‌های منو */
        .menu-item {
            transition: all 0.3s ease;
            position: relative;
        }
        .menu-item:hover {
            background-color: #e2e8f0;
            transform: translateX(-3px);
        }
        .menu-item:hover::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #1e3a8a;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- هدر -->
    <header class="bg-gradient-to-r from-blue-900 to-gray-600 shadow-md py-4 px-6 flex items-center justify-between">
        <!-- دکمه منو (سمت چپ) -->
        <div>
            <button id="menuToggle" class="text-gray-200 hover:text-white">
                <i class="fas fa-bars fa-lg"></i>
            </button>
        </div>

        <!-- لوگو و تیتر (وسط) -->
        <div class="text-center">
            <img src="assets/images/logo.png" alt="لوگو" class="mx-auto" style="width: 150px;">
            <h1 class="text-2xl font-bold text-gray-200 mt-2">تجهیزات IT موسسه خیریه کهریزک</h1>
        </div>

        <!-- حذف دارک مود، اینجا خالی می‌ماند -->
        <div></div>
    </header>

    <!-- سایدبار (منو) -->
    <aside id="sidebar" class="fixed top-0 right-0 h-full w-64 shadow-lg p-4">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">منو</h2>
            <button id="closeMenu" class="text-gray-700 hover:text-gray-900">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <nav>
            <ul class="space-y-2">
                <li>
                    <a href="index.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-home" style="margin-left: 0.3rem;"></i> صفحه اصلی
                    </a>
                </li>
                <li>
                    <a href="search.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-search" style="margin-left: 0.3rem;"></i> جستجو تجهیزات
                    </a>
                </li>
                <li>
                    <a href="all_systems.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-list" style="margin-left: 0.3rem;"></i> لیست سیستم‌ها
                    </a>
                </li>
                <li>
                    <a href="dashboard.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-tachometer-alt" style="margin-left: 0.3rem;"></i> داشبورد
                    </a>
                </li>
                <li>
                    <a href="assets_management.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-box" style="margin-left: 0.3rem;"></i> مدیریت اموال
                    </a>
                </li>
                <li>
                    <a href="data_management.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-database" style="margin-left: 0.3rem;"></i> مدیریت داده‌ها
                    </a>
                </li>
                <li>
                    <a href="daily_reports.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-file-alt" style="margin-left: 0.3rem;"></i> گزارش‌های روزانه
                    </a>
                </li>
                <li>
                    <a href="reminders.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-bell" style="margin-left: 0.3rem;"></i> یادآوری‌ها
                    </a>
                </li>
                <li>
                    <a href="task_status.php" class="menu-item flex items-center p-2 text-gray-700 hover:bg-gray-100 rounded">
                        <i class="fas fa-tasks" style="margin-left: 0.3rem;"></i> وضعیت کارها
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- فضای خالی برای بستن منو -->
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50"></div>

    <!-- جاوااسکریپت برای مدیریت منو -->
    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script>
        // مدیریت منو
        $('#menuToggle').on('click', function() {
            $('#sidebar').toggleClass('open');
            $('#overlay').toggleClass('active');
        });

        $('#closeMenu, #overlay').on('click', function() {
            $('#sidebar').removeClass('open');
            $('#overlay').removeClass('active');
        });
    </script>