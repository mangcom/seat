<?php
require 'db.php';
// สร้าง User: admin / Pass: 1234
$pass = password_hash("1234", PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password) VALUES ('admin', '$pass')";
try {
    $pdo->exec($sql);
    echo "สร้าง User 'admin' เรียบร้อย! รหัสผ่านคือ 1234";
} catch (PDOException $e) {
    echo "Error (User อาจจะมีอยู่แล้ว): " . $e->getMessage();
}
?>