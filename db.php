<?php
// Thiết lập múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host = 'localhost';
$dbname = 'Random_quay'; // Đổi thành tên DB của bạn
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ĐÃ BỎ LOGIC RESET SỐ DƯ VỀ 0 MỖI NGÀY Ở ĐÂY

} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

// --- THÊM LOGIC RESET NHIỆM VỤ QUA NGÀY MỚI ---
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'user') {
    $uid = $_SESSION['user_id'];
    $today = date('Y-m-d');

    // 1. Tự động tạo cột last_reset_date nếu chưa có
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_reset_date DATE DEFAULT NULL");
    } catch (Exception $e) {
        // Bỏ qua lỗi nếu cột đã tồn tại
    }

    // 2. Kiểm tra xem hôm nay đã reset chưa
    try {
        $stmt = $pdo->prepare("SELECT last_reset_date FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $last_reset = $stmt->fetchColumn();

        if ($last_reset !== $today) {
            // Lấy các mã nhiệm vụ hiện có trong db
            $missions = $pdo->query("SELECT mission_key FROM mission_settings")->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($missions)) {
                $setClause = "";
                foreach ($missions as $key) {
                    $setClause .= "`$key` = 0, "; // Reset số lần chơi về 0
                }
                $setClause .= "last_reset_date = ?"; // Cập nhật ngày reset là hôm nay

                $pdo->prepare("UPDATE users SET $setClause WHERE id = ?")->execute([$today, $uid]);
            } else {
                // Nếu không có nhiệm vụ nào, chỉ cập nhật ngày
                $pdo->prepare("UPDATE users SET last_reset_date = ? WHERE id = ?")->execute([$today, $uid]);
            }
        }
    } catch (Exception $e) {
        // Tránh gián đoạn game nếu lỗi truy vấn
    }
}
