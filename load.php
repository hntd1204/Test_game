<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['username'])) {
    echo json_encode(["error" => "Chưa đăng nhập"]);
    exit();
}

$username = $_SESSION['username'];
$sql = "SELECT * FROM players WHERE username = '$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(["error" => "Lỗi dữ liệu"]);
}
$conn->close();
