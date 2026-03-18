<?php
session_start();
require 'db.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $pass_confirm = $_POST['password_confirm'];

    if ($pass !== $pass_confirm) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        // Kiểm tra xem username đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$user]);
        if ($stmt->fetch()) {
            $error = "Tên đăng nhập đã tồn tại!";
        } else {
            // Mã hóa mật khẩu và Insert user mới (tặng sẵn 1 lượt quay)
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (username, password, role, balance, spins_available) VALUES (?, ?, 'user', 0, 1)");
            if ($insert->execute([$user, $hashed_pass])) {
                $success = "Đăng ký thành công! Đang chuyển hướng...";
                header("refresh:2;url=login.php");
            } else {
                $error = "Có lỗi xảy ra, vui lòng thử lại!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Đăng Ký</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md">
        <h2 class="text-3xl font-bold text-center text-slate-800 mb-8">Tạo Tài Khoản</h2>

        <?php if ($error): ?>
        <div class="bg-red-100 text-red-600 p-3 rounded-lg mb-4 text-sm text-center"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="bg-green-100 text-green-600 p-3 rounded-lg mb-4 text-sm text-center"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Tên đăng nhập</label>
                <input type="text" name="username" required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Xác nhận mật khẩu</label>
                <input type="password" name="password_confirm" required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-300">Đăng
                Ký</button>
        </form>
        <p class="text-center text-slate-500 mt-6 text-sm">
            Đã có tài khoản? <a href="login.php" class="text-blue-600 font-semibold hover:underline">Đăng nhập</a>
        </p>
    </div>
</body>

</html>