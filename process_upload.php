<?php
require 'db.php';
header('Content-Type: application/json');

// =========================================================================
// CẤU HÌNH API KEY GEMINI (Lấy tại: [https://aistudio.google.com/](https://aistudio.google.com/))
$gemini_api_key = 'AIzaSyBYDZgPsaaMfXaVcrz8PR3dTV3sXarcVSA';
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['task_image'])) {
    $user_id = $_POST['user_id'];
    $task_id = $_POST['task_id'];

    // 1. Tạo thư mục lưu ảnh (Dùng __DIR__ để luôn lấy đúng đường dẫn tuyệt đối)
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // 2. Cấu hình file upload
    $file_ext = strtolower(pathinfo($_FILES["task_image"]["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $target_file = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES["task_image"]["tmp_name"], $target_file)) {

        // 3. Lấy thông tin Keyword từ Database
        $stmt = $pdo->prepare("SELECT ai_keyword, reward_points FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['status' => 'error', 'message' => 'Nhiệm vụ không tồn tại.']);
            exit;
        }

        // =====================================================================
        // 4. GỌI API GEMINI 1.5 FLASH ĐỂ PHÂN TÍCH ẢNH
        // =====================================================================
        $imageData = base64_encode(file_get_contents($target_file));

        // Lấy mimeType an toàn
        $mimeType = mime_content_type($target_file);
        if (!$mimeType) {
            $mime_types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
            $mimeType = isset($mime_types[$file_ext]) ? $mime_types[$file_ext] : 'image/jpeg';
        }

        $keyword = $task['ai_keyword'];
        $prompt = "Bạn là một AI kiểm duyệt ảnh. Kiểm tra xem ảnh này có chứa yếu tố '$keyword' không. CHỈ trả về một chuỗi JSON chuẩn xác, KHÔNG thêm định dạng Markdown, theo mẫu sau: {\"is_valid\": true, \"reason\": \"Lý do ngắn gọn\"}";

        $url = '[https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=)' . $gemini_api_key;

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                        [
                            "inline_data" => [
                                "mime_type" => $mimeType,
                                "data" => $imageData
                            ]
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "response_mime_type" => "application/json"
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Cần thiết cho XAMPP localhost

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        // =====================================================================
        // 5. XỬ LÝ KẾT QUẢ TỪ AI (Chống lỗi JSON)
        // =====================================================================
        $ai_is_correct = false;
        $ai_reason = "Lỗi kết nối AI.";

        if ($httpcode == 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $gemini_text = $result['candidates'][0]['content']['parts'][0]['text'];

                // MẸO: Dọn dẹp chuỗi JSON phòng trường hợp AI vẫn chèn Markdown (```json)
                $gemini_text = str_replace(['```json', '```', "\n", "\r"], '', $gemini_text);
                $gemini_text = trim($gemini_text);

                $gemini_data = json_decode($gemini_text, true);

                if (is_array($gemini_data) && isset($gemini_data['is_valid'])) {
                    // Chuyển đổi an toàn về kiểu boolean
                    $ai_is_correct = filter_var($gemini_data['is_valid'], FILTER_VALIDATE_BOOLEAN);
                    $ai_reason = isset($gemini_data['reason']) ? $gemini_data['reason'] : "Không có lý do";
                } else {
                    $ai_reason = "AI trả về chuỗi không thể đọc: " . $gemini_text;
                }
            }
        } else {
            $ai_reason = "HTTP $httpcode - " . ($curl_err ? $curl_err : "API Key sai hoặc lỗi mạng.");
        }

        // =====================================================================
        // 6. CẬP NHẬT CƠ SỞ DỮ LIỆU DỰA VÀO PHÁN QUYẾT CỦA AI
        // =====================================================================
        $relative_path = 'uploads/' . $new_filename;

        if ($ai_is_correct) {
            // DUYỆT: Lưu log và cộng điểm
            $stmt = $pdo->prepare("INSERT INTO submissions (user_id, task_id, image_path, status) VALUES (?, ?, ?, 'approved')");
            $stmt->execute([$user_id, $task_id, $relative_path]);

            // Cộng điểm
            $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            $stmt->execute([$task['reward_points'], $user_id]);

            // Lấy điểm mới nhất
            $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $new_points = $stmt->fetchColumn();

            echo json_encode([
                'status' => 'success',
                'message' => 'Tuyệt vời! ' . $ai_reason,
                'new_points' => $new_points
            ]);
        } else {
            // TỪ CHỐI: Lưu log chờ admin xem xét thủ công
            $stmt = $pdo->prepare("INSERT INTO submissions (user_id, task_id, image_path, status) VALUES (?, ?, ?, 'rejected')");
            $stmt->execute([$user_id, $task_id, $relative_path]);

            echo json_encode([
                'status' => 'error',
                'message' => 'AI đã từ chối: (' . $ai_reason . '). Ảnh đã gửi cho Admin xem xét thủ công.'
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu tệp tin lên máy chủ. Hãy kiểm tra quyền (chmod) của thư mục uploads.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ hoặc file quá lớn.']);
}
