<?php
// api_plan_manager.php
require 'db.php';
header('Content-Type: application/json');

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