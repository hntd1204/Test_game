<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Lấy nhiệm vụ
try {
    $missions = $pdo->query("SELECT * FROM mission_settings")->fetchAll();
} catch (Exception $e) {
    $missions = [];
}

// Lấy lịch sử quay
try {
    $myHistoryStmt = $pdo->prepare("SELECT reward, created_at FROM spin_history WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $myHistoryStmt->execute([$_SESSION['user_id']]);
    $myHistories = $myHistoryStmt->fetchAll();
} catch (Exception $e) {
    $myHistories = [];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vòng Quay May Mắn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
        }

        .number-display {
            font-variant-numeric: tabular-nums;
        }

        /* Hiệu ứng kính mờ (Glassmorphism) */
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Tùy chỉnh thanh cuộn */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        /* Hiệu ứng Vòng quay */
        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 15px rgba(250, 204, 21, 0.4);
                text-shadow: 0 0 10px rgba(250, 204, 21, 0.5);
            }

            50% {
                box-shadow: 0 0 30px rgba(250, 204, 21, 0.8);
                text-shadow: 0 0 20px rgba(250, 204, 21, 0.8);
            }
        }

        .animate-spin-active {
            animation: pulse-glow 1s ease-in-out infinite;
        }

        /* Logic Tab */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Nav active states */
        .nav-item.active {
            color: #3b82f6;
        }

        .nav-item.active .nav-icon-bg {
            background-color: #eff6ff;
        }

        .mobile-nav-item.active {
            color: #3b82f6;
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="text-slate-800 antialiased relative overflow-x-hidden pb-20 md:pb-0">
    <div
        class="fixed top-0 left-0 w-full h-96 bg-gradient-to-b from-blue-600/10 via-purple-600/5 to-transparent -z-10 pointer-events-none hidden md:block">
    </div>

    <header class="glass-panel shadow-sm sticky top-0 z-50 transition-all w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
            <div class="flex items-center gap-2 cursor-pointer" onclick="switchTab('home')">
                <div
                    class="w-10 h-10 bg-gradient-to-tr from-blue-600 to-purple-600 rounded-xl shadow-md flex items-center justify-center text-white font-black text-lg">
                    <i class="fa-solid fa-dharmachakra"></i>
                </div>
                <h1
                    class="text-xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-blue-700 to-purple-700 tracking-tight hidden sm:block">
                    LuckySpin
                </h1>
            </div>

            <nav class="hidden md:flex items-center gap-1 bg-slate-100/50 p-1 rounded-2xl border border-slate-200">
                <button onclick="switchTab('home')"
                    class="nav-item active px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2">
                    <div class="nav-icon-bg p-1.5 rounded-lg transition-colors"><i class="fa-solid fa-house"></i></div>
                    Trang Chủ
                </button>
                <button onclick="switchTab('games')"
                    class="nav-item px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2">
                    <div class="nav-icon-bg p-1.5 rounded-lg transition-colors"><i class="fa-solid fa-gamepad"></i>
                    </div> Trò Chơi
                </button>
                <button onclick="switchTab('missions')"
                    class="nav-item px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2">
                    <div class="nav-icon-bg p-1.5 rounded-lg transition-colors"><i class="fa-solid fa-bullseye"></i>
                    </div> Nhiệm Vụ
                </button>
                <button onclick="switchTab('store')"
                    class="nav-item px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2">
                    <div class="nav-icon-bg p-1.5 rounded-lg transition-colors"><i class="fa-solid fa-store"></i></div>
                    Cửa Hàng
                </button>
            </nav>

            <div class="flex items-center gap-3">
                <div class="flex flex-col items-end hidden sm:flex">
                    <span class="text-xs font-bold text-slate-400 uppercase">Số dư</span>
                    <span class="text-sm font-extrabold text-blue-600"><span
                            class="user-balance"><?= number_format($user['balance']) ?></span>đ</span>
                </div>
                <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
                <div
                    class="flex items-center gap-2 bg-white px-2 py-1.5 rounded-full border border-slate-200 shadow-sm cursor-pointer hover:bg-slate-50 transition">
                    <div
                        class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span
                        class="text-slate-700 font-bold text-sm pr-1 truncate max-w-[100px]"><?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
                <a href="logout.php" title="Đăng xuất"
                    class="w-10 h-10 flex items-center justify-center bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white rounded-full transition-all shadow-sm">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 md:mt-8 min-h-[70vh]">

        <div id="tab-home" class="tab-content active">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-8">
                <div
                    class="bg-gradient-to-br from-blue-600 to-indigo-700 p-6 rounded-3xl shadow-lg shadow-blue-900/20 text-white relative overflow-hidden group">
                    <div class="relative z-10">
                        <h3
                            class="text-blue-200 text-sm font-bold uppercase tracking-widest mb-1 flex items-center gap-2">
                            <i class="fa-solid fa-wallet"></i> Số dư khả dụng
                        </h3>
                        <div class="flex items-baseline gap-1">
                            <span
                                class="user-balance text-4xl sm:text-5xl font-black tracking-tight"><?= number_format($user['balance']) ?></span>
                            <span class="text-lg font-bold text-blue-200">VNĐ</span>
                        </div>
                    </div>
                    <i
                        class="fa-solid fa-coins absolute -right-4 -bottom-4 text-8xl opacity-10 group-hover:scale-110 transition-transform duration-500"></i>
                </div>

                <div
                    class="bg-gradient-to-br from-purple-600 to-fuchsia-700 p-6 rounded-3xl shadow-lg shadow-purple-900/20 text-white relative overflow-hidden group">
                    <div class="relative z-10 flex justify-between items-center">
                        <div>
                            <h3
                                class="text-purple-200 text-sm font-bold uppercase tracking-widest mb-1 flex items-center gap-2">
                                <i class="fa-solid fa-ticket"></i> Lượt quay hiện có
                            </h3>
                            <div class="user-spins text-4xl sm:text-5xl font-black tracking-tight">
                                <?= $user['spins_available'] ?></div>
                        </div>
                        <div
                            class="bg-white/20 backdrop-blur-sm px-4 py-3 rounded-2xl border border-white/20 text-center">
                            <i class="fa-solid fa-arrow-down text-xl animate-bounce"></i>
                        </div>
                    </div>
                    <i
                        class="fa-solid fa-dharmachakra absolute -right-4 -bottom-4 text-8xl opacity-10 group-hover:rotate-45 transition-transform duration-500"></i>
                </div>
            </div>

            <div
                class="bg-white p-6 sm:p-10 md:p-12 rounded-[2.5rem] shadow-xl border border-slate-100 text-center relative overflow-hidden mb-8">
                <div
                    class="absolute top-0 left-0 w-full h-full bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-blue-50 via-transparent to-transparent pointer-events-none">
                </div>

                <h2 class="relative text-2xl md:text-3xl font-black text-slate-800 mb-2">Thử Vận May Hôm Nay</h2>
                <p class="relative text-slate-500 text-sm font-medium mb-8">Nhấn nút bên dưới để quay thưởng, cơ hội
                    nhận hàng triệu VNĐ!</p>

                <div class="relative max-w-lg mx-auto">
                    <div
                        class="bg-slate-900 rounded-[2rem] p-6 sm:p-10 shadow-[inset_0_-8px_0_rgba(0,0,0,0.5)] border-4 border-slate-800 relative z-10">
                        <div id="spinningNumber"
                            class="number-display text-4xl sm:text-5xl md:text-6xl font-black text-slate-300 tracking-widest transition-colors duration-300">
                            000,000 đ
                        </div>
                    </div>

                    <div class="mt-8">
                        <button id="spinBtn"
                            class="group w-full sm:w-auto relative inline-flex items-center justify-center px-10 sm:px-16 py-4 sm:py-5 text-xl font-black text-white transition-all duration-200 bg-gradient-to-b from-amber-400 to-orange-600 rounded-full hover:from-amber-300 hover:to-orange-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-[0_8px_0_#c2410c] active:shadow-[0_0px_0_#c2410c] active:translate-y-[8px] uppercase tracking-widest"
                            <?= $user['spins_available'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-play mr-2"></i> BẮT ĐẦU QUAY
                        </button>
                    </div>
                </div>

                <div id="resultMsg"
                    class="mt-8 min-h-[40px] text-lg sm:text-2xl font-black transition-all duration-300 flex items-center justify-center">
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-700 uppercase"><i
                            class="fa-solid fa-clock-rotate-left mr-2"></i> Lịch sử quay thưởng</h3>
                </div>
                <div class="p-4 overflow-x-auto custom-scrollbar">
                    <div class="flex gap-4">
                        <?php foreach ($myHistories as $h): ?>
                            <div
                                class="min-w-[140px] bg-green-50 border border-green-100 p-3 rounded-2xl flex flex-col items-center justify-center">
                                <span
                                    class="text-[10px] text-green-600 font-bold mb-1"><?= date('H:i d/m', strtotime($h['created_at'])) ?></span>
                                <span class="text-lg font-black text-green-600">+<?= number_format($h['reward']) ?>đ</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($myHistories) == 0): ?>
                            <div class="text-sm text-slate-400 italic py-4 w-full text-center">Chưa có lịch sử quay nào.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-games" class="tab-content">
            <div class="flex items-center gap-3 mb-6 px-2">
                <div
                    class="w-10 h-10 bg-slate-800 text-white rounded-xl flex items-center justify-center text-lg shadow-md">
                    <i class="fa-solid fa-gamepad"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800">Khu Vực Giải Trí</h2>
                    <p class="text-sm text-slate-500 font-medium">Chơi game nhân phẩm, rinh tiền thưởng thật!</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    class="bg-white rounded-[2rem] p-6 shadow-lg border border-slate-100 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 flex flex-col h-full group">
                    <div
                        class="w-16 h-16 rounded-2xl bg-gradient-to-br from-red-500 to-orange-500 flex items-center justify-center text-white text-3xl mb-5 shadow-lg shadow-red-500/30 group-hover:rotate-12 transition-transform">
                        <i class="fa-solid fa-dice"></i>
                    </div>
                    <h4 class="text-xl font-black text-slate-800 mb-2">Bầu Cua Tôm Cá</h4>
                    <p class="text-sm text-slate-500 font-medium mb-8 flex-1">Trò chơi dân gian với tỉ lệ trả thưởng cực
                        cao. Đặt cược thả ga, nhận tiền ngay lập tức!</p>
                    <a href="baucua.php"
                        class="block w-full text-center bg-red-50 hover:bg-red-500 hover:text-white text-red-600 font-bold py-3.5 rounded-xl transition-colors border border-red-100 hover:border-red-500">Chơi
                        Ngay</a>
                </div>

                <div
                    class="bg-white rounded-[2rem] p-6 shadow-lg border border-slate-100 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 flex flex-col h-full group">
                    <div
                        class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white text-3xl mb-5 shadow-lg shadow-emerald-500/30 group-hover:rotate-12 transition-transform">
                        <i class="fa-solid fa-diamond"></i>
                    </div>
                    <h4 class="text-xl font-black text-slate-800 mb-2">Xì Dách Hoàng Gia</h4>
                    <p class="text-sm text-slate-500 font-medium mb-8 flex-1">Đấu trí 1-1 với nhà cái. Khéo léo rút bài
                        để đạt 21 điểm hoặc Ngũ Linh để x2 tiền thưởng.</p>
                    <a href="blackjack.php"
                        class="block w-full text-center bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 font-bold py-3.5 rounded-xl transition-colors border border-emerald-100 hover:border-emerald-600">Thử
                        Thách</a>
                </div>

                <div
                    class="bg-white rounded-[2rem] p-6 shadow-lg border border-slate-100 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 flex flex-col h-full group">
                    <div
                        class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white text-3xl mb-5 shadow-lg shadow-indigo-500/30 group-hover:rotate-12 transition-transform">
                        <i class="fa-solid fa-arrow-down-up-across-line"></i>
                    </div>
                    <h4 class="text-xl font-black text-slate-800 mb-2">Lật Bài Hi-Lo</h4>
                    <p class="text-sm text-slate-500 font-medium mb-8 flex-1">Cao hay thấp hơn? Dự đoán chính xác lá bài
                        tiếp theo để nhận ngay +10.000 VNĐ vào tài khoản.</p>
                    <a href="hilo.php"
                        class="block w-full text-center bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 font-bold py-3.5 rounded-xl transition-colors border border-indigo-100 hover:border-indigo-600">Đoán
                        Ngay</a>
                </div>
            </div>
        </div>

        <div id="tab-missions" class="tab-content">
            <div class="bg-white p-6 sm:p-10 rounded-[2.5rem] shadow-lg border border-slate-100 max-w-4xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 bg-amber-100 text-amber-500 rounded-2xl flex items-center justify-center text-2xl shadow-inner">
                            <i class="fa-solid fa-bullseye"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-extrabold text-slate-800">Nhiệm Vụ Hàng Ngày</h2>
                            <p class="text-sm text-slate-500">Hoàn thành để nhận thêm lượt quay miễn phí</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <?php foreach ($missions as $ms):
                        $currentProgress = isset($user[$ms['mission_key']]) ? (int)$user[$ms['mission_key']] : 0;
                        $percent = min(100, ($currentProgress / $ms['target_count']) * 100);
                        $isCompleted = $percent == 100;
                    ?>
                        <div
                            class="p-5 rounded-2xl border-2 <?= $isCompleted ? 'bg-green-50/50 border-green-200' : 'bg-slate-50 border-slate-100' ?> relative overflow-hidden group transition-all hover:shadow-md">
                            <div class="absolute top-0 left-0 h-full bg-blue-500/5 transition-all duration-1000 -z-10"
                                style="width: <?= $percent ?>%"></div>

                            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-4">
                                <div>
                                    <h4 class="text-base font-extrabold text-slate-800 mb-1">
                                        <?= htmlspecialchars($ms['mission_name']) ?></h4>
                                    <span
                                        class="text-xs font-black text-amber-600 bg-amber-100 px-3 py-1 rounded-full border border-amber-200">
                                        <i class="fa-solid fa-gift mr-1"></i> +<?= $ms['reward_spins'] ?> Lượt quay
                                    </span>
                                </div>
                                <div class="text-right flex items-center justify-between sm:block">
                                    <span class="text-xs font-bold text-slate-500 sm:block mb-1">Tiến độ</span>
                                    <span
                                        class="text-sm font-black <?= $isCompleted ? 'text-green-600 bg-green-100' : 'text-blue-600 bg-white' ?> px-3 py-1.5 rounded-xl shadow-sm border border-slate-200 block">
                                        <?= $isCompleted ? '<i class="fa-solid fa-check"></i> Hoàn thành' : $currentProgress . ' / ' . $ms['target_count'] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden">
                                <div class="<?= $isCompleted ? 'bg-green-500' : 'bg-gradient-to-r from-blue-500 to-purple-500' ?> h-full rounded-full transition-all duration-1000"
                                    style="width: <?= $percent ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($missions)): ?>
                        <div
                            class="text-center py-12 border-2 border-dashed border-slate-200 rounded-3xl text-slate-400 font-medium flex flex-col items-center">
                            <i class="fa-solid fa-box-open text-4xl mb-3 text-slate-300"></i>
                            <p>Admin chưa cập nhật nhiệm vụ nào hôm nay.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="tab-store" class="tab-content">
            <div class="grid lg:grid-cols-2 gap-8">
                <div
                    class="bg-white p-6 sm:p-8 rounded-[2.5rem] shadow-xl border border-slate-100 flex flex-col h-full relative overflow-hidden">
                    <div
                        class="absolute top-0 right-0 w-40 h-40 bg-emerald-500/5 rounded-bl-[100px] pointer-events-none">
                    </div>

                    <div class="flex items-center gap-4 mb-8">
                        <div
                            class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl shadow-inner">
                            <i class="fa-solid fa-store"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-extrabold text-slate-800">Cửa Hàng</h2>
                            <p class="text-sm text-slate-500 font-medium">Mua vật phẩm bằng số dư</p>
                        </div>
                    </div>

                    <div class="space-y-4 flex-1">
                        <button onclick="buyAction('buy_spin')"
                            class="w-full flex justify-between items-center bg-white hover:bg-orange-50 p-4 rounded-2xl border-2 border-slate-100 hover:border-orange-200 transition-all active:scale-95 group shadow-sm">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white text-lg shadow-md">
                                    <i class="fa-solid fa-ticket"></i>
                                </div>
                                <span class="font-extrabold text-slate-700 text-lg">1 Lượt Quay</span>
                            </div>
                            <span class="text-orange-600 font-black text-lg">50.000đ</span>
                        </button>

                        <?php try {
                            $shopStmt = $pdo->query("SELECT * FROM shop_items WHERE is_active = 1 ORDER BY cost ASC");
                            while ($item = $shopStmt->fetch()): ?>
                                <button
                                    onclick="buyAction('buy_gift', <?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>', <?= $item['cost'] ?>)"
                                    class="w-full flex justify-between items-center bg-white hover:bg-emerald-50 p-4 rounded-2xl border-2 border-slate-100 hover:border-emerald-200 transition-all active:scale-95 group shadow-sm">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white text-lg shadow-md">
                                            <i class="fa-solid fa-gift"></i>
                                        </div>
                                        <span
                                            class="font-extrabold text-slate-700 text-lg text-left"><?= htmlspecialchars($item['name']) ?></span>
                                    </div>
                                    <span
                                        class="text-emerald-600 font-black text-lg whitespace-nowrap"><?= number_format($item['cost']) ?>đ</span>
                                </button>
                        <?php endwhile;
                        } catch (Exception $e) {
                        } ?>
                    </div>

                    <div class="mt-8 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <p class="text-xs font-black text-slate-500 mb-3 uppercase tracking-widest"><i
                                class="fa-solid fa-clock-rotate-left"></i> Lịch sử đổi quà</p>
                        <div class="space-y-2 max-h-[100px] overflow-y-auto custom-scrollbar pr-2">
                            <?php try {
                                $giftStmt = $pdo->prepare("SELECT gift_name, status FROM user_gifts WHERE user_id = ? ORDER BY id DESC LIMIT 5");
                                $giftStmt->execute([$_SESSION['user_id']]);
                                $gifts = $giftStmt->fetchAll();
                                if (count($gifts) > 0) {
                                    foreach ($gifts as $g) {
                                        $statusBadge = $g['status'] == 'pending' ? '<span class="text-amber-500 bg-amber-50 px-2 py-1 rounded-md text-[10px] font-bold">Chờ xử lý</span>' : ($g['status'] == 'completed' ? '<span class="text-green-600 bg-green-50 px-2 py-1 rounded-md text-[10px] font-bold">Thành công</span>' : '<span class="text-rose-600 bg-rose-50 px-2 py-1 rounded-md text-[10px] font-bold">Từ chối</span>');
                                        echo '<div class="text-sm flex justify-between items-center py-2 border-b border-slate-200/50 last:border-0"><span class="text-slate-600 font-bold">' . htmlspecialchars($g['gift_name']) . '</span>' . $statusBadge . '</div>';
                                    }
                                } else {
                                    echo '<p class="text-xs text-slate-400 italic">Chưa có giao dịch</p>';
                                }
                            } catch (Exception $e) {
                            } ?>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-slate-900 p-6 sm:p-8 rounded-[2.5rem] shadow-xl text-white flex flex-col relative overflow-hidden">
                    <div
                        class="absolute top-0 right-0 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none">
                    </div>

                    <div class="flex items-center gap-4 mb-8">
                        <div
                            class="w-12 h-12 bg-blue-500/20 text-blue-400 border border-blue-500/30 rounded-2xl flex items-center justify-center text-2xl shadow-inner">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-extrabold">Rút Tiền Về Bank</h2>
                            <p class="text-sm text-slate-400 font-medium">Tối thiểu: 10.000 VNĐ</p>
                        </div>
                    </div>

                    <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 mb-6">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Số tiền
                            muốn rút</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-4 text-slate-400 font-black">đ</span>
                            <input type="number" id="withdrawAmount" placeholder="VD: 50000"
                                class="w-full pl-10 pr-4 py-4 rounded-xl bg-slate-900 border-2 border-slate-700 focus:border-blue-500 focus:ring-0 font-black text-white text-lg placeholder-slate-600 transition-all outline-none">
                        </div>
                    </div>

                    <button onclick="requestWithdraw()"
                        class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl shadow-lg shadow-blue-900/50 active:scale-95 transition-all uppercase tracking-widest text-lg flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Gửi Yêu Cầu
                    </button>

                    <div class="mt-8 pt-6 border-t border-slate-800 flex-1">
                        <p class="text-[10px] font-black text-slate-500 mb-3 uppercase tracking-widest"><i
                                class="fa-solid fa-clock-rotate-left"></i> Giao dịch rút tiền</p>
                        <div class="space-y-2 max-h-[150px] overflow-y-auto custom-scrollbar pr-2">
                            <?php try {
                                $wdStmt = $pdo->prepare("SELECT amount, status FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 5");
                                $wdStmt->execute([$_SESSION['user_id']]);
                                $withdrawals = $wdStmt->fetchAll();
                                if (count($withdrawals) > 0) {
                                    foreach ($withdrawals as $w) {
                                        $statusBadge = $w['status'] == 'pending' ? '<span class="text-amber-400 border border-amber-400/30 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-400/10">Chờ duyệt</span>' : ($w['status'] == 'approved' ? '<span class="text-green-400 border border-green-400/30 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-green-400/10">Hoàn tất</span>' : '<span class="text-rose-400 border border-rose-400/30 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-400/10">Từ chối</span>');
                                        echo '<div class="text-sm flex justify-between items-center py-2 border-b border-slate-700/50 last:border-0"><span class="font-bold text-slate-200">' . number_format($w['amount']) . 'đ</span>' . $statusBadge . '</div>';
                                    }
                                } else {
                                    echo '<p class="text-xs text-slate-500 italic">Chưa có giao dịch</p>';
                                }
                            } catch (Exception $e) {
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <footer class="max-w-7xl mx-auto px-4 py-8 mt-12 border-t border-slate-200 text-center hidden md:block">
        <p class="text-slate-500 text-sm font-medium">© 2024 Vòng Quay May Mắn. Chúc bạn chơi game vui vẻ!</p>
    </footer>

    <nav class="md:hidden glass-panel fixed bottom-0 w-full z-50 border-t border-slate-200 pb-safe">
        <div class="flex justify-around items-center h-16 px-2">
            <button onclick="switchTab('home')"
                class="mobile-nav-item active flex flex-col items-center justify-center w-1/4 text-slate-400 hover:text-blue-600 transition-all">
                <i class="fa-solid fa-house text-xl mb-1"></i>
                <span class="text-[10px] font-bold">Trang Chủ</span>
            </button>
            <button onclick="switchTab('games')"
                class="mobile-nav-item flex flex-col items-center justify-center w-1/4 text-slate-400 hover:text-blue-600 transition-all">
                <i class="fa-solid fa-gamepad text-xl mb-1"></i>
                <span class="text-[10px] font-bold">Trò Chơi</span>
            </button>
            <button onclick="switchTab('missions')"
                class="mobile-nav-item flex flex-col items-center justify-center w-1/4 text-slate-400 hover:text-blue-600 transition-all">
                <i class="fa-solid fa-bullseye text-xl mb-1"></i>
                <span class="text-[10px] font-bold">Nhiệm Vụ</span>
            </button>
            <button onclick="switchTab('store')"
                class="mobile-nav-item flex flex-col items-center justify-center w-1/4 text-slate-400 hover:text-blue-600 transition-all">
                <i class="fa-solid fa-store text-xl mb-1"></i>
                <span class="text-[10px] font-bold">Cửa Hàng</span>
            </button>
        </div>
    </nav>

    <script>
        // Hệ thống chuyển Tab mượt mà
        function switchTab(tabId) {
            // Ẩn tất cả nội dung
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Xóa active class ở tất cả nút nav
            document.querySelectorAll('.nav-item').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.mobile-nav-item').forEach(btn => btn.classList.remove('active'));

            // Hiển thị nội dung mới
            document.getElementById('tab-' + tabId).classList.add('active');

            // Cập nhật trạng thái active cho nút nav (Dựa vào thuộc tính onclick)
            document.querySelectorAll(`button[onclick="switchTab('${tabId}')"]`).forEach(btn => {
                btn.classList.add('active');
            });

            // Cuộn lên đầu trang nhẹ nhàng
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

            // Lưu trạng thái vào localStorage
            localStorage.setItem('activeUserTab', tabId);
        }

        // Khôi phục Tab sau khi F5
        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('activeUserTab') || 'home';
            switchTab(savedTab);
        });

        // Âm thanh
        const sounds = {
            spin: new Audio('https://www.soundjay.com/misc/sounds/mechanical-clonk-1.mp3'),
            win: new Audio('https://www.soundjay.com/misc/sounds/bell-ringing-05.mp3'),
            error: new Audio('https://www.soundjay.com/buttons/button-10.mp3')
        };

        // Logic Vòng Quay
        document.getElementById('spinBtn').addEventListener('click', async function() {
            const btn = this;
            const msg = document.getElementById('resultMsg');
            const numberDisplay = document.getElementById('spinningNumber');
            const numberContainer = numberDisplay.parentElement;

            btn.disabled = true;
            msg.innerText = "";
            numberDisplay.classList.remove('text-green-400', 'scale-110');
            numberDisplay.classList.add('text-amber-400');
            numberContainer.classList.add('animate-spin-active');

            sounds.spin.play();
            sounds.spin.loop = true;

            let spinInterval = setInterval(() => {
                const randomVisualNum = Math.floor(Math.random() * 100) * 1000 + 1000;
                numberDisplay.innerText = randomVisualNum.toLocaleString() + " đ";
            }, 50);

            try {
                const response = await fetch('process_spin.php');
                const data = await response.json();

                setTimeout(() => {
                    clearInterval(spinInterval);
                    sounds.spin.loop = false;
                    sounds.spin.pause();
                    numberContainer.classList.remove('animate-spin-active');

                    if (data.success) {
                        sounds.win.play();
                        numberDisplay.innerText = data.reward.toLocaleString() + " đ";
                        numberDisplay.classList.remove('text-amber-400');
                        numberDisplay.classList.add('text-green-400', 'scale-110');

                        msg.innerHTML = "🎉 Tuyệt vời! Bạn trúng <span class='text-rose-500 ml-2'>" +
                            data.reward.toLocaleString() + " đ</span>";
                        msg.className =
                            "mt-8 min-h-[40px] text-xl sm:text-2xl font-black text-slate-800 animate-bounce flex items-center justify-center drop-shadow-sm";

                        updateUI(data.new_balance, data.spins_left);

                        if (data.spins_left > 0) btn.disabled = false;
                    } else {
                        sounds.error.play();
                        numberDisplay.classList.remove('text-amber-400');
                        numberDisplay.innerText = "0 đ";
                        msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-2"></i> ' + data
                            .error;
                        msg.className =
                            "mt-8 min-h-[40px] text-lg sm:text-xl font-bold text-rose-500 flex items-center justify-center";
                    }
                }, 1500);

            } catch (err) {
                clearInterval(spinInterval);
                sounds.spin.pause();
                numberContainer.classList.remove('animate-spin-active');
                numberDisplay.innerText = "LỖI";
                msg.innerText = "Có lỗi xảy ra, thử lại sau.";
                msg.className =
                    "mt-8 min-h-[40px] text-lg font-bold text-rose-500 flex items-center justify-center";
                btn.disabled = false;
            }
        });

        // Rút Tiền
        async function requestWithdraw() {
            const amount = document.getElementById('withdrawAmount').value;
            if (!amount || amount < 10000) return alert("Vui lòng nhập số tiền hợp lệ (Tối thiểu 10,000đ)!");
            if (!confirm(`Bạn chắc chắn muốn gửi yêu cầu rút ${Number(amount).toLocaleString()} VNĐ?`)) return;

            const formData = new FormData();
            formData.append('action', 'withdraw');
            formData.append('amount', amount);
            await sendAction(formData);
            setTimeout(() => location.reload(), 1500);
        }

        // Mua Lượt / Đổi Quà
        async function buyAction(actionName, itemId = null, giftName = '', cost = 0) {
            let msg = actionName === 'buy_spin' ? "Xác nhận mua 1 Lượt Quay với giá 50.000 VNĐ?" :
                `Xác nhận đổi [${giftName}] với giá ${Number(cost).toLocaleString()} VNĐ?`;
            if (!confirm(msg)) return;

            const formData = new FormData();
            formData.append('action', actionName);
            if (itemId) formData.append('item_id', itemId);
            await sendAction(formData);
        }

        // Gửi API và Cập nhật UI
        async function sendAction(formData) {
            try {
                const res = await fetch('user_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert("🎉 " + data.message);
                    updateUI(data.new_balance, data.spins_left);
                } else {
                    alert("❌ Lỗi: " + data.error);
                }
            } catch (err) {
                alert("Lỗi kết nối đến máy chủ!");
            }
        }

        // Cập nhật số dư & số lượt trên toàn bộ trang
        function updateUI(balance, spins) {
            document.querySelectorAll('.user-balance').forEach(el => el.innerText = balance.toLocaleString());
            if (spins !== undefined) {
                document.querySelectorAll('.user-spins').forEach(el => el.innerText = spins);
                if (spins > 0) document.getElementById('spinBtn').disabled = false;
            }
        }
    </script>
</body>

</html>