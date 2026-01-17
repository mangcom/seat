<?php
session_start();
require 'db.php';

// 1. ตรวจสอบว่า Login หรือยัง? ถ้ายัง ให้เด้งไปหน้า Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 2. Query ตามสิทธิ์
if ($role == 'admin') {
    // Admin เห็นทั้งหมด
    $stmt = $pdo->query("SELECT * FROM plans ORDER BY id DESC");
} else {
    // User เห็นแค่ของตัวเอง (created_by = ฉัน)
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE created_by = ? ORDER BY id DESC");
    $stmt->execute([$user_id]);
}
$plans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style> body { font-family: 'Sarabun', sans-serif; background: #f8f9fa; } </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1"><i class="bi bi-speedometer2"></i> Admin Dashboard</span>
        <div>
            <a href="admin_users.php" class="btn btn-warning btn-sm me-2">
                <i class="bi bi-people-fill"></i> จัดการผู้ใช้
            </a>
            <span class="text-white me-3">สวัสดี, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger">ออกจากระบบ</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>จัดการผังที่นั่งทั้งหมด</h4>
        <a href="index.php" class="btn btn-outline-primary"><i class="bi bi-house"></i> ไปหน้าผู้ใช้งาน</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>ชื่อผัง</th>
                        <th class="text-center">สถานะ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach($plans as $p): 
    // --- 1. เช็คสิทธิ์ก่อน ---
    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] == 'admin');
    $is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $p['created_by']);
    // กฎ: Admin ทำได้ทุกอย่าง, Owner ทำได้แค่ของตัวเอง
    $can_manage = ($is_admin || $is_owner);
    // ตรวจสอบสถานะ
    $isDeleted = !empty($p['is_deleted']) && $p['is_deleted'] == 1;
    $isActive = $p['is_active'] == 1;

    // กำหนดสีพื้นหลังของแถว
    $rowClass = '';
    if ($isDeleted) {
        $rowClass = 'table-danger'; // สีแดงอ่อน (User ลบแล้ว)
    } elseif (!$isActive) {
        $rowClass = 'table-secondary text-muted'; // สีเทา (Admin ปิด)
    }
?>
    <tr id="row-<?php echo $p['id']; ?>" class="<?php echo $rowClass; ?>">
        <td><?php echo $p['id']; ?></td>
        <td>
            <strong><?php echo htmlspecialchars($p['name']); ?></strong>
            
            <?php if ($isDeleted): ?>
                <span class="badge bg-danger ms-2"><i class="bi bi-trash3-fill"></i> User Deleted</span>
            <?php elseif (!$isActive): ?>
                <span class="badge bg-secondary ms-2">Disabled</span>
            <?php else: ?>
                <span class="badge bg-success ms-2">Active</span>
            <?php endif; ?>
        </td>
        
        <td class="text-center">
            <div class="form-check form-switch d-inline-block">
                <input class="form-check-input" type="checkbox" 
                       onclick="toggleStatus(<?php echo $p['id']; ?>)" 
                       <?php echo $isActive ? 'checked' : ''; ?>
                       <?php echo $isDeleted ? 'disabled' : ''; ?> 
                       title="<?php echo $isDeleted ? 'กู้คืนก่อนเปิดใช้งาน' : 'เปิด/ปิดการใช้งาน'; ?>">
            </div>
        </td>
        
        <td class="text-center">
            <a href="interactive_map.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                <i class="bi bi-eye"></i> ดู
            </a>
            
            <?php if ($isDeleted): ?>
                <button onclick="hardDelete(<?php echo $p['id']; ?>)" class="btn btn-sm btn-dark shadow-sm border-white">
                    <i class="bi bi-x-circle"></i> ลบขยะทิ้ง (ถาวร)
                </button>
            <?php else: ?>
                <button onclick="hardDelete(<?php echo $p['id']; ?>)" class="btn btn-sm btn-danger">
                    <i class="bi bi-trash"></i> ลบถาวร
                </button>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleStatus(id) {
    fetch('api_admin_plan.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'toggle_status', id: id })
    }).then(res => res.json()).then(data => {
        if(data.success) {
            location.reload(); // รีโหลดหน้าเพื่ออัปเดตสีตาราง
        } else {
            alert('Error updating status');
        }
    });
}

function hardDelete(id) {
    if(confirm('คำเตือน: การลบนี้จะลบข้อมูลถาวร!\n- ลบผัง\n- ลบรายชื่อทั้งหมด\n- ลบรูปภาพออกจาก Server\n\nยืนยันหรือไม่?')) {
        fetch('api_admin_plan.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'hard_delete', id: id })
        }).then(res => res.json()).then(data => {
            if(data.success) {
                document.getElementById('row-' + id).remove();
            } else {
                alert('ลบไม่สำเร็จ: ' + data.error);
            }
        });
    }
}
</script>
</body>
</html>