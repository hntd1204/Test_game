<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Lấy các mốc ID cuối cùng từ Client gửi lên
$last_spin = isset($_GET['last_spin']) ? (int)$_GET['last_spin'] : 0;
$last_baucua = isset($_GET['last_baucua']) ? (int)$_GET['last_baucua'] : 0;
$last_bj = isset($_GET['last_bj']) ? (int)$_GET['last_bj'] : 0;
$last_hilo = isset($_GET['last_hilo']) ? (int)$_GET['last_hilo'] : 0;
$last_mines = isset($_GET['last_mines']) ? (int)$_GET['last_mines'] : 0;
$last_wd = isset($_GET['last_wd']) ? (int)$_GET['last_wd'] : 0;
$last_gift = isset($_GET['last_gift']) ? (int)$_GET['last_gift'] : 0;

$data = [];

try {
    // 1. Vòng quay
    $stmt = $pdo->prepare("SELECT h.*, u.username FROM spin_history h JOIN users u ON h.user_id = u.id WHERE h.id > ? ORDER BY h.id ASC");
    $stmt->execute([$last_spin]);
    $data['spins'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Bầu Cua
    $stmt = $pdo->prepare("SELECT b.*, u.username FROM baucua_history b JOIN users u ON b.user_id = u.id WHERE b.id > ? ORDER BY b.id ASC");
    $stmt->execute([$last_baucua]);
    $data['baucua'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Xì Dách
    $stmt = $pdo->prepare("SELECT b.*, u.username FROM blackjack_history b JOIN users u ON b.user_id = u.id WHERE b.id > ? ORDER BY b.id ASC");
    $stmt->execute([$last_bj]);
    $data['blackjack'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Hi-Lo
    $stmt = $pdo->prepare("SELECT h.*, u.username FROM hilo_history h JOIN users u ON h.user_id = u.id WHERE h.id > ? ORDER BY h.id ASC");
    $stmt->execute([$last_hilo]);
    $data['hilo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Dò Mìn
    $stmt = $pdo->prepare("SELECT m.*, u.username FROM mines_history m JOIN users u ON m.user_id = u.id WHERE m.id > ? ORDER BY m.id ASC");
    $stmt->execute([$last_mines]);
    $data['mines'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Rút Tiền
    $stmt = $pdo->prepare("SELECT w.*, u.username FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.id > ? ORDER BY w.id ASC");
    $stmt->execute([$last_wd]);
    $data['withdrawals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Đổi Quà
    $stmt = $pdo->prepare("SELECT g.*, u.username FROM user_gifts g JOIN users u ON g.user_id = u.id WHERE g.id > ? ORDER BY g.id ASC");
    $stmt->execute([$last_gift]);
    $data['gifts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Đếm số lượng chờ duyệt mới nhất (để cập nhật số màu đỏ trên menu)
    $data['pending_withdraws'] = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
    $data['pending_gifts'] = $pdo->query("SELECT COUNT(*) FROM user_gifts WHERE status='pending'")->fetchColumn();

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => 'Lỗi DB']);
}
