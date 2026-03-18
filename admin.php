<?php
session_start();
require 'db.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Bạn không có quyền truy cập.");
}

// Lấy thông báo từ Session (nếu có) và xóa đi để không hiện lại ở lần load sau
$msg = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// 1. Xử lý lưu cài đặt mức tiền
if (isset($_POST['update_settings'])) {
    $min = $_POST['min_reward'];
    $max = $_POST['max_reward'];
    $stmt = $pdo->prepare("UPDATE settings SET min_reward = ?, max_reward = ? WHERE id = 1");
    $stmt->execute([$min, $max]);

    $_SESSION['msg'] = "✅ Đã cập nhật mức tiền thưởng!";
    header("Location: admin.php"); // Chuyển hướng để tránh lỗi F5
    exit;
}

// 2. Xử lý CỘNG / TRỪ lượt quay
if (isset($_POST['adjust_spins'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    $spins_count = (int)$_POST['spins_count'];
    $action_type = $_POST['action_type'];

    if ($target_user_id > 0 && $spins_count > 0) {
        if ($action_type === 'add') {
            $stmt = $pdo->prepare("UPDATE users SET spins_available = spins_available + ? WHERE id = ? AND role = 'user'");
            $stmt->execute([$spins_count, $target_user_id]);
            $_SESSION['msg'] = "✅ Đã CỘNG thêm $spins_count lượt quay cho người dùng!";
        } elseif ($action_type === 'sub') {
            $stmt = $pdo->prepare("UPDATE users SET spins_available = GREATEST(0, spins_available - ?) WHERE id = ? AND role = 'user'");
            $stmt->execute([$spins_count, $target_user_id]);
            $_SESSION['msg'] = "✅ Đã TRỪ $spins_count lượt quay của người dùng!";
        }
    } else {
        $_SESSION['msg'] = "❌ Vui lòng chọn một người dùng và nhập số lượt hợp lệ!";
    }

    header("Location: admin.php");
    exit;
}

// 3. Xử lý Duyệt / Từ chối Rút tiền
if (isset($_POST['handle_withdraw'])) {
    $wd_id = (int)$_POST['withdraw_id'];
    $wd_action = $_POST['withdraw_action']; // 'approve' hoặc 'reject'

    // Lấy thông tin phiếu rút
    $wdStmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'");
    $wdStmt->execute([$wd_id]);
    $wd = $wdStmt->fetch();

    if ($wd) {
        if ($wd_action === 'approve') {
            $pdo->prepare("UPDATE withdrawals SET status = 'approved' WHERE id = ?")->execute([$wd_id]);
            $_SESSION['msg'] = "✅ Đã DUYỆT phiếu rút tiền #$wd_id!";
        } elseif ($wd_action === 'reject') {
            // Từ chối -> Hoàn lại tiền cho user
            $pdo->prepare("UPDATE withdrawals SET status = 'rejected' WHERE id = ?")->execute([$wd_id]);
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$wd['amount'], $wd['user_id']]);
            $_SESSION['msg'] = "❌ Đã TỪ CHỐI phiếu #$wd_id và hoàn tiền cho User!";
        }
    }
    header("Location: admin.php");
    exit;
}

// 4. Xử lý Thêm quà vào Shop
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

// 5. Xử lý Xóa quà khỏi Shop
if (isset($_POST['delete_shop_item'])) {
    $id = (int)$_POST['item_id'];
    $pdo->prepare("DELETE FROM shop_items WHERE id = ?")->execute([$id]);
    $_SESSION['msg'] = "✅ Đã xóa quà tặng khỏi Shop!";
    header("Location: admin.php");
    exit;
}

// Lấy dữ liệu cài đặt hiện tại
$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();

// Lấy danh sách tất cả User
$users_stmt = $pdo->query("SELECT id, username, spins_available FROM users WHERE role = 'user' ORDER BY id DESC");
$user_list = $users_stmt->fetchAll();

// --- LẤY LỊCH SỬ QUAY THƯỞNG ---
$history_stmt = $pdo->query("
    SELECT h.id, u.username, h.reward, h.created_at 
    FROM spin_history h 
    JOIN users u ON h.user_id = u.id 
    ORDER BY h.id DESC LIMIT 50
");
$histories = $history_stmt->fetchAll();
$max_history_id = count($histories) > 0 ? $histories[0]['id'] : 0;

// 6. Xử lý Duyệt / Từ chối Đơn Đổi Quà
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
            // Hoàn lại tiền cho user
            $pdo->prepare("UPDATE user_gifts SET status = 'rejected' WHERE id = ?")->execute([$gift_id]);
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$gift['cost'], $gift['user_id']]);
            $_SESSION['msg'] = "❌ Đã TỪ CHỐI đơn đổi quà #$gift_id và hoàn tiền cho User!";
        }
    }
    header("Location: admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 min-h-screen relative">
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-3"></div>

    <nav class="bg-slate-800 shadow-sm px-6 py-4 flex justify-between items-center text-white sticky top-0 z-40">
        <h1 class="text-xl font-bold">Admin Panel</h1>
        <div class="flex items-center gap-4">
            <span class="text-sm text-slate-300">Xin chào, Admin</span>
            <a href="logout.php" class="text-sm bg-slate-700 px-4 py-2 rounded-full hover:bg-red-600 transition">Đăng
                xuất</a>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto mt-8 px-4 pb-10">
        <?php if ($msg): ?>
        <div class="bg-indigo-100 text-indigo-800 p-4 rounded-lg mb-6 font-medium shadow-sm border border-indigo-200">
            <?= $msg ?>
        </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Cài đặt Mức Tiền Random</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Tối thiểu (VNĐ)</label>
                                <input type="number" name="min_reward" value="<?= $settings['min_reward'] ?>" required
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Tối đa (VNĐ)</label>
                                <input type="number" name="max_reward" value="<?= $settings['max_reward'] ?>" required
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50">
                            </div>
                        </div>
                        <button type="submit" name="update_settings"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition shadow-md">Lưu
                            Cài Đặt</button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Quản Lý Lượt Quay</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <select name="target_user_id" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-slate-500 outline-none bg-slate-50 cursor-pointer">
                                <option value="">-- Chọn User --</option>
                                <?php foreach ($user_list as $u): ?>
                                <option value="<?= $u['id'] ?>">
                                    <?= htmlspecialchars($u['username']) ?> (Đang có: <?= $u['spins_available'] ?> lượt)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex gap-4">
                            <input type="number" name="spins_count" value="1" min="1" required
                                class="flex-1 px-4 py-2 border rounded-lg outline-none bg-slate-50"
                                placeholder="Số lượt">
                            <select name="action_type"
                                class="flex-1 px-4 py-2 border rounded-lg outline-none bg-slate-50 font-medium cursor-pointer">
                                <option value="add" class="text-green-600">➕ Cộng thêm</option>
                                <option value="sub" class="text-red-600">➖ Trừ đi</option>
                            </select>
                        </div>
                        <button type="submit" name="adjust_spins"
                            class="w-full bg-slate-800 hover:bg-slate-900 text-white font-medium py-2.5 rounded-lg transition shadow-md mt-4">Thực
                            Hiện</button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">🛒 Quản Lý Shop Đổi Quà</h2>

                    <form method="POST" class="flex gap-2 mb-4">
                        <input type="text" name="item_name" placeholder="Tên món quà..." required
                            class="flex-1 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm bg-slate-50">
                        <input type="number" name="item_cost" placeholder="Giá VNĐ" required
                            class="w-1/3 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm bg-slate-50">
                        <button type="submit" name="add_shop_item"
                            class="bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-2 rounded-lg transition shadow-sm text-sm whitespace-nowrap">Thêm</button>
                    </form>

                    <div class="overflow-y-auto max-h-[200px]">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-100 text-slate-700 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2">Tên quà</th>
                                    <th class="px-3 py-2">Giá tiền</th>
                                    <th class="px-3 py-2 text-right">Xóa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                try {
                                    $items_stmt = $pdo->query("SELECT * FROM shop_items ORDER BY cost ASC");
                                    while ($item = $items_stmt->fetch()):
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 font-medium text-slate-800">
                                        <?= htmlspecialchars($item['name']) ?></td>
                                    <td class="px-3 py-2 font-bold text-green-600"><?= number_format($item['cost']) ?>đ
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <form method="POST"
                                            onsubmit="return confirm('Bạn có chắc muốn xóa món quà này khỏi Shop?');">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <button type="submit" name="delete_shop_item"
                                                class="text-red-500 hover:text-red-700 font-bold bg-red-50 hover:bg-red-100 px-2 py-1 rounded">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                } catch (Exception $e) {
                                    echo "<tr><td colspan='3' class='text-center py-2'>Chưa tạo bảng shop_items</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Danh Sách Người Dùng</h2>
                    <div class="overflow-y-auto max-h-[300px]">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-100 text-slate-700 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3">Tài khoản</th>
                                    <th class="px-4 py-3">Số dư</th>
                                    <th class="px-4 py-3">Lượt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                $full_users_stmt = $pdo->query("SELECT username, balance, spins_available FROM users WHERE role = 'user' ORDER BY id DESC");
                                while ($row = $full_users_stmt->fetch()):
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-medium text-slate-800">
                                        <?= htmlspecialchars($row['username']) ?></td>
                                    <td class="px-4 py-3 text-blue-600 font-semibold">
                                        <?= number_format($row['balance']) ?>đ</td>
                                    <td class="px-4 py-3 font-bold"><?= $row['spins_available'] ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Duyệt Yêu Cầu Rút Tiền</h2>
                    <div class="overflow-y-auto max-h-[300px]">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-100 text-slate-700 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2">Tài khoản</th>
                                    <th class="px-3 py-2">Số tiền</th>
                                    <th class="px-3 py-2">Trạng thái</th>
                                    <th class="px-3 py-2 text-right">Hành động</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                try {
                                    $wd_stmt = $pdo->query("SELECT w.*, u.username FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY w.id DESC");
                                    while ($w = $wd_stmt->fetch()):
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-3 font-medium text-slate-800">
                                        <?= htmlspecialchars($w['username']) ?></td>
                                    <td class="px-3 py-3 font-bold text-blue-600"><?= number_format($w['amount']) ?>đ
                                    </td>
                                    <td class="px-3 py-3">
                                        <?php if ($w['status'] == 'pending'): ?>
                                        <span
                                            class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-bold">Chờ
                                            duyệt</span>
                                        <?php elseif ($w['status'] == 'approved'): ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">Đã
                                            duyệt</span>
                                        <?php else: ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold">Từ
                                            chối</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <?php if ($w['status'] == 'pending'): ?>
                                        <form method="POST" class="inline-flex gap-1">
                                            <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
                                            <button type="submit" name="handle_withdraw" value="1"
                                                onclick="document.getElementById('wd_act_<?= $w['id'] ?>').value='approve'"
                                                class="bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-xs font-bold">Duyệt</button>
                                            <button type="submit" name="handle_withdraw" value="1"
                                                onclick="document.getElementById('wd_act_<?= $w['id'] ?>').value='reject'"
                                                class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs font-bold">Hủy</button>
                                            <input type="hidden" id="wd_act_<?= $w['id'] ?>" name="withdraw_action"
                                                value="">
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                } catch (Exception $e) {
                                    echo "<tr><td colspan='4' class='text-center py-2'>Chưa có bảng rút tiền trong DB</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">🎁 Quản Lý Đơn Đổi Quà</h2>
                    <div class="overflow-y-auto max-h-[300px]">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-100 text-slate-700 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2">Tài khoản</th>
                                    <th class="px-3 py-2">Món quà</th>
                                    <th class="px-3 py-2">Trạng thái</th>
                                    <th class="px-3 py-2 text-right">Hành động</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                try {
                                    $gifts_stmt = $pdo->query("SELECT g.*, u.username FROM user_gifts g JOIN users u ON g.user_id = u.id ORDER BY g.id DESC");
                                    while ($g = $gifts_stmt->fetch()):
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-3 font-medium text-slate-800">
                                        <?= htmlspecialchars($g['username']) ?></td>
                                    <td class="px-3 py-3 font-bold text-green-600">
                                        <?= htmlspecialchars($g['gift_name']) ?></td>
                                    <td class="px-3 py-3">
                                        <?php if ($g['status'] == 'pending'): ?>
                                        <span
                                            class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-bold">Chờ
                                            xử lý</span>
                                        <?php elseif ($g['status'] == 'completed'): ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">Đã
                                            trao</span>
                                        <?php else: ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold">Đã
                                            hủy</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <?php if ($g['status'] == 'pending'): ?>
                                        <form method="POST" class="inline-flex gap-1">
                                            <input type="hidden" name="gift_id" value="<?= $g['id'] ?>">
                                            <button type="submit" name="handle_gift" value="1"
                                                onclick="document.getElementById('gf_act_<?= $g['id'] ?>').value='complete'"
                                                class="bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-xs font-bold">Hoàn
                                                thành</button>
                                            <button type="submit" name="handle_gift" value="1"
                                                onclick="document.getElementById('gf_act_<?= $g['id'] ?>').value='reject'"
                                                class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs font-bold">Hủy</button>
                                            <input type="hidden" id="gf_act_<?= $g['id'] ?>" name="gift_action"
                                                value="">
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                } catch (Exception $e) {
                                    echo "<tr><td colspan='4' class='text-center py-2'>Chưa có lịch sử đổi quà</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit sticky top-24">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h2 class="text-lg font-bold text-slate-800">Lịch Sử Quay Gần Đây</h2>
                    <span class="relative flex h-3 w-3">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                </div>

                <div class="overflow-y-auto max-h-[700px]">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-100 text-slate-700 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 rounded-tl-lg">Thời gian</th>
                                <th class="px-4 py-3">Người chơi</th>
                                <th class="px-4 py-3 rounded-tr-lg text-right">Phần thưởng</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body" class="divide-y divide-slate-100">
                            <?php foreach ($histories as $h): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    <?= date('H:i:s d/m', strtotime($h['created_at'])) ?></td>
                                <td class="px-4 py-3 font-medium text-blue-600"><?= htmlspecialchars($h['username']) ?>
                                </td>
                                <td class="px-4 py-3 font-bold text-green-600 text-right">
                                    +<?= number_format($h['reward']) ?> đ</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($histories) == 0): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-slate-400">Chưa có lịch sử quay nào.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
    let lastId = <?= $max_history_id ?>;

    function showToast(username, reward) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');

        toast.className =
            'bg-white border-l-4 border-green-500 shadow-xl rounded-lg p-4 flex items-center gap-4 transform transition-all duration-300 translate-x-10 opacity-0 min-w-[300px]';

        toast.innerHTML = `
                <div class="text-green-500 text-3xl animate-bounce">🎁</div>
                <div>
                    <h4 class="font-bold text-slate-800">${username} vừa trúng!</h4>
                    <p class="text-green-600 font-extrabold">+${Number(reward).toLocaleString('vi-VN')} VNĐ</p>
                </div>
            `;
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

        if (tbody.querySelector('td[colspan="3"]')) {
            tbody.innerHTML = '';
        }

        const tr = document.createElement('tr');
        tr.className = 'transition bg-yellow-100';

        const date = new Date(item.created_at);
        const timeStr = ('0' + date.getHours()).slice(-2) + ':' + ('0' + date.getMinutes()).slice(-2) + ':' + ('0' +
                date.getSeconds()).slice(-2) + ' ' +
            ('0' + date.getDate()).slice(-2) + '/' + ('0' + (date.getMonth() + 1)).slice(-2);

        tr.innerHTML = `
                <td class="px-4 py-3 text-xs text-slate-500">${timeStr}</td>
                <td class="px-4 py-3 font-medium text-blue-600">${item.username}</td>
                <td class="px-4 py-3 font-bold text-green-600 text-right">+${Number(item.reward).toLocaleString('vi-VN')} đ</td>
            `;

        tbody.prepend(tr);

        setTimeout(() => {
            tr.classList.remove('bg-yellow-100');
            tr.classList.add('hover:bg-slate-50');
        }, 2000);
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
                    if (parseInt(item.id) > lastId) {
                        lastId = parseInt(item.id);
                    }
                });
            }
        } catch (err) {
            console.error("Lỗi đồng bộ thông báo: ", err);
        }
    }, 3000);
    </script>
</body>

</html>