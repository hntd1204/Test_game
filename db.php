<?php
$host = 'localhost';
$dbname = 'ai_tasks_db';
$username = 'root'; // Thay bằng user của bạn
$password = '';     // Thay bằng pass của bạn

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}
