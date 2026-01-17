<?php
// db.php

// 1. กำหนด Path (ปรับเลข 1 หรือลบ dirname ตามโครงสร้างโฟลเดอร์จริงของคุณ)
// สมมติว่า db.php อยู่ในโฟลเดอร์ root เดียวกับ .env ให้ใช้บรรทัดนี้:
$envPath = __DIR__ . '/.env'; 

// แต่ถ้า db.php อยู่ในโฟลเดอร์ย่อย (เช่น config/) ให้ใช้บรรทัดนี้แทน:
// $envPath = dirname(__DIR__, 1) . '/.env';

$env = [];

// 2. พยายามอ่านไฟล์ .env ถ้ามี
if (file_exists($envPath)) {
    // ใช้ parse_ini_file และปิด warning หากไฟล์ format ไม่เป๊ะ
    $env = @parse_ini_file($envPath);
}

// 3. ฟังก์ชันดึงค่า (Priority: เอาจาก Environment ของ Docker ก่อน -> ถ้าไม่มีค่อยเอาจากไฟล์ .env -> ถ้าไม่มีเอาค่า Default)
function getEnvValue($key, $default, $fileEnv) {
    // ลองดึงจาก Server Environment (Docker)
    $val = getenv($key);
    if ($val !== false) return $val;

    // ลองดึงจากไฟล์ .env ที่อ่านมา
    if (isset($fileEnv[$key])) return $fileEnv[$key];

    // ถ้าไม่มีเลย ใช้ค่า default
    return $default;
}

// 4. กำหนดค่าตัวแปร
$host     = getEnvValue('DB_HOST', 'mariadb_container', $env); // Default เดิมของคุณ
$dbname   = getEnvValue('DB_NAME', 'seating_db', $env);
$username = getEnvValue('DB_USER', 'root', $env);
$password = getEnvValue('DB_PASS', 'bnccitconfig', $env);
$charset  = 'utf8mb4';

try {
    // เพิ่ม Port ด้วยเผื่อจำเป็น (MySQL Default 3306)
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // ไม่จำเป็นต้อง exec set names แยก ถ้าใส่ใน DSN แล้ว แต่ใส่ไว้ก็ไม่เสียหาย
    // $pdo->exec("set names $charset"); 

} catch (PDOException $e) {
    // ใน Production ไม่ควร echo $e->getMessage() ออกหน้าจอตรงๆ เพราะอาจเผย Password
    // ควรเก็บลง Log แทน
    error_log("Database Connection Failed: " . $e->getMessage());
    die("Database Connection Error. Please check logs.");
}
?>