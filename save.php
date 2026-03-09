<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $username = $_SESSION['username'];
    $money = (int)$data['money'];
    $click_power = (int)$data['clickPower'];
    $auto_income = (int)$data['autoIncome'];
    $click_cost = (int)$data['clickUpgradeCost'];
    $auto_cost = (int)$data['autoUpgradeCost'];

    $sql = "UPDATE players SET 
            money = $money, 
            click_power = $click_power, 
            auto_income = $auto_income, 
            click_cost = $click_cost, 
            auto_cost = $auto_cost 
            WHERE username = '$username'";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
}
$conn->close();
