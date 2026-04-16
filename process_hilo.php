<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Hàm tạo bộ bài cho Hi-Lo (Kèm giá trị từ 2 đến 14, A là cao nhất)
function getDeckHilo()
{
    $suits = ['♠', '♣', '♦', '♥'];
    $ranks = ['2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10, 'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14];
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($ranks as $label => $val) {
            $deck[] = ['rank' => $label, 'val' => $val, 'suit' => $suit, 'color' => in_array($suit, ['♦', '♥']) ? 'red' : 'black'];
        }
    }
    shuffle($deck);
    return $deck;
}

// Hàm lưu lịch sử
function saveHistory($pdo, $userId, $bet, $win, $streak)
{
    $net = $win - $bet;
    $pdo->prepare("INSERT INTO hilo_history (user_id, bet, win, net_profit, streak) VALUES (?, ?, ?, ?, ?)")
        ->execute([$userId, $bet, $win, $net, $streak]);
}

// ==========================================
// 1. XỬ LÝ BẮT ĐẦU VÁN CHƠI
// ==========================================
if ($action === 'start') {
    $bet = (int)($_POST['bet'] ?? 0);
    if ($bet <= 0) {
        echo json_encode(['success' => false, 'error' => 'Tiền cược không hợp lệ']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user['balance'] < $bet) {
            echo json_encode(['success' => false, 'error' => 'Số dư không đủ!']);
            $pdo->rollBack();
            exit;
        }

        // Trừ tiền cược và tăng tiến độ nhiệm vụ (hilo_count)
        $newBalance = $user['balance'] - $bet;
        $pdo->prepare("UPDATE users SET balance = ?, hilo_count = hilo_count + 1 WHERE id = ?")->execute([$newBalance, $userId]);
        $pdo->commit();

        $deck = getDeckHilo();
        $firstCard = array_pop($deck);

        // Lưu thông tin ván chơi vào Session
        $_SESSION['hilo'] = [
            'status' => 'playing',
            'deck' => $deck,
            'current_card' => $firstCard,
            'bet' => $bet,
            'pot' => $bet, // Số tiền đang tích lũy ban đầu bằng đúng tiền cược
            'streak' => 0  // Chuỗi đoán đúng
        ];

        echo json_encode([
            'success' => true,
            'card' => $firstCard,
            'pot' => $bet,
            'balance' => $newBalance
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống']);
    }
    exit;
}

// ==========================================
// 2. XỬ LÝ ĐOÁN BÀI (CAO HƠN / THẤP HƠN)
// ==========================================
if ($action === 'guess') {
    if (!isset($_SESSION['hilo']) || $_SESSION['hilo']['status'] !== 'playing') {
        echo json_encode(['success' => false, 'error' => 'Ván chơi không tồn tại']);
        exit;
    }

    $choice = $_POST['choice']; // 'hi' hoặc 'lo'
    $hilo = $_SESSION['hilo'];
    $currentCard = $hilo['current_card'];

    // Nếu bộ bài hết (trường hợp hiếm), tạo bộ bài mới
    if (count($hilo['deck']) == 0) {
        $hilo['deck'] = getDeckHilo();
    }

    $nextCard = array_pop($hilo['deck']);

    $isWin = false;
    $isTie = false;

    // So sánh giá trị lá bài mới với lá bài cũ
    if ($nextCard['val'] > $currentCard['val'] && $choice === 'hi') $isWin = true;
    elseif ($nextCard['val'] < $currentCard['val'] && $choice === 'lo') $isWin = true;
    elseif ($nextCard['val'] == $currentCard['val']) $isTie = true;

    // Cập nhật lá bài hiện tại trên bàn
    $hilo['current_card'] = $nextCard;

    if ($isWin) {
        $hilo['streak']++;

        // Truy xuất cấu hình 1 mức tỉ lệ nhân từ Database (Do Admin cài đặt)
        try {
            $setStmt = $pdo->query("SELECT hilo_multi FROM settings WHERE id = 1");
            $settings = $setStmt->fetch();
            // Nếu admin chưa set, lấy mặc định là 1.5
            $multiplier = (isset($settings['hilo_multi']) && $settings['hilo_multi'] > 0) ? (float)$settings['hilo_multi'] : 1.5;
        } catch (Exception $e) {
            $multiplier = 1.5; // Đề phòng lỗi DB chưa có cột
        }

        // Tính tiền mới = Tiền tích lũy hiện tại x Hệ số nhân thưởng
        $hilo['pot'] = floor($hilo['pot'] * $multiplier);

        $_SESSION['hilo'] = $hilo;
        echo json_encode([
            'success' => true,
            'is_end' => false,
            'card' => $nextCard,
            'pot' => $hilo['pot'],
            'message' => 'Chính xác! 🎉'
        ]);
    } elseif ($isTie) {
        // Rút trúng lá bài bằng điểm -> Cho Hòa, giữ nguyên tiền, cho rút lá tiếp
        $_SESSION['hilo'] = $hilo;
        echo json_encode([
            'success' => true,
            'is_end' => false,
            'card' => $nextCard,
            'pot' => $hilo['pot'],
            'message' => 'Hòa! Rút tiếp lá nữa 🤝'
        ]);
    } else {
        // Đoán Sai -> Thua mất trắng
        saveHistory($pdo, $userId, $hilo['bet'], 0, $hilo['streak']);
        unset($_SESSION['hilo']);
        echo json_encode([
            'success' => true,
            'is_end' => true,
            'card' => $nextCard,
            'pot' => 0,
            'message' => 'Sai rồi! Bạn mất trắng 💥'
        ]);
    }
    exit;
}

// ==========================================
// 3. XỬ LÝ CHỐT LỜI (CASH OUT)
// ==========================================
if ($action === 'cashout') {
    if (!isset($_SESSION['hilo']) || $_SESSION['hilo']['status'] !== 'playing') {
        echo json_encode(['success' => false, 'error' => 'Ván chơi không tồn tại']);
        exit;
    }

    $hilo = $_SESSION['hilo'];

    // Nếu chưa đoán đúng lần nào mà bấm Dừng thì chỉ hoàn trả lại tiền cược (Hòa vốn)
    $winnings = $hilo['streak'] > 0 ? $hilo['pot'] : $hilo['bet'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // Cộng tiền vào tài khoản
        $newBalance = $user['balance'] + $winnings;
        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

        saveHistory($pdo, $userId, $hilo['bet'], $winnings, $hilo['streak']);
        $pdo->commit();

        // Hủy ván chơi
        unset($_SESSION['hilo']);

        echo json_encode([
            'success' => true,
            'balance' => $newBalance,
            'winnings' => $winnings
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống']);
    }
    exit;
}

// Nếu action không hợp lệ
echo json_encode(['success' => false, 'error' => 'Yêu cầu không hợp lệ']);
