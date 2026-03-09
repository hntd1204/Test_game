<?php
session_start();
include '../config/db.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Kiểm tra mật khẩu khớp
    if ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        // Kiểm tra username tồn tại chưa
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            $error = "Tên đăng nhập đã tồn tại!";
        } else {
            // Mã hóa mật khẩu và lưu
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, points, role) VALUES ('$username', '$hashed_password', 0, 'user')";
            if ($conn->query($sql)) {
                header("Location: login.php?success=1");
                exit;
            } else {
                $error = "Lỗi hệ thống, vui lòng thử lại!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng ký - Coffee Tycoon</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-amber-50 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-xl shadow-md w-96">
        <h2 class="text-2xl font-bold text-amber-900 mb-6 text-center">Đăng Ký Tài Khoản</h2>
        <?php if ($error): ?>
            <p class="text-red-500 text-sm mb-4"><?= $error ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Tên đăng nhập" class="w-full p-3 mb-4 border rounded"
                required>
            <input type="password" name="password" placeholder="Mật khẩu" class="w-full p-3 mb-4 border rounded"
                required>
            <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu"
                class="w-full p-3 mb-6 border rounded" required>
            <button type="submit"
                class="w-full bg-amber-700 text-white py-3 rounded-lg font-bold hover:bg-amber-800">ĐĂNG KÝ</button>
        </form>
        <p class="mt-4 text-center text-sm">Đã có tài khoản? <a href="login.php" class="text-amber-700 font-bold">Đăng
                nhập ngay</a></p>
    </div>
</body>

</html>