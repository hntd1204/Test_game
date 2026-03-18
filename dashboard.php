<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT balance, spins_available FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-8 sm:mb-10">
            <div
                class="bg-gradient-to-br from-blue-500 to-blue-700 p-5 sm:p-6 rounded-2xl shadow-lg text-white relative overflow-hidden">
                <h3 class="text-blue-100 text-xs sm:text-sm font-semibold uppercase tracking-wider mb-1 sm:mb-2">Số dư
                    may mắn</h3>
                <div class="text-3xl sm:text-4xl font-bold"><span
                        id="balance"><?= number_format($user['balance']) ?></span> <span
                        class="text-lg sm:text-xl">VNĐ</span></div>
                <p class="text-blue-200 text-[10px] sm:text-xs mt-2 italic">* Số dư reset về 0 mỗi nửa đêm</p>
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
    </main>

    <script>
    // JS không thay đổi, vẫn mượt mà như cũ
    document.getElementById('spinBtn').addEventListener('click', async function() {
        const btn = this;
        const msg = document.getElementById('resultMsg');
        const numberDisplay = document.getElementById('spinningNumber');

        btn.disabled = true;
        msg.innerText = "";
        numberDisplay.classList.remove('text-green-400', 'scale-110');
        numberDisplay.classList.add('text-yellow-400');

        let spinInterval = setInterval(() => {
            const randomVisualNum = Math.floor(Math.random() * 100) * 1000 + 1000;
            numberDisplay.innerText = randomVisualNum.toLocaleString() + " VNĐ";
        }, 50);

        try {
            const response = await fetch('process_spin.php');
            const data = await response.json();

            setTimeout(() => {
                clearInterval(spinInterval);

                if (data.success) {
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
                    numberDisplay.classList.remove('text-yellow-400');
                    numberDisplay.innerText = "0 VNĐ";
                    msg.innerText = "❌ " + data.error;
                    msg.className =
                        "mt-4 sm:mt-6 min-h-[32px] sm:min-h-[40px] text-lg font-bold text-red-500 flex items-center justify-center";
                }
            }, 1500);

        } catch (err) {
            clearInterval(spinInterval);
            numberDisplay.innerText = "LỖI";
            msg.innerText = "Có lỗi xảy ra, thử lại sau.";
            msg.className =
                "mt-4 sm:mt-6 min-h-[32px] sm:min-h-[40px] text-lg font-bold text-red-500 flex items-center justify-center";
            btn.disabled = false;
        }
    });
    </script>
</body>

</html>