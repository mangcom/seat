<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $_POST['plan_id'];
    $guest_data = $_POST['guests'] ?? [];

    try {
        $pdo->beginTransaction();

        foreach ($guest_data as $group_id => $items) {
            foreach ($items as $index => $data) {
                $guest_id = $data['id'] ?? null;
                $name = $data['name'];
                $role = $data['role'];
                $image_path = $data['old_img']; // ค่าเริ่มต้นใช้รูปเดิม

                // Handle Image Upload
                // key ของไฟล์คือ guest_img_{group_id}_{index}
                $file_key = "guest_img_{$group_id}_{$index}";
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === 0) {
                    $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                    $new_filename = uniqid() . "." . $ext;
                    
                    if(move_uploaded_file($_FILES[$file_key]['tmp_name'], "uploads/" . $new_filename)) {
                        $image_path = $new_filename; // อัปเดตชื่อไฟล์ใหม่
                    }
                }

                if ($guest_id) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE guests SET name=?, role=?, image_path=?, sort_order=? WHERE id=?");
                    $stmt->execute([$name, $role, $image_path, $index, $guest_id]);
                } else {
                    // Insert New (ถ้ามีชื่อหรือรูป)
                    if (!empty($name) || !empty($image_path)) {
                        $stmt = $pdo->prepare("INSERT INTO guests (group_id, name, role, image_path, sort_order) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$group_id, $name, $role, $image_path, $index]);
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: interactive_map.php?id=" . $plan_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error: " . $e->getMessage();
    }
}
?>