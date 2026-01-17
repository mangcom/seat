<?php
require 'db.php';
// รับ JSON จาก JS (List ของ ID ที่เรียงใหม่)
$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    try {
        $pdo->beginTransaction();
        
        // $input['items'] คือ array ของ guest_id ที่เรียงตามลำดับใหม่แล้ว
        // $input['group_id'] คือ group ที่ถูกย้ายไปใส่
        
        $groupId = $input['group_id'];
        $items = $input['items']; // Array ของ guest_id [10, 5, 2, ...]

        $sql = "UPDATE guests SET sort_order = ?, group_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        foreach ($items as $index => $guestId) {
            $stmt->execute([$index, $groupId, $guestId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false]);
    }
}
?>