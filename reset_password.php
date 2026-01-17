<?php
require 'db.php';

// กำหนด Username และ Password ที่ต้องการ
$username = 'admin';
$password = '1234'; // รหัสผ่านใหม่ที่ต้องการ

// เข้ารหัสรหัสผ่าน
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. ลบ User admin เดิมทิ้งก่อน (เพื่อความชัวร์)
    $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
    $stmt->execute([$username]);

    // 2. สร้าง User admin ใหม่
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    if ($stmt->execute([$username, $hashed_password])) {
        echo "<h1>✅ รีเซ็ตรหัสผ่านสำเร็จ!</h1>";
        echo "<p>Username: <b>$username</b></p>";
        echo "<p>Password: <b>$password</b></p>";
        echo "<br><a href='login.php'>คลิกที่นี่เพื่อไปหน้า Login</a>";
    } else {
        echo "❌ เกิดข้อผิดพลาดในการสร้าง User";
    }

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage();
}
?>