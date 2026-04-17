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

// Lọc loại bỏ những ô có tiền cược <= 0 (tránh trường hợp mảng gửi lên có ô tiền = 0)
$bets = array_filter($bets, function ($amount) {
    return (int)$amount > 0;
});

if (empty($bets)) {
    echo json_encode(['success' => false, 'error' => 'Vui lòng đặt cược!']);
    exit;
}

// Kiểm tra: Không cho phép cược lớn hơn 3 ô
if (count($bets) > 3) {
    echo json_encode(['success' => false, 'error' => 'Bạn chỉ được cược tối đa 3 ô!']);
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
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user['balance'] < $totalBet) {
        echo json_encode(['success' => false, 'error' => 'Số dư của bạn không đủ!']);
        $pdo->rollBack();
        exit;
    }

    // Trừ tiền cược
    $newBalance = $user['balance'] - $totalBet;

    // --- LẤY HỆ SỐ NHÂN VÀ TỶ LỆ THẮNG TỪ BẢNG SETTINGS ---
    $settingsStmt = $pdo->query("SELECT baucua_multiplier, baucua_win_rate FROM settings WHERE id = 1");
    $settings = $settingsStmt->fetch();
    $baucua_mul = (float)($settings['baucua_multiplier'] ?? 1.0);
    $win_rate = (int)($settings['baucua_win_rate'] ?? 40); // Mặc định tỷ lệ thắng 40% nếu chưa có

    // Lắc xí ngầu
    $dice = [];

    // Quyết định ván này cho User thắng hay thua
    $is_rigged_to_lose = (rand(1, 100) > $win_rate);

    if ($is_rigged_to_lose) {
        // TÌM Ô CƯỢC ĐỂ NHÀ CÁI KHÔNG LỖ
        // Tìm các con vật mà người chơi KHÔNG đặt cược
        $unbet_options = array_diff($valid_options, array_keys($bets));

        // Chống lỗi mảng rỗng (dù code đã chặn cược > 3 ô, nhưng cứ phòng hờ)
        if (empty($unbet_options)) {
            asort($bets); // Sắp xếp mảng bets tăng dần
            $unbet_options = [array_key_first($bets)]; // Bốc ô cược ít tiền nhất
        } else {
            // Re-index mảng để dùng array_rand một cách an toàn
            $unbet_options = array_values($unbet_options);
        }

        // Đổ xí ngầu cố tình rơi vào các ô nhà cái an toàn
        for ($i = 0; $i < 3; $i++) {
            $dice[] = $unbet_options[array_rand($unbet_options)];
        }
    } else {
        // Xanh chín (Random bình thường nếu rơi vào % chiến thắng của user)
        for ($i = 0; $i < 3; $i++) {
            $dice[] = $valid_options[array_rand($valid_options)];
        }
    }

    $winnings = 0;
    $winning_counts = [];

    // Tính tiền thắng
    foreach ($bets as $key => $amount) {
        $amount = (int)$amount;
        if ($amount > 0) {
            $count = 0;
            foreach ($dice as $d) {
                if ($d === $key) $count++;
            }
            if ($count > 0) {
                // Lợi nhuận = Vốn cược * Số lần xí ngầu ra * Hệ số nhân (Admin cài đặt)
                // Tiền thắng = Vốn cược (hoàn gốc) + Lợi nhuận
                $winnings += $amount + ($amount * $count * $baucua_mul);
                $winning_counts[$key] = $count;
            }
        }
    }

    // Cộng tiền thắng và tăng số ván Bầu Cua (baucua_count)
    $newBalance += $winnings;
    $updateStmt = $pdo->prepare("UPDATE users SET balance = ?, baucua_count = baucua_count + 1 WHERE id = ?");
    $updateStmt->execute([$newBalance, $userId]);

    // Tính Lãi/Lỗ thực tế
    $net_profit = $winnings - $totalBet;

    // Lưu lịch sử cho Admin
    $bet_details_json = json_encode($bets);
    $dice_result_str = implode(',', $dice);
    $insertHistory = $pdo->prepare("INSERT INTO baucua_history (user_id, bet_details, dice_result, total_bet, total_win, net_profit) VALUES (?, ?, ?, ?, ?, ?)");
    $insertHistory->execute([$userId, $bet_details_json, $dice_result_str, $totalBet, $winnings, $net_profit]);

    // --- XỬ LÝ NHIỆM VỤ ĐA NĂNG ---
    $mission_info = ['rewarded' => false];
    $currentCount = $pdo->query("SELECT baucua_count FROM users WHERE id = $userId")->fetchColumn();
    $missions = $pdo->query("SELECT * FROM mission_settings WHERE mission_key = 'baucua_count'")->fetchAll();

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

    // Lấy lại tiến độ để hiện UI
    if (!$mission_info['rewarded'] && count($missions) > 0) {
        $mission_info['current'] = $currentCount;
        $mission_info['target'] = $missions[0]['target_count'];
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'dice' => $dice,
        'winnings' => $winnings,
        'total_bet' => $totalBet,
        'net_profit' => $net_profit,
        'new_balance' => $newBalance,
        'winning_counts' => $winning_counts,
        'mission' => $mission_info
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống!']);
}
