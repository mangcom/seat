<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // --- จุดที่แก้ไข (FIX) ---
        // ตรวจสอบค่าที่รับมา ถ้าเป็นค่าว่างให้เปลี่ยนเป็น 0 (สำหรับตัวเลข)
        $plan_name = $_POST['plan_name'];
        $total_capacity = empty($_POST['total_capacity']) ? 0 : intval($_POST['total_capacity']);
        $seats_per_row = empty($_POST['seats_per_row']) ? 40 : intval($_POST['seats_per_row']);

        // 1. Insert Plan
        $stmt = $pdo->prepare("INSERT INTO plans (name, total_capacity, seats_per_row) VALUES (?, ?, ?)");
        // ใช้ตัวแปรที่ดักค่าแล้วแทน $_POST โดยตรง
        $stmt->execute([$plan_name, $total_capacity, $seats_per_row]);
        
        $plan_id = $pdo->lastInsertId();

        // 2. Insert Executive Groups
        if (!empty($_POST['exec_groups'])) {
            $sql = "INSERT INTO plan_groups (plan_id, zone_type, name, seat_type, row_count, seats_in_row, sort_order) VALUES (?, 'exec', ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            foreach ($_POST['exec_groups'] as $idx => $group) {
                // ดักค่าตัวเลขใน Loop ด้วยเพื่อความชัวร์
                $rows = empty($group['rows']) ? 1 : intval($group['rows']);
                $seats = empty($group['seats']) ? 1 : intval($group['seats']);
                
                $stmt->execute([$plan_id, $group['name'], $group['seat_type'], $rows, $seats, $idx]);
            }
        }

        // 3. Insert Participant Groups
        if (!empty($_POST['part_groups'])) {
            $sql = "INSERT INTO plan_groups (plan_id, zone_type, name, member_count, quantity, sort_order) VALUES (?, 'part', ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            foreach ($_POST['part_groups'] as $idx => $group) {
                // ดักค่าตัวเลขใน Loop ด้วย
                $members = empty($group['members']) ? 1 : intval($group['members']);
                $quantity = empty($group['quantity']) ? 1 : intval($group['quantity']);

                $stmt->execute([$plan_id, $group['name'], $members, $quantity, $idx]);
            }
        }

        $pdo->commit();
        
        // ส่งไปหน้าจัดการรายชื่อ พร้อม ID
        header("Location: manage_guests.php?id=" . $plan_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        // แสดง Error ให้ชัดเจนขึ้น
        echo "<h3>เกิดข้อผิดพลาดในการบันทึก:</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "<a href='create_plan.php'>กลับไปหน้าสร้างผัง</a>";
    }
}
?>