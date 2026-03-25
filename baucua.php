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
    'bau' => ['name' => 'Bầu', 'icon' => '🎃'],
    'cua' => ['name' => 'Cua', 'icon' => '🦀'],
    'tom' => ['name' => 'Tôm', 'icon' => '🦐'],
    'ca'  => ['name' => 'Cá',  'icon' => '🐟'],
    'ga'  => ['name' => 'Gà',  'icon' => '🐓'],
    'nai' => ['name' => 'Nai', 'icon' => '🦌']
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bầu Cua Tôm Cá</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 min-h-screen">
    <nav class="bg-white shadow-sm px-4 py-3 flex justify-between items-center sticky top-0 z-50">
        <h1 class="text-xl font-bold text-red-600">Mini Game: Bầu Cua</h1>
        <div class="flex items-center gap-4">
            <span class="font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                Số dư: <span id="balance"><?= number_format($user['balance']) ?></span> VNĐ
            </span>
            <a href="dashboard.php"
                class="text-sm bg-slate-200 text-slate-700 px-4 py-2 rounded-full font-medium hover:bg-slate-300 transition">Quay
                lại</a>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto mt-8 px-4 pb-10">
        <div class="bg-white p-6 rounded-2xl shadow-lg border border-slate-100 text-center mb-8">
            <h2 class="text-2xl font-bold text-slate-800 mb-6">Kết Quả Lắc Xí Ngầu</h2>
            <div class="flex justify-center gap-4 sm:gap-8 mb-4">
                <div id="dice-1"
                    class="w-20 h-20 sm:w-24 sm:h-24 bg-slate-100 border-2 border-slate-200 rounded-xl flex items-center justify-center text-5xl shadow-inner transition-all">
                    ❓</div>
                <div id="dice-2"
                    class="w-20 h-20 sm:w-24 sm:h-24 bg-slate-100 border-2 border-slate-200 rounded-xl flex items-center justify-center text-5xl shadow-inner transition-all">
                    ❓</div>
                <div id="dice-3"
                    class="w-20 h-20 sm:w-24 sm:h-24 bg-slate-100 border-2 border-slate-200 rounded-xl flex items-center justify-center text-5xl shadow-inner transition-all">
                    ❓</div>
            </div>
            <div id="resultMsg" class="h-8 text-lg font-bold"></div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-lg border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 text-center">Chọn ô để đặt cược</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
                <?php foreach ($animals as $key => $animal): ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col items-center">
                        <span class="text-4xl mb-2"><?= $animal['icon'] ?></span>
                        <span class="font-bold text-slate-700 mb-2"><?= $animal['name'] ?></span>
                        <input type="number" id="bet-<?= $key ?>" min="0" step="1000" placeholder="0đ"
                            class="bet-input w-full px-3 py-2 text-center border rounded-lg focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 text-sm font-semibold">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex justify-center mt-6">
                <button id="rollBtn"
                    class="bg-red-600 hover:bg-red-700 text-white text-xl font-bold py-4 px-12 rounded-full shadow-lg transform transition active:scale-95">
                    🎲 LẮC NGAY 🎲
                </button>
            </div>
        </div>
    </main>

    <script>
        const animalIcons = {
            'bau': '🎃',
            'cua': '🦀',
            'tom': '🦐',
            'ca': '🐟',
            'ga': '🐓',
            'nai': '🦌'
        };

        document.getElementById('rollBtn').addEventListener('click', async function() {
            const btn = this;
            const msg = document.getElementById('resultMsg');
            const dice1 = document.getElementById('dice-1');
            const dice2 = document.getElementById('dice-2');
            const dice3 = document.getElementById('dice-3');

            // Thu thập tiền cược
            let bets = {};
            let totalBetAmount = 0;
            document.querySelectorAll('.bet-input').forEach(input => {
                let amount = parseInt(input.value) || 0;
                if (amount > 0) {
                    const key = input.id.replace('bet-', '');
                    bets[key] = amount;
                    totalBetAmount += amount;
                }
            });

            if (totalBetAmount <= 0) {
                alert("Bạn phải đặt cược vào ít nhất 1 ô!");
                return;
            }

            btn.disabled = true;
            btn.innerHTML = "Đang lắc...";
            msg.innerText = "";

            // Hiệu ứng lắc
            let spinInterval = setInterval(() => {
                let keys = Object.keys(animalIcons);
                dice1.innerText = animalIcons[keys[Math.floor(Math.random() * keys.length)]];
                dice2.innerText = animalIcons[keys[Math.floor(Math.random() * keys.length)]];
                dice3.innerText = animalIcons[keys[Math.floor(Math.random() * keys.length)]];
            }, 100);

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

                    if (data.success) {
                        // Hiển thị kết quả chính xác
                        dice1.innerText = animalIcons[data.dice[0]];
                        dice2.innerText = animalIcons[data.dice[1]];
                        dice3.innerText = animalIcons[data.dice[2]];

                        // Hiển thị thông báo
                        if (data.net_profit > 0) {
                            msg.innerHTML =
                                `<span class="text-green-600">🎉 Bạn thắng +${data.net_profit.toLocaleString()} VNĐ</span>`;
                        } else if (data.net_profit === 0) {
                            msg.innerHTML =
                                `<span class="text-slate-600">Hòa vốn! Bạn nhận lại ${data.winnings.toLocaleString()} VNĐ</span>`;
                        } else {
                            msg.innerHTML =
                                `<span class="text-red-600">💸 Bạn thua ${Math.abs(data.net_profit).toLocaleString()} VNĐ</span>`;
                        }

                        // Cập nhật số dư
                        document.getElementById('balance').innerText = data.new_balance
                            .toLocaleString();

                        // Reset input sau khi chơi
                        document.querySelectorAll('.bet-input').forEach(input => input.value = '');
                    } else {
                        clearInterval(spinInterval);
                        dice1.innerText = "❓";
                        dice2.innerText = "❓";
                        dice3.innerText = "❓";
                        msg.innerHTML = `<span class="text-red-500">❌ ${data.error}</span>`;
                    }

                    btn.disabled = false;
                    btn.innerHTML = "🎲 LẮC LẠI 🎲";
                }, 1000);

            } catch (err) {
                clearInterval(spinInterval);
                msg.innerText = "Lỗi kết nối!";
                btn.disabled = false;
                btn.innerHTML = "🎲 LẮC NGAY 🎲";
            }
        });
    </script>
</body>

</html>