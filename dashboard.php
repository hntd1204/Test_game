<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

// Lấy thông tin user (thêm baucua_count để phục vụ nhiệm vụ)
$stmt = $pdo->prepare("SELECT balance, spins_available, baucua_count FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Lấy cấu hình nhiệm vụ từ Admin
try {
    $mission = $pdo->query("SELECT target_count, reward_spins FROM mission_settings WHERE id = 1")->fetch();
} catch (Exception $e) {
    // Giá trị mặc định nếu chưa chạy SQL tạo bảng mission_settings
    $mission = ['target_count' => 5, 'reward_spins' => 1];
}

// Truy vấn lịch sử quay thưởng cá nhân
try {
    $myHistoryStmt = $pdo->prepare("SELECT reward, created_at FROM spin_history WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $myHistoryStmt->execute([$_SESSION['user_id']]);
    $myHistories = $myHistoryStmt->fetchAll();
} catch (Exception $e) {
    $myHistories = [];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Vòng Quay May Mắn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .number-display {
            font-variant-numeric: tabular-nums;
        }

        @keyframes pulse-custom {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .animate-pulse-custom {
            animation: pulse-custom 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen">
    <nav class="bg-white shadow-sm px-4 sm:px-6 py-3 sm:py-4 flex justify-between items-center sticky top-0 z-50">
        <h1 class="text-lg sm:text-xl font-bold text-blue-600 truncate mr-2">Vòng Quay May Mắn</h1>
        <div class="flex items-center gap-2 sm:gap-4 shrink-0">
            <span class="text-slate-600 font-medium hidden sm:inline-block text-sm">Chào,
                <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="logout.php"
                class="text-xs sm:text-sm bg-red-100 text-red-600 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full hover:bg-red-200 transition font-medium">Đăng
                xuất</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto mt-6 sm:mt-10 px-4 pb-10">

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-bold text-slate-700 uppercase">🎯 Nhiệm vụ hiện tại</h3>
                <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded">Thưởng
                    +<?= $mission['reward_spins'] ?> lượt quay</span>
            </div>
            <p class="text-xs text-slate-500 mb-3">Chơi đủ <?= $mission['target_count'] ?> ván Bầu Cua để nhận thưởng
                lượt quay miễn phí.</p>
            <div class="w-full bg-slate-100 rounded-full h-2.5 mb-1">
                <?php
                $progress = min(100, ($user['baucua_count'] / $mission['target_count']) * 100);
                ?>
                <div class="bg-purple-600 h-2.5 rounded-full transition-all duration-500"
                    style="width: <?= $progress ?>%"></div>
            </div>
            <div class="flex justify-between text-[10px] font-bold text-slate-400">
                <span>TIẾN ĐỘ: <?= $user['baucua_count'] ?>/<?= $mission['target_count'] ?> ván</span>
                <span><?= round($progress) ?>%</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-8 sm:mb-10">
            <div
                class="bg-gradient-to-br from-blue-500 to-blue-700 p-5 sm:p-6 rounded-2xl shadow-lg text-white relative overflow-hidden">
                <h3 class="text-blue-100 text-xs sm:text-sm font-semibold uppercase tracking-wider mb-1 sm:mb-2">Số dư
                    hiện tại</h3>
                <div class="text-3xl sm:text-4xl font-bold"><span
                        id="balance"><?= number_format($user['balance']) ?></span> <span
                        class="text-lg sm:text-xl">VNĐ</span></div>
                <div class="absolute -bottom-4 -right-4 text-white opacity-20 text-6xl sm:text-7xl">💰</div>
            </div>

            <div
                class="bg-gradient-to-br from-purple-500 to-purple-700 p-5 sm:p-6 rounded-2xl shadow-lg text-white relative overflow-hidden">
                <h3 class="text-purple-100 text-xs sm:text-sm font-semibold uppercase tracking-wider mb-1 sm:mb-2">Lượt
                    quay còn lại</h3>
                <div class="text-3xl sm:text-4xl font-bold" id="spins"><?= $user['spins_available'] ?></div>
                <p class="text-purple-200 text-[10px] sm:text-xs mt-2 italic">Hãy quay ngay trước khi hết lượt nhé!</p>
                <div class="absolute -bottom-4 -right-4 text-white opacity-20 text-6xl sm:text-7xl">🎰</div>
            </div>
        </div>

        <div
            class="bg-gradient-to-r from-red-500 to-orange-500 p-6 rounded-2xl shadow-lg text-white mb-8 flex flex-col sm:flex-row justify-between items-center transform transition hover:scale-[1.02]">
            <div>
                <h3 class="text-xl font-bold mb-2">🎲 Mini Game: Bầu Cua Tôm Cá</h3>
                <p class="text-sm text-red-100">Dùng số dư của bạn để thử nghiệm nhân phẩm, cược càng nhiều ăn càng lớn!
                </p>
            </div>
            <a href="baucua.php"
                class="mt-4 sm:mt-0 bg-white text-red-600 font-bold px-6 py-3 rounded-full shadow-md hover:bg-slate-100 whitespace-nowrap">
                Chơi Ngay
            </a>
        </div>

        <div
            class="bg-gradient-to-r from-emerald-600 to-teal-600 p-6 rounded-2xl shadow-lg text-white mb-8 flex flex-col sm:flex-row justify-between items-center transform transition hover:scale-[1.02]">
            <div>
                <h3 class="text-xl font-bold mb-2">🃏 Mini Game: Xì Dách</h3>
                <p class="text-sm text-emerald-100">Đấu trí với nhà cái, đạt Xì Dách hoặc Ngũ Linh để nhận thưởng gấp
                    đôi!</p>
            </div>
            <a href="blackjack.php"
                class="mt-4 sm:mt-0 bg-white text-emerald-700 font-bold px-6 py-3 rounded-full shadow-md hover:bg-slate-100 whitespace-nowrap">
                Chơi Ngay
            </a>
        </div>

        <div
            class="bg-white p-5 sm:p-8 md:p-10 rounded-3xl shadow-xl border border-slate-100 text-center max-w-2xl mx-auto">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800 mb-6 sm:mb-8">Trải Nghiệm Vận May</h2>

            <div
                class="bg-slate-900 rounded-2xl p-4 sm:p-6 md:p-8 mb-6 sm:mb-8 shadow-inner border-2 sm:border-4 border-slate-700 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-b from-black/50 to-transparent pointer-events-none"></div>
                <div id="spinningNumber"
                    class="number-display text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-extrabold text-slate-300 tracking-wider transition-all duration-300 whitespace-nowrap">
                    000,000 VNĐ
                </div>
            </div>

            <button id="spinBtn"
                class="relative w-full md:w-auto inline-flex items-center justify-center px-8 sm:px-12 py-3 sm:py-4 text-lg sm:text-xl font-bold text-white transition-all duration-200 bg-gradient-to-r from-orange-500 to-red-500 rounded-full hover:from-orange-600 hover:to-red-600 focus:outline-none focus:ring-4 focus:ring-orange-300 disabled:opacity-50 disabled:cursor-not-allowed shadow-xl active:scale-95 transform hover:-translate-y-1"
                <?= $user['spins_available'] <= 0 ? 'disabled' : '' ?>>
                🎰 BẮT ĐẦU QUAY
            </button>

            <div id="resultMsg"
                class="mt-4 sm:mt-6 min-h-[32px] sm:min-h-[40px] text-lg sm:text-xl font-bold transition-all duration-300 flex items-center justify-center">
            </div>
        </div>

        <div class="bg-white mt-8 rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-700 uppercase">📜 Lịch sử trúng thưởng của bạn</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase">
                        <tr>
                            <th class="px-5 py-3">Thời gian</th>
                            <th class="px-5 py-3 text-right">Số tiền nhận</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($myHistories as $h): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-5 py-3 text-xs"><?= date('H:i d/m/Y', strtotime($h['created_at'])) ?></td>
                                <td class="px-5 py-3 text-right font-bold text-green-600">
                                    +<?= number_format($h['reward']) ?>đ</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($myHistories) == 0): ?>
                            <tr>
                                <td colspan="2" class="px-5 py-8 text-center text-slate-400">Bạn chưa thực hiện lượt quay
                                    nào.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">

            <div class="bg-white p-6 rounded-2xl shadow-lg border border-slate-100 flex flex-col justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">💸 Rút Tiền</h3>
                    <p class="text-sm text-slate-600 mb-4">Tối thiểu rút 10.000 VNĐ. Tiền sẽ bị trừ ngay và chờ Admin
                        duyệt.</p>
                </div>
                <div class="flex gap-2">
                    <input type="number" id="withdrawAmount" placeholder="Nhập số tiền..."
                        class="flex-1 px-4 py-3 rounded-xl border focus:outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                    <button onclick="requestWithdraw()"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl shadow-md active:scale-95 whitespace-nowrap transition-all">
                        Rút Ngay
                    </button>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100 max-h-32 overflow-y-auto">
                    <p class="text-xs font-bold text-slate-500 mb-2">LỊCH SỬ RÚT GẦN ĐÂY:</p>
                    <?php
                    try {
                        $wdStmt = $pdo->prepare("SELECT amount, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 5");
                        $wdStmt->execute([$_SESSION['user_id']]);
                        $withdrawals = $wdStmt->fetchAll();
                        if (count($withdrawals) > 0) {
                            foreach ($withdrawals as $w) {
                                $statusBadge = $w['status'] == 'pending' ? '<span class="text-yellow-500">Chờ duyệt</span>' : ($w['status'] == 'approved' ? '<span class="text-green-500">Thành công</span>' :
                                    '<span class="text-red-500">Từ chối</span>');
                                echo '<div class="text-sm flex justify-between py-1 border-b border-slate-50 last:border-0">';
                                echo '<span>' . number_format($w['amount']) . 'đ</span>';
                                echo '<span class="font-bold">' . $statusBadge . '</span>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p class="text-xs text-slate-400">Chưa có lịch sử</p>';
                        }
                    } catch (Exception $e) {
                        echo '<p class="text-xs text-slate-400">Hệ thống đang cập nhật tính năng rút tiền...</p>';
                    }
                    ?>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-lg border border-slate-100 flex flex-col justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">🛍️ Cửa Hàng</h3>
                    <p class="text-sm text-slate-600 mb-4">Dùng số dư để mua thêm lượt hoặc đổi quà tặng.</p>
                </div>

                <div class="space-y-3 max-h-[250px] overflow-y-auto pr-1">
                    <button onclick="buyAction('buy_spin')"
                        class="w-full flex justify-between items-center bg-orange-50 hover:bg-orange-100 p-3 rounded-xl border border-orange-200 transition-all active:scale-95">
                        <span class="font-bold text-orange-700">1 Lượt Quay</span>
                        <span class="bg-orange-500 text-white text-xs font-bold px-3 py-1 rounded-full">50.000
                            VNĐ</span>
                    </button>

                    <?php
                    try {
                        $shopStmt = $pdo->query("SELECT * FROM shop_items WHERE is_active = 1 ORDER BY cost ASC");
                        while ($item = $shopStmt->fetch()):
                    ?>
                            <button
                                onclick="buyAction('buy_gift', <?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>', <?= $item['cost'] ?>)"
                                class="w-full flex justify-between items-center bg-green-50 hover:bg-green-100 p-3 rounded-xl border border-green-200 transition-all active:scale-95">
                                <span class="font-bold text-green-700"><?= htmlspecialchars($item['name']) ?></span>
                                <span
                                    class="bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full"><?= number_format($item['cost']) ?>
                                    VNĐ</span>
                            </button>
                    <?php
                        endwhile;
                    } catch (Exception $e) {
                        echo '<p class="text-xs text-slate-400 text-center">Đang tải cửa hàng...</p>';
                    }
                    ?>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100 max-h-32 overflow-y-auto">
                    <p class="text-xs font-bold text-slate-500 mb-2">LỊCH SỬ ĐỔI QUÀ:</p>
                    <?php
                    try {
                        $giftStmt = $pdo->prepare("SELECT gift_name, cost, status, created_at FROM user_gifts WHERE user_id = ? ORDER BY id DESC LIMIT 5");
                        $giftStmt->execute([$_SESSION['user_id']]);
                        $gifts = $giftStmt->fetchAll();
                        if (count($gifts) > 0) {
                            foreach ($gifts as $g) {
                                $statusBadge = $g['status'] == 'pending' ? '<span class="text-yellow-500">Chờ xử lý</span>' : ($g['status'] == 'completed' ? '<span class="text-green-500">Thành công</span>' :
                                    '<span class="text-red-500">Từ chối</span>');
                                echo '<div class="text-sm flex justify-between py-1 border-b border-slate-50 last:border-0">';
                                echo '<span class="text-slate-700">' . htmlspecialchars($g['gift_name']) . '</span>';
                                echo '<span class="font-bold">' . $statusBadge . '</span>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p class="text-xs text-slate-400">Chưa có lịch sử</p>';
                        }
                    } catch (Exception $e) {
                    }
                    ?>
                </div>
            </div>

        </div>
    </main>

    <script>
        // MỚI: Âm thanh hệ thống
        const sounds = {
            spin: new Audio('https://www.soundjay.com/misc/sounds/mechanical-clonk-1.mp3'),
            win: new Audio('https://www.soundjay.com/misc/sounds/bell-ringing-05.mp3'),
            error: new Audio('https://www.soundjay.com/buttons/button-10.mp3')
        };

        document.getElementById('spinBtn').addEventListener('click', async function() {
            const btn = this;
            const msg = document.getElementById('resultMsg');
            const numberDisplay = document.getElementById('spinningNumber');

            btn.disabled = true;
            msg.innerText = "";
            numberDisplay.classList.remove('text-green-400', 'scale-110');
            numberDisplay.classList.add('text-yellow-400', 'animate-pulse-custom');

            // Phát âm thanh khi quay
            sounds.spin.play();
            sounds.spin.loop = true;

            let spinInterval = setInterval(() => {
                const randomVisualNum = Math.floor(Math.random() * 100) * 1000 + 1000;
                numberDisplay.innerText = randomVisualNum.toLocaleString() + " VNĐ";
            }, 50);

            try {
                const response = await fetch('process_spin.php');
                const data = await response.json();

                setTimeout(() => {
                    clearInterval(spinInterval);
                    sounds.spin.loop = false;
                    sounds.spin.pause();

                    if (data.success) {
                        sounds.win.play(); // Âm thanh trúng
                        numberDisplay.innerText = data.reward.toLocaleString() + " VNĐ";
                        numberDisplay.classList.remove('text-yellow-400');
                        numberDisplay.classList.add('text-green-400', 'scale-110');

                        msg.innerText = "🎉 Bạn trúng " + data.reward.toLocaleString() + " đ";
                        msg.className =
                            "mt-4 sm:mt-6 min-h-[32px] sm:min-h-[40px] text-lg md:text-2xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-green-500 to-emerald-700 animate-bounce flex items-center justify-center";

                        document.getElementById('balance').innerText = data.new_balance
                            .toLocaleString();
                        document.getElementById('spins').innerText = data.spins_left;

                        if (data.spins_left > 0) btn.disabled = false;
                    } else {
                        sounds.error.play(); // Âm thanh lỗi/hết lượt
                        numberDisplay.classList.remove('text-yellow-400');
                        numberDisplay.innerText = "0 VNĐ";
                        msg.innerText = "❌ " + data.error;
                        msg.className =
                            "mt-4 sm:mt-6 min-h-[32px] sm:min-h-[40px] text-lg font-bold text-red-500 flex items-center justify-center";
                    }
                    numberDisplay.classList.remove('animate-pulse-custom');
                }, 1500);

            } catch (err) {
                clearInterval(spinInterval);
                sounds.spin.pause();
                numberDisplay.innerText = "LỖI";
                msg.innerText = "Có lỗi xảy ra, thử lại sau.";
                msg.className =
                    "mt-4 sm:mt-6 min-h-[32px] sm:min-h-[40px] text-lg font-bold text-red-500 flex items-center justify-center";
                btn.disabled = false;
            }
        });

        // Giữ nguyên các hàm JS cũ của bạn bên dưới
        async function requestWithdraw() {
            const amount = document.getElementById('withdrawAmount').value;
            if (!amount || amount < 10000) return alert("Vui lòng nhập số tiền hợp lệ (Tối thiểu 10k)!");
            if (!confirm(`Bạn chắc chắn muốn rút ${Number(amount).toLocaleString()} VNĐ?`)) return;
            const formData = new FormData();
            formData.append('action', 'withdraw');
            formData.append('amount', amount);
            await sendAction(formData);
            setTimeout(() => location.reload(), 1500);
        }

        async function buyAction(actionName, itemId = null, giftName = '', cost = 0) {
            let msg = actionName === 'buy_spin' ? "Mua 1 lượt quay với giá 50.000 VNĐ?" :
                `Đổi ${giftName} với giá ${Number(cost).toLocaleString()} VNĐ?`;
            if (!confirm(msg)) return;
            const formData = new FormData();
            formData.append('action', actionName);
            if (itemId) formData.append('item_id', itemId);
            await sendAction(formData);
        }

        async function sendAction(formData) {
            try {
                const res = await fetch('user_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert("🎉 " + data.message);
                    document.getElementById('balance').innerText = data.new_balance.toLocaleString();
                    if (data.spins_left !== undefined) {
                        document.getElementById('spins').innerText = data.spins_left;
                        if (data.spins_left > 0) document.getElementById('spinBtn').disabled = false;
                    }
                } else {
                    alert("❌ " + data.error);
                }
            } catch (err) {
                alert("Lỗi kết nối!");
                console.error(err);
            }
        }
    </script>
</body>

</html>