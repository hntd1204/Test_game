<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user = $pdo->query("SELECT balance FROM users WHERE id = {$_SESSION['user_id']}")->fetch();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Dò Mìn (Mines)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-900 text-white min-h-screen p-4 flex flex-col items-center">
    <div class="w-full max-w-md flex justify-between items-center mb-6 bg-slate-800 p-4 rounded-xl">
        <a href="dashboard.php" class="text-blue-400 font-bold">⬅ Quay lại</a>
        <div class="font-bold text-amber-400">💰 <span id="balance"><?= number_format($user['balance']) ?></span>đ</div>
    </div>

    <div class="w-full max-w-md bg-slate-800 p-6 rounded-3xl shadow-2xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Dò Mìn</h2>
            <div class="text-sm font-bold text-emerald-400">Lãi: <span id="pot">0</span>đ</div>
        </div>

        <div id="grid" class="grid grid-cols-5 gap-2 mb-6 pointer-events-none opacity-50">
            <?php for ($i = 0; $i < 25; $i++): ?>
                <button onclick="openTile(<?= $i ?>, this)"
                    class="tile aspect-square bg-slate-700 rounded-lg shadow font-black text-2xl hover:bg-slate-600 transition flex items-center justify-center"></button>
            <?php endfor; ?>
        </div>

        <div id="controls" class="space-y-4">
            <select id="betAmount"
                class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold">
                <option value="10000">Cược 10.000đ</option>
                <option value="50000">Cược 50.000đ</option>
                <option value="100000">Cược 100.000đ</option>
            </select>
            <button id="startBtn" onclick="startGame()"
                class="w-full bg-blue-600 hover:bg-blue-500 py-3 rounded-xl font-black shadow-lg">BẮT ĐẦU</button>
            <button id="cashoutBtn" onclick="cashout()"
                class="w-full bg-emerald-500 hover:bg-emerald-400 py-3 rounded-xl font-black shadow-lg hidden">💰 CHỐT
                LỜI NHẬN TIỀN</button>
        </div>
        <div id="msg" class="mt-4 text-center font-bold h-6"></div>
    </div>

    <script>
        let isPlaying = false;

        async function startGame() {
            const bet = document.getElementById('betAmount').value;
            const fd = new FormData();
            fd.append('action', 'start');
            fd.append('bet', bet);
            const res = await fetch('process_mines.php', {
                method: 'POST',
                body: fd
            }).then(r => r.json());

            if (!res.success) return alert(res.error);

            isPlaying = true;
            document.getElementById('balance').innerText = res.balance.toLocaleString();
            document.getElementById('pot').innerText = res.pot.toLocaleString();
            document.getElementById('startBtn').classList.add('hidden');
            document.getElementById('betAmount').classList.add('hidden');
            document.getElementById('cashoutBtn').classList.remove('hidden');
            document.getElementById('msg').innerHTML = '';

            const grid = document.getElementById('grid');
            grid.classList.remove('pointer-events-none', 'opacity-50');
            document.querySelectorAll('.tile').forEach(t => {
                t.innerHTML = '';
                t.classList.remove('bg-emerald-500', 'bg-rose-500');
                t.disabled = false;
            });
        }

        async function openTile(index, btn) {
            if (!isPlaying || btn.disabled) return;
            const fd = new FormData();
            fd.append('action', 'open');
            fd.append('index', index);
            const res = await fetch('process_mines.php', {
                method: 'POST',
                body: fd
            }).then(r => r.json());

            btn.disabled = true;
            if (res.is_bomb) {
                isPlaying = false;
                btn.innerHTML = '💣';
                btn.classList.add('bg-rose-500');
                document.getElementById('msg').innerHTML =
                    '<span class="text-rose-500">BÙM! Bạn đã đạp trúng mìn!</span>';
                resetUI();
            } else {
                btn.innerHTML = '💎';
                btn.classList.add('bg-emerald-500');
                document.getElementById('pot').innerText = res.pot.toLocaleString();
            }
        }

        async function cashout() {
            if (!isPlaying) return;
            const fd = new FormData();
            fd.append('action', 'cashout');
            const res = await fetch('process_mines.php', {
                method: 'POST',
                body: fd
            }).then(r => r.json());
            if (res.success) {
                document.getElementById('balance').innerText = res.balance.toLocaleString();
                document.getElementById('msg').innerHTML =
                    `<span class="text-emerald-400">Chốt lời thành công: +${res.winnings.toLocaleString()}đ</span>`;
                resetUI();
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