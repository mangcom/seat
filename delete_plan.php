<?php
require 'db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // ลบ Plan (เนื่องจากเราตั้ง ON DELETE CASCADE ใน Database ไว้แล้ว 
    // ข้อมูลใน plan_groups และ guests จะหายไปเองอัตโนมัติครับ)
    $stmt = $pdo->prepare("DELETE FROM plans WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: index.php");
exit;
?>