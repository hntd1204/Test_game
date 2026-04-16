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

if ($action === 'start') {
    $bet = (int)($_POST['bet'] ?? 0);
    if ($bet <= 0) {
        echo json_encode(['success' => false, 'error' => 'Cược không hợp lệ']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user['balance'] < $bet) {
            echo json_encode(['success' => false, 'error' => 'Không đủ số dư']);
            $pdo->rollBack();
            exit;
        }

        $newBalance = $user['balance'] - $bet;
        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

        $bombs = (int)$pdo->query("SELECT mines_bombs FROM settings WHERE id = 1")->fetchColumn();
        if ($bombs < 1 || $bombs > 24) $bombs = 3;

        $board = array_fill(0, 25, 'safe');
        $bombIndexes = (array)array_rand($board, $bombs);
        foreach ($bombIndexes as $i) {
            $board[$i] = 'bomb';
        }

        $_SESSION['mines'] = [
            'bet' => $bet,
            'pot' => $bet,
            'board' => $board,
            'bombs' => $bombs,
            'step' => 0,
            'status' => 'playing'
        ];

        $pdo->commit();
        echo json_encode(['success' => true, 'balance' => $newBalance, 'pot' => $bet, 'bombs' => $bombs]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống']);
    }
    exit;
}

if ($action === 'open') {
    if (!isset($_SESSION['mines']) || $_SESSION['mines']['status'] !== 'playing') {
        echo json_encode(['success' => false, 'error' => 'Ván chơi không hợp lệ']);
        exit;
    }
    $index = (int)$_POST['index'];
    $mines = $_SESSION['mines'];

    if ($mines['board'][$index] === 'bomb') {
        // Thua
        $pdo->prepare("INSERT INTO mines_history (user_id, bet, win, net_profit, bombs, steps) VALUES (?, ?, 0, ?, ?, ?)")
            ->execute([$userId, $mines['bet'], -$mines['bet'], $mines['bombs'], $mines['step']]);
        unset($_SESSION['mines']);
        echo json_encode(['success' => true, 'is_bomb' => true, 'board' => $mines['board']]);
    } else {
        // An toàn - Tăng tiền thưởng
        $mines['step']++;
        $multiplier = 1 + ($mines['bombs'] * 0.05 * $mines['step']); // Công thức hệ số nhân cơ bản
        $mines['pot'] = round($mines['bet'] * $multiplier);
        $_SESSION['mines'] = $mines;

        echo json_encode(['success' => true, 'is_bomb' => false, 'pot' => $mines['pot'], 'step' => $mines['step']]);
    }
    exit;
}

if ($action === 'cashout') {
    if (!isset($_SESSION['mines']) || $_SESSION['mines']['status'] !== 'playing') {
        echo json_encode(['success' => false, 'error' => 'Ván chơi không tồn tại']);
        exit;
    }
    $mines = $_SESSION['mines'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET balance = balance + ?, mines_count = mines_count + 1 WHERE id = ?")->execute([$mines['pot'], $userId]);
        $pdo->prepare("INSERT INTO mines_history (user_id, bet, win, net_profit, bombs, steps) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $mines['bet'], $mines['pot'], $mines['pot'] - $mines['bet'], $mines['bombs'], $mines['step']]);

        $newBalance = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();
        $pdo->commit();
        unset($_SESSION['mines']);

        echo json_encode(['success' => true, 'winnings' => $mines['pot'], 'balance' => $newBalance]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false]);
    }
    exit;
}
