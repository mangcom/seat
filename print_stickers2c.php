<?php
// print_stickers.php

// 1. พยายามรับค่าจาก POST (payload) ที่ส่งมาจาก JavaScript
$payload = $_POST['payload'] ?? '';

if (empty($payload)) {
    // 2. ถ้าไม่มี ให้ลองอ่านจาก Raw Input (เผื่อส่งมาแบบ JSON stream)
    $payload = file_get_contents('php://input');
}

// 3. แปลง JSON เป็น Array
$data = json_decode($payload, true);

// 4. ตรวจสอบข้อมูล
if (!$data || empty($data['guests'])) {
    // ถ้าไม่มีข้อมูลจริงๆ ให้หยุดทำงาน
    die("<h3>Error: ไม่มีข้อมูลสำหรับพิมพ์</h3><p>กรุณากลับไปเลือกที่นั่งแล้วกดพิมพ์ใหม่อีกครั้ง</p>");
}

$title = $data['title'] ?? 'Seating Plan';
$guests = $data['guests'];

// --- (ส่วนที่ซ้ำซ้อนเดิม ถูกลบออกแล้ว) ---

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>พิมพ์สติกเกอร์ - <?php echo htmlspecialchars($title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* ตั้งค่าหน้ากระดาษ A4 */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 1cm;
            background: white;
            box-sizing: border-box;
        }

        /* Container สำหรับ 1 คน (1 แถวยาว) */
        .sticker-row {
            width: 100%;
            height: 28mm;
            border-bottom: 1px dashed #999;
            /* เปลี่ยนเป็นขีดเส้นใต้แทนกรอบ เพื่อความประหยัดหมึก */
            display: flex;
            page-break-inside: avoid;
            box-sizing: border-box;
            margin-bottom: 5px;
            padding-bottom: 5px;
        }

        /* แบ่งครึ่ง ซ้าย-ขวา */
        .sticker-half {
            width: 50%;
            height: 100%;
            position: relative;
            border-right: 1px dashed #ccc;
            padding: 5px 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .sticker-half:last-child {
            border-right: none;
        }

        /* มุมซ้ายบน */
        .corner-top-left {
            position: absolute;
            top: 2px;
            left: 5px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            border: 1px solid #333;
            padding: 1px 5px;
            border-radius: 3px;
        }

        /* มุมขวาบน */
        .corner-top-right {
            position: absolute;
            top: 2px;
            right: 5px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            background-color: #eee;
            padding: 1px 5px;
            border-radius: 3px;
        }

        .guest-name {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 5px;
            margin-top: 10px;
        }

        .guest-role {
            font-size: 16px;
            color: #555;
            font-weight: 400;
        }

        @media print {
            .no-print {
                display: none;
            }
        }

        .btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0d6efd;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Sarabun';
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .btn-print:hover {
            background: #0b5ed7;
        }
    </style>
</head>

<body>

    <button onclick="window.print()" class="no-print btn-print">🖨️ สั่งพิมพ์ (Print)</button>

    <?php foreach ($guests as $g): ?>
        <div class="sticker-row">
            <div class="sticker-half">
                <div class="corner-top-left">No. <?php echo $g['seatNo']; ?></div>
                <div class="corner-top-right">Row <?php echo $g['rowNo']; ?></div>
                <div class="guest-name"><?php echo htmlspecialchars($g['name']); ?></div>
                <div class="guest-role"><?php echo htmlspecialchars($g['role']); ?></div>
            </div>

            <div class="sticker-half">
                <div class="corner-top-left">No. <?php echo $g['seatNo']; ?></div>
                <div class="corner-top-right">Row <?php echo $g['rowNo']; ?></div>
                <div class="guest-name"><?php echo htmlspecialchars($g['name']); ?></div>
                <div class="guest-role"><?php echo htmlspecialchars($g['role']); ?></div>
            </div>
        </div>
    <?php endforeach; ?>

</body>

</html>