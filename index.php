<?php
require 'db.php';
// Giả lập người dùng đang đăng nhập có ID = 1
$current_user_id = 1;

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user = $stmt->fetch();

// Lấy danh sách nhiệm vụ
$tasks = $pdo->query("SELECT * FROM tasks")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Nhiệm vụ AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Xin chào, <?= htmlspecialchars($user['username']) ?>!</h2>
            <h4 class="text-success">Điểm của bạn: <span id="user-points"><?= $user['points'] ?></span></h4>
        </div>

        <div class="row">
            <?php foreach ($tasks as $task): ?>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($task['title']) ?></h5>
                            <p class="card-text">Phần thưởng: <strong><?= $task['reward_points'] ?> điểm</strong></p>

                            <form id="uploadForm-<?= $task['id'] ?>" onsubmit="submitTask(event, <?= $task['id'] ?>)">
                                <input type="file" name="task_image" class="form-control mb-2" accept="image/*" required>
                                <button type="submit" class="btn btn-primary w-100">Tải ảnh lên (AI Kiểm duyệt)</button>
                            </form>
                            <div id="msg-<?= $task['id'] ?>" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        async function submitTask(event, taskId) {
            event.preventDefault();
            const form = document.getElementById(`uploadForm-${taskId}`);
            const msgDiv = document.getElementById(`msg-${taskId}`);
            const formData = new FormData(form);
            formData.append('task_id', taskId);
            formData.append('user_id', <?= $current_user_id ?>);

            msgDiv.innerHTML = '<span class="text-info">Đang xử lý bằng AI...</span>';
            const btn = form.querySelector('button');
            btn.disabled = true;

            try {
                const response = await fetch('process_upload.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    msgDiv.innerHTML = `<span class="text-success">${result.message}</span>`;
                    document.getElementById('user-points').innerText = result.new_points;
                } else {
                    msgDiv.innerHTML = `<span class="text-danger">${result.message}</span>`;
                }
            } catch (error) {
                msgDiv.innerHTML = `<span class="text-danger">Có lỗi xảy ra kết nối hệ thống.</span>`;
            } finally {
                btn.disabled = false;
                form.reset();
            }
        }
    </script>
</body>

</html>