<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

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
            animation: shake 0.3s cubic-bezier(.36, .07, .19, .97) infinite;
        }

        @keyframes shake {

            10%,
            90% {
                transform: translate3d(-2px, 0, 0) rotate(-5deg);
            }

            20%,
            80% {
                transform: translate3d(2px, 0, 0) rotate(5deg);
            }

            30%,
            50%,
            70% {
                transform: translate3d(-4px, 0, 0) rotate(-10deg);
            }

            40%,
            60% {
                transform: translate3d(4px, 0, 0) rotate(10deg);
            }
        }

        .winner-glow {
            animation: glow 1.5s ease-in-out infinite alternate;
            box-shadow: 0 0 20px #fbbf24, inset 0 0 20px #fbbf24;
            border-color: #f59e0b !important;
        }

        @keyframes glow {
            from {
                box-shadow: 0 0 10px #fbbf24, inset 0 0 10px #fbbf24;
            }

            to {
                box-shadow: 0 0 30px #f59e0b, inset 0 0 30px #f59e0b;
            }
        }

        /* Ẩn scrollbar nhưng vẫn cho cuộn trên mobile */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen font-sans selection:bg-amber-500 selection:text-white">

    <nav
        class="bg-slate-800/80 backdrop-blur-md border-b border-slate-700 px-4 py-3 flex justify-between items-center sticky top-0 z-50">
        <h1
            class="text-xl font-bold bg-gradient-to-r from-amber-400 to-yellow-200 bg-clip-text text-transparent uppercase tracking-wider">
            Bầu Cua</h1>
        <div class="flex items-center gap-3">
            <div class="bg-slate-900 border border-amber-500/30 px-4 py-1.5 rounded-full flex items-center gap-2">
                <span class="text-amber-400 text-sm">💰</span>
                <span class="font-bold text-amber-400 tracking-wide"
                    id="balance"><?= number_format($user['balance']) ?></span>
            </div>
            <a href="dashboard.php"
                class="text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 px-3 py-2 rounded-full font-medium transition">Thoát</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto mt-6 px-4 pb-20">

        <div
            class="bg-slate-800 border border-slate-700 p-6 rounded-3xl shadow-2xl text-center mb-6 relative overflow-hidden">
            <div
                class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-amber-900/20 via-slate-800 to-slate-800 pointer-events-none">
            </div>

            <div class="flex justify-center gap-4 sm:gap-8 mb-4 relative z-10">
                <div id="dice-1"
                    class="w-20 h-20 sm:w-28 sm:h-28 bg-slate-700 border-2 border-slate-600 rounded-2xl flex items-center justify-center text-6xl sm:text-7xl shadow-[inset_0_-8px_15px_rgba(0,0,0,0.5)] transition-all">
                    ❓</div>
                <div id="dice-2"
                    class="w-20 h-20 sm:w-28 sm:h-28 bg-slate-700 border-2 border-slate-600 rounded-2xl flex items-center justify-center text-6xl sm:text-7xl shadow-[inset_0_-8px_15px_rgba(0,0,0,0.5)] transition-all">
                    ❓</div>
                <div id="dice-3"
                    class="w-20 h-20 sm:w-28 sm:h-28 bg-slate-700 border-2 border-slate-600 rounded-2xl flex items-center justify-center text-6xl sm:text-7xl shadow-[inset_0_-8px_15px_rgba(0,0,0,0.5)] transition-all">
                    ❓</div>
            </div>
            <div id="resultMsg"
                class="h-10 text-lg sm:text-xl font-bold flex items-center justify-center relative z-10"></div>
        </div>

        <div class="bg-slate-800 border border-slate-700 p-4 sm:p-6 rounded-3xl shadow-2xl mb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm sm:text-base font-bold text-slate-300 uppercase tracking-wider">Bàn Cược</h3>
                <button onclick="clearBets()"
                    class="text-xs bg-red-500/20 text-red-400 hover:bg-red-500/30 px-3 py-1.5 rounded-lg border border-red-500/30 transition">
                    🗑️ Hủy Cược
                </button>
            </div>

            <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-2">
                <?php foreach ($animals as $key => $animal): ?>
                    <div id="box-<?= $key ?>" onclick="placeBet('<?= $key ?>')"
                        class="animal-box relative <?= $animal['color'] ?> border-2 <?= $animal['border'] ?> rounded-2xl p-3 sm:p-5 flex flex-col items-center cursor-pointer hover:bg-slate-700/50 transition-all active:scale-95 overflow-hidden group">
                        <span
                            class="text-4xl sm:text-5xl mb-1 sm:mb-2 group-hover:scale-110 transition-transform"><?= $animal['icon'] ?></span>
                        <span class="font-bold text-slate-300 text-sm sm:text-base"><?= $animal['name'] ?></span>

                        <div id="bet-badge-<?= $key ?>"
                            class="hidden absolute top-2 right-2 bg-amber-500 text-slate-900 text-[10px] sm:text-xs font-bold px-2 py-0.5 rounded-full shadow-lg">
                            0
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-4">
                <span class="text-slate-400 text-sm">Tổng cược: <span id="totalBetDisplay"
                        class="font-bold text-amber-400">0</span> VNĐ</span>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="text-sm font-bold text-slate-400 mb-3 text-center uppercase tracking-wider">Chọn Phỉnh Để Đặt
            </h3>
            <div class="flex overflow-x-auto gap-3 pb-4 no-scrollbar justify-start sm:justify-center px-2">
                <button onclick="selectChip(5000, this)"
                    class="chip-btn shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-xs sm:text-sm shadow-lg hover:border-amber-400 transition-all relative">5K</button>
                <button onclick="selectChip(10000, this)"
                    class="chip-btn active-chip shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-full border-4 border-amber-400 bg-amber-500/20 text-amber-400 font-bold text-xs sm:text-sm shadow-[0_0_15px_rgba(251,191,36,0.3)] transition-all relative">10K</button>
                <button onclick="selectChip(20000, this)"
                    class="chip-btn shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-xs sm:text-sm shadow-lg hover:border-amber-400 transition-all relative">20K</button>
                <button onclick="selectChip(50000, this)"
                    class="chip-btn shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-xs sm:text-sm shadow-lg hover:border-amber-400 transition-all relative">50K</button>
                <button onclick="selectChip(100000, this)"
                    class="chip-btn shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-xs sm:text-sm shadow-lg hover:border-amber-400 transition-all relative">100K</button>
                <button onclick="selectChip(500000, this)"
                    class="chip-btn shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-xs sm:text-sm shadow-lg hover:border-amber-400 transition-all relative">500K</button>
            </div>
        </div>

        <div
            class="fixed bottom-0 left-0 right-0 p-4 bg-slate-900/90 backdrop-blur-md border-t border-slate-800 z-40 sm:static sm:bg-transparent sm:border-0 sm:p-0">
            <button id="rollBtn"
                class="w-full sm:w-auto sm:mx-auto sm:block bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-900 text-xl font-black py-4 px-16 rounded-2xl shadow-[0_0_20px_rgba(245,158,11,0.4)] transform transition active:scale-95 uppercase tracking-widest border border-amber-300/50">
                🎲 XÓC NGAY 🎲
            </button>
        </div>

    </main>

    <script>
        const animalIcons = {
            'nai': '🦌',
            'bau': '🎃',
            'ga': '🐓',
            'ca': '🐟',
            'cua': '🦀',
            'tom': '🦐'
        };

        // State
        let currentChip = 10000;
        let bets = {};
        let isRolling = false;

        // Định dạng tiền tệ
        const formatMoney = (amount) => {
            if (amount >= 1000) return (amount / 1000) + 'K';
            return amount;
        }

        // Chọn Phỉnh
        function selectChip(amount, element) {
            currentChip = amount;
            // Xóa active khỏi tất cả
            document.querySelectorAll('.chip-btn').forEach(btn => {
                btn.className =
                    "chip-btn shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-xs sm:text-sm shadow-lg hover:border-amber-400 transition-all relative";
            });
            // Set active cho nút được bấm
            element.className =
                "chip-btn active-chip shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-full border-4 border-amber-400 bg-amber-500/20 text-amber-400 font-bold text-xs sm:text-sm shadow-[0_0_15px_rgba(251,191,36,0.3)] transition-all relative";
        }

        // Đặt cược vào ô
        function placeBet(animal) {
            if (isRolling) return;

            if (!bets[animal]) bets[animal] = 0;
            bets[animal] += currentChip;

            // Cập nhật UI badge
            const badge = document.getElementById(`bet-badge-${animal}`);
            badge.innerText = formatMoney(bets[animal]);
            badge.classList.remove('hidden');

            // Cập nhật tổng cược
            updateTotalBet();

            // Hiệu ứng bấm
            const box = document.getElementById(`box-${animal}`);
            box.classList.add('scale-95', 'brightness-125');
            setTimeout(() => box.classList.remove('scale-95', 'brightness-125'), 100);
        }

        // Hủy cược
        function clearBets() {
            if (isRolling) return;
            bets = {};
            document.querySelectorAll('[id^="bet-badge-"]').forEach(badge => {
                badge.innerText = '0';
                badge.classList.add('hidden');
            });
            updateTotalBet();
            document.getElementById('resultMsg').innerHTML = "";
            removeGlow();
        }

        // Tính tổng tiền đang đặt
        function updateTotalBet() {
            let total = Object.values(bets).reduce((a, b) => a + b, 0);
            document.getElementById('totalBetDisplay').innerText = total.toLocaleString();
        }

        // Xóa hiệu ứng phát sáng của ván trước
        function removeGlow() {
            document.querySelectorAll('.animal-box').forEach(box => {
                box.classList.remove('winner-glow');
            });
        }

        // Nút Xóc Xí Ngầu
        document.getElementById('rollBtn').addEventListener('click', async function() {
            if (isRolling) return;

            let totalBetAmount = Object.values(bets).reduce((a, b) => a + b, 0);
            if (totalBetAmount <= 0) {
                alert("Bạn chưa chọn phỉnh và đặt cược vào bàn!");
                return;
            }

            isRolling = true;
            const btn = this;
            const msg = document.getElementById('resultMsg');
            const diceDivs = [document.getElementById('dice-1'), document.getElementById('dice-2'), document
                .getElementById('dice-3')
            ];

            btn.disabled = true;
            btn.innerHTML = "ĐANG XÓC...";
            msg.innerText = "";
            removeGlow();

            // Hiệu ứng lắc
            diceDivs.forEach(d => d.classList.add('shake', 'brightness-50'));

            let spinInterval = setInterval(() => {
                let keys = Object.keys(animalIcons);
                diceDivs.forEach(d => d.innerText = animalIcons[keys[Math.floor(Math.random() * keys
                    .length)]]);
            }, 80);

            try {
                const formData = new FormData();
                formData.append('bets', JSON.stringify(bets));

                const response = await fetch('process_baucua.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                setTimeout(() => {
                    clearInterval(spinInterval);
                    diceDivs.forEach(d => d.classList.remove('shake', 'brightness-50'));

                    if (data.success) {
                        // Hiện kết quả hột
                        diceDivs[0].innerText = animalIcons[data.dice[0]];
                        diceDivs[1].innerText = animalIcons[data.dice[1]];
                        diceDivs[2].innerText = animalIcons[data.dice[2]];

                        // Làm sáng các ô trúng thưởng
                        if (data.winning_counts) {
                            for (const [animal, count] of Object.entries(data.winning_counts)) {
                                document.getElementById(`box-${animal}`).classList.add('winner-glow');
                                // Có thể thêm nhãn x2, x3 vào đây nếu muốn
                            }
                        }

                        // Thông báo
                        // Thay thế đoạn thông báo cũ bằng đoạn này:
                        if (data.net_profit > 0) {
                            msg.innerHTML =
                                `<span class="text-amber-400 drop-shadow-[0_0_8px_rgba(251,191,36,0.8)] text-base sm:text-lg">🎉 LÃI: +${data.net_profit.toLocaleString()}đ (Thu về ${data.winnings.toLocaleString()}đ)</span>`;
                        } else if (data.net_profit === 0) {
                            msg.innerHTML =
                                `<span class="text-slate-300 text-base sm:text-lg">HÒA VỐN! Nhận lại đúng ${data.winnings.toLocaleString()}đ</span>`;
                        } else {
                            // Nếu lỗ, kiểm tra xem có trúng con nào không để an ủi
                            if (data.winnings > 0) {
                                msg.innerHTML =
                                    `<span class="text-rose-400 text-sm sm:text-base">Trúng nhưng LỖ: ${data.net_profit.toLocaleString()}đ (Cược ${data.total_bet.toLocaleString()}đ, chỉ thu về ${data.winnings.toLocaleString()}đ)</span>`;
                            } else {
                                msg.innerHTML =
                                    `<span class="text-rose-500 text-base sm:text-lg font-black">💸 TRƯỢT SẠCH! Mất trắng ${Math.abs(data.net_profit).toLocaleString()}đ</span>`;
                            }
                        }

                        // Cập nhật tiền
                        document.getElementById('balance').innerText = data.new_balance
                            .toLocaleString();

                        // Xóa cược để chơi ván mới
                        setTimeout(() => {
                            clearBets();
                        }, 5000); // Giữ cược 5 giây cho người chơi nhìn rồi mới xóa

                    } else {
                        diceDivs.forEach(d => d.innerText = "❓");
                        msg.innerHTML = `<span class="text-red-500">❌ ${data.error}</span>`;
                    }

                    btn.disabled = false;
                    btn.innerHTML = "🎲 XÓC LẠI 🎲";
                    isRolling = false;
                }, 1200);

            } catch (err) {
                clearInterval(spinInterval);
                diceDivs.forEach(d => d.classList.remove('shake', 'brightness-50'));
                msg.innerText = "Lỗi kết nối!";
                btn.disabled = false;
                btn.innerHTML = "🎲 XÓC NGAY 🎲";
                isRolling = false;
            }
        });
    </script>
</body>

</html>