<?php
// api_user_manager.php
session_start();
require 'db.php';
header('Content-Type: application/json');

// 1. ตรวจสอบสิทธิ์ (ต้อง Login และต้องเป็น Admin เท่านั้น)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// รับข้อมูล JSON
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    // --- ADMIN ACTIONS (ต้องเป็น Admin เท่านั้น) ---
    if ($_SESSION['role'] === 'admin') {
        
        // 1. เพิ่ม User ใหม่
        if ($action === 'add_user') {
            $username = trim($data['username']);
            $password = $data['password'];
            $role = $data['role'];

            // เช็คว่าชื่อซ้ำไหม
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่แล้ว']);
                exit;
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$username, $hashed, $role]);
            
            echo json_encode(['success' => true]);
            exit;
        }

        // 2. ระงับ/เปิดใช้งาน User (Toggle Ban)
        if ($action === 'toggle_status') {
            $target_id = $data['id'];
            
            // ป้องกันไม่ให้แบนตัวเอง
            if ($target_id == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถระงับบัญชีตัวเองได้']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$target_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        // 3. Admin เปลี่ยนรหัสให้ User (Reset Password)
        if ($action === 'admin_reset_pass') {
            $target_id = $data['id'];
            $new_pass = $data['new_password'];
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $target_id]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // --- GENERAL ACTIONS (Admin หรือ User ทำได้) ---

    // 4. เปลี่ยนรหัสผ่านของตัวเอง (Change Own Password)
    if ($action === 'change_own_pass') {
        $old_pass = $data['old_password'];
        $new_pass = $data['new_password'];
        $user_id = $_SESSION['user_id'];

        // ตรวจสอบรหัสเดิมก่อน
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($old_pass, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านเดิมไม่ถูกต้อง']);
            exit;
        }

        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>