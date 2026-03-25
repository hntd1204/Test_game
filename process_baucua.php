<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    exit;
}

$userId = $_SESSION['user_id'];
$bets = isset($_POST['bets']) ? json_decode($_POST['bets'], true) : [];

if (empty($bets)) {
    echo json_encode(['success' => false, 'error' => 'Vui lòng đặt cược!']);
    exit;
}

$valid_options = ['bau', 'cua', 'tom', 'ca', 'ga', 'nai'];
$totalBet = 0;

foreach ($bets as $key => $amount) {
    if (!in_array($key, $valid_options) || !is_numeric($amount) || $amount < 0) {
        echo json_encode(['success' => false, 'error' => 'Cược không hợp lệ!']);
        exit;
    }
    $totalBet += (int)$amount;
}

if ($totalBet <= 0) {
    echo json_encode(['success' => false, 'error' => 'Số tiền cược phải lớn hơn 0!']);
    exit;
}

$pdo->beginTransaction();

try {
    // Khóa dòng user để kiểm tra số dư
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user['balance'] < $totalBet) {
        echo json_encode(['success' => false, 'error' => 'Số dư của bạn không đủ!']);
        $pdo->rollBack();
        exit;
    }

    // Trừ tổng tiền cược
    $newBalance = $user['balance'] - $totalBet;

    // Lắc 3 viên xí ngầu
    $dice = [];
    for ($i = 0; $i < 3; $i++) {
        $dice[] = $valid_options[array_rand($valid_options)];
    }

    // Tính tiền thắng cược
    $winnings = 0;
    foreach ($bets as $key => $amount) {
        $amount = (int)$amount;
        if ($amount > 0) {
            $count = 0;
            // Đếm số lần xuất hiện của linh vật
            foreach ($dice as $d) {
                if ($d === $key) $count++;
            }
            // Nếu trúng, trả lại tiền gốc + tiền thưởng (gốc x số lần xuất hiện)
            if ($count > 0) {
                $winnings += $amount + ($amount * $count);
            }
        }
    }

    $newBalance += $winnings;

    // Cập nhật số dư mới vào DB
    $updateStmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $updateStmt->execute([$newBalance, $userId]);

    $pdo->commit();

    $net_profit = $winnings - $totalBet;

    echo json_encode([
        'success' => true,
        'dice' => $dice,
        'winnings' => $winnings,
        'total_bet' => $totalBet,
        'net_profit' => $net_profit,
        'new_balance' => $newBalance
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống!']);
}
