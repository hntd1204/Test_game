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

// Hàm tạo bộ bài
function getDeck()
{
    $suits = ['♠', '♣', '♦', '♥'];
    $ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($ranks as $rank) {
            $deck[] = ['rank' => $rank, 'suit' => $suit, 'color' => in_array($suit, ['♦', '♥']) ? 'red' : 'black'];
        }
    }
    shuffle($deck);
    return $deck;
}

// Hàm tính điểm
function calcScore($hand)
{
    $score = 0;
    $aces = 0;
    foreach ($hand as $card) {
        if ($card['rank'] === 'A') {
            $aces++;
            $score += 11;
        } elseif (in_array($card['rank'], ['J', 'Q', 'K'])) {
            $score += 10;
        } else {
            $score += (int)$card['rank'];
        }
    }
    while ($score > 21 && $aces > 0) {
        $score -= 10;
        $aces--;
    }
    return $score;
}

// Kiểm tra Xì bàng, Xì dách, Ngũ linh, Quắc
function checkType($hand)
{
    if (count($hand) == 2) {
        $aces = 0;
        $tens = 0;
        foreach ($hand as $c) {
            if ($c['rank'] == 'A') $aces++;
            if (in_array($c['rank'], ['10', 'J', 'Q', 'K'])) $tens++;
        }
        if ($aces == 2) return 'xibang';
        if ($aces == 1 && $tens == 1) return 'xidach';
    }
    if (count($hand) == 5 && calcScore($hand) <= 21) return 'ngulinh';
    if (calcScore($hand) > 21) return 'quac';
    return 'thuong';
}

function saveHistory($pdo, $userId, $bet, $win)
{
    $net = $win - $bet;
    $pdo->prepare("INSERT INTO blackjack_history (user_id, bet, win, net_profit) VALUES (?, ?, ?, ?)")->execute([$userId, $bet, $win, $net]);
}

if ($action === 'deal') {
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

        $newBalance = $user['balance'] - $bet;
        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

        $deck = getDeck();
        $playerHand = [array_pop($deck), array_pop($deck)];
        $dealerHand = [array_pop($deck), array_pop($deck)];

        $_SESSION['bj'] = [
            'deck' => $deck,
            'player' => $playerHand,
            'dealer' => $dealerHand,
            'bet' => $bet,
            'status' => 'playing'
        ];

        $pType = checkType($playerHand);
        $isEnd = false;
        $message = '';
        $winnings = 0;

        // Xử lý Xì Dách / Xì Bàng ngay từ đầu
        if ($pType == 'xibang' || $pType == 'xidach') {
            $dType = checkType($dealerHand);
            if ($dType == 'xibang' || $dType == 'xidach') {
                $winnings = $bet;
                $message = "Hòa! Cả hai đều có Xì dách/Xì bàng.";
            } else {
                $winnings = $bet * 2; // Thắng gấp đôi
                $message = "🎉 THẮNG! Bạn có " . ($pType == 'xibang' ? 'Xì Bàng' : 'Xì Dách');
            }
            $isEnd = true;
        }

        if ($isEnd) {
            $newBalance += $winnings;
            $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
            saveHistory($pdo, $userId, $bet, $winnings);
            $pdo->commit();
            unset($_SESSION['bj']);

            echo json_encode([
                'success' => true,
                'is_end' => true,
                'player' => $playerHand,
                'dealer' => $dealerHand,
                'player_score' => calcScore($playerHand),
                'dealer_score' => calcScore($dealerHand),
                'winnings' => $winnings,
                'net_profit' => $winnings - $bet,
                'balance' => $newBalance,
                'message' => $message
            ]);
            exit;
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'is_end' => false,
            'player' => $playerHand,
            'dealer' => [$dealerHand[0]], // Giấu lá thứ 2
            'player_score' => calcScore($playerHand),
            'balance' => $newBalance
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống']);
    }
    exit;
}

if ($action === 'hit') {
    if (!isset($_SESSION['bj']) || $_SESSION['bj']['status'] !== 'playing') {
        echo json_encode(['success' => false, 'error' => 'Ván chơi không tồn tại']);
        exit;
    }

    $bj = $_SESSION['bj'];
    $bj['player'][] = array_pop($bj['deck']);

    $score = calcScore($bj['player']);
    $type = checkType($bj['player']);
    $isEnd = false;
    $winnings = 0;
    $message = '';

    if ($score > 21) {
        $isEnd = true;
        $message = "💥 QUẮC! Bạn vượt quá 21 điểm.";
    } elseif (count($bj['player']) == 5) {
        $isEnd = true;
        $winnings = $bj['bet'] * 2;
        $message = "🌟 NGŨ LINH! Bạn thắng tuyệt đối.";
    }

    if ($isEnd) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE")->execute([$userId]);
        $user = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetch();

        $newBalance = $user['balance'] + $winnings;
        if ($winnings > 0) $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

        saveHistory($pdo, $userId, $bj['bet'], $winnings);
        $pdo->commit();

        $dealerHand = $bj['dealer'];
        unset($_SESSION['bj']);

        echo json_encode([
            'success' => true,
            'is_end' => true,
            'player' => $bj['player'],
            'dealer' => $dealerHand,
            'player_score' => $score,
            'dealer_score' => calcScore($dealerHand),
            'winnings' => $winnings,
            'net_profit' => $winnings - $bj['bet'],
            'balance' => $newBalance,
            'message' => $message
        ]);
        exit;
    }

    $_SESSION['bj'] = $bj;
    echo json_encode(['success' => true, 'is_end' => false, 'player' => $bj['player'], 'player_score' => $score]);
    exit;
}

if ($action === 'stand') {
    if (!isset($_SESSION['bj']) || $_SESSION['bj']['status'] !== 'playing') {
        echo json_encode(['success' => false, 'error' => 'Ván chơi không tồn tại']);
        exit;
    }

    $bj = $_SESSION['bj'];
    $playerScore = calcScore($bj['player']);

    // Nhà cái rút bài nếu dưới 16 điểm và chưa đủ 5 lá
    while (calcScore($bj['dealer']) < 16 && count($bj['dealer']) < 5) {
        $bj['dealer'][] = array_pop($bj['deck']);
    }

    $dealerScore = calcScore($bj['dealer']);
    $dealerType = checkType($bj['dealer']);

    $winnings = 0;
    $message = '';

    if ($dealerType == 'quac') {
        $winnings = $bj['bet'] * 2;
        $message = "Nhà cái Quắc! BẠN THẮNG 🎉";
    } elseif ($dealerType == 'ngulinh') {
        $message = "Nhà cái Ngũ Linh! BẠN THUA 💥";
    } elseif ($playerScore > $dealerScore) {
        $winnings = $bj['bet'] * 2;
        $message = "BẠN THẮNG 🎉 (" . $playerScore . " vs " . $dealerScore . ")";
    } elseif ($playerScore < $dealerScore) {
        $message = "BẠN THUA 💥 (" . $playerScore . " vs " . $dealerScore . ")";
    } else {
        $winnings = $bj['bet'];
        $message = "HÒA VỐN 🤝 (" . $playerScore . " điểm)";
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE")->execute([$userId]);
    $user = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetch();

    $newBalance = $user['balance'] + $winnings;
    if ($winnings > 0) $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

    saveHistory($pdo, $userId, $bj['bet'], $winnings);
    $pdo->commit();
    unset($_SESSION['bj']);

    echo json_encode([
        'success' => true,
        'is_end' => true,
        'player' => $bj['player'],
        'dealer' => $bj['dealer'],
        'player_score' => $playerScore,
        'dealer_score' => $dealerScore,
        'winnings' => $winnings,
        'net_profit' => $winnings - $bj['bet'],
        'balance' => $newBalance,
        'message' => $message
    ]);
    exit;
}
