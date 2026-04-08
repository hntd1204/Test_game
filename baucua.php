<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT balance, baucua_count FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Lấy cấu hình nhiệm vụ từ Admin
$mission = $pdo->query("SELECT target_count, reward_spins FROM mission_settings WHERE id = 1")->fetch();

// LẤY LỊCH SỬ CHƠI BẦU CUA CỦA USER
$historyStmt = $pdo->prepare("SELECT * FROM baucua_history WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$historyStmt->execute([$_SESSION['user_id']]);
$myHistories = $historyStmt->fetchAll();

$animals = [
    'nai' => ['name' => 'Nai', 'icon' => '🦌', 'color' => 'bg-amber-700/20', 'border' => 'border-amber-500/50'],
    'bau' => ['name' => 'Bầu', 'icon' => '🎃', 'color' => 'bg-orange-500/20', 'border' => 'border-orange-500/50'],
    'ga'  => ['name' => 'Gà',  'icon' => '🐓', 'color' => 'bg-red-500/20', 'border' => 'border-red-500/50'],
    'ca'  => ['name' => 'Cá',  'icon' => '🐟', 'color' => 'bg-blue-500/20', 'border' => 'border-blue-500/50'],
    'cua' => ['name' => 'Cua', 'icon' => '🦀', 'color' => 'bg-rose-500/20', 'border' => 'border-rose-500/50'],
    'tom' => ['name' => 'Tôm', 'icon' => '🦐', 'color' => 'bg-red-400/20', 'border' => 'border-red-400/50']
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bầu Cua Hoàng Gia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    .shake {
        animation: shake 0.3s infinite;
    }

    @keyframes shake {

        10%,
        90% {
            transform: translate3d(-2px, 0, 0);
        }

        20%,
        80% {
            transform: translate3d(2px, 0, 0);
        }
    }

    .winner-glow {
        animation: glow 1.5s infinite alternate;
        box-shadow: 0 0 20px #fbbf24;
        border-color: #f59e0b !important;
    }

    @keyframes glow {
        from {
            box-shadow: 0 0 10px #fbbf24;
        }

        to {
            box-shadow: 0 0 30px #f59e0b;
        }
    }
    </style>
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen font-sans">
    <nav
        class="bg-slate-800/80 backdrop-blur-md border-b border-slate-700 px-4 py-3 flex justify-between items-center sticky top-0 z-50">
        <h1 class="text-xl font-bold text-amber-400 uppercase tracking-wider">Bầu Cua</h1>
        <div class="flex items-center gap-3">
            <div
                class="hidden sm:flex bg-purple-900/50 border border-purple-500/30 px-3 py-1 rounded-full text-[10px] items-center gap-1">
                <span class="text-purple-300">Nhiệm vụ:</span>
                <span id="missionProgress"
                    class="font-bold text-white"><?= $user['baucua_count'] ?>/<?= $mission['target_count'] ?></span>
            </div>
            <div class="bg-slate-900 border border-amber-500/30 px-4 py-1.5 rounded-full flex items-center gap-2">
                <span class="text-amber-400 text-sm">💰</span>
                <span class="font-bold text-amber-400 tracking-wide"
                    id="balance"><?= number_format($user['balance']) ?></span>
            </div>
            <a href="dashboard.php" class="text-xs bg-slate-700 px-3 py-2 rounded-full font-medium">Thoát</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto mt-6 px-4 pb-32">
        <div class="bg-slate-800 border border-slate-700 p-6 rounded-3xl shadow-2xl text-center mb-6">
            <div class="flex justify-center gap-4 mb-4">
                <div id="dice-1"
                    class="w-20 h-20 bg-slate-700 border-2 border-slate-600 rounded-2xl flex items-center justify-center text-6xl shadow-inner">
                    ❓</div>
                <div id="dice-2"
                    class="w-20 h-20 bg-slate-700 border-2 border-slate-600 rounded-2xl flex items-center justify-center text-6xl shadow-inner">
                    ❓</div>
                <div id="dice-3"
                    class="w-20 h-20 bg-slate-700 border-2 border-slate-600 rounded-2xl flex items-center justify-center text-6xl shadow-inner">
                    ❓</div>
            </div>
            <div id="resultMsg" class="h-10 text-lg font-bold flex items-center justify-center"></div>
        </div>

        <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-6">
            <?php foreach ($animals as $key => $animal): ?>
            <div id="box-<?= $key ?>" onclick="placeBet('<?= $key ?>')"
                class="animal-box relative <?= $animal['color'] ?> border-2 <?= $animal['border'] ?> rounded-xl p-3 flex flex-col items-center cursor-pointer hover:bg-slate-700/50 transition-all active:scale-95">
                <span class="text-4xl mb-1"><?= $animal['icon'] ?></span>
                <span class="font-bold text-slate-300 text-xs sm:text-base"><?= $animal['name'] ?></span>
                <div id="bet-badge-<?= $key ?>"
                    class="hidden absolute top-1 right-1 bg-amber-500 text-slate-900 text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-lg">
                    0</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex overflow-x-auto gap-3 pb-4 justify-center mb-4">
            <?php foreach ([5000, 10000, 20000, 50000, 100000] as $val): ?>
            <button onclick="selectChip(<?= $val ?>, this)"
                class="chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-xs <?= $val == 10000 ? 'border-amber-400 bg-amber-500/20 text-amber-400' : '' ?>"><?= $val / 1000 ?>K</button>
            <?php endforeach; ?>
        </div>

        <div class="bg-slate-800 border border-slate-700 p-4 sm:p-6 rounded-3xl shadow-xl mt-4">
            <h3 class="text-lg font-bold text-amber-400 mb-4 border-b border-slate-700 pb-2">📜 Lịch Sử Chơi Gần Đây
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300 whitespace-nowrap">
                    <thead class="bg-slate-700/50 text-slate-400">
                        <tr>
                            <th class="px-3 py-2 rounded-tl-lg">Thời gian</th>
                            <th class="px-3 py-2">Chi tiết cược</th>
                            <th class="px-3 py-2">Xí ngầu</th>
                            <th class="px-3 py-2 text-right rounded-tr-lg">Lãi/Lỗ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach ($myHistories as $h):
                            $h_bets = json_decode($h['bet_details'], true);
                            $bet_str = [];
                            foreach ($h_bets as $ani => $amt) {
                                $bet_str[] = $animals[$ani]['icon'] . " " . ($amt / 1000) . "K";
                            }
                            $dice = explode(',', $h['dice_result']);
                            $dice_str = $animals[$dice[0]]['icon'] . " " . $animals[$dice[1]]['icon'] . " " . $animals[$dice[2]]['icon'];
                        ?>
                        <tr class="hover:bg-slate-700/30 transition">
                            <td class="px-3 py-3 text-xs text-slate-400">
                                <?= date('H:i d/m', strtotime($h['created_at'])) ?></td>
                            <td class="px-3 py-3 text-xs"><?= implode(', ', $bet_str) ?></td>
                            <td class="px-3 py-3 text-base"><?= $dice_str ?></td>
                            <td
                                class="px-3 py-3 text-right font-bold <?= $h['net_profit'] > 0 ? 'text-green-400' : ($h['net_profit'] < 0 ? 'text-rose-400' : 'text-slate-400') ?>">
                                <?= $h['net_profit'] > 0 ? '+' : '' ?><?= number_format($h['net_profit']) ?>đ
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($myHistories) == 0): ?>
                        <tr>
                            <td colspan="4" class="text-center py-6 text-slate-500">Bạn chưa tham gia ván Bầu Cua nào.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-500 text-center mt-3 italic">* Tải lại trang (F5) để cập nhật lịch sử mới nhất.
            </p>
        </div>

        <div class="fixed bottom-0 left-0 right-0 p-4 bg-slate-900/95 border-t border-slate-800">
            <button id="rollBtn"
                class="w-full max-w-md mx-auto block bg-gradient-to-r from-amber-500 to-orange-600 text-slate-900 text-lg font-black py-3 rounded-xl shadow-lg uppercase tracking-widest">🎲
                XÓC NGAY 🎲</button>
        </div>
    </main>

    <script>
    // Hệ thống âm thanh
    const sounds = {
        dice: new Audio('https://www.soundjay.com/misc/sounds/dice-shake-1.mp3'),
        win: new Audio('https://www.soundjay.com/misc/sounds/bell-ringing-05.mp3'),
        click: new Audio('https://www.soundjay.com/buttons/button-16.mp3')
    };

    const animalIcons = {
        'nai': '🦌',
        'bau': '🎃',
        'ga': '🐓',
        'ca': '🐟',
        'cua': '🦀',
        'tom': '🦐'
    };
    let currentChip = 10000;
    let bets = {};
    let isRolling = false;

    // Chọn mệnh giá chip
    function selectChip(amount, el) {
        sounds.click.play();
        currentChip = amount;
        document.querySelectorAll('.chip-btn').forEach(b => b.className =
            "chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-xs"
        );
        el.className =
            "chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-amber-400 bg-amber-500/20 text-amber-400 font-bold text-xs";
    }

    // Đặt cược vào ô
    function placeBet(animal) {
        if (isRolling) return;

        // 1. Lấy số dư hiện tại trực tiếp từ trên màn hình
        let balanceEl = document.getElementById('balance');
        let currentBalance = parseInt(balanceEl.innerText.replace(/,/g, '').replace(/\./g, ''));

        // 2. Kiểm tra xem chip cược có lớn hơn số dư hiện tại không
        if (currentChip > currentBalance) {
            alert("Số dư của bạn không đủ để cược thêm!");
            return; // Dừng lại, không cho cược
        }

        // 3. Kiểm tra: Chỉ được đặt tối đa 3 ô khác nhau
        if (!bets[animal] && Object.keys(bets).length >= 3) {
            alert("Bạn chỉ được đặt cược tối đa 3 ô!");
            return;
        }

        // 4. TRỪ TIỀN NGAY LẬP TỨC TRÊN GIAO DIỆN
        balanceEl.innerText = (currentBalance - currentChip).toLocaleString('vi-VN');

        sounds.click.play();
        bets[animal] = (bets[animal] || 0) + currentChip;

        // Hiển thị số tiền cược vào badge của con vật
        const badge = document.getElementById(`bet-badge-${animal}`);
        badge.innerText = (bets[animal] / 1000) + 'K';
        badge.classList.remove('hidden');
    }

    // Xử lý khi bấm nút "XÓC NGAY"
    document.getElementById('rollBtn').addEventListener('click', async function() {
        if (isRolling || Object.keys(bets).length === 0) return;
        isRolling = true;
        this.disabled = true;
        sounds.dice.play();

        const diceDivs = [document.getElementById('dice-1'), document.getElementById('dice-2'), document
            .getElementById('dice-3')
        ];
        diceDivs.forEach(d => d.classList.add('shake'));

        // Tính tổng tiền đang cược trên bàn (để hoàn lại nếu ván chơi lỗi)
        let totalBet = 0;
        for (let k in bets) totalBet += bets[k];

        try {
            const response = await fetch('process_baucua.php', {
                method: 'POST',
                body: new URLSearchParams({
                    'bets': JSON.stringify(bets)
                })
            });
            const data = await response.json();

            setTimeout(() => {
                diceDivs.forEach((d, i) => {
                    d.classList.remove('shake');
                    if (data.success) d.innerText = animalIcons[data.dice[i]];
                });

                if (data.success) {
                    // Cập nhật lại số dư chốt từ Server (để đảm bảo đồng bộ chuẩn 100%)
                    document.getElementById('balance').innerText = data.new_balance.toLocaleString(
                        'vi-VN');

                    // Hiển thị 3 trạng thái: THẮNG, THUA, HÒA VỐN
                    let msgHtml = "";
                    if (data.net_profit > 0) {
                        sounds.win.play();
                        msgHtml =
                            `<span class="text-amber-400">🎉 THẮNG: +${data.net_profit.toLocaleString('vi-VN')}đ</span>`;
                    } else if (data.net_profit < 0) {
                        msgHtml =
                            `<span class="text-rose-500">💸 THUA: ${data.net_profit.toLocaleString('vi-VN')}đ</span>`;
                    } else {
                        msgHtml = `<span class="text-slate-300">🤝 HÒA VỐN</span>`;
                    }
                    document.getElementById('resultMsg').innerHTML = msgHtml;

                    // Cập nhật tiến độ nhiệm vụ
                    if (data.mission) {
                        document.getElementById('missionProgress').innerText =
                            `${data.mission.current}/${data.mission.target}`;
                        if (data.mission.rewarded) alert(
                            "🎁 Chúc mừng! Bạn đã hoàn thành nhiệm vụ và nhận được 1 lượt quay!"
                        );
                    }
                } else {
                    // SERVER TỪ CHỐI -> HOÀN TIỀN LẠI LÊN MÀN HÌNH
                    alert("❌ " + data.error);
                    let balanceEl = document.getElementById('balance');
                    let currentBalance = parseInt(balanceEl.innerText.replace(/,/g, '').replace(
                        /\./g, ''));
                    balanceEl.innerText = (currentBalance + totalBet).toLocaleString('vi-VN');
                }

                // Khóa bàn cược 3 giây để người chơi xem kết quả rồi reset bàn cờ
                setTimeout(() => {
                    bets = {};
                    document.querySelectorAll('[id^="bet-badge-"]').forEach(b => {
                        b.classList.add('hidden');
                        b.innerText = '0';
                    });
                    document.getElementById('resultMsg').innerHTML = '';
                    diceDivs.forEach(d => d.innerText = '❓'); // Reset xí ngầu
                    isRolling = false;
                    document.getElementById('rollBtn').disabled = false;
                }, 3000);
            }, 1500);

        } catch (err) {
            alert("Lỗi kết nối! Đã hoàn lại tiền trên màn hình.");

            // LỖI MẠNG -> HOÀN TIỀN LẠI LÊN MÀN HÌNH
            let balanceEl = document.getElementById('balance');
            let currentBalance = parseInt(balanceEl.innerText.replace(/,/g, '').replace(/\./g, ''));
            balanceEl.innerText = (currentBalance + totalBet).toLocaleString('vi-VN');

            diceDivs.forEach(d => d.classList.remove('shake'));
            isRolling = false;
            document.getElementById('rollBtn').disabled = false;
        }
    });
    </script>
</body>

</html>