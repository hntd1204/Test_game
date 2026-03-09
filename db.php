<?php
$host = 'localhost';
$user = 'root'; // Sửa lại nếu bạn có mật khẩu mysql
$pass = '';
$dbname = 'idle_game';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
