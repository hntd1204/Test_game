<?php
session_start();
include '../config/db.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE username = '$username'");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Kiểm tra mật khẩu đã mã hóa
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['points'] = $user['points'];
            $_SESSION['role'] = $user['role'];

            header("Location: ../index.php");
            exit;
        } else {
            $error = "Mật khẩu không chính xác!";
        }
    } else {
        $error = "Tài khoản không tồn tại!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - Coffee Tycoon</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-amber-50 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-xl shadow-md w-96">
        <h2 class="text-2xl font-bold text-amber-900 mb-6 text-center">Đăng Nhập</h2>
        <?php if (isset($_GET['success'])): ?>
            <p class="text-green-600 text-sm mb-4">Đăng ký thành công! Hãy đăng nhập.</p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="text-red-500 text-sm mb-4"><?= $error ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Tên đăng nhập" class="w-full p-3 mb-4 border rounded"
                required>
            <input type="password" name="password" placeholder="Mật khẩu" class="w-full p-3 mb-6 border rounded"
                required>
            <button type="submit"
                class="w-full bg-amber-700 text-white py-3 rounded-lg font-bold hover:bg-amber-800">ĐĂNG NHẬP</button>
        </form>
        <p class="mt-4 text-center text-sm">Chưa có tài khoản? <a href="register.php"
                class="text-amber-700 font-bold">Tham gia ngay</a></p>
    </div>
</body>

</html>