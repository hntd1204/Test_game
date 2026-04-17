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

// --- LẤY HỆ SỐ NHÂN VÀ TỶ LỆ THẮNG TỪ BẢNG SETTINGS ---
try {
    $settingsStmt = $pdo->query("SELECT hilo_multiplier, hilo_win_rate FROM settings WHERE id = 1");
    $settings = $settingsStmt->fetch();
    $hilo_mul = (float)($settings['hilo_multiplier'] ?? 1.2);
    $win_rate = (int)($settings['hilo_win_rate'] ?? 40); // Mặc định tỷ lệ thắng 40%
} catch (Exception $e) {
    $hilo_mul = 1.2;
    $win_rate = 40; // Dự phòng nếu chưa có cột
}

// Hàm tạo bộ bài cho Hi-Lo
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
// 1. XỬ LÝ BẮT ĐẦU VÁN CHƠI & NHIỆM VỤ
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

        // Trừ tiền cược và tăng tiến độ nhiệm vụ
        $newBalance = $user['balance'] - $bet;
        $pdo->prepare("UPDATE users SET balance = ?, hilo_count = hilo_count + 1 WHERE id = ?")->execute([$newBalance, $userId]);

        // --- XỬ LÝ KIỂM TRA NHIỆM VỤ LẬT BÀI ---
        $mission_info = ['rewarded' => false];
        $currentCount = $pdo->query("SELECT hilo_count FROM users WHERE id = $userId")->fetchColumn();
        $missions = $pdo->query("SELECT * FROM mission_settings WHERE mission_key = 'hilo_count'")->fetchAll();

        foreach ($missions as $m) {
            if ($currentCount == $m['target_count']) {
                $pdo->prepare("UPDATE users SET spins_available = spins_available + ? WHERE id = ?")
                    ->execute([$m['reward_spins'], $userId]);
                $mission_info = ['rewarded' => true, 'current' => $currentCount, 'target' => $m['target_count']];
                break;
            }
        }
        if (!$mission_info['rewarded'] && count($missions) > 0) {
            $mission_info['current'] = $currentCount;
            $mission_info['target'] = $missions[0]['target_count'];
        }

        $pdo->commit();

        $deck = getDeckHilo();
        $firstCard = array_pop($deck);

        // Lưu thông tin ván chơi vào Session
        $_SESSION['hilo'] = [
            'status' => 'playing',
            'deck' => $deck,
            'current_card' => $firstCard,
            'bet' => $bet,
            'pot' => $bet,
            'streak' => 0
        ];

        echo json_encode([
            'success' => true,
            'card' => $firstCard,
            'pot' => $bet,
            'balance' => $newBalance,
            'mission' => $mission_info // Trả về UI để hiển thị
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống']);
    }
    exit;
}

// ==========================================
// 2. XỬ LÝ ĐOÁN BÀI
// ==========================================
if ($action === 'guess') {
    if (!isset($_SESSION['hilo']) || $_SESSION['hilo']['status'] !== 'playing') {
        echo json_encode(['success' => false, 'error' => 'Ván chơi không tồn tại']);
        exit;
    }

    $choice = $_POST['choice'];
    $hilo = $_SESSION['hilo'];
    $currentCard = $hilo['current_card'];

    if (count($hilo['deck']) == 0) {
        $hilo['deck'] = getDeckHilo();
    }

    // --- BẮT ĐẦU THUẬT TOÁN ÉP THUA ---
    $is_rigged_to_lose = (rand(1, 100) > $win_rate);

    if ($is_rigged_to_lose) {
        // Tìm trong Deck một lá bài NGƯỢC LẠI với dự đoán của User để user chắn chắn mất tiền
        $forced_card_index = -1;
        foreach ($hilo['deck'] as $idx => $card) {
            // Nếu người chơi đoán CAO HƠN, nhà cái tìm lá THẤP HƠN
            if ($choice === 'hi' && $card['val'] < $currentCard['val']) {
                $forced_card_index = $idx;
                break;
            }
            // Nếu người chơi đoán THẤP HƠN, nhà cái tìm lá CAO HƠN
            if ($choice === 'lo' && $card['val'] > $currentCard['val']) {
                $forced_card_index = $idx;
                break;
            }
        }

        // Tráo lá bài ép thua lên đầu
        if ($forced_card_index !== -1) {
            $nextCard = $hilo['deck'][$forced_card_index];
            unset($hilo['deck'][$forced_card_index]); // Rút lá đó ra khỏi bộ bài
            $hilo['deck'] = array_values($hilo['deck']); // Cập nhật lại chỉ số mảng
        } else {
            // Nếu đen đủi trong bài không còn lá nào để ép (rất hiếm xảy ra), rút ngẫu nhiên
            $nextCard = array_pop($hilo['deck']);
        }
    } else {
        // Xanh chín (Nếu nằm trong phần trăm User được thắng)
        $nextCard = array_pop($hilo['deck']);
    }
    // --- KẾT THÚC THUẬT TOÁN ÉP THUA ---

    $isWin = false;
    $isTie = false;

    if ($nextCard['val'] > $currentCard['val'] && $choice === 'hi') $isWin = true;
    elseif ($nextCard['val'] < $currentCard['val'] && $choice === 'lo') $isWin = true;
    elseif ($nextCard['val'] == $currentCard['val']) $isTie = true;

    $hilo['current_card'] = $nextCard;

    if ($isWin) {
        $hilo['streak']++;

        // Nhân POT lên theo hệ số, làm tròn đến hàng ngàn
        $hilo['pot'] = (int)round(($hilo['pot'] * $hilo_mul) / 1000) * 1000;
        $profitDisplay = number_format($hilo['pot']);

        $_SESSION['hilo'] = $hilo;
        echo json_encode([
            'success' => true,
            'is_end' => false,
            'card' => $nextCard,
            'pot' => $hilo['pot'],
            'message' => 'Chính xác! 🎉 (Pot: ' . $profitDisplay . 'đ)'
        ]);
    } elseif ($isTie) {
        $_SESSION['hilo'] = $hilo;
        echo json_encode([
            'success' => true,
            'is_end' => false,
            'card' => $nextCard,
            'pot' => $hilo['pot'],
            'message' => 'Hòa! Rút tiếp lá nữa 🤝'
        ]);
    } else {
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
    $winnings = $hilo['streak'] > 0 ? $hilo['pot'] : $hilo['bet'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $newBalance = $user['balance'] + $winnings;
        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

        saveHistory($pdo, $userId, $hilo['bet'], $winnings, $hilo['streak']);
        $pdo->commit();
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

echo json_encode(['success' => false, 'error' => 'Yêu cầu không hợp lệ']);
