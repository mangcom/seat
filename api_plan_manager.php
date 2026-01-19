<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// 1. รับค่า JSON เพียงครั้งเดียว และใช้ชื่อตัวแปร $data ตลอดทั้งไฟล์
$json_content = file_get_contents('php://input');
$data = json_decode($json_content, true);

// ป้องกันกรณี JSON ไม่ถูกต้อง
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$action = $data['action'] ?? '';
$plan_id = $data['id'] ?? 0;

// ฟังก์ชันเช็คสิทธิ์
function checkPermission($pdo, $plan_id) {
    if (!isset($_SESSION['user_id'])) return false;
    if ($_SESSION['role'] == 'admin') return true;

    $stmt = $pdo->prepare("SELECT created_by FROM plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    
    return ($plan && $plan['created_by'] == $_SESSION['user_id']);
}

// ตรวจสอบว่ามี ID ไหม (ยกเว้น action delete อาจจะส่งมาแค่ id ก็ได้)
if (!$plan_id) {
    echo json_encode(['success' => false, 'error' => 'No Plan ID provided']);
    exit;
}

// ตรวจสอบสิทธิ์ก่อนทำรายการ
if (in_array($action, ['save_positions', 'rename', 'delete_guest', 'add_guest', 'delete'])) {
    if (!checkPermission($pdo, $plan_id)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: คุณไม่มีสิทธิ์แก้ไขผังนี้']);
        exit;
    }
}

try {
    // =========================================================
    // 1. เปลี่ยนชื่อผัง (Rename)
    // =========================================================
    if ($action === 'rename') {
        $newName = trim($data['name']);
        if (!$newName) { throw new Exception("Name cannot be empty"); }
        
        $stmt = $pdo->prepare("UPDATE plans SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $plan_id]);
        
        echo json_encode(['success' => true]);
    } 
    // =========================================================
    // 2. ลบผัง (Delete Plan)
    // =========================================================
    elseif ($action === 'delete') {
        $stmt = $pdo->prepare("UPDATE plans SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$plan_id]);
        echo json_encode(['success' => true]);
    }
    // =========================================================
    // 3. เพิ่มที่นั่ง (Add Guest) - [จุดที่คุณต้องการ]
    // =========================================================
    elseif ($action === 'add_guest') {
        $groupId = $data['group_id'] ?? 0;

        if (!$groupId) {
            echo json_encode(['success' => false, 'message' => 'Missing Group ID']);
            exit;
        }

        // หาลำดับสุดท้าย
        $stmt = $pdo->prepare("SELECT MAX(sort_order) FROM guests WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $maxOrder = $stmt->fetchColumn();
        $nextOrder = ($maxOrder !== false) ? $maxOrder + 1 : 0;

        // สร้างที่นั่งใหม่
        $stmt = $pdo->prepare("INSERT INTO guests (group_id, name, role, sort_order, status) VALUES (?, 'ที่นั่งเสริม', 'รอระบุ', ?, 'normal')");
        if ($stmt->execute([$groupId, $nextOrder])) {
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database Error']);
        }
    }
    // =========================================================
    // 4. ลบที่นั่ง (Delete Guest)
    // =========================================================
    elseif ($action === 'delete_guest') {
        $guestId = $data['guest_id'] ?? 0;

        // ลบไฟล์รูปก่อน (ถ้ามี)
        $stmt = $pdo->prepare("SELECT image_path FROM guests WHERE id = ?");
        $stmt->execute([$guestId]);
        $img = $stmt->fetchColumn();
        
        // ตรวจสอบ path รูปภาพให้แน่ใจว่าลบได้ (รองรับทั้งแบบมีโฟลเดอร์และไม่มี)
        if ($img && file_exists("uploads/" . $img)) {
            @unlink("uploads/" . $img);
        }

        // ลบข้อมูลจาก DB
        $stmt = $pdo->prepare("DELETE FROM guests WHERE id = ?");
        $stmt->execute([$guestId]);

        echo json_encode(['success' => true]);
    }
    // =========================================================
    // 5. บันทึกตำแหน่ง (Save Positions) - ถ้ามีฟังก์ชันนี้
    // =========================================================
    elseif ($action === 'save_positions') {
        // (โค้ดสำหรับบันทึกตำแหน่งถ้าคุณมี)
        // ถ้าไม่ได้ใช้ส่วนนี้ ก็ปล่อยว่างไว้หรือลบออกได้ครับ
        echo json_encode(['success' => true]); 
    }
    // =========================================================
    // 6. อัปเดตการตั้งค่าผัง (เช่น จำนวนคอลัมน์)
    // =========================================================
    elseif ($action === 'update_settings') {
        $seatsPerRow = intval($data['seats_per_row']);
        
        $stmt = $pdo->prepare("UPDATE plans SET seats_per_row = ? WHERE id = ?");
        $stmt->execute([$seatsPerRow, $plan_id]);
        
        echo json_encode(['success' => true]);
    }
    // 6. อัปเดตโครงสร้าง (ชื่อกลุ่ม, จำนวนที่นั่ง, ขนาด Grid)
    elseif ($action === 'update_structure') {
        try {
            $pdo->beginTransaction();

            // 1. อัปเดตค่า Grid รวม (Seats Per Row) ลงตาราง plans
            $seatsPerRow = intval($data['seats_per_row']);
            $stmt = $pdo->prepare("UPDATE plans SET seats_per_row = ? WHERE id = ?");
            $stmt->execute([$seatsPerRow, $plan_id]);

            // 2. วนลูปอัปเดตแต่ละกลุ่ม
            foreach ($data['groups'] as $groupData) {
                $groupId = $groupData['id'];
                $newName = trim($groupData['name']);
                $newQty = intval($groupData['qty']);

                // --- [จุดที่แก้ไข] อัปเดตทั้ง 'name' และ 'quantity' ลงตาราง plan_groups ---
                // หมายเหตุ: ถ้าใน Database คุณตั้งชื่อ field ว่า seats_in_row ให้แก้คำว่า quantity เป็น seats_in_row นะครับ
                // แต่โดยปกติจากไฟล์ create_plan น่าจะชื่อ quantity ครับ
                $stmtGroup = $pdo->prepare("UPDATE plan_groups SET name = ?, quantity = ? WHERE id = ? AND plan_id = ?");
                $stmtGroup->execute([$newName, $newQty, $groupId, $plan_id]);

                // 2.2 ตรวจสอบจำนวนปัจจุบันในตาราง guests (ของจริง)
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE group_id = ?");
                $stmtCount->execute([$groupId]);
                $currentQty = $stmtCount->fetchColumn();

                if ($newQty > $currentQty) {
                    // --- กรณีเพิ่ม (Add) ---
                    $diff = $newQty - $currentQty;
                    // หา Sort Order ตัวสุดท้าย
                    $stmtMax = $pdo->prepare("SELECT MAX(sort_order) FROM guests WHERE group_id = ?");
                    $stmtMax->execute([$groupId]);
                    $maxOrder = $stmtMax->fetchColumn();
                    $startOrder = ($maxOrder !== false) ? $maxOrder + 1 : 0;

                    $stmtInsert = $pdo->prepare("INSERT INTO guests (group_id, name, role, sort_order, status) VALUES (?, ?, ?, ?, 'normal')");
                    for ($i = 0; $i < $diff; $i++) {
                        // สร้างชื่อระบุเลขต่อท้ายให้ชัดเจน
                        $runningNumber = $currentQty + $i + 1;
                        $name = "ที่นั่งเสริม " . $runningNumber;
                        $stmtInsert->execute([$groupId, $name, 'รอระบุ', $startOrder + $i]);
                    }

                } elseif ($newQty < $currentQty) {
                    // --- กรณีลด (Remove) ---
                    // ลบจากท้ายสุด
                    $diff = $currentQty - $newQty;
                    $stmtIds = $pdo->prepare("SELECT id FROM guests WHERE group_id = ? ORDER BY sort_order DESC LIMIT $diff");
                    $stmtIds->execute([$groupId]);
                    $idsToDelete = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($idsToDelete)) {
                        // ลบรูปภาพก่อน
                        $inQuery = implode(',', array_fill(0, count($idsToDelete), '?'));
                        $stmtImg = $pdo->prepare("SELECT image_path FROM guests WHERE id IN ($inQuery)");
                        $stmtImg->execute($idsToDelete);
                        while ($img = $stmtImg->fetchColumn()) {
                            if ($img && file_exists("uploads/" . $img)) {
                                @unlink("uploads/" . $img);
                            }
                        }

                        // ลบจาก DB
                        $sqlDelete = "DELETE FROM guests WHERE id IN ($inQuery)";
                        $stmtDelete = $pdo->prepare($sqlDelete);
                        $stmtDelete->execute($idsToDelete);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}

// // api_plan_manager.php
// session_start();
// require 'db.php';

// header('Content-Type: application/json');
// $input = json_decode(file_get_contents('php://input'), true);
// $action = $input['action'] ?? '';
// $plan_id = $input['id'] ?? 0;

// // เช็คว่ามีสิทธิ์กับ Plan นี้ไหม
// function checkPermission($pdo, $plan_id) {
//     if (!isset($_SESSION['user_id'])) return false;
//     if ($_SESSION['role'] == 'admin') return true;

//     $stmt = $pdo->prepare("SELECT created_by FROM plans WHERE id = ?");
//     $stmt->execute([$plan_id]);
//     $plan = $stmt->fetch();
    
//     return ($plan && $plan['created_by'] == $_SESSION['user_id']);
// }
// // ถ้าเป็นการกระทำที่ "แก้ไขข้อมูล" (Update, Delete, Save Position)
// if (in_array($action, ['save_positions', 'rename', 'delete_guest'])) {
//     if (!checkPermission($pdo, $plan_id)) {
//         echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์แก้ไขผังนี้']);
//         exit;
//     }
// }
// $data = json_decode(file_get_contents('php://input'), true);
// $action = $data['action'] ?? '';
// $id = $data['id'] ?? 0;

// if (!$id) {
//     echo json_encode(['success' => false, 'error' => 'Invalid ID']);
//     exit;
// }

// try {
//     if ($action === 'rename') {
//         // --- แก้ไขชื่อแผนผัง ---
//         $newName = trim($data['name']);
//         if (!$newName) { throw new Exception("Name cannot be empty"); }
        
//         $stmt = $pdo->prepare("UPDATE plans SET name = ? WHERE id = ?");
//         $stmt->execute([$newName, $id]);
        
//         echo json_encode(['success' => true]);

//     } elseif ($action === 'delete') {
//         // --- ลบแบบ Soft Delete (แค่เปลี่ยนสถานะ) ---
//         // ไม่ใช้ DELETE FROM แต่ใช้ UPDATE แทน
//         $stmt = $pdo->prepare("UPDATE plans SET is_deleted = 1 WHERE id = ?");
//         $stmt->execute([$id]);
        
//         echo json_encode(['success' => true]);
        
//     } elseif ($action === 'add_guest') {
//     // --- รับค่า group_id ---
//     $groupId = $data['group_id'];

//     if (!$groupId) {
//         echo json_encode(['success' => false, 'message' => 'Missing Group ID']);
//         exit;
//     }

//     // 1. หาลำดับ (Sort Order) สุดท้ายของกลุ่มนั้น เพื่อให้ที่นั่งใหม่อยู่ท้ายสุด
//     $stmt = $pdo->prepare("SELECT MAX(sort_order) FROM guests WHERE group_id = ?");
//     $stmt->execute([$groupId]);
//     $maxOrder = $stmt->fetchColumn();
//     $nextOrder = ($maxOrder !== false) ? $maxOrder + 1 : 0;

//     // 2. สร้าง Guest ใหม่ (ใส่ชื่อเริ่มต้นว่า "ที่นั่งเสริม")
//     $stmt = $pdo->prepare("INSERT INTO guests (group_id, name, role, sort_order, status) VALUES (?, 'ที่นั่งเสริม', 'รอระบุ', ?, 'normal')");
    
//     if ($stmt->execute([$groupId, $nextOrder])) {
//         echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
//     } else {
//         echo json_encode(['success' => false, 'message' => 'Database Insert Error']);
//     }
// } elseif ($action === 'delete_guest') {
//         // --- ลบที่นั่ง (ลบถาวรจาก Database) ---
//         $guestId = $data['guest_id'];

//         // ลบรูปภาพด้วย (ถ้ามี) - Optional
//         $stmt = $pdo->prepare("SELECT image_path FROM guests WHERE id = ?");
//         $stmt->execute([$guestId]);
//         $img = $stmt->fetchColumn();
//         if ($img && file_exists("uploads/" . $img)) {
//             @unlink("uploads/" . $img);
//         }

//         $stmt = $pdo->prepare("DELETE FROM guests WHERE id = ?");
//         $stmt->execute([$guestId]);

//         echo json_encode(['success' => true]);

//     } else {
//         throw new Exception("Invalid action");
//     }

// } catch (Exception $e) {
//     echo json_encode(['success' => false, 'error' => $e->getMessage()]);
// }

// api_plan_manager.php
?>