<?php
session_start();
require 'db.php';
$error = '';

// Nếu user đã đăng nhập, tự động đẩy về trang tương ứng
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? "admin.php" : "dashboard.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $account = $stmt->fetch();

    if ($account && password_verify($pass, $account['password'])) {
        $_SESSION['user_id'] = $account['id'];
        $_SESSION['username'] = $account['username'];
        $_SESSION['role'] = $account['role'];

        header("Location: " . ($account['role'] == 'admin' ? "admin.php" : "dashboard.php"));
        exit;
    } else {
        $error = "Sai tài khoản hoặc mật khẩu!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đăng Nhập - Vòng Quay</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xl border border-slate-100 w-full max-w-md">

        <div class="text-center mb-6 sm:mb-8">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 mb-2">Đăng Nhập</h2>
            <p class="text-sm text-slate-500">Chào mừng bạn quay trở lại!</p>
        </div>

        <?php if ($error): ?>
        <div
            class="bg-red-50 border border-red-200 text-red-600 p-3 sm:p-4 rounded-xl mb-5 text-sm font-medium text-center">
            <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 sm:space-y-5">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1.5">Tên đăng nhập</label>
                <input type="text" name="username" required placeholder="Nhập tài khoản..."
                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all bg-slate-50 focus:bg-white text-base">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1.5">Mật khẩu</label>
                <input type="password" name="password" required placeholder="••••••••"
                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all bg-slate-50 focus:bg-white text-base">
            </div>

            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl active:scale-95 transform mt-2">
                Đăng Nhập
            </button>
        </form>

        <!-- <p class="text-center text-slate-500 mt-6 sm:mt-8 text-sm">
            Chưa có tài khoản? <a href="register.php"
                class="text-blue-600 font-bold hover:text-blue-700 hover:underline transition-all">Đăng ký ngay</a>
        </p> -->
    </div>
</body>

</html>