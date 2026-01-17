<?php
// api_plan_manager.php
session_start();
require 'db.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$plan_id = $input['id'] ?? 0;

// เช็คว่ามีสิทธิ์กับ Plan นี้ไหม
function checkPermission($pdo, $plan_id) {
    if (!isset($_SESSION['user_id'])) return false;
    if ($_SESSION['role'] == 'admin') return true;

    $stmt = $pdo->prepare("SELECT created_by FROM plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    
    return ($plan && $plan['created_by'] == $_SESSION['user_id']);
}
// ถ้าเป็นการกระทำที่ "แก้ไขข้อมูล" (Update, Delete, Save Position)
if (in_array($action, ['save_positions', 'rename', 'delete_guest'])) {
    if (!checkPermission($pdo, $plan_id)) {
        echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์แก้ไขผังนี้']);
        exit;
    }
}
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$id = $data['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

try {
    if ($action === 'rename') {
        // --- แก้ไขชื่อแผนผัง ---
        $newName = trim($data['name']);
        if (!$newName) { throw new Exception("Name cannot be empty"); }
        
        $stmt = $pdo->prepare("UPDATE plans SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $id]);
        
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete') {
        // --- ลบแบบ Soft Delete (แค่เปลี่ยนสถานะ) ---
        // ไม่ใช้ DELETE FROM แต่ใช้ UPDATE แทน
        $stmt = $pdo->prepare("UPDATE plans SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        
    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>