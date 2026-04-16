<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT balance, hilo_count FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$historyStmt = $pdo->prepare("SELECT * FROM hilo_history WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$historyStmt->execute([$_SESSION['user_id']]);
$myHistories = $historyStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lật Bài Hi-Lo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card-enter {
            animation: flipIn 0.4s ease-out forwards;
        }

        @keyframes flipIn {
            0% {
                transform: scale(0.9) rotateY(90deg);
                opacity: 0;
            }

            100% {
                transform: scale(1) rotateY(0deg);
                opacity: 1;
            }
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>

<body class="bg-indigo-950 text-slate-100 min-h-screen">
    <nav
        class="bg-indigo-900/80 backdrop-blur-md px-4 py-3 flex justify-between items-center sticky top-0 z-50 shadow-md border-b border-indigo-800">
        <h1 class="text-xl font-bold text-white uppercase tracking-wider">Lật Bài Hi-Lo</h1>
        <div class="flex items-center gap-3">
            <div class="bg-indigo-800 border border-indigo-500/30 px-4 py-1.5 rounded-full flex items-center gap-2">
                <span class="text-amber-400 text-sm">💰</span>
                <span class="font-bold text-amber-400 tracking-wide"
                    id="balance"><?= number_format($user['balance']) ?></span>
            </div>
            <a href="dashboard.php"
                class="text-xs bg-slate-700 hover:bg-rose-600 px-3 py-2 rounded-full font-medium transition">Thoát</a>
        </div>
    </nav>

    <main class="max-w-md mx-auto mt-6 px-4 pb-10">
        <div class="bg-indigo-800 border-4 border-indigo-600 p-6 rounded-3xl shadow-2xl mb-8 relative">
            <div class="text-center mb-4 flex justify-between items-center bg-indigo-900/50 p-3 rounded-xl">
                <div class="text-indigo-200 text-sm font-bold">Tiền Thưởng: <br> <span id="currentPot"
                        class="text-xl text-amber-400">0</span></div>
                <div class="text-indigo-200 text-sm font-bold text-right">Lá bài <br> <span
                        class="text-xs text-slate-400">2 thấp nhất, A cao nhất</span></div>
            </div>

            <div class="flex justify-center items-center min-h-[220px] mb-4 relative" id="cardArea">
                <div
                    class="w-32 h-48 bg-indigo-900 border-2 border-indigo-400/20 rounded-xl flex items-center justify-center shadow-lg">
                    <span class="text-4xl opacity-20">?</span>
                </div>
            </div>

            <div id="resultMsg" class="text-center text-lg font-black min-h-[28px] mb-4 text-amber-400 drop-shadow-md">
                Sẵn sàng!</div>

            <div id="betArea" class="flex justify-center gap-3 overflow-x-auto no-scrollbar mb-4">
                <?php foreach ([10000, 20000, 50000, 100000] as $chip): ?>
                    <button onclick="selectChip(<?= $chip ?>, this)"
                        class="chip-btn shrink-0 w-12 h-12 rounded-full border-2 border-slate-600 bg-slate-800 font-bold text-xs <?= $chip == 10000 ? 'border-amber-400 text-amber-400' : '' ?>"><?= $chip / 1000 ?>K</button>
                <?php endforeach; ?>
            </div>

            <div class="flex flex-col gap-3">
                <button id="startBtn" onclick="startGame()"
                    class="w-full bg-amber-500 hover:bg-amber-400 text-indigo-950 text-lg font-black py-4 rounded-xl shadow-lg uppercase transition">Bắt
                    Đầu Cược</button>

                <div id="actionBtns" class="hidden grid grid-cols-2 gap-3">
                    <button onclick="makeGuess('lo')"
                        class="bg-rose-500 hover:bg-rose-400 text-white font-black py-4 rounded-xl uppercase flex flex-col items-center shadow-lg transition">
                        <span class="text-2xl mb-1">👇</span> THẤP HƠN
                    </button>
                    <button onclick="makeGuess('hi')"
                        class="bg-emerald-500 hover:bg-emerald-400 text-white font-black py-4 rounded-xl uppercase flex flex-col items-center shadow-lg transition">
                        <span class="text-2xl mb-1">👆</span> CAO HƠN
                    </button>
                    <button onclick="cashOut()"
                        class="col-span-2 bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl uppercase shadow-lg transition mt-2">
                        💰 DỪNG LẠI & NHẬN TIỀN
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-indigo-900 border border-indigo-700 p-4 rounded-2xl shadow-xl">
            <h3 class="text-sm font-bold text-indigo-300 mb-3 uppercase tracking-wider">Lịch Sử Của Bạn</h3>
            <div class="space-y-2">
                <?php foreach ($myHistories as $h): ?>
                    <div class="flex justify-between items-center bg-indigo-950 p-2 rounded-lg text-xs">
                        <span class="text-slate-400"><?= date('H:i d/m', strtotime($h['created_at'])) ?> (Chuỗi:
                            <?= $h['streak'] ?>)</span>
                        <span class="font-bold <?= $h['net_profit'] > 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                            <?= $h['net_profit'] > 0 ? '+' . number_format($h['net_profit']) : number_format($h['net_profit']) ?>đ
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
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
                "chip-btn shrink-0 w-12 h-12 rounded-full border-2 border-slate-600 bg-slate-800 font-bold text-xs");
            el.className =
                "chip-btn shrink-0 w-12 h-12 rounded-full border-2 border-amber-400 text-amber-400 font-bold text-xs";
        }

        function renderCard(card) {
            const color = card.color === 'red' ? 'text-rose-600' : 'text-slate-900';
            return `
            <div class="w-32 h-48 bg-white rounded-2xl shadow-xl card-enter flex flex-col justify-between p-3 ${color} border-4 border-slate-200 absolute">
                <div class="text-xl font-bold">${card.rank}</div>
                <div class="text-6xl text-center self-center">${card.suit}</div>
                <div class="text-xl font-bold text-right transform rotate-180">${card.rank}</div>
            </div>`;
        }

        async function startGame() {
            if (isPlaying) return;

            const formData = new FormData();
            formData.append('action', 'start');
            formData.append('bet', currentBet);

            try {
                const res = await fetch('process_hilo.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (!data.success) return alert(data.error);

                isPlaying = true;
                sounds.card.play();

                document.getElementById('balance').innerText = data.balance.toLocaleString();
                document.getElementById('currentPot').innerText = data.pot.toLocaleString();
                document.getElementById('cardArea').innerHTML = renderCard(data.card);
                document.getElementById('resultMsg').innerHTML = "<span class='text-white'>Đoán lá tiếp theo!</span>";

                document.getElementById('startBtn').classList.add('hidden');
                document.getElementById('betArea').classList.add('hidden');
                document.getElementById('actionBtns').classList.remove('hidden');
            } catch (e) {
                alert("Lỗi kết nối!");
            }
        }

        async function makeGuess(choice) {
            if (!isPlaying) return;

            const formData = new FormData();
            formData.append('action', 'guess');
            formData.append('choice', choice);

            try {
                const res = await fetch('process_hilo.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                sounds.card.play();
                document.getElementById('cardArea').innerHTML = renderCard(data.card);

                if (data.is_end) {
                    // Thua
                    sounds.lose.play();
                    document.getElementById('currentPot').innerText = "0";
                    document.getElementById('resultMsg').innerHTML =
                        `<span class='text-rose-500'>${data.message}</span>`;
                    resetGameUI();
                } else {
                    // Thắng hoặc Hòa
                    if (data.message.includes('Chính xác')) sounds.win.play();
                    document.getElementById('currentPot').innerText = data.pot.toLocaleString();
                    document.getElementById('resultMsg').innerHTML =
                        `<span class='text-emerald-400'>${data.message}</span>`;
                }
            } catch (e) {
                alert("Lỗi hệ thống!");
            }
        }

        async function cashOut() {
            if (!isPlaying) return;

            const formData = new FormData();
            formData.append('action', 'cashout');

            try {
                const res = await fetch('process_hilo.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    sounds.win.play();
                    document.getElementById('balance').innerText = data.balance.toLocaleString();
                    document.getElementById('resultMsg').innerHTML =
                        `<span class='text-amber-400'>🎉 Đã chốt lời: +${data.winnings.toLocaleString()}đ</span>`;
                    resetGameUI();
                }
            } catch (e) {
                alert("Lỗi kết nối!");
            }
        }

        function resetGameUI() {
            isPlaying = false;
            setTimeout(() => {
                document.getElementById('actionBtns').classList.add('hidden');
                document.getElementById('startBtn').classList.remove('hidden');
                document.getElementById('betArea').classList.remove('hidden');
            }, 2000);
        }
    </script>
</body>

</html>