<?php
session_start();
require 'db.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Bạn không có quyền truy cập.");
}

// Lấy thông báo từ Session
$msg = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// 1. Xử lý lưu cài đặt hệ thống
if (isset($_POST['update_settings'])) {
    $min = $_POST['min_reward'];
    $max = $_POST['max_reward'];
    $stmt = $pdo->prepare("UPDATE settings SET min_reward = ?, max_reward = ? WHERE id = 1");
    $stmt->execute([$min, $max]);
    $_SESSION['msg'] = "✅ Đã cập nhật cài đặt hệ thống!";
    header("Location: admin.php");
    exit;
}

// 2. Xử lý CỘNG / TRỪ lượt quay
if (isset($_POST['adjust_spins'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    $spins_count = (int)$_POST['spins_count'];
    $action_type = $_POST['action_type'];
    if ($target_user_id > 0 && $spins_count > 0) {
        if ($action_type === 'add') {
            $pdo->prepare("UPDATE users SET spins_available = spins_available + ? WHERE id = ? AND role = 'user'")->execute([$spins_count, $target_user_id]);
            $_SESSION['msg'] = "✅ Đã CỘNG thêm $spins_count lượt quay cho người dùng!";
        } elseif ($action_type === 'sub') {
            $pdo->prepare("UPDATE users SET spins_available = GREATEST(0, spins_available - ?) WHERE id = ? AND role = 'user'")->execute([$spins_count, $target_user_id]);
            $_SESSION['msg'] = "✅ Đã TRỪ $spins_count lượt quay của người dùng!";
        }
    }
    header("Location: admin.php");
    exit;
}

// 3. Xử lý Duyệt / Từ chối Rút tiền
if (isset($_POST['handle_withdraw'])) {
    $wd_id = (int)$_POST['withdraw_id'];
    $wd_action = $_POST['withdraw_action'];
    $wdStmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'");
    $wdStmt->execute([$wd_id]);
    $wd = $wdStmt->fetch();
    if ($wd) {
        if ($wd_action === 'approve') {
            $pdo->prepare("UPDATE withdrawals SET status = 'approved' WHERE id = ?")->execute([$wd_id]);
            $_SESSION['msg'] = "✅ Đã DUYỆT phiếu rút tiền #$wd_id!";
        } elseif ($wd_action === 'reject') {
            $pdo->prepare("UPDATE withdrawals SET status = 'rejected' WHERE id = ?")->execute([$wd_id]);
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$wd['amount'], $wd['user_id']]);
            $_SESSION['msg'] = "❌ Đã TỪ CHỐI phiếu #$wd_id và hoàn tiền cho User!";
        }
    }
    header("Location: admin.php");
    exit;
}

// 4. Xử lý Thêm/Xóa quà
if (isset($_POST['add_shop_item'])) {
    $name = trim($_POST['item_name']);
    $cost = (int)$_POST['item_cost'];
    if (!empty($name) && $cost > 0) {
        $pdo->prepare("INSERT INTO shop_items (name, cost) VALUES (?, ?)")->execute([$name, $cost]);
        $_SESSION['msg'] = "✅ Đã thêm món quà mới vào Shop!";
    }
    header("Location: admin.php");
    exit;
}
if (isset($_POST['delete_shop_item'])) {
    $id = (int)$_POST['item_id'];
    $pdo->prepare("DELETE FROM shop_items WHERE id = ?")->execute([$id]);
    $_SESSION['msg'] = "✅ Đã xóa quà tặng khỏi Shop!";
    header("Location: admin.php");
    exit;
}

// 5. Xử lý Duyệt / Từ chối Đơn Đổi Quà
if (isset($_POST['handle_gift'])) {
    $gift_id = (int)$_POST['gift_id'];
    $gift_action = $_POST['gift_action'];
    $gStmt = $pdo->prepare("SELECT * FROM user_gifts WHERE id = ? AND status = 'pending'");
    $gStmt->execute([$gift_id]);
    $gift = $gStmt->fetch();
    if ($gift) {
        if ($gift_action === 'complete') {
            $pdo->prepare("UPDATE user_gifts SET status = 'completed' WHERE id = ?")->execute([$gift_id]);
            $_SESSION['msg'] = "✅ Đã xác nhận giao quà thành công đơn #$gift_id!";
        } elseif ($gift_action === 'reject') {
            $pdo->prepare("UPDATE user_gifts SET status = 'rejected' WHERE id = ?")->execute([$gift_id]);
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$gift['cost'], $gift['user_id']]);
            $_SESSION['msg'] = "❌ Đã TỪ CHỐI đơn đổi quà #$gift_id và hoàn tiền cho User!";
        }
    }
    header("Location: admin.php");
    exit;
}

// 6. Xử lý Nhiệm vụ
if (isset($_POST['add_mission'])) {
    $name = trim($_POST['mission_name']);
    $game_type = $_POST['game_type'];
    $target = (int)$_POST['target_count'];
    $reward = (int)$_POST['reward_spins'];
    $mapping = ['baucua' => 'baucua_count', 'blackjack' => 'blackjack_count', 'hilo' => 'hilo_count'];
    $key = $mapping[$game_type] ?? 'baucua_count';
    $pdo->prepare("INSERT INTO mission_settings (mission_name, mission_key, target_count, reward_spins) VALUES (?, ?, ?, ?)")->execute([$name, $key, $target, $reward]);
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN `$key` INT DEFAULT 0");
    } catch (Exception $e) {
    }
    $_SESSION['msg'] = "✅ Đã thêm nhiệm vụ mới thành công!";
    header("Location: admin.php");
    exit;
}
if (isset($_POST['update_mission'])) {
    $m_id = (int)$_POST['m_id'];
    $target = (int)$_POST['target_count'];
    $reward = (int)$_POST['reward_spins'];
    $pdo->prepare("UPDATE mission_settings SET target_count = ?, reward_spins = ? WHERE id = ?")->execute([$target, $reward, $m_id]);
    $_SESSION['msg'] = "✅ Đã cập nhật cấu hình nhiệm vụ!";
    header("Location: admin.php");
    exit;
}
if (isset($_GET['delete_mission'])) {
    $m_id = (int)$_GET['delete_mission'];
    $pdo->prepare("DELETE FROM mission_settings WHERE id = ?")->execute([$m_id]);
    $_SESSION['msg'] = "✅ Đã xóa nhiệm vụ thành công!";
    header("Location: admin.php");
    exit;
}

// --- FETCH DỮ LIỆU HIỂN THỊ & THỐNG KÊ ---
$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
$users_stmt = $pdo->query("SELECT id, username, balance, spins_available FROM users WHERE role = 'user' ORDER BY id DESC");
$user_list = $users_stmt->fetchAll();
$missions = $pdo->query("SELECT * FROM mission_settings")->fetchAll();

$history_stmt = $pdo->query("SELECT h.id, u.username, h.reward, h.created_at FROM spin_history h JOIN users u ON h.user_id = u.id ORDER BY h.id DESC LIMIT 50");
$histories = $history_stmt->fetchAll();
$max_history_id = count($histories) > 0 ? $histories[0]['id'] : 0;

// Tính toán lợi nhuận
$bc_stats = $pdo->query("SELECT SUM(total_bet) as sum_bet, SUM(total_win) as sum_win FROM baucua_history")->fetch();
$bc_profit = ($bc_stats['sum_bet'] ?? 0) - ($bc_stats['sum_win'] ?? 0);
$bj_stats = $pdo->query("SELECT SUM(bet) as sum_bet, SUM(win) as sum_win FROM blackjack_history")->fetch();
$bj_profit = ($bj_stats['sum_bet'] ?? 0) - ($bj_stats['sum_win'] ?? 0);
$hilo_stats = $pdo->query("SELECT SUM(bet) as sum_bet, SUM(win) as sum_win FROM hilo_history")->fetch();
$hilo_profit = ($hilo_stats['sum_bet'] ?? 0) - ($hilo_stats['sum_win'] ?? 0);
$total_profit = $bc_profit + $bj_profit + $hilo_profit;

$bc_histories = $pdo->query("SELECT b.*, u.username FROM baucua_history b JOIN users u ON b.user_id = u.id ORDER BY b.id DESC LIMIT 50")->fetchAll();
$bj_histories = $pdo->query("SELECT b.*, u.username FROM blackjack_history b JOIN users u ON b.user_id = u.id ORDER BY b.id DESC LIMIT 50")->fetchAll();
$hilo_histories = $pdo->query("SELECT h.*, u.username FROM hilo_history h JOIN users u ON h.user_id = u.id ORDER BY h.id DESC LIMIT 50")->fetchAll();
$bc_icons = ['nai' => '🦌', 'bau' => '🎃', 'ga' => '🐓', 'ca' => '🐟', 'cua' => '🦀', 'tom' => '🦐'];

// Thống kê Quick Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$pending_withdraws = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
$pending_gifts = $pdo->query("SELECT COUNT(*) FROM user_gifts WHERE status='pending'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Tùy chỉnh thanh cuộn cho bảng */
        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex text-slate-800 font-sans">

    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-3"></div>

    <aside
        class="w-64 bg-slate-900 text-slate-300 flex flex-col hidden md:flex shrink-0 h-screen sticky top-0 shadow-xl z-40">
        <div class="h-16 flex items-center px-6 border-b border-slate-800 bg-slate-950">
            <h1 class="text-xl font-black text-white tracking-wider flex items-center gap-2">
                <span class="text-blue-500">⚙️</span> ADMIN PANEL
            </h1>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto custom-scrollbar">
            <p class="px-2 text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 mt-4">Menu Chính</p>
            <button onclick="switchTab('dashboard', this)"
                class="nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition text-left hover:bg-slate-800 hover:text-white">
                📊 Tổng Quan & Game
            </button>
            <button onclick="switchTab('users', this)"
                class="nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition text-left hover:bg-slate-800 hover:text-white">
                👥 Người Dùng & Rút Tiền
                <?php if ($pending_withdraws > 0) echo "<span class='ml-auto bg-rose-500 text-white text-[10px] px-2 py-0.5 rounded-full'>$pending_withdraws</span>"; ?>
            </button>
            <button onclick="switchTab('system', this)"
                class="nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition text-left hover:bg-slate-800 hover:text-white">
                🛠️ Hệ Thống & Cửa Hàng
                <?php if ($pending_gifts > 0) echo "<span class='ml-auto bg-amber-500 text-white text-[10px] px-2 py-0.5 rounded-full'>$pending_gifts</span>"; ?>
            </button>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center gap-3 mb-4 px-2">
                <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">A
                </div>
                <div>
                    <p class="text-sm font-bold text-white">Administrator</p>
                    <p class="text-xs text-slate-400">Đang hoạt động</p>
                </div>
            </div>
            <a href="logout.php"
                class="block w-full text-center bg-rose-600 hover:bg-rose-700 text-white py-2 rounded-lg text-sm font-bold transition">Đăng
                Xuất</a>
        </div>
    </aside>

    <div class="md:hidden fixed top-0 w-full bg-slate-900 h-16 flex items-center justify-between px-4 z-50">
        <h1 class="text-lg font-black text-white tracking-wider flex items-center gap-2">⚙️ ADMIN</h1>
        <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-1">
            <button onclick="switchTab('dashboard', this)"
                class="nav-btn bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs whitespace-nowrap">Tổng
                Quan</button>
            <button onclick="switchTab('users', this)"
                class="nav-btn bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs whitespace-nowrap relative">Người
                Dùng
                <?= $pending_withdraws > 0 ? "<span class='absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full'></span>" : "" ?></button>
            <button onclick="switchTab('system', this)"
                class="nav-btn bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs whitespace-nowrap relative">Hệ
                Thống
                <?= $pending_gifts > 0 ? "<span class='absolute -top-1 -right-1 w-3 h-3 bg-amber-500 rounded-full'></span>" : "" ?></button>
        </div>
    </div>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto md:mt-0 mt-16 p-4 sm:p-8 bg-slate-100/50 relative">

        <?php if ($msg): ?>
            <div
                class="bg-emerald-100 text-emerald-800 border-l-4 border-emerald-500 p-4 rounded shadow-sm mb-6 font-medium animate-pulse">
                <?= $msg ?></div>
        <?php endif; ?>

        <div id="tab-dashboard" class="tab-content active">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-2xl">
                        👥</div>
                    <div>
                        <p class="text-xs text-slate-500 font-bold uppercase">Tổng User</p>
                        <p class="text-2xl font-black text-slate-800"><?= $total_users ?></p>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-2xl">
                        💸</div>
                    <div>
                        <p class="text-xs text-slate-500 font-bold uppercase">Rút Tiền Chờ</p>
                        <p class="text-2xl font-black text-slate-800"><?= $pending_withdraws ?></p>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-2xl">
                        🎁</div>
                    <div>
                        <p class="text-xs text-slate-500 font-bold uppercase">Đơn Quà Chờ</p>
                        <p class="text-2xl font-black text-slate-800"><?= $pending_gifts ?></p>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl">
                        📈</div>
                    <div>
                        <p class="text-xs text-slate-500 font-bold uppercase">Lãi Nhà Cái (Game)</p>
                        <p
                            class="text-lg sm:text-xl font-black <?= $total_profit >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                            <?= number_format($total_profit) ?>đ</p>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <div
                    class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 lg:col-span-1 h-[500px] flex flex-col">
                    <div class="flex justify-between items-center mb-4 border-b pb-3">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">🎰 Vòng Quay Realtime</h2>
                        <span class="relative flex h-3 w-3"><span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span
                                class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span></span>
                    </div>
                    <div class="overflow-y-auto custom-scrollbar flex-1 pr-2">
                        <table class="w-full text-left text-sm text-slate-600">
                            <tbody id="history-table-body" class="divide-y divide-slate-100">
                                <?php foreach ($histories as $h): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3 text-xs text-slate-400">
                                            <?= date('H:i:s', strtotime($h['created_at'])) ?></td>
                                        <td class="py-3 font-medium text-blue-600"><?= htmlspecialchars($h['username']) ?>
                                        </td>
                                        <td class="py-3 font-bold text-green-600 text-right">
                                            +<?= number_format($h['reward']) ?>đ</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                            <h2 class="font-bold text-slate-800">🎲 Lịch Sử Bầu Cua</h2>
                            <span
                                class="text-sm font-bold <?= $bc_profit >= 0 ? 'text-green-600' : 'text-red-500' ?>">Lãi:
                                <?= number_format($bc_profit) ?>đ</span>
                        </div>
                        <div class="overflow-x-auto max-h-[250px] custom-scrollbar">
                            <table class="w-full text-left text-sm text-slate-600 whitespace-nowrap">
                                <thead class="bg-slate-50 text-slate-500 sticky top-0 text-xs">
                                    <tr>
                                        <th class="px-4 py-2">Giờ</th>
                                        <th class="px-4 py-2">User</th>
                                        <th class="px-4 py-2">KQ</th>
                                        <th class="px-4 py-2 text-right">Lãi/Lỗ User</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($bc_histories as $bc): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-2 text-xs"><?= date('H:i', strtotime($bc['created_at'])) ?>
                                            </td>
                                            <td class="px-4 py-2 font-bold text-blue-600">
                                                <?= htmlspecialchars($bc['username']) ?></td>
                                            <td class="px-4 py-2 text-lg"><?= implode(' ', array_map(function ($a) use ($bc_icons) {
                                                                                return $bc_icons[$a];
                                                                            }, explode(',', $bc['dice_result']))) ?>
                                            </td>
                                            <td
                                                class="px-4 py-2 text-right font-bold <?= $bc['net_profit'] > 0 ? 'text-green-500' : ($bc['net_profit'] < 0 ? 'text-red-500' : 'text-slate-500') ?>">
                                                <?= $bc['net_profit'] > 0 ? '+' : '' ?><?= number_format($bc['net_profit']) ?>đ
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                                <h2 class="font-bold text-slate-800">🃏 Lịch Sử Xì Dách</h2>
                                <span
                                    class="text-sm font-bold <?= $bj_profit >= 0 ? 'text-green-600' : 'text-red-500' ?>">Lãi:
                                    <?= number_format($bj_profit) ?>đ</span>
                            </div>
                            <div class="overflow-x-auto max-h-[200px] custom-scrollbar">
                                <table class="w-full text-left text-sm text-slate-600 whitespace-nowrap text-xs">
                                    <thead class="bg-slate-50 text-slate-500 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2">User</th>
                                            <th class="px-3 py-2 text-right">Cược</th>
                                            <th class="px-3 py-2 text-right">KQ User</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($bj_histories as $bj): ?>
                                            <tr>
                                                <td class="px-3 py-2 text-blue-600 font-bold">
                                                    <?= htmlspecialchars($bj['username']) ?></td>
                                                <td class="px-3 py-2 text-right"><?= number_format($bj['bet']) ?></td>
                                                <td
                                                    class="px-3 py-2 text-right font-bold <?= $bj['net_profit'] > 0 ? 'text-green-500' : ($bj['net_profit'] < 0 ? 'text-red-500' : '') ?>">
                                                    <?= $bj['net_profit'] > 0 ? '+' : '' ?><?= number_format($bj['net_profit']) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                                <h2 class="font-bold text-slate-800">🃏 Lịch Sử Hi-Lo</h2>
                                <span
                                    class="text-sm font-bold <?= $hilo_profit >= 0 ? 'text-green-600' : 'text-red-500' ?>">Lãi:
                                    <?= number_format($hilo_profit) ?>đ</span>
                            </div>
                            <div class="overflow-x-auto max-h-[200px] custom-scrollbar">
                                <table class="w-full text-left text-sm text-slate-600 whitespace-nowrap text-xs">
                                    <thead class="bg-slate-50 text-slate-500 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2">User</th>
                                            <th class="px-3 py-2 text-center">Chuỗi</th>
                                            <th class="px-3 py-2 text-right">KQ User</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($hilo_histories as $hl): ?>
                                            <tr>
                                                <td class="px-3 py-2 text-blue-600 font-bold">
                                                    <?= htmlspecialchars($hl['username']) ?></td>
                                                <td class="px-3 py-2 text-center text-indigo-500 font-bold">
                                                    <?= $hl['streak'] ?></td>
                                                <td
                                                    class="px-3 py-2 text-right font-bold <?= $hl['net_profit'] > 0 ? 'text-green-500' : ($hl['net_profit'] < 0 ? 'text-red-500' : '') ?>">
                                                    <?= $hl['net_profit'] > 0 ? '+' : '' ?><?= number_format($hl['net_profit']) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-users" class="tab-content">
            <div class="grid lg:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">💸 Duyệt Yêu
                        Cầu Rút Tiền
                        <?php if ($pending_withdraws > 0) echo "<span class='bg-rose-100 text-rose-600 px-2 py-0.5 rounded text-xs'>$pending_withdraws</span>"; ?>
                    </h2>
                    <div class="overflow-y-auto max-h-[500px] custom-scrollbar">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-100 text-slate-700 sticky top-0">
                                <tr>
                                    <th class="px-3 py-3">Tài khoản</th>
                                    <th class="px-3 py-3">Số tiền</th>
                                    <th class="px-3 py-3 text-right">Hành động</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                try {
                                    $wd_stmt = $pdo->query("SELECT w.*, u.username FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY FIELD(w.status, 'pending', 'approved', 'rejected'), w.id DESC");
                                    while ($w = $wd_stmt->fetch()):
                                ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="px-3 py-3 font-bold text-slate-800">
                                                <?= htmlspecialchars($w['username']) ?></td>
                                            <td class="px-3 py-3 font-bold text-blue-600"><?= number_format($w['amount']) ?>đ
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <?php if ($w['status'] == 'pending'): ?>
                                                    <form method="POST" class="inline-flex gap-1">
                                                        <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
                                                        <button type="submit" name="handle_withdraw" value="1"
                                                            onclick="document.getElementById('wd_act_<?= $w['id'] ?>').value='approve'"
                                                            class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition">Duyệt</button>
                                                        <button type="submit" name="handle_withdraw" value="1"
                                                            onclick="document.getElementById('wd_act_<?= $w['id'] ?>').value='reject'"
                                                            class="bg-rose-500 hover:bg-rose-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition">Hủy</button>
                                                        <input type="hidden" id="wd_act_<?= $w['id'] ?>" name="withdraw_action"
                                                            value="">
                                                    </form>
                                                <?php elseif ($w['status'] == 'approved'): ?>
                                                    <span
                                                        class="text-emerald-500 font-bold text-xs bg-emerald-50 px-2 py-1 rounded">Đã
                                                        duyệt</span>
                                                <?php else: ?>
                                                    <span class="text-rose-500 font-bold text-xs bg-rose-50 px-2 py-1 rounded">Từ
                                                        chối</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                <?php endwhile;
                                } catch (Exception $e) {
                                } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">🎰 Nạp/Trừ Lượt Quay User</h2>
                        <form method="POST" class="space-y-4">
                            <select name="target_user_id" required
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 cursor-pointer text-sm">
                                <option value="">-- Chọn Người Dùng --</option>
                                <?php foreach ($user_list as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (Hiện có:
                                        <?= $u['spins_available'] ?> lượt)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="flex gap-4">
                                <input type="number" name="spins_count" value="1" min="1" required
                                    class="flex-1 px-4 py-3 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 bg-slate-50 text-sm"
                                    placeholder="Số lượng">
                                <select name="action_type"
                                    class="w-1/3 px-4 py-3 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 bg-slate-50 text-sm font-bold cursor-pointer">
                                    <option value="add" class="text-emerald-600">➕ Cộng</option>
                                    <option value="sub" class="text-rose-600">➖ Trừ</option>
                                </select>
                            </div>
                            <button type="submit" name="adjust_spins"
                                class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 rounded-xl transition shadow-md">Thực
                                Hiện</button>
                        </form>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">👥 Danh Sách Người Dùng</h2>
                        <div class="overflow-y-auto max-h-[300px] custom-scrollbar">
                            <table class="w-full text-left text-sm text-slate-600 whitespace-nowrap">
                                <thead class="bg-slate-100 text-slate-700 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3">Tài khoản</th>
                                        <th class="px-4 py-3 text-right">Số dư</th>
                                        <th class="px-4 py-3 text-center">Lượt quay</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($user_list as $row): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-bold text-slate-800">
                                                <?= htmlspecialchars($row['username']) ?></td>
                                            <td class="px-4 py-3 text-blue-600 font-bold text-right">
                                                <?= number_format($row['balance']) ?>đ</td>
                                            <td class="px-4 py-3 font-bold text-center bg-slate-50/50">
                                                <?= $row['spins_available'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-system" class="tab-content">
            <div class="grid lg:grid-cols-2 gap-6">
                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">⚙️ Cài đặt Thưởng Vòng Quay</h2>
                        <form method="POST" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1">Tối thiểu (VNĐ)</label>
                                    <input type="number" name="min_reward" value="<?= $settings['min_reward'] ?>"
                                        required
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 text-sm font-bold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1">Tối đa (VNĐ)</label>
                                    <input type="number" name="max_reward" value="<?= $settings['max_reward'] ?>"
                                        required
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 text-sm font-bold">
                                </div>
                            </div>
                            <button type="submit" name="update_settings"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-md">Lưu
                                Cài Đặt Hệ Thống</button>
                        </form>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">🎯 Quản Lý Nhiệm Vụ</h2>
                        <form method="POST"
                            class="bg-slate-50 border border-slate-100 p-4 rounded-xl mb-6 shadow-inner">
                            <div class="mb-3">
                                <label class="block text-xs font-bold text-slate-500 mb-1">Tên nhiệm vụ</label>
                                <input type="text" name="mission_name" required placeholder="VD: Chơi 5 ván Xì Dách"
                                    class="w-full px-3 py-2 border rounded-lg outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1">Loại Game</label>
                                    <select name="game_type"
                                        class="w-full px-3 py-2 border rounded-lg outline-none text-sm cursor-pointer">
                                        <option value="baucua">Bầu Cua Tôm Cá</option>
                                        <option value="blackjack">Xì Dách</option>
                                        <option value="hilo">Lật Bài (Hi-Lo)</option>
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    <div class="w-1/2">
                                        <label class="block text-xs font-bold text-slate-500 mb-1">Target</label>
                                        <input type="number" name="target_count" placeholder="Ván" required
                                            class="w-full px-3 py-2 border rounded-lg outline-none text-sm text-center">
                                    </div>
                                    <div class="w-1/2">
                                        <label class="block text-xs font-bold text-slate-500 mb-1">Thưởng</label>
                                        <input type="number" name="reward_spins" placeholder="Lượt" required
                                            class="w-full px-3 py-2 border rounded-lg outline-none text-sm text-center">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="add_mission"
                                class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 rounded-lg transition text-sm">Thêm
                                Mới</button>
                        </form>

                        <div class="space-y-3 max-h-[300px] overflow-y-auto custom-scrollbar pr-2">
                            <?php foreach ($missions as $m): ?>
                                <form method="POST"
                                    class="bg-white border border-slate-200 p-3 rounded-xl shadow-sm flex flex-col gap-2">
                                    <input type="hidden" name="m_id" value="<?= $m['id'] ?>">
                                    <div class="flex justify-between items-center border-b border-slate-100 pb-2 mb-1">
                                        <p class="font-bold text-sm text-slate-800">
                                            <?= htmlspecialchars($m['mission_name']) ?></p>
                                        <a href="?delete_mission=<?= $m['id'] ?>"
                                            onclick="return confirm('Xóa nhiệm vụ này?')"
                                            class="text-rose-500 hover:text-rose-700 text-[10px] font-bold bg-rose-50 px-2 py-1 rounded">XÓA</a>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs text-slate-500">Yêu cầu:</span>
                                            <input type="number" name="target_count" value="<?= $m['target_count'] ?>"
                                                class="w-12 px-1 py-1 border rounded text-xs text-center font-bold">
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs text-slate-500">Thưởng:</span>
                                            <input type="number" name="reward_spins" value="<?= $m['reward_spins'] ?>"
                                                class="w-12 px-1 py-1 border rounded text-xs text-center font-bold text-green-600">
                                        </div>
                                        <button type="submit" name="update_mission"
                                            class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1.5 rounded-lg font-bold text-xs transition">Lưu</button>
                                    </div>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">🛒 Quản Lý Shop Đổi Quà</h2>
                        <form method="POST" class="flex gap-2 mb-4">
                            <input type="text" name="item_name" placeholder="Tên món quà..." required
                                class="flex-1 px-3 py-2 border border-slate-200 rounded-lg outline-none text-sm focus:ring-2 focus:ring-blue-500">
                            <input type="number" name="item_cost" placeholder="Giá (VNĐ)" required
                                class="w-1/3 px-3 py-2 border border-slate-200 rounded-lg outline-none text-sm focus:ring-2 focus:ring-blue-500 text-right">
                            <button type="submit" name="add_shop_item"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-lg transition text-sm">Thêm</button>
                        </form>
                        <div class="overflow-y-auto max-h-[200px] custom-scrollbar border border-slate-100 rounded-xl">
                            <table class="w-full text-left text-sm text-slate-600">
                                <thead class="bg-slate-50 text-slate-700 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2">Tên quà</th>
                                        <th class="px-3 py-2">Giá tiền</th>
                                        <th class="px-3 py-2 text-right">Xóa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php try {
                                        $items_stmt = $pdo->query("SELECT * FROM shop_items ORDER BY cost ASC");
                                        while ($item = $items_stmt->fetch()): ?>
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-3 py-2 font-bold text-slate-800">
                                                    <?= htmlspecialchars($item['name']) ?></td>
                                                <td class="px-3 py-2 font-bold text-emerald-600">
                                                    <?= number_format($item['cost']) ?>đ</td>
                                                <td class="px-3 py-2 text-right">
                                                    <form method="POST"
                                                        onsubmit="return confirm('Bạn có chắc muốn xóa món quà này?');">
                                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                        <button type="submit" name="delete_shop_item"
                                                            class="text-rose-500 hover:bg-rose-50 px-2 py-1 rounded text-xs font-bold">Xóa</button>
                                                    </form>
                                                </td>
                                            </tr>
                                    <?php endwhile;
                                    } catch (Exception $e) {
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">🎁 Duyệt
                            Đơn Đổi Quà
                            <?php if ($pending_gifts > 0) echo "<span class='bg-amber-100 text-amber-600 px-2 py-0.5 rounded text-xs'>$pending_gifts</span>"; ?>
                        </h2>
                        <div class="overflow-y-auto max-h-[300px] custom-scrollbar">
                            <table class="w-full text-left text-sm text-slate-600">
                                <thead class="bg-slate-50 text-slate-700 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-3">Tài khoản</th>
                                        <th class="px-3 py-3">Món quà</th>
                                        <th class="px-3 py-3 text-right">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php try {
                                        $gifts_stmt = $pdo->query("SELECT g.*, u.username FROM user_gifts g JOIN users u ON g.user_id = u.id ORDER BY FIELD(g.status, 'pending', 'completed', 'rejected'), g.id DESC");
                                        while ($g = $gifts_stmt->fetch()): ?>
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="px-3 py-3 font-bold text-slate-800">
                                                    <?= htmlspecialchars($g['username']) ?></td>
                                                <td class="px-3 py-3 font-bold text-emerald-600">
                                                    <?= htmlspecialchars($g['gift_name']) ?></td>
                                                <td class="px-3 py-3 text-right">
                                                    <?php if ($g['status'] == 'pending'): ?>
                                                        <form method="POST" class="inline-flex gap-1 flex-col sm:flex-row">
                                                            <input type="hidden" name="gift_id" value="<?= $g['id'] ?>">
                                                            <button type="submit" name="handle_gift" value="1"
                                                                onclick="document.getElementById('gf_act_<?= $g['id'] ?>').value='complete'"
                                                                class="bg-emerald-500 hover:bg-emerald-600 text-white px-2 py-1 rounded text-xs font-bold transition">Giao</button>
                                                            <button type="submit" name="handle_gift" value="1"
                                                                onclick="document.getElementById('gf_act_<?= $g['id'] ?>').value='reject'"
                                                                class="bg-rose-500 hover:bg-rose-600 text-white px-2 py-1 rounded text-xs font-bold transition">Hủy</button>
                                                            <input type="hidden" id="gf_act_<?= $g['id'] ?>" name="gift_action"
                                                                value="">
                                                        </form>
                                                    <?php elseif ($g['status'] == 'completed'): ?>
                                                        <span
                                                            class="text-emerald-500 font-bold text-[10px] bg-emerald-50 px-2 py-1 rounded uppercase">Đã
                                                            giao</span>
                                                    <?php else: ?>
                                                        <span
                                                            class="text-rose-500 font-bold text-[10px] bg-rose-50 px-2 py-1 rounded uppercase">Đã
                                                            hủy</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endwhile;
                                    } catch (Exception $e) {
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Logic chuyển Tab & Lưu trạng thái bằng LocalStorage
        function switchTab(tabId, btnElement) {
            // 1. Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            // 2. Remove styling from all buttons
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white', 'shadow-md');
                if (btnElement.closest('aside')) btn.classList.add('text-slate-300'); // Desktop
                if (btnElement.closest('.md\\:hidden')) btn.classList.add('bg-slate-800'); // Mobile
            });
            // 3. Show target tab
            document.getElementById('tab-' + tabId).classList.add('active');

            // 4. Highlight active buttons (Desktop + Mobile)
            document.querySelectorAll(`.nav-btn[onclick*="'${tabId}'"]`).forEach(activeBtn => {
                activeBtn.classList.remove('text-slate-300', 'bg-slate-800');
                activeBtn.classList.add('bg-blue-600', 'text-white', 'shadow-md');
            });

            // 5. Save to LocalStorage
            localStorage.setItem('activeAdminTab', tabId);
        }

        // Khôi phục Tab sau khi F5 hoặc Submit Form
        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('activeAdminTab') || 'dashboard';
            const targetBtn = document.querySelector(`.nav-btn[onclick*="'${savedTab}'"]`);
            if (targetBtn) {
                switchTab(savedTab, targetBtn);
            } else {
                switchTab('dashboard', document.querySelector('.nav-btn'));
            }
        });

        // Realtime Lịch Sử Vòng Quay (Giữ nguyên logic cũ)
        let lastId = <?= $max_history_id ?>;

        function showToast(username, reward) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className =
                'bg-white border-l-4 border-green-500 shadow-xl rounded-lg p-4 flex items-center gap-4 transform transition-all duration-300 translate-x-10 opacity-0 min-w-[300px]';
            toast.innerHTML =
                `<div class="text-green-500 text-3xl animate-bounce">🎁</div><div><h4 class="font-bold text-slate-800">${username} vừa trúng!</h4><p class="text-green-600 font-extrabold">+${Number(reward).toLocaleString('vi-VN')} VNĐ</p></div>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('translate-x-10', 'opacity-0');
                toast.classList.add('translate-x-0', 'opacity-100');
            }, 10);
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-x-10');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        function prependHistory(item) {
            const tbody = document.getElementById('history-table-body');
            const tr = document.createElement('tr');
            tr.className = 'transition bg-emerald-50';
            const d = new Date(item.created_at);
            const timeStr = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2) + ':' + ('0' + d
                .getSeconds()).slice(-2);
            tr.innerHTML =
                `<td class="py-3 text-xs text-slate-400">${timeStr}</td><td class="py-3 font-medium text-blue-600">${item.username}</td><td class="py-3 font-bold text-green-600 text-right">+${Number(item.reward).toLocaleString('vi-VN')}đ</td>`;
            tbody.prepend(tr);
            setTimeout(() => tr.classList.remove('bg-emerald-50'), 2000);
        }
        setInterval(async () => {
            try {
                const res = await fetch(`get_new_spins.php?last_id=${lastId}`);
                if (!res.ok) return;
                const data = await res.json();
                if (data && data.length > 0) {
                    data.forEach(item => {
                        showToast(item.username, item.reward);
                        prependHistory(item);
                        if (parseInt(item.id) > lastId) lastId = parseInt(item.id);
                    });
                }
            } catch (err) {}
        }, 3000);
    </script>
</body>

</html>