<?php
// save_initial_plan.php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. บันทึกข้อมูลผังหลัก (Plan)
        // รับค่าจาก form ถ้าไม่มีให้เป็นค่า default
        $planName = $_POST['plan_name'] ?: 'Untitled Plan';
        $capacity = !empty($_POST['total_capacity']) ? intval($_POST['total_capacity']) : 0;
        $seatsPerRow = !empty($_POST['seats_per_row']) ? intval($_POST['seats_per_row']) : 40;

        $stmt = $pdo->prepare("INSERT INTO plans (name, total_capacity, seats_per_row) VALUES (?, ?, ?)");
        $stmt->execute([$planName, $capacity, $seatsPerRow]);
        $plan_id = $pdo->lastInsertId();

        // ---------------------------------------------------------
        // ฟังก์ชันช่วยสร้าง Dummy Guest ลง Database
        // ---------------------------------------------------------
        function generateDummyGuests($pdo, $groupId, $count, $prefixName) {
            $sql = "INSERT INTO guests (group_id, name, role, sort_order, status) VALUES (?, ?, ?, ?, 'normal')";
            $stmt = $pdo->prepare($sql);
            
            for ($i = 1; $i <= $count; $i++) {
                // สร้างชื่อสมมติ เช่น "VIP คนที่ 1", "ผู้รับรางวัล คนที่ 5"
                $dummyName = "$prefixName คนที่ $i";
                $dummyRole = "ตำแหน่ง/สังกัด (จำลอง)";
                
                // sort_order เริ่มที่ 0 (ดังนั้นใช้ $i - 1)
                $stmt->execute([$groupId, $dummyName, $dummyRole, ($i - 1)]);
            }
        }
        // ---------------------------------------------------------

        // 2. จัดการกลุ่มผู้บริหาร (Executive Groups)
        if (!empty($_POST['exec_groups'])) {
            $sqlGroup = "INSERT INTO plan_groups (plan_id, zone_type, name, seat_type, row_count, seats_in_row, sort_order) VALUES (?, 'exec', ?, ?, ?, ?, ?)";
            $stmtGroup = $pdo->prepare($sqlGroup);
            
            foreach ($_POST['exec_groups'] as $idx => $group) {
                $rows = intval($group['rows']);
                $seats = intval($group['seats']);
                $totalSpots = $rows * $seats; // คำนวณจำนวนที่นั่งทั้งหมดในกลุ่มนี้

                $stmtGroup->execute([$plan_id, $group['name'], $group['seat_type'], $rows, $seats, $idx]);
                $groupId = $pdo->lastInsertId();

                // *** สร้าง Dummy Data ทันที ***
                generateDummyGuests($pdo, $groupId, $totalSpots, $group['name']);
            }
        }

        // 3. จัดการกลุ่มผู้เข้าร่วม (Participant Groups)
        if (!empty($_POST['part_groups'])) {
            $sqlGroup = "INSERT INTO plan_groups (plan_id, zone_type, name, member_count, quantity, sort_order) VALUES (?, 'part', ?, ?, ?, ?)";
            $stmtGroup = $pdo->prepare($sqlGroup);

            foreach ($_POST['part_groups'] as $idx => $group) {
                $qty = intval($group['quantity']); // จำนวนรายการ (เช่น 10 ทีม)
                $members = intval($group['members']); // สมาชิกต่อทีม (เช่น 3 คน)
                $isTeam = ($group['type'] === 'team');
                
                // คำนวณจำนวนคนทั้งหมดที่จะสร้าง
                // ถ้าเป็นทีม: 10 ทีม * 3 คน = 30 คน
                // ถ้าเป็นเดี่ยว: 10 คน * 1 คน = 10 คน
                $totalPeople = $isTeam ? ($qty * $members) : $qty;

                $stmtGroup->execute([$plan_id, $group['name'], $members, $qty, $idx]);
                $groupId = $pdo->lastInsertId();

                // *** สร้าง Dummy Data ทันที ***
                // ถ้าเป็นทีม อาจจะตั้งชื่อให้ดูเป็นทีม เช่น "ทีม A (1)", "ทีม A (2)"
                if ($isTeam) {
                    $sqlTeam = "INSERT INTO guests (group_id, name, role, sort_order, status) VALUES (?, ?, ?, ?, 'normal')";
                    $stmtTeam = $pdo->prepare($sqlTeam);
                    $order = 0;
                    for ($t = 1; $t <= $qty; $t++) {
                        for ($m = 1; $m <= $members; $m++) {
                            $name = "{$group['name']} ชุดที่ $t ($m)";
                            $role = "สมาชิกคนที่ $m";
                            $stmtTeam->execute([$groupId, $name, $role, $order]);
                            $order++;
                        }
                    }
                } else {
                    // แบบรายบุคคล
                    generateDummyGuests($pdo, $groupId, $totalPeople, $group['name']);
                }
            }
        }

        $pdo->commit();
        
        // Redirect ข้ามหน้ากรอกรายชื่อ ไปหน้าแผนผังเลย
        header("Location: interactive_map.php?id=" . $plan_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error: " . $e->getMessage();
        echo "<br><a href='create_plan.php'>กลับไปหน้าสร้างผัง</a>";
    }
}
?>