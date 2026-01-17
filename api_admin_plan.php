<?php
// api_admin_plan.php
require 'db.php';
session_start();
header('Content-Type: application/json');

// ตรวจสอบว่า Login หรือยัง
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$plan_id = $data['id'] ?? 0;

if ($action == 'toggle_status') {
    // สลับสถานะ (Soft Delete / Restore)
    $stmt = $pdo->prepare("UPDATE plans SET is_active = NOT is_active WHERE id = ?");
    if ($stmt->execute([$plan_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

} elseif ($action == 'hard_delete') {
    // ลบจริง (Hard Delete) - ลบทุกอย่างที่เกี่ยวข้องรวมถึงไฟล์รูปภาพ
    try {
        $pdo->beginTransaction();

        // 1. หาไฟล์รูปภาพทั้งหมดที่เกี่ยวข้องกับ Plan นี้
        // ต้อง Join: plans -> plan_groups -> guests
        $sql = "SELECT g.image_path 
                FROM guests g
                JOIN plan_groups pg ON g.group_id = pg.id
                WHERE pg.plan_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$plan_id]);
        $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. ลบไฟล์รูปออกจาก Server
        foreach ($images as $img) {
            if ($img && file_exists("uploads/" . $img)) {
                unlink("uploads/" . $img); // คำสั่งลบไฟล์
            }
        }

        // 3. ลบข้อมูลใน Database (ลบ Guest -> Group -> Plan)
        // ลบ Guests
        $stmt = $pdo->prepare("DELETE FROM guests WHERE group_id IN (SELECT id FROM plan_groups WHERE plan_id = ?)");
        $stmt->execute([$plan_id]);

        // ลบ Groups
        $stmt = $pdo->prepare("DELETE FROM plan_groups WHERE plan_id = ?");
        $stmt->execute([$plan_id]);

        // ลบ Plan หลัก
        $stmt = $pdo->prepare("DELETE FROM plans WHERE id = ?");
        $stmt->execute([$plan_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>