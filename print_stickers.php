<?php
// print_stickers.php

// 1. รับค่า Payload (เหมือนเดิม)
$payload = $_POST['payload'] ?? '';
if (empty($payload)) {
    $payload = file_get_contents('php://input');
}
$data = json_decode($payload, true);

if (!$data || empty($data['guests'])) {
    die("<h3>Error: ไม่มีข้อมูลสำหรับพิมพ์</h3>");
}

$title = $data['title'] ?? 'Seating Plan';
$guests = $data['guests'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>พิมพ์รายชื่อ - <?php echo htmlspecialchars($title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* ตั้งค่าหน้ากระดาษ A4 */
        @page {
            size: A4;
            margin: 10mm;
            /* เว้นขอบกระดาษเล็กน้อยเพื่อความสวยงาม */
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Sarabun', sans-serif;
            background: white;
            color: #000;
        }

        /* กล่องคลุมแต่ละรายชื่อ */
        .sticker-row {
            display: block;
            width: 100%;
            padding: 20px 10px;
            /* ระยะห่างบนล่าง */
            border-bottom: 1px dashed #999;
            /* เส้นประคั่นแต่ละคน */
            position: relative;
            box-sizing: border-box;

            /* หัวใจสำคัญ: ห้ามตัดบรรทัดกลางคน ถ้าที่เหลือไม่พอให้ยกไปหน้าใหม่เลย */
            page-break-inside: avoid;
        }

        /* จัดข้อมูลให้อยู่กึ่งกลาง */
        .content-wrapper {
            text-align: center;
        }

        /* ชื่อแขก (ขยายใหญ่ขึ้น 50%) */
        .guest-name {
            font-size: 36px;
            /* จากเดิมประมาณ 24px -> 36px */
            font-weight: bold;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        /* ตำแหน่ง (ปรับให้สมส่วน) */
        .guest-role {
            font-size: 24px;
            /* ขนาดรองลงมา */
            color: #333;
            font-weight: 500;
        }

        /* ข้อมูลมุมซ้าย/ขวา (เลขที่นั่ง) */
        .corner-info {
            position: absolute;
            top: 5px;
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }

        .info-left {
            left: 5px;
        }

        .info-right {
            right: 5px;
        }

        /* ปุ่มพิมพ์ (ซ่อนเวลาสั่ง Print) */
        .btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0d6efd;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            font-family: 'Sarabun', sans-serif;
        }

        .btn-print:hover {
            background: #0b5ed7;
            transform: scale(1.05);
        }

        @media print {
            .btn-print {
                display: none;
            }

            body {
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <button onclick="window.print()" class="no-print btn-print">🖨️ สั่งพิมพ์ (Print)</button>

    <?php foreach ($guests as $g): ?>
        <div class="sticker-row">

            <div class="corner-info info-left">
                No. <?php echo $g['seatNo']; ?>
            </div>

            <div class="corner-info info-right">
                Row <?php echo $g['rowNo']; ?>
            </div>

            <div class="content-wrapper">
                <div class="guest-name"><?php echo htmlspecialchars($g['name']); ?></div>
                <div class="guest-role"><?php echo htmlspecialchars($g['role']); ?></div>
            </div>

        </div>
    <?php endforeach; ?>

</body>

</html>