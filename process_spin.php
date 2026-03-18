<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    exit;
}

$userId = $_SESSION['user_id'];

// Bắt đầu Transaction để tránh lỗi nạp rút (race condition)
$pdo->beginTransaction();

try {
    // 1. Khóa row của user lại để kiểm tra số lượt (chống click liên tục)
    $stmt = $pdo->prepare("SELECT balance, spins_available FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user['spins_available'] <= 0) {
        echo json_encode(['success' => false, 'error' => 'Bạn đã hết lượt quay!']);
        $pdo->rollBack();
        exit;
    }

    // 2. Lấy mức tiền random từ settings
    $setStmt = $pdo->query("SELECT min_reward, max_reward FROM settings WHERE id = 1");
    $settings = $setStmt->fetch();

    // 3. Random số tiền thưởng (làm tròn đến hàng nghìn, VD: 15,000)
    $reward = rand($settings['min_reward'], $settings['max_reward']);
    $reward = round($reward / 1000) * 1000;

    $newBalance = $user['balance'] + $reward;
    $spinsLeft = $user['spins_available'] - 1;

    // 4. Cập nhật vào DB
    $updateStmt = $pdo->prepare("UPDATE users SET balance = ?, spins_available = ? WHERE id = ?");
    $updateStmt->execute([$newBalance, $spinsLeft, $userId]);

    $pdo->commit();

    // 5. Trả kết quả về cho JavaScript
    echo json_encode([
        'success' => true,
        'reward' => $reward,
        'new_balance' => $newBalance,
        'spins_left' => $spinsLeft
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống!']);
}