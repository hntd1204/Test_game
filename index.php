<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vòng Quay May Mắn</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 min-h-screen flex flex-col justify-center items-center">
    <div class="max-w-3xl text-center px-4">
        <h1
            class="text-5xl md:text-6xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600 mb-6">
            Vòng Quay May Mắn
        </h1>
        <p class="text-lg text-slate-600 mb-10">
            Tham gia ngay để nhận lượt quay miễn phí hàng tuần. Cơ hội trúng thưởng lên đến 100.000 VNĐ!
        </p>

        <div class="flex justify-center gap-4">
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $_SESSION['role'] === 'admin' ? 'admin.php' : 'dashboard.php' ?>"
                class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-full shadow-lg transition duration-300">Vào
                Trang Quản Lý</a>
            <?php else: ?>
            <a href="login.php"
                class="px-8 py-3 bg-white text-blue-600 font-semibold rounded-full shadow-lg hover:shadow-xl border border-blue-100 transition duration-300">Đăng
                Nhập</a>
            <a href="register.php"
                class="px-8 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold rounded-full shadow-lg transition duration-300">Đăng
                Ký Ngay</a>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>