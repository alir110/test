<?php
   // فایل اصلی برای آپلود، فشرده‌سازی و نمایش تصاویر (سازگار با Intervention Image 2.7)

   // فعال کردن نمایش خطاها برای دیباگ
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);

   // تابع برای ثبت پیام‌های دیباگ
   function logMessage($message) {
       $log_file = 'debug_log.txt';
       $current_time = date('Y-m-d H:i:s');
       file_put_contents($log_file, "[$current_time] $message\n", FILE_APPEND);
   }

   logMessage("Starting image_compression.php");

   // بارگذاری کتابخونه Intervention Image
   try {
       require 'vendor/autoload.php';
       logMessage("vendor/autoload.php loaded successfully");
   } catch (Exception $e) {
       logMessage("Failed to load vendor/autoload.php: " . $e->getMessage());
       die("خطا در بارگذاری autoload: " . $e->getMessage());
   }

   // استفاده از Intervention Image
   use Intervention\Image\ImageManager;

   // متغیر برای ذخیره پیام‌ها
   $message = '';

   // پوشه آپلود
   $upload_dir = 'uploads/';
   if (!is_dir($upload_dir)) {
       mkdir($upload_dir, 0755, true);
   }

   // ایجاد یک نمونه از ImageManager
   try {
       $manager = new ImageManager(['driver' => 'gd']); // یا 'imagick' اگه GD ندارید
       logMessage("ImageManager instantiated successfully");
   } catch (Exception $e) {
       logMessage("Failed to instantiate ImageManager: " . $e->getMessage());
       die("خطا در ایجاد ImageManager: " . $e->getMessage());
   }

   // آپلود و فشرده‌سازی تصویر
   if (isset($_POST['upload_image'])) {
       if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
           $image_tmp = $_FILES['image']['tmp_name'];
           $image_name = time() . '_' . basename($_FILES['image']['name']);
           $image_path = $upload_dir . $image_name;

           // انتقال فایل به پوشه آپلود
           if (move_uploaded_file($image_tmp, $image_path)) {
               try {
                   // فشرده‌سازی تصویر با Intervention Image
                   $img = $manager->make($image_path);

                   // تغییر اندازه تصویر (اختیاری، برای بهینه‌سازی بیشتر)
                   $img->resize(1200, null, function ($constraint) {
                       $constraint->aspectRatio(); // حفظ نسبت تصویر
                       $constraint->upsize(); // جلوگیری از بزرگ شدن تصویر
                   });

                   // فشرده‌سازی با کیفیت بالا (80%) و تبدیل به WebP
                   $webp_path = $upload_dir . pathinfo($image_name, PATHINFO_FILENAME) . '.webp';
                   $img->save($webp_path, 80, 'webp');

                   // فشرده‌سازی تصویر اصلی به JPEG با کیفیت 80%
                   $img->save($image_path, 80);

                   $message = 'تصویر با موفقیت آپلود و فشرده شد.';
                   logMessage("Image compressed and saved: $image_path and $webp_path");
               } catch (Exception $e) {
                   $message = 'خطا در فشرده‌سازی تصویر: ' . $e->getMessage();
                   logMessage("Error compressing image: " . $e->getMessage());
               }
           } else {
               $message = 'خطا در آپلود تصویر.';
               logMessage("Failed to upload image");
           }
       } else {
           $message = 'لطفاً یک تصویر انتخاب کنید.';
           logMessage("No image selected for upload");
       }
   }

   // دریافت لیست تصاویر
   $images = [];
   $files = glob($upload_dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
   foreach ($files as $file) {
       $filename = basename($file);
       $webp_file = $upload_dir . pathinfo($filename, PATHINFO_FILENAME) . '.webp';
       $images[] = [
           'original' => $filename,
           'webp' => file_exists($webp_file) ? pathinfo($filename, PATHINFO_FILENAME) . '.webp' : null
       ];
   }
   ?>

   <!DOCTYPE html>
   <html lang="fa" dir="rtl">
   <head>
       <meta charset="UTF-8">
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <title>فشرده‌سازی و نمایش تصاویر</title>
       <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
       <style>
           .image-container img {
               width: 100%;
               height: auto;
               object-fit: cover;
           }
       </style>
   </head>
   <body class="bg-gray-100 font-sans">
       <main class="container mx-auto py-8 px-4">
           <div class="bg-white shadow-lg rounded-lg p-6">
               <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">فشرده‌سازی و نمایش تصاویر</h1>

               <!-- پیام‌ها -->
               <?php if (!empty($message)): ?>
                   <p class="text-center text-gray-600 mb-4"><?php echo htmlspecialchars($message); ?></p>
               <?php endif; ?>

               <!-- فرم آپلود تصویر -->
               <div class="mb-8">
                   <form method="POST" enctype="multipart/form-data" class="space-y-4">
                       <div>
                           <label for="image" class="block text-sm font-medium text-gray-700">انتخاب تصویر</label>
                           <input type="file" id="image" name="image" accept="image/*" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                       </div>
                       <button type="submit" name="upload_image" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-all duration-300">آپلود و فشرده‌سازی</button>
                   </form>
               </div>

               <!-- نمایش تصاویر -->
               <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                   <?php foreach ($images as $image): ?>
                       <div class="image-container">
                           <picture>
                               <?php if ($image['webp']): ?>
                                   <source srcset="<?php echo $upload_dir . $image['webp']; ?>" type="image/webp">
                               <?php endif; ?>
                               <img src="<?php echo $upload_dir . $image['original']; ?>" alt="Compressed Image" loading="lazy" class="rounded-lg shadow-md">
                           </picture>
                       </div>
                   <?php endforeach; ?>
               </div>
           </div>
       </main>
   </body>
   </html>

   <?php
   logMessage("Finished rendering image_compression.php");
   ?>