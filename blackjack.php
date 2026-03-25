<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

// Lấy thông tin balance và blackjack_count
$stmt = $pdo->prepare("SELECT balance, blackjack_count FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Lấy cấu hình nhiệm vụ Xì Dách từ Admin
try {
    $missionStmt = $pdo->query("SELECT target_count, reward_spins FROM mission_settings WHERE mission_key = 'blackjack_count'");
    $mission = $missionStmt->fetch();
    if (!$mission) {
        $mission = ['target_count' => 5, 'reward_spins' => 1]; // Mặc định nếu chưa cài
    }
} catch (Exception $e) {
    $mission = ['target_count' => 5, 'reward_spins' => 1];
}

// LẤY LỊCH SỬ CHƠI XÌ DÁCH CỦA USER
$historyStmt = $pdo->prepare("SELECT * FROM blackjack_history WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$historyStmt->execute([$_SESSION['user_id']]);
$myHistories = $historyStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Xì Dách Hoàng Gia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card-enter {
            animation: slideIn 0.3s ease-out forwards;
            opacity: 0;
            transform: translateY(-20px);
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>

<body class="bg-emerald-900 text-slate-100 min-h-screen">
    <nav class="bg-slate-900/80 backdrop-blur-md px-4 py-3 flex justify-between items-center sticky top-0 z-50">
        <h1 class="text-xl font-bold text-white uppercase tracking-wider">Xì Dách</h1>
        <div class="flex items-center gap-3">
            <div
                class="hidden sm:flex bg-emerald-800 border border-emerald-500/50 px-3 py-1 rounded-full text-[10px] items-center gap-1">
                <span class="text-emerald-300">Nhiệm vụ:</span>
                <span id="missionProgress"
                    class="font-bold text-white"><?= (int)$user['blackjack_count'] ?>/<?= $mission['target_count'] ?></span>
            </div>

            <div class="bg-slate-800 border border-emerald-500/30 px-4 py-1.5 rounded-full flex items-center gap-2">
                <span class="text-amber-400 text-sm">💰</span>
                <span class="font-bold text-amber-400 tracking-wide"
                    id="balance"><?= number_format($user['balance']) ?></span>
            </div>
            <a href="dashboard.php" class="text-xs bg-slate-700 px-3 py-2 rounded-full font-medium transition">Thoát</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto mt-6 px-4 pb-24">
        <div class="bg-emerald-800 border-4 border-emerald-700 p-6 rounded-[2rem] shadow-2xl mb-8 relative">
            <div class="text-center mb-2 text-emerald-300 font-bold text-xs tracking-widest uppercase">Nhà Cái <span
                    id="dealerScore" class="hidden ml-2 bg-emerald-900 px-2 py-0.5 rounded">0</span></div>
            <div id="dealerCards" class="flex justify-center min-h-[100px] gap-2 mb-8"></div>

            <div id="resultMsg" class="text-center text-xl font-black min-h-[40px] mb-8 text-amber-400 drop-shadow-md">
            </div>

            <div class="text-center mb-2 text-emerald-300 font-bold text-xs tracking-widest uppercase">Bạn <span
                    id="playerScore" class="hidden ml-2 bg-emerald-900 px-2 py-0.5 rounded">0</span></div>
            <div id="playerCards" class="flex justify-center gap-2 min-h-[120px]"></div>
        </div>

        <div id="betArea" class="mb-8 flex justify-center gap-3 overflow-x-auto no-scrollbar">
            <?php foreach ([10000, 20000, 50000, 100000] as $chip): ?>
                <button onclick="selectChip(<?= $chip ?>, this)"
                    class="chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 font-bold text-xs <?= $chip == 10000 ? 'border-amber-400 text-amber-400' : '' ?>"><?= $chip / 1000 ?>K</button>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-center gap-4 mb-8">
            <button id="dealBtn" onclick="dealCards()"
                class="w-full sm:w-64 bg-amber-500 text-slate-900 text-lg font-black py-4 rounded-xl shadow-lg uppercase">Chia
                Bài</button>
            <button id="hitBtn" onclick="action('hit')"
                class="hidden flex-1 bg-blue-600 text-white font-black py-4 rounded-xl uppercase">Rút</button>
            <button id="standBtn" onclick="action('stand')"
                class="hidden flex-1 bg-rose-600 text-white font-black py-4 rounded-xl uppercase">Dừng</button>
        </div>

        <div class="bg-emerald-800 border-4 border-emerald-700 p-4 sm:p-6 rounded-3xl shadow-xl">
            <h3 class="text-lg font-bold text-amber-400 mb-4 border-b border-emerald-600 pb-2">📜 Lịch Sử Chơi Gần Đây
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-emerald-100 whitespace-nowrap">
                    <thead class="bg-emerald-900/50 text-emerald-300">
                        <tr>
                            <th class="px-3 py-2 rounded-tl-lg">Thời gian</th>
                            <th class="px-3 py-2 text-right">Cược</th>
                            <th class="px-3 py-2 text-right">Thắng</th>
                            <th class="px-3 py-2 text-right rounded-tr-lg">Lãi/Lỗ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-emerald-700/50">
                        <?php foreach ($myHistories as $h): ?>
                            <tr class="hover:bg-emerald-700/30 transition">
                                <td class="px-3 py-3 text-xs text-emerald-200">
                                    <?= date('H:i d/m', strtotime($h['created_at'])) ?></td>
                                <td class="px-3 py-3 text-right font-medium"><?= number_format($h['bet']) ?>đ</td>
                                <td class="px-3 py-3 text-right font-bold text-amber-300"><?= number_format($h['win']) ?>đ
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-bold <?= $h['net_profit'] > 0 ? 'text-green-400' : ($h['net_profit'] < 0 ? 'text-rose-400' : 'text-slate-300') ?>">
                                    <?= $h['net_profit'] > 0 ? '+' : '' ?><?= number_format($h['net_profit']) ?>đ
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($myHistories) == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center py-6 text-emerald-400/50">Bạn chưa tham gia ván Xì Dách
                                    nào.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-emerald-400/70 text-center mt-3 italic">* Tải lại trang (F5) để cập nhật lịch sử mới
                nhất.</p>
        </div>
    </main>

    <script>
        // Âm thanh
        const sounds = {
            card: new Audio('https://www.soundjay.com/misc/sounds/card-flip-1.mp3'),
            win: new Audio('https://www.soundjay.com/misc/sounds/bell-ringing-05.mp3'),
            lose: new Audio('https://www.soundjay.com/buttons/button-10.mp3')
        };

        let currentBet = 10000;
        let isPlaying = false;

        function selectChip(amount, el) {
            if (isPlaying) return;
            currentBet = amount;
            document.querySelectorAll('.chip-btn').forEach(b => b.className =
                "chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 font-bold text-xs");
            el.className =
                "chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-amber-400 text-amber-400 font-bold text-xs";
        }

        function renderCard(card, isHidden = false) {
            if (isHidden)
                return `<div class="w-14 h-20 bg-blue-900 border-2 border-white/20 rounded-lg card-enter flex items-center justify-center"></div>`;
            const color = card.color === 'red' ? 'text-rose-600' : 'text-slate-900';
            return `<div class="w-14 h-20 bg-white rounded-lg card-enter flex flex-col justify-between p-1 ${color}">
                <div class="text-xs font-bold">${card.rank}</div>
                <div class="text-2xl text-center">${card.suit}</div>
                <div class="text-xs font-bold text-right transform rotate-180">${card.rank}</div>
            </div>`;
        }

        async function dealCards() {
            if (isPlaying) return;
            isPlaying = true;
            sounds.card.play();

            const formData = new FormData();
            formData.append('action', 'deal');
            formData.append('bet', currentBet);

            try {
                const res = await fetch('process_blackjack.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (!data.success) {
                    alert(data.error);
                    isPlaying = false;
                    return;
                }

                document.getElementById('balance').innerText = data.balance.toLocaleString();
                document.getElementById('playerCards').innerHTML = data.player.map(c => renderCard(c)).join('');
                document.getElementById('dealerCards').innerHTML = renderCard(data.dealer[0]) + renderCard(null, true);
                document.getElementById('playerScore').innerText = data.player_score;
                document.getElementById('playerScore').classList.remove('hidden');
                document.getElementById('resultMsg').innerText = '';

                if (data.is_end) endGame(data);
                else {
                    document.getElementById('dealBtn').classList.add('hidden');
                    document.getElementById('hitBtn').classList.remove('hidden');
                    document.getElementById('standBtn').classList.remove('hidden');
                }
            } catch (err) {
                alert("Lỗi kết nối!");
                isPlaying = false;
            }
        }

        async function action(type) {
            sounds.card.play();
            const formData = new FormData();
            formData.append('action', type);
            try {
                const res = await fetch('process_blackjack.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                document.getElementById('playerCards').innerHTML = data.player.map(c => renderCard(c)).join('');
                document.getElementById('playerScore').innerText = data.player_score;
                if (data.is_end) endGame(data);
            } catch (err) {
                alert("Lỗi kết nối!");
            }
        }

        function endGame(data) {
            document.getElementById('dealerCards').innerHTML = data.dealer.map(c => renderCard(c)).join('');
            document.getElementById('dealerScore').innerText = data.dealer_score;
            document.getElementById('dealerScore').classList.remove('hidden');
            document.getElementById('resultMsg').innerHTML = data.message;
            document.getElementById('balance').innerText = data.balance.toLocaleString();

            if (data.net_profit > 0) sounds.win.play();
            else if (data.net_profit < 0) sounds.lose.play();

            // Cập nhật tiến độ nhiệm vụ từ server
            if (data.mission) {
                const progressSpan = document.getElementById('missionProgress');
                if (data.mission.current !== undefined) {
                    progressSpan.innerText = `${data.mission.current}/${data.mission.target}`;
                }

                if (data.mission.rewarded) {
                    alert("🎁 Chúc mừng! Bạn đã hoàn thành nhiệm vụ Xì Dách và nhận được lượt quay miễn phí!");
                }
            }

            document.getElementById('hitBtn').classList.add('hidden');
            document.getElementById('standBtn').classList.add('hidden');
            document.getElementById('dealBtn').classList.remove('hidden');
            document.getElementById('dealBtn').innerText = "Ván Mới";
            isPlaying = false;
        }
    </script>
</body>

</html>