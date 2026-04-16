<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$stmt = $pdo->prepare("SELECT balance, mines_count FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dò Mìn (Mines)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tile-enter {
            animation: popIn 0.2s ease-out forwards;
        }

        @keyframes popIn {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen font-sans">
    <nav
        class="bg-slate-800/80 backdrop-blur-md border-b border-slate-700 px-4 py-3 flex justify-between items-center sticky top-0 z-50">
        <h1 class="text-xl font-bold text-emerald-400 uppercase tracking-wider">Dò Mìn</h1>
        <div class="flex items-center gap-3">
            <div
                class="bg-slate-900 border border-emerald-500/30 px-4 py-1.5 rounded-full flex items-center gap-2 shadow-inner">
                <span class="text-amber-400 text-sm">💰</span>
                <span class="font-bold text-amber-400 tracking-wide"
                    id="balance"><?= number_format($user['balance']) ?></span>
            </div>
            <a href="dashboard.php"
                class="text-xs bg-rose-600 hover:bg-rose-700 px-3 py-2 rounded-full font-bold transition shadow-lg">Thoát</a>
        </div>
    </nav>

    <main class="max-w-md mx-auto mt-6 px-4 pb-10">
        <div class="bg-slate-800 border-2 border-slate-700 p-6 rounded-3xl shadow-2xl mb-6 relative">
            <div class="flex justify-between items-center mb-6 bg-slate-900 p-3 rounded-xl border border-slate-700">
                <div class="text-slate-400 text-xs font-bold uppercase">Tiền Thưởng<br>
                    <span id="pot" class="text-xl text-emerald-400">0</span>
                </div>
                <div id="msg" class="text-right text-sm font-bold h-10 flex items-center justify-end w-1/2">
                    <span class="text-slate-500">Bấm Bắt Đầu để chơi</span>
                </div>
            </div>

            <div id="grid"
                class="grid grid-cols-5 gap-2 mb-6 pointer-events-none opacity-50 transition-opacity duration-300">
                <?php for ($i = 0; $i < 25; $i++): ?>
                    <button onclick="openTile(<?= $i ?>, this)"
                        class="tile aspect-square bg-slate-700 rounded-xl shadow-inner font-black text-2xl border-b-4 border-slate-900 hover:bg-slate-600 transition-all flex items-center justify-center active:border-b-0 active:translate-y-1"></button>
                <?php endfor; ?>
            </div>

            <div id="controls" class="space-y-3">
                <select id="betAmount"
                    class="w-full p-4 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold outline-none focus:border-emerald-500 transition">
                    <option value="10000">Cược 10.000đ</option>
                    <option value="20000">Cược 20.000đ</option>
                    <option value="50000">Cược 50.000đ</option>
                    <option value="100000">Cược 100.000đ</option>
                </select>
                <button id="startBtn" onclick="startGame()"
                    class="w-full bg-blue-600 hover:bg-blue-500 text-white py-4 rounded-xl font-black shadow-lg uppercase tracking-widest transition">BẮT
                    ĐẦU</button>
                <button id="cashoutBtn" onclick="cashout()"
                    class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-900 py-4 rounded-xl font-black shadow-lg hidden uppercase tracking-widest transition">💰
                    CHỐT LỜI</button>
            </div>
        </div>
    </main>

    <script>
        // Giữ nguyên logic JS cũ của bạn, chỉ tinh chỉnh hiệu ứng hiển thị
        let isPlaying = false;
        let isProcessing = false; // Thêm cờ chặn spam click

        async function startGame() {
            if (isProcessing) return;
            isProcessing = true; // Khóa thao tác

            const bet = document.getElementById('betAmount').value;
            const fd = new FormData();
            fd.append('action', 'start');
            fd.append('bet', bet);

            try {
                const res = await fetch('process_mines.php', {
                    method: 'POST',
                    body: fd
                }).then(r => r.json());

                if (!res.success) return alert(res.error);

                isPlaying = true;
                // Ép kiểu Number() để dấu phẩy hiển thị chuẩn 100%
                document.getElementById('balance').innerText = Number(res.balance).toLocaleString('vi-VN');
                document.getElementById('pot').innerText = Number(res.pot).toLocaleString('vi-VN');

                document.getElementById('startBtn').classList.add('hidden');
                document.getElementById('betAmount').classList.add('hidden');
                document.getElementById('cashoutBtn').classList.remove('hidden');
                document.getElementById('msg').innerHTML =
                    '<span class="text-amber-400 animate-pulse">Đang rà mìn...</span>';

                const grid = document.getElementById('grid');
                grid.classList.remove('pointer-events-none', 'opacity-50');
                document.querySelectorAll('.tile').forEach(t => {
                    t.innerHTML = '';
                    t.className =
                        'tile aspect-square bg-slate-700 rounded-xl shadow-inner font-black text-2xl border-b-4 border-slate-900 hover:bg-slate-600 transition-all flex items-center justify-center active:border-b-0 active:translate-y-1';
                    t.disabled = false;
                });
            } finally {
                isProcessing = false; // Mở khóa thao tác
            }
        }

        async function openTile(index, btn) {
            if (!isPlaying || btn.disabled || isProcessing) return;
            isProcessing = true; // Khóa thao tác
            btn.disabled = true; // Disable ô bấm ngay lập tức

            const fd = new FormData();
            fd.append('action', 'open');
            fd.append('index', index);

            try {
                const res = await fetch('process_mines.php', {
                    method: 'POST',
                    body: fd
                }).then(r => r.json());
                btn.classList.add('tile-enter');

                if (res.is_bomb) {
                    isPlaying = false;
                    btn.innerHTML = '💣';
                    btn.classList.replace('bg-slate-700', 'bg-rose-500');
                    btn.classList.replace('border-slate-900', 'border-rose-700');
                    document.getElementById('msg').innerHTML = '<span class="text-rose-500">BÙM! Đạp mìn!</span>';
                    resetUI();
                } else {
                    btn.innerHTML = '💎';
                    btn.classList.replace('bg-slate-700', 'bg-emerald-500');
                    btn.classList.replace('border-slate-900', 'border-emerald-700');
                    document.getElementById('pot').innerText = Number(res.pot).toLocaleString('vi-VN');
                }
            } finally {
                isProcessing = false;
            }
        }

        async function cashout() {
            if (!isPlaying || isProcessing) return;
            isProcessing = true;
            isPlaying = false; // Dừng game ngay lập tức để chặn bấm tiếp mìn

            const fd = new FormData();
            fd.append('action', 'cashout');

            try {
                const res = await fetch('process_mines.php', {
                    method: 'POST',
                    body: fd
                }).then(r => r.json());

                if (res.success) {
                    document.getElementById('balance').innerText = Number(res.balance).toLocaleString('vi-VN');
                    document.getElementById('msg').innerHTML =
                        `<span class="text-emerald-400">Đã chốt: +${Number(res.winnings).toLocaleString('vi-VN')}đ</span>`;
                    resetUI();
                }
            } finally {
                isProcessing = false;
            }
        }

        function resetUI() {
            isPlaying = false;
            document.getElementById('grid').classList.add('pointer-events-none', 'opacity-50');
            document.getElementById('startBtn').classList.remove('hidden');
            document.getElementById('betAmount').classList.remove('hidden');
            document.getElementById('cashoutBtn').classList.add('hidden');
        }
    </script>
</body>

</html>