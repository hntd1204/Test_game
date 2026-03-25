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
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Xì Dách Hoàng Gia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    /* Ẩn scrollbar */
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

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
    </style>
</head>

<body class="bg-emerald-900 text-slate-100 min-h-screen font-sans">
    <nav
        class="bg-slate-900/80 backdrop-blur-md border-b border-emerald-700/50 px-4 py-3 flex justify-between items-center sticky top-0 z-50">
        <h1 class="text-xl font-bold text-white uppercase tracking-wider">Xì Dách</h1>
        <div class="flex items-center gap-3">
            <div class="bg-slate-800 border border-emerald-500/30 px-4 py-1.5 rounded-full flex items-center gap-2">
                <span class="text-amber-400 text-sm">💰</span>
                <span class="font-bold text-amber-400 tracking-wide"
                    id="balance"><?= number_format($user['balance']) ?></span>
            </div>
            <a href="dashboard.php"
                class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-2 rounded-full font-medium transition">Thoát</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto mt-6 px-4 pb-24">

        <div class="bg-emerald-800 border-4 border-emerald-700 p-6 rounded-[2rem] shadow-2xl mb-8 relative">
            <div
                class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-emerald-600/20 to-transparent pointer-events-none rounded-[2rem]">
            </div>

            <div class="mb-8 relative z-10">
                <div class="text-center mb-2 text-emerald-300 font-bold text-sm tracking-widest">NHÀ CÁI <span
                        id="dealerScore" class="ml-2 bg-emerald-900 px-2 py-0.5 rounded text-xs hidden">0</span></div>
                <div id="dealerCards" class="flex justify-center min-h-[100px] sm:min-h-[120px] px-4">
                </div>
            </div>

            <div id="resultMsg"
                class="text-center text-xl sm:text-2xl font-black min-h-[40px] mb-8 text-amber-400 drop-shadow-md z-10 relative">
            </div>

            <div class="relative z-10">
                <div class="text-center mb-2 text-emerald-300 font-bold text-sm tracking-widest">BẠN <span
                        id="playerScore" class="ml-2 bg-emerald-900 px-2 py-0.5 rounded text-xs hidden">0</span></div>
                <div id="playerCards" class="flex justify-center gap-2 min-h-[120px]">
                </div>
            </div>
        </div>

        <div id="betArea" class="mb-8 transition-opacity duration-300">
            <h3 class="text-sm font-bold text-emerald-300 mb-3 text-center uppercase tracking-wider">Chọn Phỉnh Để Đặt
                Cược</h3>
            <div class="flex overflow-x-auto gap-3 pb-4 no-scrollbar justify-start sm:justify-center px-2">
                <button onclick="selectChip(10000, this)"
                    class="chip-btn active-chip shrink-0 w-14 h-14 rounded-full border-4 border-amber-400 bg-amber-500/20 text-amber-400 font-bold text-sm shadow-[0_0_15px_rgba(251,191,36,0.3)]">10K</button>
                <button onclick="selectChip(20000, this)"
                    class="chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-sm shadow-lg">20K</button>
                <button onclick="selectChip(50000, this)"
                    class="chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-sm shadow-lg">50K</button>
                <button onclick="selectChip(100000, this)"
                    class="chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-sm shadow-lg">100K</button>
                <button onclick="selectChip(500000, this)"
                    class="chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-sm shadow-lg">500K</button>
            </div>
        </div>

        <div
            class="fixed bottom-0 left-0 right-0 p-4 bg-slate-900/95 backdrop-blur-md z-40 sm:static sm:bg-transparent sm:p-0 flex justify-center gap-4">
            <button id="dealBtn" onclick="dealCards()"
                class="w-full sm:w-64 bg-amber-500 hover:bg-amber-400 text-slate-900 text-lg font-black py-4 rounded-xl shadow-[0_0_20px_rgba(245,158,11,0.3)] uppercase">CHIA
                BÀI</button>

            <button id="hitBtn" onclick="action('hit')"
                class="hidden flex-1 sm:w-48 bg-blue-600 hover:bg-blue-500 text-white text-lg font-black py-4 rounded-xl shadow-lg uppercase">RÚT</button>
            <button id="standBtn" onclick="action('stand')"
                class="hidden flex-1 sm:w-48 bg-rose-600 hover:bg-rose-500 text-white text-lg font-black py-4 rounded-xl shadow-lg uppercase">DỪNG</button>
        </div>
    </main>

    <script>
    let currentBet = 10000;
    let isPlaying = false;

    function selectChip(amount, el) {
        if (isPlaying) return;
        currentBet = amount;
        document.querySelectorAll('.chip-btn').forEach(btn => {
            btn.className =
                "chip-btn shrink-0 w-14 h-14 rounded-full border-4 border-slate-600 bg-slate-800 text-slate-300 font-bold text-sm shadow-lg";
        });
        el.className =
            "chip-btn active-chip shrink-0 w-14 h-14 rounded-full border-4 border-amber-400 bg-amber-500/20 text-amber-400 font-bold text-sm shadow-[0_0_15px_rgba(251,191,36,0.3)]";
    }

    function renderCard(card, isHidden = false) {
        // Thêm class xếp chồng bài (-ml-6 trên mobile, -ml-8 trên PC) để không bị tràn màn hình khi rút 5 lá
        const overlapClass =
            " -ml-6 sm:-ml-8 first:ml-0 relative hover:-translate-y-2 transition-transform shadow-[-4px_0_10px_rgba(0,0,0,0.2)]";

        if (isHidden) {
            return `<div class="w-14 h-20 sm:w-20 sm:h-28 bg-blue-900 border-2 border-white/20 rounded-lg card-enter flex items-center justify-center bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCI+PGNpcmNsZSBjeD0iMTAiIGN5PSIxMCIgcj0iMiIgZmlsbD0id2hpdGUiIG9wYWNpdHk9IjAuMiIvPjwvc3ZnPg==')] ${overlapClass}"></div>`;
        }
        const color = card.color === 'red' ? 'text-rose-600' : 'text-slate-900';

        // w-14 h-20 cho mobile (nhỏ gọn), w-20 h-28 cho màn hình to
        return `<div class="w-14 h-20 sm:w-20 sm:h-28 bg-white border border-slate-200 rounded-lg card-enter flex flex-col justify-between p-1 sm:p-2 ${color} ${overlapClass}">
                <div class="text-xs sm:text-base font-bold leading-none">${card.rank}</div>
                <div class="text-xl sm:text-3xl text-center leading-none">${card.suit}</div>
                <div class="text-xs sm:text-base font-bold leading-none text-right transform rotate-180">${card.rank}</div>
            </div>`;
    }

    async function dealCards() {
        if (isPlaying) return;
        isPlaying = true;

        document.getElementById('dealerCards').innerHTML = '';
        document.getElementById('playerCards').innerHTML = '';
        document.getElementById('resultMsg').innerText = '';
        document.getElementById('dealerScore').classList.add('hidden');
        document.getElementById('playerScore').classList.add('hidden');

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
                alert("❌ " + data.error);
                isPlaying = false;
                return;
            }

            document.getElementById('balance').innerText = data.balance.toLocaleString();

            // Hiển thị bài
            let pHtml = data.player.map(c => renderCard(c)).join('');
            document.getElementById('playerCards').innerHTML = pHtml;

            let dHtml = renderCard(data.dealer[0]) + renderCard(null, true);
            document.getElementById('dealerCards').innerHTML = dHtml;

            document.getElementById('playerScore').innerText = data.player_score;
            document.getElementById('playerScore').classList.remove('hidden');

            if (data.is_end) {
                endGame(data);
            } else {
                document.getElementById('dealBtn').classList.add('hidden');
                document.getElementById('betArea').classList.add('opacity-50', 'pointer-events-none');
                document.getElementById('hitBtn').classList.remove('hidden');
                document.getElementById('standBtn').classList.remove('hidden');
            }
        } catch (err) {
            alert("Lỗi kết nối!");
            isPlaying = false;
        }
    }

    async function action(type) {
        const formData = new FormData();
        formData.append('action', type);

        try {
            const res = await fetch('process_blackjack.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                alert(data.error);
                return;
            }

            document.getElementById('playerCards').innerHTML = data.player.map(c => renderCard(c)).join('');
            document.getElementById('playerScore').innerText = data.player_score;

            if (data.is_end) {
                endGame(data);
            }
        } catch (e) {
            alert("Lỗi kết nối");
        }
    }

    function endGame(data) {
        document.getElementById('dealerCards').innerHTML = data.dealer.map(c => renderCard(c)).join('');
        document.getElementById('dealerScore').innerText = data.dealer_score;
        document.getElementById('dealerScore').classList.remove('hidden');

        document.getElementById('resultMsg').innerHTML =
            `<span class="${data.net_profit > 0 ? 'text-amber-400' : (data.net_profit < 0 ? 'text-rose-400' : 'text-slate-300')}">${data.message} <br> <span class="text-sm">${data.net_profit > 0 ? '+' : ''}${data.net_profit.toLocaleString()}đ</span></span>`;
        document.getElementById('balance').innerText = data.balance.toLocaleString();

        document.getElementById('hitBtn').classList.add('hidden');
        document.getElementById('standBtn').classList.add('hidden');
        document.getElementById('dealBtn').classList.remove('hidden');
        document.getElementById('dealBtn').innerText = "CHƠI VÁN MỚI";
        document.getElementById('betArea').classList.remove('opacity-50', 'pointer-events-none');

        isPlaying = false;
    }
    </script>
</body>

</html>