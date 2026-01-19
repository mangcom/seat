<?php
session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); // ปิดการแจ้งเตือนเรื่องทศนิยม (Deprecated)
ini_set('display_errors', 0); // ห้ามแสดง Error ออกทางหน้าจอ (เพื่อไม่ให้ JSON พัง)
require 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$id = $_POST['guest_id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$role = trim($_POST['role'] ?? '');
$status = $_POST['status'] ?? 'normal';
$deleteImage = isset($_POST['delete_image']) && $_POST['delete_image'] == '1';

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}

// 1. ดึงข้อมูลเก่า และหาว่า Guest นี้อยู่ใน Plan ID ไหน
// เชื่อมตาราง guests -> plan_groups -> plans
$sql = "SELECT g.image_path, pg.plan_id, p.created_by 
        FROM guests g
        JOIN plan_groups pg ON g.group_id = pg.id
        JOIN plans p ON pg.plan_id = p.id
        WHERE g.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Guest not found']);
    exit;
}
$current_user = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? 'guest';

// ถ้าไม่ใช่ Admin และ ไม่ใช่เจ้าของผัง -> ห้ามแก้ไข
if ($user_role !== 'admin' && $data['created_by'] != $current_user) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access (Not Owner/Admin)']);
    exit;
}

try {
    $pdo->beginTransaction();
$oldImage = $data['image_path'] ?? null;
$planId = $data['plan_id']; // ได้รหัสผังแล้ว

// กำหนดโฟลเดอร์ปลายทาง: uploads/{plan_id}/
$targetDir = "uploads/" . $planId . "/";

// ถ้ายังไม่มีโฟลเดอร์ ให้สร้างใหม่ (Permission 0777)
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create directory']);
        exit;
    }
}

$newImagePathDB = $oldImage; // ค่าเริ่มต้นคือใช้รูปเดิม

// --- 2. กรณีสั่งลบรูป ---
if ($deleteImage) {
    // ลบรูปเก่า (เช็คว่ามีไฟล์อยู่จริงหรือไม่ ก่อนลบ)
    if ($oldImage && file_exists("uploads/" . $oldImage)) {
        unlink("uploads/" . $oldImage); 
    }
    $newImagePathDB = null; // เคลียร์ค่าใน DB
}

// --- 3. กรณีมีการอัปโหลดรูปใหม่ ---
if (isset($_FILES['guest_image']) && $_FILES['guest_image']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['guest_image']['tmp_name'];
    $fileName = $_FILES['guest_image']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($ext, $allowed)) {
        // ลบรูปเก่าทิ้งก่อน (ถ้ามีอยู่ และไม่ได้ถูกลบไปในขั้นตอนข้างบน)
        if ($oldImage && file_exists("uploads/" . $oldImage) && !$deleteImage) {
            unlink("uploads/" . $oldImage);
        }

        // ตั้งชื่อไฟล์ใหม่สุ่ม
        $finalName = uniqid('img_') . '.' . $ext;
        
        // Path เต็มสำหรับบันทึกลงไฟล์ (เช่น uploads/5/img_xxx.jpg)
        $destination = $targetDir . $finalName;
        
        // Path สำหรับเก็บใน DB (เก็บแบบ relative: 5/img_xxx.jpg)
        // เพื่อให้หน้าแสดงผลเรียก uploads/ + ค่าใน DB ได้เลย
        $dbValue = $planId . '/' . $finalName;

        // Resize และบันทึก
        if (resizeAndSaveImage($fileTmp, $destination, 480)) {
            $newImagePathDB = $dbValue;
        } else {
            // ถ้า Resize ไม่ได้ ให้ Copy ปกติ
            if(move_uploaded_file($fileTmp, $destination)) {
                $newImagePathDB = $dbValue;
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save file']);
                exit;
            }
        }
    }
}

// อัปเดตลงฐานข้อมูล
$stmt = $pdo->prepare("UPDATE guests SET name = ?, role = ?, status = ?, image_path = ? WHERE id = ?");
$result = $stmt->execute([$name, $role, $status, $newImagePathDB, $id]);

if ($result) {
        $pdo->commit();
        echo json_encode(['success' => true, 'image_path' => $newImagePathDB]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}




// --- ฟังก์ชัน Resize (เหมือนเดิม) ---
function resizeAndSaveImage($source, $destination, $maxDim) {
    list($width, $height, $type) = getimagesize($source);
    if (!$width) return false;

    if ($width <= $maxDim && $height <= $maxDim) {
        return move_uploaded_file($source, $destination);
    }

    $ratio = $width / $height;
    if ($ratio > 1) {
        $newWidth = $maxDim;
        $newHeight = $maxDim / $ratio;
    } else {
        $newHeight = $maxDim;
        $newWidth = $maxDim * $ratio;
    }

    $src = null;
    if ($type == IMAGETYPE_JPEG) $src = imagecreatefromjpeg($source);
    elseif ($type == IMAGETYPE_PNG) $src = imagecreatefrompng($source);
    elseif ($type == IMAGETYPE_GIF) $src = imagecreatefromgif($source);

    if (!$src) return false;

    $dst = imagecreatetruecolor($newWidth, $newHeight);

    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $result = false;
    if ($type == IMAGETYPE_JPEG) $result = imagejpeg($dst, $destination, 85);
    elseif ($type == IMAGETYPE_PNG) $result = imagepng($dst, $destination);
    elseif ($type == IMAGETYPE_GIF) $result = imagegif($dst, $destination);

    imagedestroy($src);
    imagedestroy($dst);

    return $result;
}
?>