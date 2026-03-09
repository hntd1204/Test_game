<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Coffee Tycoon - Barista Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
</head>

<body class="bg-stone-100 font-sans overflow-hidden">
    <nav class="bg-white shadow-md p-4 flex justify-between items-center px-10">
        <h1 class="text-2xl font-black text-amber-800">☕ COFFEE TYCOON</h1>
        <div class="flex items-center gap-6">
            <div class="bg-amber-100 px-4 py-2 rounded-full font-bold text-amber-800">
                ⭐ Điểm: <span id="user-points"><?= $_SESSION['points'] ?></span>
            </div>
            <a href="auth/logout.php" class="text-red-500 font-bold hover:underline">Thoát</a>
        </div>
    </nav>

    [Image of a coffee shop game UI with customer and bar counter]

    <main
        class="container mx-auto mt-5 relative h-[80vh] bg-sky-100 rounded-[3rem] border-8 border-stone-800 shadow-2xl overflow-hidden">

        <div id="customer-zone" class="h-1/2 flex justify-center items-end pb-10 relative">
            <div id="customer-wrap" class="hidden flex-col items-center animate__animated">
                <div class="w-24 h-2 bg-gray-300 rounded-full mb-3 overflow-hidden">
                    <div id="patience-bar" class="h-full bg-green-500 w-full transition-all"></div>
                </div>
                <div class="relative">
                    <img id="customer-img" src="" class="w-32 h-32 drop-shadow-lg">
                    <div
                        class="absolute -top-10 -right-20 bg-white p-3 rounded-2xl shadow-xl border-2 border-stone-800 min-w-[120px]">
                        <p class="text-xs font-bold text-gray-400">ORDER:</p>
                        <p id="order-text" class="text-lg font-black text-amber-900">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="absolute bottom-0 w-full h-1/2 bg-stone-800 p-8 flex justify-around items-center border-t-8 border-amber-900">
            <div id="cup"
                class="w-28 h-40 border-x-4 border-b-4 border-white/30 rounded-b-3xl flex flex-col-reverse p-2 gap-1 bg-white/10 shadow-inner">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <button onclick="addIng('Sữa')"
                    class="bg-blue-50 p-4 rounded-2xl hover:scale-105 active:scale-95 transition-all shadow-lg font-bold">🥛
                    SỮA</button>
                <button onclick="addIng('Cafe')"
                    class="bg-stone-700 text-white p-4 rounded-2xl hover:scale-105 active:scale-95 transition-all shadow-lg font-bold">☕
                    CAFE</button>
                <button onclick="addIng('Đá')"
                    class="bg-cyan-50 p-4 rounded-2xl hover:scale-105 active:scale-95 transition-all shadow-lg font-bold">🧊
                    ĐÁ</button>
                <button onclick="addIng('Đường')"
                    class="bg-yellow-50 p-4 rounded-2xl hover:scale-105 active:scale-95 transition-all shadow-lg font-bold">🍬
                    ĐƯỜNG</button>
            </div>

            <div class="flex flex-col gap-4">
                <button id="btn-next" onclick="spawnCustomer()"
                    class="bg-green-500 text-white px-8 py-3 rounded-xl font-black text-lg hover:bg-green-600 shadow-lg">KHÁCH
                    TIẾP</button>
                <button onclick="serve()"
                    class="bg-amber-600 text-white px-8 py-3 rounded-xl font-black text-lg hover:bg-amber-700 shadow-lg">PHỤC
                    VỤ ✅</button>
            </div>
        </div>
    </main>

    <script src="assets/js/game.js"></script>
</body>

</html>