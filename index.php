<?php
session_start();
require 'db.php';

// ฟังก์ชันแปลงวันที่ (คงเดิมไว้)
function thai_date($strDate) {
    if (!$strDate) return "ไม่ระบุวันที่";
    $strYear = date("Y",strtotime($strDate))+543;
    $strMonth= date("n",strtotime($strDate));
    $strDay= date("j",strtotime($strDate));
    $strMonthCut = Array("","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค.");
    $strMonthThai=$strMonthCut[$strMonth];
    return "$strDay $strMonthThai $strYear";
}

// --- ส่วนที่แก้ไข: กรองการแสดงผลตามสิทธิ์ ---
$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? 'guest';

if ($role == 'admin') {
    // 1. Admin: เห็นทั้งหมด (รวมที่ Active=0 และ Deleted=1 เพื่อจัดการได้)
    // แต่หน้า index ปกติเราอาจจะโชว์เฉพาะที่ยังไม่ลบก็ได้ (แล้วแต่ตกลง)
    // เอาแบบ Admin เห็นทุกอย่างที่ User ทั่วไปเห็น + ของที่ User คนอื่นสร้าง
    $sql = "SELECT * FROM plans WHERE (is_deleted = 0 OR is_deleted IS NULL) ORDER BY plan_date DESC, id DESC";
    $params = [];

} elseif ($role == 'user') {
    // 2. User: เห็นเฉพาะ "ของตัวเอง" (created_by = ฉัน) และยังไม่ลบ
    $sql = "SELECT * FROM plans WHERE created_by = ? AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY plan_date DESC, id DESC";
    $params = [$user_id];

} else {
    // 3. Guest: เห็นเฉพาะที่ "เปิดใช้งาน (Active)" และยังไม่ลบ
    $sql = "SELECT * FROM plans WHERE is_active = 1 AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY plan_date DESC, id DESC";
    $params = [];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$plans = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบผังที่นั่ง (Seating Plan)</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f0f2f5; }
        .plan-card { transition: all 0.3s ease; border: none; border-radius: 12px; overflow: hidden; }
        .plan-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        .card-img-top-placeholder { height: 120px; background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%); display: flex; align-items: center; justify-content: center; color: #6c757d; }
        .action-btn { border: none; background: transparent; padding: 8px 15px; border-radius: 5px; transition: 0.2s; color: #555; font-size: 0.9rem; }
        .action-btn:hover { background-color: #f0f0f0; color: #000; }
        .btn-delete:hover { background-color: #ffebee; color: #d32f2f; }
        .btn-edit:hover { background-color: #e3f2fd; color: #1976d2; }
        .btn-create { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
        .btn-create:hover { opacity: 0.9; color: white; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-grid-3x3-gap-fill"></i> ระบบผังที่นั่ง
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item me-3 text-white">
                            <span class="text-secondary small">สวัสดี,</span> 
                            <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </li>

                        <li class="nav-item me-2">
                            <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-speedometer2"></i> จัดการหลังบ้าน
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a href="logout.php" class="btn btn-danger btn-sm">
                                <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
                            </a>
                        </li>

                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="btn btn-primary btn-sm px-4">
                                <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ (เจ้าหน้าที่)
                            </a>
                        </li>
                    <?php endif; ?>

                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark">รายการผังที่นั่ง</h2>
                <p class="text-muted mb-0">จัดการผังที่นั่งตามวันที่จัดงาน</p>
            </div>
            <?php if(isset($_SESSION['user_id'])): ?>
            <button class="btn btn-create px-4 py-2 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="bi bi-plus-lg"></i> สร้างผังใหม่
            </button>
            <?php endif; ?>
        </div>

        <hr class="mb-4">

        <div class="row g-4">
            <?php if (count($plans) > 0): ?>
                <?php foreach ($plans as $plan): ?>
                    <?php 
                        // --- 1. เช็คสิทธิ์ (Logic เดียวกับ Dashboard) ---
                        $is_login = isset($_SESSION['user_id']);
                        $is_admin = ($is_login && $_SESSION['role'] == 'admin');
                        $is_owner = ($is_login && $_SESSION['user_id'] == $plan['created_by']);
                        
                        // ใครมีสิทธิ์แก้? (Admin หรือ เจ้าของผังนี้)
                        $can_edit = ($is_admin || $is_owner);
                    ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3" id="card-plan-<?php echo $plan['id']; ?>">
                    <div class="card plan-card shadow-sm h-100">
                        <a href="interactive_map.php?id=<?php echo $plan['id']; ?>" class="text-decoration-none">
                            <div class="card-img-top-placeholder">
                                <i class="bi bi-calendar-check display-4 opacity-50"></i>
                            </div>
                            <div class="card-body text-center pt-3 pb-2">
                                <h5 class="card-title fw-bold text-dark mb-1" id="name-plan-<?php echo $plan['id']; ?>">
                                    <?php echo htmlspecialchars($plan['name']); ?>
                                </h5>
                                <p class="text-muted small mb-0 mt-2">
                                    <i class="bi bi-calendar-event me-1"></i> 
                                    <?php echo thai_date($plan['plan_date']); ?>
                                </p>
                            </div>
                        </a>
                        <div class="card-footer bg-white border-top d-flex justify-content-between py-2">
                            <?php if ($can_edit): ?>
                            <button class="action-btn btn-edit" onclick="renamePlan(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>')" title="แก้ไขชื่อ">
                                <i class="bi bi-pencil-square"></i> แก้ไข
                            </button>
                            <?php endif; ?>
                            <a href="interactive_map.php?id=<?php echo $plan['id']; ?>" class="action-btn text-decoration-none" title="พิมพ์/ส่งออก" target="_blank">
                                <i class="bi bi-printer"></i> พิมพ์
                            </a>
                                <?php if ($can_edit): ?>
                            <button class="action-btn btn-delete" onclick="deletePlan(<?php echo $plan['id']; ?>)" title="ลบแผนผัง">
                                <i class="bi bi-trash"></i> ลบ
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 bg-white rounded shadow-sm">
                    <i class="bi bi-inbox display-1 text-muted mb-3 d-block"></i>
                    <h4 class="text-muted">ยังไม่มีรายการผังที่นั่ง</h4>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createModal">เริ่มต้นสร้างใหม่</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="create_plan.php" method="POST">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">สร้างผังที่นั่งใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ชื่อผังงาน / ชื่องาน</label>
                            <input type="text" name="plan_name" class="form-control form-control-lg" placeholder="เช่น พิธีปิดงานมหกรรมสิ่งประดิษฐ์คนรุ่นใหม่" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">วันที่จัดงาน</label>
                            <input type="date" name="plan_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">จำนวนที่นั่งต่อแถว (ค่าเริ่มต้น)</label>
                            <input type="number" name="rows" class="form-control" value="20" min="1" max="30" 
       oninput="if(this.value > 30) this.value = 30;" 
       required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary px-4">สร้างทันที</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชัน renamePlan และ deletePlan (JavaScript เดิม)
        function renamePlan(id, oldName) {
            Swal.fire({
                title: 'แก้ไขชื่อแผนผัง',
                input: 'text',
                inputValue: oldName,
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก',
                inputValidator: (value) => { if (!value) return 'กรุณากรอกชื่อแผนผัง'; }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    fetch('api_plan_manager.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ action: 'rename', id: id, name: result.value })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById(`name-plan-${id}`).innerText = result.value;
                            document.querySelector(`#card-plan-${id} .btn-edit`).setAttribute('onclick', `renamePlan(${id}, '${result.value}')`);
                            Swal.fire({icon: 'success', title: 'บันทึกสำเร็จ', showConfirmButton: false, timer: 1500});
                        } else {
                            Swal.fire('Error', data.error || 'เกิดข้อผิดพลาด', 'error');
                        }
                    });
                }
            });
        }

        function deletePlan(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลจะถูกซ่อนไว้ (กู้คืนได้โดย Admin)",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api_plan_manager.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ action: 'delete', id: id })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const card = document.getElementById(`card-plan-${id}`);
                            card.style.transition = 'all 0.5s';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.8)';
                            setTimeout(() => card.remove(), 500);
                            Swal.fire('ลบเรียบร้อย', 'ย้ายไปถังขยะแล้ว', 'success');
                        } else {
                            Swal.fire('Error', data.error || 'เกิดข้อผิดพลาด', 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>