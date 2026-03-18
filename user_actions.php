<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT balance, spins_available FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($action === 'withdraw') {
        $amount = (int)$_POST['amount'];

        if ($amount < 10000) {
            echo json_encode(['success' => false, 'error' => 'Số tiền rút tối thiểu là 10.000 VNĐ']);
            $pdo->rollBack();
            exit;
        }
        if ($user['balance'] < $amount) {
            echo json_encode(['success' => false, 'error' => 'Số dư không đủ!']);
            $pdo->rollBack();
            exit;
        }

        $newBalance = $user['balance'] - $amount;
        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
        $pdo->prepare("INSERT INTO withdrawals (user_id, amount, status) VALUES (?, ?, 'pending')")->execute([$userId, $amount]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Đã gửi yêu cầu rút tiền! Đang chờ duyệt.', 'new_balance' => $newBalance]);
    } elseif ($action === 'buy_spin') {
        $cost = 50000;

        if ($user['balance'] < $cost) {
            echo json_encode(['success' => false, 'error' => 'Không đủ tiền! Cần 50.000 VNĐ.']);
            $pdo->rollBack();
            exit;
        }

        $newBalance = $user['balance'] - $cost;
        $newSpins = $user['spins_available'] + 1;

        $pdo->prepare("UPDATE users SET balance = ?, spins_available = ? WHERE id = ?")->execute([$newBalance, $newSpins, $userId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Mua 1 lượt quay thành công!', 'new_balance' => $newBalance, 'spins_left' => $newSpins]);
    } elseif ($action === 'buy_gift') {
        $item_id = (int)($_POST['item_id'] ?? 0);

        // Truy xuất giá tiền thực tế của món quà từ CSDL để chống hack đổi giá
        $itemStmt = $pdo->prepare("SELECT name, cost FROM shop_items WHERE id = ? AND is_active = 1");
        $itemStmt->execute([$item_id]);
        $item = $itemStmt->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Món quà này không tồn tại hoặc đã bị xóa!']);
            $pdo->rollBack();
            exit;
        }

        $cost = (int)$item['cost'];
        $gift_name = $item['name'];

        if ($user['balance'] < $cost) {
            echo json_encode(['success' => false, 'error' => 'Số dư không đủ để đổi quà này!']);
            $pdo->rollBack();
            exit;
        }

        $newBalance = $user['balance'] - $cost;

        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
        $pdo->prepare("INSERT INTO user_gifts (user_id, gift_name, cost) VALUES (?, ?, ?)")->execute([$userId, $gift_name, $cost]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Đổi quà: ' . $gift_name . ' thành công!', 'new_balance' => $newBalance]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Hành động không hợp lệ']);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống!']);
}