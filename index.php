<?php
session_start();
// Nếu chưa đăng nhập, đá về trang login
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiệm Cà Phê Idle - Fullstack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .money-text {
            color: #198754;
            font-weight: bold;
        }

        .btn-main-click {
            background-color: #6f42c1;
            color: white;
            border: none;
        }

        .btn-main-click:hover {
            background-color: #59339d;
            color: white;
        }

        .btn-main-click:active {
            transform: scale(0.98);
        }

        .toast-save {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center bg-white p-4 rounded-4 shadow-sm">

                <h2 class="mb-4 text-primary">☕ Tiệm Cà Phê Của Bạn</h2>

                <div class="mb-4">
                    <h1 class="display-4 money-text"><span id="money">0</span> $</h1>
                    <p class="text-muted fs-5">Thu nhập tự động: <span id="mps" class="fw-bold">0</span> $/giây</p>
                </div>

                <button id="click-btn" class="btn btn-main-click w-100 py-3 fs-4 rounded-3 mb-4 shadow-sm"
                    onclick="makeCoffee()">
                    Pha Cà Phê (+<span id="click-power">1</span>$)
                </button>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="card h-100 bg-light border-0">
                            <div class="card-body">
                                <h5 class="card-title">Máy Pha xịn</h5>
                                <p class="card-text small text-muted">Tăng tiền mỗi click</p>
                                <button id="btn-upgrade-click" class="btn btn-outline-primary w-100"
                                    onclick="buyClickUpgrade()">
                                    Mua (<span id="cost-click">10</span>$)
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card h-100 bg-light border-0">
                            <div class="card-body">
                                <h5 class="card-title">Thuê Nhân Viên</h5>
                                <p class="card-text small text-muted">+2$ tự động/giây</p>
                                <button id="btn-upgrade-auto" class="btn btn-outline-success w-100"
                                    onclick="buyAutoUpgrade()">
                                    Mua (<span id="cost-auto">50</span>$)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-muted small" id="save-status">
                    Đang tải dữ liệu...
                </div>

                <a href="logout.php" class="btn btn-outline-light btn-sm" onclick="saveGame()">Đăng xuất</a>

            </div>
        </div>
    </div>

    <script>
        // 1. Trạng thái Game
        let gameState = {
            money: 0,
            clickPower: 1,
            autoIncome: 0,
            clickUpgradeCost: 10,
            autoUpgradeCost: 50
        };

        // 2. Load Game từ Database qua PHP
        function loadGame() {
            fetch('load.php')
                .then(res => res.json())
                .then(data => {
                    if (!data.error) {
                        gameState.money = parseInt(data.money);
                        gameState.clickPower = parseInt(data.click_power);
                        gameState.autoIncome = parseInt(data.auto_income);
                        gameState.clickUpgradeCost = parseInt(data.click_cost);
                        gameState.autoUpgradeCost = parseInt(data.auto_cost);
                        document.getElementById('save-status').innerText = "✅ Đã tải dữ liệu thành công!";
                    }
                    updateUI();
                })
                .catch(err => console.error("Lỗi khi load game:", err));
        }

        // 3. Save Game lên Database
        function saveGame() {
            fetch('save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(gameState)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        let statusEl = document.getElementById('save-status');
                        statusEl.innerText = "💾 Đã tự động lưu...";
                        setTimeout(() => statusEl.innerText = "", 2000);
                    }
                });
        }

        // 4. Cập nhật Giao diện
        function updateUI() {
            document.getElementById('money').innerText = gameState.money;
            document.getElementById('click-power').innerText = gameState.clickPower;
            document.getElementById('mps').innerText = gameState.autoIncome;
            document.getElementById('cost-click').innerText = gameState.clickUpgradeCost;
            document.getElementById('cost-auto').innerText = gameState.autoUpgradeCost;

            document.getElementById('btn-upgrade-click').disabled = gameState.money < gameState.clickUpgradeCost;
            document.getElementById('btn-upgrade-auto').disabled = gameState.money < gameState.autoUpgradeCost;
        }

        // 5. Logic Game
        function makeCoffee() {
            gameState.money += gameState.clickPower;
            updateUI();
        }

        function buyClickUpgrade() {
            if (gameState.money >= gameState.clickUpgradeCost) {
                gameState.money -= gameState.clickUpgradeCost;
                gameState.clickPower += 1;
                gameState.clickUpgradeCost = Math.floor(gameState.clickUpgradeCost * 1.5);
                updateUI();
                saveGame(); // Lưu ngay khi mua đồ
            }
        }

        function buyAutoUpgrade() {
            if (gameState.money >= gameState.autoUpgradeCost) {
                gameState.money -= gameState.autoUpgradeCost;
                gameState.autoIncome += 2;
                gameState.autoUpgradeCost = Math.floor(gameState.autoUpgradeCost * 1.5);
                updateUI();
                saveGame(); // Lưu ngay khi mua đồ
            }
        }

        // 6. Vòng lặp Game (Auto Income)
        setInterval(() => {
            if (gameState.autoIncome > 0) {
                gameState.money += gameState.autoIncome;
                updateUI();
            }
        }, 1000);

        // 7. Auto Save (Lưu tự động mỗi 5 giây)
        setInterval(saveGame, 5000);

        // Khởi động
        loadGame();
    </script>
</body>

</html>