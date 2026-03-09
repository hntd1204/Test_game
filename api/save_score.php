<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['user_id'])) exit;

$pts = (int)$_POST['points'];
$uid = $_SESSION['user_id'];

$conn->query("UPDATE users SET points = points + $pts WHERE id = $uid");
$_SESSION['points'] += $pts;

echo json_encode(['status' => 'success', 'new_total' => $_SESSION['points']]);
