<?php
session_start();
require 'db.php';

// ถ้า Login อยู่แล้ว ให้ดีดไปหน้า Dashboard เลย
if (isset($_SESSION['user_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // ดึงข้อมูล User จาก Username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // ✅ ตรวจสอบรหัสผ่านด้วย password_verify (รองรับ Hash)
    if ($user && password_verify($password, $user['password'])) { 
        // Login สำเร็จ: บันทึก Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Seating Plan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #eef2f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-card { width: 100%; max-width: 400px; padding: 40px; background: white; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .brand-logo { font-size: 3rem; color: #0d6efd; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="login-card text-center">
        <i class="bi bi-person-circle brand-logo"></i>
        <h3 class="fw-bold mb-4">เข้าสู่ระบบจัดการผัง</h3>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger text-start"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-floating mb-3 text-start">
                <input type="text" name="username" class="form-control" id="floatingInput" placeholder="Username" required>
                <label for="floatingInput">ชื่อผู้ใช้งาน</label>
            </div>
            <div class="form-floating mb-4 text-start">
                <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
                <label for="floatingPassword">รหัสผ่าน</label>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fs-5 mb-3">เข้าสู่ระบบ</button>
        </form>
        
        <hr>
        <a href="index.php" class="text-decoration-none text-secondary">
            <i class="bi bi-arrow-left"></i> กลับไปหน้าผังที่นั่ง (Guest)
        </a>
    </div>
</body>
</html>