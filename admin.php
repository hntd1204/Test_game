<?php
session_start();
require 'db.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Bạn không có quyền truy cập.");
}

$msg = '';

// 1. Xử lý lưu cài đặt mức tiền
if (isset($_POST['update_settings'])) {
    $min = $_POST['min_reward'];
    $max = $_POST['max_reward'];
    $stmt = $pdo->prepare("UPDATE settings SET min_reward = ?, max_reward = ? WHERE id = 1");
    $stmt->execute([$min, $max]);
    $msg = "✅ Đã cập nhật mức tiền thưởng!";
}

// 2. Xử lý CỘNG / TRỪ lượt quay cho MỘT user cụ thể
if (isset($_POST['adjust_spins'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    $spins_count = (int)$_POST['spins_count'];
    $action_type = $_POST['action_type']; // Nhận giá trị 'add' hoặc 'sub'

    // Kiểm tra xem admin đã chọn user chưa và số lượt nhập vào > 0
    if ($target_user_id > 0 && $spins_count > 0) {

        if ($action_type === 'add') {
            // Cộng lượt
            $stmt = $pdo->prepare("UPDATE users SET spins_available = spins_available + ? WHERE id = ? AND role = 'user'");
            $stmt->execute([$spins_count, $target_user_id]);
            $msg = "✅ Đã CỘNG thêm $spins_count lượt quay cho người dùng!";
        } elseif ($action_type === 'sub') {
            // Trừ lượt (Dùng GREATEST để nếu trừ lố thì số lượt nhỏ nhất vẫn là 0)
            $stmt = $pdo->prepare("UPDATE users SET spins_available = GREATEST(0, spins_available - ?) WHERE id = ? AND role = 'user'");
            $stmt->execute([$spins_count, $target_user_id]);
            $msg = "✅ Đã TRỪ $spins_count lượt quay của người dùng!";
        }
    } else {
        $msg = "❌ Vui lòng chọn một người dùng và nhập số lượt hợp lệ!";
    }
}

// Lấy dữ liệu cài đặt hiện tại
$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();

// Lấy danh sách tất cả User để đưa vào thẻ <select>
$users_stmt = $pdo->query("SELECT id, username, spins_available FROM users WHERE role = 'user' ORDER BY id DESC");
$user_list = $users_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 min-h-screen">
    <nav class="bg-slate-800 shadow-sm px-6 py-4 flex justify-between items-center text-white">
        <h1 class="text-xl font-bold">Admin Panel</h1>
        <div class="flex items-center gap-4">
            <span class="text-sm text-slate-300">Xin chào, Admin</span>
            <a href="logout.php" class="text-sm bg-slate-700 px-4 py-2 rounded-full hover:bg-red-600 transition">Đăng
                xuất</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto mt-10 px-4">
        <?php if ($msg): ?>
        <div class="bg-indigo-100 text-indigo-800 p-4 rounded-lg mb-6 font-medium shadow-sm border border-indigo-200">
            <?= $msg ?>
        </div>
        <?php endif; ?>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Cài đặt Mức Tiền Random</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tối thiểu (Min VNĐ)</label>
                        <input type="number" name="min_reward" value="<?= $settings['min_reward'] ?>" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tối đa (Max VNĐ)</label>
                        <input type="number" name="max_reward" value="<?= $settings['max_reward'] ?>" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50">
                    </div>
                    <button type="submit" name="update_settings"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition shadow-md">Lưu
                        Cài Đặt</button>
                </form>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Quản Lý Lượt Quay Của User</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Chọn Người Dùng</label>
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
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-600 mb-1">Số lượt</label>
                            <input type="number" name="spins_count" value="1" min="1" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-slate-500 outline-none bg-slate-50">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-600 mb-1">Hành động</label>
                            <select name="action_type"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-slate-500 outline-none bg-slate-50 font-medium cursor-pointer">
                                <option value="add" class="text-green-600">➕ Cộng thêm</option>
                                <option value="sub" class="text-red-600">➖ Trừ đi</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="adjust_spins"
                        class="w-full bg-slate-800 hover:bg-slate-900 text-white font-medium py-2.5 rounded-lg transition shadow-md mt-4">Thực
                        Hiện</button>
                </form>
            </div>
        </div>

        <div class="mt-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Danh Sách Người Dùng</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-100 text-slate-700">
                        <tr>
                            <th class="px-4 py-3 rounded-tl-lg">ID</th>
                            <th class="px-4 py-3">Tên đăng nhập</th>
                            <th class="px-4 py-3">Số dư (VNĐ)</th>
                            <th class="px-4 py-3 rounded-tr-lg">Lượt quay còn lại</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $full_users_stmt = $pdo->query("SELECT id, username, balance, spins_available FROM users WHERE role = 'user' ORDER BY id DESC");
                        while ($row = $full_users_stmt->fetch()):
                        ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3">#<?= $row['id'] ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($row['username']) ?>
                            </td>
                            <td class="px-4 py-3 text-blue-600 font-semibold"><?= number_format($row['balance']) ?> đ
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="bg-slate-100 border border-slate-200 text-slate-700 py-1 px-3 rounded-full text-xs font-bold">
                                    <?= $row['spins_available'] ?> lượt
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>

</html>