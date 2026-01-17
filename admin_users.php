<?php
session_start();
require 'db.php';

// เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// ดึงข้อมูล User ทั้งหมด
$stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style> body { font-family: 'Sarabun', sans-serif; background: #f8f9fa; } </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand"><i class="bi bi-people-fill"></i> จัดการผู้ใช้งาน</span>
        <div>
            <span class="text-white me-3">สวัสดี, <?php echo $_SESSION['username']; ?></span>
            <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">กลับ Dashboard</a>
            <button class="btn btn-outline-warning btn-sm" onclick="openChangeOwnPassModal()">เปลี่ยนรหัสผ่านตัวเอง</button>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary">รายชื่อผู้ใช้งานในระบบ</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus"></i> เพิ่มผู้ใช้งาน
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Username</th>
                        <th>Role</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($users as $u): ?>
                    <tr class="<?php echo ($u['is_active'] == 0) ? 'table-secondary text-muted' : ''; ?>">
                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($u['username']); ?></td>
                        <td>
                            <?php if($u['role']=='admin'): ?>
                                <span class="badge bg-danger">ADMIN</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark">USER</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($u['is_active'] == 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-dark me-1" onclick="openResetModal(<?php echo $u['id']; ?>, '<?php echo $u['username']; ?>')">
                                <i class="bi bi-key"></i> เปลี่ยนรหัส
                            </button>
                            
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <?php if($u['is_active'] == 1): ?>
                                    <button class="btn btn-sm btn-warning" onclick="toggleStatus(<?php echo $u['id']; ?>)">
                                        <i class="bi bi-pause-circle"></i> ระงับ
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="toggleStatus(<?php echo $u['id']; ?>)">
                                        <i class="bi bi-play-circle"></i> เปิดใช้งาน
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">เพิ่มผู้ใช้งานใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" id="new_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" id="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Role</label>
                        <select id="new_role" class="form-select">
                            <option value="user">User (ทั่วไป)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">บันทึก</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ตั้งรหัสผ่านใหม่ให้: <span id="reset_username_show" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reset_user_id">
                <div class="mb-3">
                    <label>รหัสผ่านใหม่</label>
                    <input type="text" id="reset_new_password" class="form-control" placeholder="พิมพ์รหัสผ่านใหม่ที่นี่">
                </div>
                <button onclick="confirmResetPass()" class="btn btn-warning w-100">บันทึกรหัสผ่านใหม่</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ownPassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">เปลี่ยนรหัสผ่านของฉัน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>รหัสผ่านเดิม</label>
                    <input type="password" id="own_old_pass" class="form-control">
                </div>
                <div class="mb-3">
                    <label>รหัสผ่านใหม่</label>
                    <input type="password" id="own_new_pass" class="form-control">
                </div>
                <button onclick="confirmOwnPass()" class="btn btn-success w-100">ยืนยัน</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // 1. เพิ่มผู้ใช้
    document.getElementById('addUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const u = document.getElementById('new_username').value;
        const p = document.getElementById('new_password').value;
        const r = document.getElementById('new_role').value;

        fetch('api_user_manager.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'add_user', username: u, password: p, role: r })
        }).then(res => res.json()).then(data => {
            if(data.success) {
                Swal.fire('สำเร็จ', 'เพิ่มผู้ใช้เรียบร้อย', 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    });

    // 2. ระงับ/เลิกระงับ
    function toggleStatus(id) {
        fetch('api_user_manager.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'toggle_status', id: id })
        }).then(res => res.json()).then(data => {
            if(data.success) location.reload();
            else Swal.fire('Error', data.message, 'error');
        });
    }

    // 3. Admin รีเซ็ตรหัสคนอื่น
    const resetModal = new bootstrap.Modal(document.getElementById('resetModal'));
    function openResetModal(id, name) {
        document.getElementById('reset_user_id').value = id;
        document.getElementById('reset_username_show').innerText = name;
        document.getElementById('reset_new_password').value = '';
        resetModal.show();
    }
    function confirmResetPass() {
        const id = document.getElementById('reset_user_id').value;
        const pass = document.getElementById('reset_new_password').value;
        if(!pass) return Swal.fire('แจ้งเตือน', 'กรุณากรอกรหัสผ่าน', 'warning');

        fetch('api_user_manager.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'admin_reset_pass', id: id, new_password: pass })
        }).then(res => res.json()).then(data => {
            if(data.success) {
                resetModal.hide();
                Swal.fire('สำเร็จ', 'เปลี่ยนรหัสผ่านให้ User แล้ว', 'success');
            }
        });
    }

    // 4. เปลี่ยนรหัสตัวเอง
    const ownModal = new bootstrap.Modal(document.getElementById('ownPassModal'));
    function openChangeOwnPassModal() { ownModal.show(); }
    function confirmOwnPass() {
        const oldP = document.getElementById('own_old_pass').value;
        const newP = document.getElementById('own_new_pass').value;
        
        fetch('api_user_manager.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'change_own_pass', old_password: oldP, new_password: newP })
        }).then(res => res.json()).then(data => {
            if(data.success) {
                ownModal.hide();
                Swal.fire('สำเร็จ', 'เปลี่ยนรหัสผ่านเรียบร้อย', 'success');
            } else {
                Swal.fire('ไม่สำเร็จ', data.message, 'error');
            }
        });
    }
</script>
</body>
</html>