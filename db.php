<?php
// Thiết lập múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host = 'localhost';
$dbname = 'Random_quay'; // Đổi thành tên DB của bạn
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- LOGIC RESET SỐ DƯ MỖI NGÀY ---
    // 1. Lấy ngày reset cuối cùng trong hệ thống
    $dateCheckStmt = $pdo->query("SELECT last_reset_date FROM settings WHERE id = 1");
    $setting = $dateCheckStmt->fetch();

    $currentDate = date('Y-m-d'); // Lấy ngày hôm nay (VD: 2026-03-18)

    // 2. Nếu ngày lưu trong CSDL khác ngày hôm nay -> Đã qua ngày mới
    if ($setting && $setting['last_reset_date'] != $currentDate) {
        // Reset toàn bộ số dư của user về 0
        $pdo->exec("UPDATE users SET balance = 0");

        // Cập nhật lại ngày hiện tại vào CSDL để không bị reset lặp lại trong ngày
        $updateDateStmt = $pdo->prepare("UPDATE settings SET last_reset_date = ? WHERE id = 1");
        $updateDateStmt->execute([$currentDate]);
    }
} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}