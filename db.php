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

    // ĐÃ BỎ LOGIC RESET SỐ DƯ VỀ 0 MỖI NGÀY Ở ĐÂY

} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}