<?php
session_start();
require 'db.php';

// Nếu đã đăng nhập thì chuyển thẳng vào game
if (isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$message = '';

// Xử lý Form Submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    if ($action == 'register') {
        // Kiểm tra user tồn tại chưa
        $check = $conn->query("SELECT id FROM players WHERE username='$username'");
        if ($check->num_rows > 0) {
            $message = "<div class='alert alert-danger'>Tên đăng nhập đã tồn tại!</div>";
        } else {
            // Mã hóa mật khẩu và tạo tài khoản với chỉ số game mặc định
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO players (username, password, money, click_power, auto_income, click_cost, auto_cost) 
                    VALUES ('$username', '$hashed_password', 0, 1, 0, 10, 50)";
            if ($conn->query($sql) === TRUE) {
                $message = "<div class='alert alert-success'>Đăng ký thành công! Hãy đăng nhập.</div>";
            }
        }
    } elseif ($action == 'login') {
        $result = $conn->query("SELECT * FROM players WHERE username='$username'");
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // Kiểm tra mật khẩu
            if (password_verify($password, $row['password'])) {
                $_SESSION['username'] = $username;
                header("Location: index.php");
                exit();
            } else {
                $message = "<div class='alert alert-danger'>Sai mật khẩu!</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Tài khoản không tồn tại!</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng Nhập Game</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light d-flex align-items-center" style="height: 100vh;">
    <div class="container" style="max-width: 400px;">
        <div class="card shadow-sm p-4">
            <h2 class="text-center text-primary mb-4">☕ Cà Phê Idle</h2>
            <?= $message ?>

            <ul class="nav nav-pills mb-3 justify-content-center" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#login">Đăng Nhập</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#register">Đăng Ký</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="login">
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <input type="text" name="username" class="form-control mb-3" placeholder="Tên đăng nhập"
                            required>
                        <input type="password" name="password" class="form-control mb-3" placeholder="Mật khẩu"
                            required>
                        <button type="submit" class="btn btn-primary w-100">Vào Game</button>
                    </form>
                </div>
                <div class="tab-pane fade" id="register">
                    <form method="POST">
                        <input type="hidden" name="action" value="register">
                        <input type="text" name="username" class="form-control mb-3" placeholder="Tên đăng nhập mới"
                            required>
                        <input type="password" name="password" class="form-control mb-3" placeholder="Mật khẩu"
                            required>
                        <button type="submit" class="btn btn-success w-100">Tạo Tài Khoản</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>