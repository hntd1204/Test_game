<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Chỉ cho phép admin gọi API này
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([]);
    exit;
}

// Lấy ID của lượt quay mới nhất mà admin đã thấy trên màn hình
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

try {
    // Lấy những lượt quay có ID lớn hơn last_id (nghĩa là những lượt quay vừa mới diễn ra)
    $stmt = $pdo->prepare("
        SELECT h.id, h.reward, h.created_at, u.username
        FROM spin_history h
        JOIN users u ON h.user_id = u.id
        WHERE h.id > ?
        ORDER BY h.id ASC
    ");
    $stmt->execute([$last_id]);
    $new_spins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($new_spins);
} catch (Exception $e) {
    echo json_encode([]);
}