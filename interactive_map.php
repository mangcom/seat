<?php
session_start(); // เริ่ม Session เพื่อเช็ค Login
require 'db.php';

$plan_id = $_GET['id'] ?? 0;

// ดึงข้อมูล Plan มาก่อน
$stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) die("ไม่พบแผนผัง");

// --- ตรวจสอบสิทธิ์ (Authorization Logic) ---
$can_edit = false; // ค่าเริ่มต้นคือ แก้ไม่ได้

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        $can_edit = true; // Admin แก้ได้หมด
    } elseif ($plan['created_by'] == $_SESSION['user_id']) {
        $can_edit = true; // User แก้ได้เฉพาะของตัวเอง
    }
}

$global_seats_per_row = $plan['seats_per_row'] ?: 30;

$stmt = $pdo->prepare("SELECT * FROM plan_groups WHERE plan_id = ? ORDER BY zone_type, sort_order");
$stmt->execute([$plan_id]);
$raw_groups = $stmt->fetchAll();

$groups = ['exec' => [], 'part' => []];
foreach ($raw_groups as $g) {
    $groups[$g['zone_type']][] = $g;
}

$colorPalette = [
    '#FFCDD2',
    '#F8BBD0',
    '#E1BEE7',
    '#D1C4E9',
    '#C5CAE9',
    '#BBDEFB',
    '#B3E5FC',
    '#B2EBF2',
    '#B2DFDB',
    '#C8E6C9',
    '#DCEDC8',
    '#F0F4C3',
    '#FFF9C4',
    '#FFECB3',
    '#FFE0B2',
    '#FFCCBC',
    '#D7CCC8',
    '#CFD8DC',
    '#E0E0E0'
];
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Editor: <?php echo htmlspecialchars($plan['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #eef2f5;
        }

        .stage-container {
            background: white;
            padding: 40px 20px;
            min-height: 800px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 95%;
            overflow-x: auto;
            white-space: nowrap;
            text-align: center;
        }

        .group-header {
            position: sticky;
            left: 0;
            margin-bottom: 5px;
            margin-top: 15px;
        }

        .theater-row {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
            padding: 5px;
        }

        .seat-block {
            display: flex;
            gap: 6px;
        }

        .aisle-gap {
            width: 70px;
            height: 10px;
            flex-shrink: 0;
            position: relative;
        }

        .aisle-gap::after {
            content: "ทางเดิน";
            font-size: 8px;
            color: #ddd;
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
        }

        .row-number {
            font-weight: bold;
            color: #333;
            font-size: 16px;
            width: 40px;
            text-align: center;
            flex-shrink: 0;
            user-select: none;
            background: #f8f9fa;
            border-radius: 4px;
            padding: 2px 0;
            border: 1px solid #ddd;
        }

        /* ปรับแต่งรูปทรงที่นั่งให้สมส่วน (Square Box) */
        .seat {
            /* 1. ปรับขนาดเป็นแนวตั้ง (Width น้อยกว่า Height) */
            width: 60px;
            /* ขยายความกว้างนิดหน่อยเพื่อให้ชื่อยาวๆ แสดงได้ดีขึ้น */
            height: 82px;
            /* เพิ่มความสูง (เดิม 54px) เพื่อให้มีพื้นที่ด้านบน/ล่าง */
            margin: 4px;

            /* 2. สไตล์เดิม */
            background-color: #ffffff;
            /* เปลี่ยนเป็นขาวล้วนเพื่อให้ดูสะอาดตาขึ้น */
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);

            /* 3. จัดการ Layout ภายใน */
            display: flex;
            flex-direction: column;
            justify-content: center;
            /* จัดกึ่งกลางแนวตั้ง */
            align-items: center;
            position: relative;
            padding-top: 10px;
            /* เพิ่ม Padding ด้านบน เพื่อหนีจากเลขที่นั่ง */
            padding-bottom: 5px;
            /* เพิ่ม Padding ด้านล่าง */
            gap: 2px;
            /* ระยะห่างระหว่าง รูป-ชื่อ-ตำแหน่ง */

            /* 4. เทคนิคป้องกันกล่องเบี้ยว */
            flex-shrink: 0;
            overflow: hidden;
            user-select: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        /* --- เพิ่ม Class เหล่านี้ต่อท้าย .seat --- */
        .seat-block {
            display: flex;
            justify-content: center;
            /* จัดกึ่งกลางถ้าที่นั่งน้อย */
            align-items: center;
            gap: 2px;

            /* ระบบ Scroll แนวนอน */
            overflow-x: auto;
            padding-bottom: 10px;
            max-width: 100%;
            /* ห้ามกว้างเกินจอ */

            /* ตกแต่ง Scrollbar */
            scrollbar-width: thin;
            scrollbar-color: #aaa #f0f0f0;
        }

        .theater-row {
            display: flex;
            align-items: flex-start;
            /* เปลี่ยนเป็น start เพื่อให้ Scrollbar ทำงานถูก */
            margin-bottom: 15px;
            width: 100%;
        }

        .row-number {
            font-weight: bold;
            min-width: 40px;
            text-align: center;
            margin-top: 15px;
            /* ดันเลขแถวลงมาให้ตรงกับที่นั่ง */
            z-index: 10;
            background: #fff;
            position: sticky;
            /* (Optional) ล็อคเลขแถวไว้ */
            left: 0;
        }

        /* เพิ่ม CSS สำหรับแสดงเลขแถวและที่นั่ง */
        .seat-badge-row {
            position: absolute;
            top: 2px;
            /* ขยับลงมานิดนึง */
            left: 4px;
            font-size: 9px;
            /* ตัวเลขใหญ่ขึ้นนิดนึงเพื่อให้อ่านง่าย */
            font-weight: bold;
            color: #999;
        }

        .seat-badge-num {
            position: absolute;
            top: 2px;
            right: 4px;
            font-size: 9px;
            font-weight: bold;
            color: #999;
        }



        .seat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-color: #0d6efd;
            z-index: 5;
            /* ให้ลอยอยู่เหนือเพื่อนข้างๆ */
        }

        .seat.sofa {
            width: 90px;
            border-radius: 12px;
            border-width: 2px;
        }

        /* รูปภาพเล็กในที่นั่ง (วงกลม เหมือนเดิม) */
        .seat-img {
            width: 32px;
            /* ขยายรูปให้ใหญ่ขึ้น (เดิม 24px) */
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 2px;
            background: #f8f9fa;
            border: 1px solid #eee;
            flex-shrink: 0;
            /* ห้ามรูปบี้ */
        }

        .seat-name {
            font-size: 11px;
            /* เพิ่มขนาดตัวอักษรนิดหน่อย (เดิม 10px) */
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
            width: 95%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #333;
        }

        .seat-role {
            font-size: 9px;
            /* เพิ่มขนาด (เดิม 8px) */
            color: #777;
            width: 90%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
        }

        .status-reserved {
            border: 2px solid #ffc107 !important;
            position: relative;
        }

        .status-reserved::after {
            content: "จอง";
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ffc107;
            color: black;
            font-size: 8px;
            padding: 1px 3px;
            border-radius: 4px;
        }

        .status-empty {
            opacity: 0.2;
            border: 1px dashed #999 !important;
            background: transparent !important;
        }

        .seat.ghost {
            visibility: hidden;
            pointer-events: none;
            border: none;
            background: transparent !important;
            box-shadow: none;
        }

        /* --- TOOLTIP STYLE (กล่องขยายใหญ่ขึ้น) --- */
        #seat-tooltip {
            position: fixed;
            display: none;
            z-index: 1050;
            background: white;
            border: 1px solid #ccc;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            padding: 20px;
            width: 260px;
            /* ขยายความกว้างกล่อง */
            text-align: center;
            pointer-events: none;
        }

        /* โหมดเลือกพิมพ์ */
        .seat.selecting {
            border: 2px solid #ccc;
            cursor: pointer;
        }

        .seat.selected {
            border: 2px solid #0d6efd !important;
            background-color: #e7f1ff !important;
            transform: scale(1.05);
            box-shadow: 0 0 5px rgba(13, 110, 253, 0.5);
        }

        /* ซ่อน Checkbox เดิมๆ ถ้ามี */
        #print-toolbar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #343a40;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 1000;
            align-items: center;
            gap: 15px;
        }

        #tooltip-img {
            width: 160px;
            /* เพิ่มขนาดเป็น 1 เท่าตัว (เดิม 80px) */
            height: 160px;
            border-radius: 15px;
            /* เปลี่ยนเป็นสี่เหลี่ยมมุมโค้ง */
            object-fit: cover;
            border: 1px solid #ddd;
            margin-bottom: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        #tooltip-name {
            font-size: 18px;
            font-weight: 400;
            margin-bottom: 5px;
            color: #333;
        }

        #tooltip-role {
            font-size: 16px;
            font-weight: 700;
            color: #000;
        }

        /* กำหนดสไตล์ให้ Toolbar */
        .floating-toolbar {
            position: fixed;
            top: 140px;
            /* อยู่ต่ำกว่าปุ่มกดนิดหน่อย */
            right: 20px;
            width: 260px;
            /* ความกว้างเมนู */
            z-index: 1050;

            /* Animation settings */
            opacity: 0;
            /* เริ่มต้นจางหาย */
            visibility: hidden;
            /* เริ่มต้นมองไม่เห็นกดไม่ได้ */
            transform: translateX(20px);
            /* ขยับไปทางขวานิดหน่อย */
            transition: all 0.3s ease-in-out;
            /* เอฟเฟกต์นุ่มนวล */
        }

        /* คลาสที่จะถูกเติมเมื่อกดเปิด (Show) */
        .floating-toolbar.active {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }


        .stage-box {
            width: 80%;
            /* ความกว้าง 80% ของพื้นที่ */
            height: 60px;
            /* ความสูงของกล่อง */
            background-color: #e0e0e0;
            /* สีพื้นหลังเทา */
            border: 2px solid #999;
            /* ขอบสีเทาเข้ม */
            margin: 0 auto 40px auto;
            /* จัดกึ่งกลาง และเว้นระยะด้านล่าง */
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            /* มุมมนเล็กน้อย */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            /* เงาให้ดูมีมิติ */

            /* ตัวหนังสือ */
            font-size: 18px;
            font-weight: bold;
            color: #555;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* ใส่ใน <style> */

        /* Animation สำหรับ Indicator */
        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
                transform: scale(1);
            }

            70% {
                box-shadow: 0 0 0 20px rgba(220, 53, 69, 0);
                transform: scale(1.1);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
                transform: scale(1);
            }
        }

        .highlight-target {
            animation: pulse-red 2s infinite;
            /* กระพริบตลอดเวลา */
            border: 3px solid #dc3545 !important;
            /* ขอบสีแดงเข้ม */
            z-index: 1050 !important;
            /* ให้อยู่บนสุด */
            position: relative;
        }

        /* เพิ่มให้แน่ใจว่า scroll แล้วเห็นชัดๆ */
        html {
            scroll-behavior: smooth;
        }

        /* แก้ปัญหาที่นั่งบังเมนู: ดันเมนูให้ลอยเหนือทุกสิ่ง */
        #mainToolbar {
            z-index: 9999 !important;
            /* ค่าสูงสุด เพื่อให้อยู่บนสุดเสมอ */
            position: fixed;
            /* ย้ำว่าเป็น fixed */
        }

        /* เพิ่มเติม: ป้องกัน Modal โดนบังด้วย (เผื่อไว้ครับ) */
        .modal {
            z-index: 10000 !important;
            /* Bootstrap Modal ปกติจะ 1055 แต่เผื่อไว้ */
        }

        .selected-print {
            border: 3px solid #28a745 !important;
            /* ขอบสีเขียวหนาๆ */
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5) !important;
            /* เงาเรืองแสง */
            transform: scale(1.15) !important;
            /* ขยายใหญ่ขึ้น */
            z-index: 999 !important;
            /* ลอยทับเพื่อน */
            background-color: #fff !important;
            /* พื้นหลังขาวให้อ่านง่าย */
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div id="seat-tooltip">
        <img id="tooltip-img" src="">
        <div id="tooltip-name"></div>
        <div id="tooltip-role"></div>
    </div>

    <button id="toolbarToggleBtn" class="btn btn-primary rounded-circle shadow" onclick="toggleToolbar()"
        style="position: fixed; top: 80px; right: 20px; width: 50px; height: 50px; z-index: 1060; display: flex; align-items: center; justify-content: center;">
        <i class="bi bi-tools fs-5"></i>
    </button>

    <div class="floating-toolbar shadow p-3 bg-white rounded" id="mainToolbar">

        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <h6 class="fw-bold m-0"><i class="bi bi-tools"></i> เครื่องมือ</h6>
            <button type="button" class="btn-close small" onclick="toggleToolbar()"></button>
        </div>

        <div>
            <button onclick="exportImage()" class="btn btn-primary btn-sm w-100 mb-2">
                <i class="bi bi-camera"></i> บันทึกภาพ
            </button>
        </div>

        <?php if ($can_edit): ?>
            <div>
                <button onclick="savePositions()" class="btn btn-success btn-sm w-100 mb-2">💾 บันทึกตำแหน่ง</button>
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center bg-light border rounded px-2 mb-2" style="height: 38px;">
            <i class="bi bi-zoom-out text-secondary small"></i>
            <input type="range" class="form-range mx-2" min="30" max="80" value="50" id="zoomSlider" style="cursor: pointer;">
            <i class="bi bi-zoom-in text-secondary small"></i>
        </div>

        <div class="dropdown w-100 mb-2">
            <button class="btn btn-outline-dark dropdown-toggle w-100 btn-sm" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-printer"></i> พิมพ์สติกเกอร์
            </button>
            <ul class="dropdown-menu w-100 shadow" style="max-height: 300px; overflow-y: auto;">

                <li>
                    <h6 class="dropdown-header text-primary fw-bold">🅰️ แบบตัวใหญ่ (1 คอลัมน์)</h6>
                </li>
                <li>
                    <a class="dropdown-item" href="#" onclick="printAll('large', 'print_stickers.php')">
                        🖨️ พิมพ์ทั้งหมด
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" onclick="printSelected('large', 'print_stickers.php')">
                        ✅ พิมพ์เฉพาะที่เลือก
                    </a>
                </li>

                <li>
                    <hr class="dropdown-divider">
                </li>

                <li>
                    <h6 class="dropdown-header text-success fw-bold">🅱️ แบบเดิม (2 คอลัมน์)</h6>
                </li>
                <li>
                    <a class="dropdown-item" href="#" onclick="printAll('std', 'print_stickers2c.php')">
                        🖨️ พิมพ์ทั้งหมด
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" onclick="printSelected('std', 'print_stickers2c.php')">
                        ✅ พิมพ์เฉพาะที่เลือก
                    </a>
                </li>

                <li>
                    <hr class="dropdown-divider">
                </li>

                <li>
                    <a class="dropdown-item text-muted small" href="#" onclick="toggleSelectMode()">
                        👆 เปิด/ปิด โหมดจิ้มเลือก
                    </a>
                </li>
            </ul>
            <!-- <ul class="dropdown-menu w-100">
                <li><a class="dropdown-item" href="#" onclick="printAll()">พิมพ์ทั้งหมด (ทั้งผัง)</a></li>
                <li><a class="dropdown-item" href="#" onclick="toggleSelectMode()">เลือกพิมพ์บางคน...</a></li>
            </ul> -->
        </div>

        <?php if ($can_edit): ?>
            <div>
                <button class="btn btn-info btn-sm w-100 mb-2" onclick="openStructureModal()">
                    <i class="bi bi-gear-fill"></i> จัดจำนวนที่นั่ง
                </button>
            </div>
        <?php endif; ?>
        <div>
            <a href="search_seat.php?id=<?php echo $plan_id; ?>" class="btn btn-warning btn-sm w-100 mb-2">
                <i class="bi bi-search"></i> ค้นหาที่นั่ง
            </a>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm w-100">กลับหน้าหลัก</a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="stage-container" id="chart-area">
            <h3 class="text-center mb-4 fw-bold text-dark sticky-left">
                <span id="pageTitle"><?php echo htmlspecialchars($plan['name']); ?></span>
                <?php if ($can_edit): ?>
                    <button onclick="editPageTitle()" class="btn btn-sm btn-outline-secondary ms-2" style="border:none;">
                        <i class="bi bi-pencil"></i>
                    </button>

                <?php endif; ?>
                <div class="mt-1 text-secondary" style="font-size: 0.85rem; font-weight: 300;">
                    <i class="bi bi-code-slash me-1"></i>
                    ออกแบบและพัฒนาโดย นายพรชัย ตุ่นแก้ว วิทยาลัยพณิชยการบางนา
                </div>
            </h3>
            <div class="stage-box">เวที (Stage)&nbsp;&nbsp;<span><a href="search_seat.php?id=<?php echo $plan_id; ?>"><i class="bi bi-search"></i></a></span></div>

            <?php
            $globalRowCounter = 1;

            function renderSeat($g, $bgColor, $rowNo = '', $seatNo = '')
            {
                if (isset($g['is_ghost'])) {
                    echo '<div class="seat ghost"></div>';
                    return;
                }

                $statusClass = ($g['status'] == 'reserved') ? 'status-reserved' : (($g['status'] == 'empty') ? 'status-empty' : '');
                $sofaClass = (isset($g['seat_type']) && $g['seat_type'] == 'sofa') ? 'sofa' : '';
                $style = "background-color: $bgColor;";
                $sName = htmlspecialchars($g['name']);
                $sRole = htmlspecialchars($g['role']);
                $imgSrc = $g['image_path'] ? 'uploads/' . $g['image_path'] : '';

                // --- จุดที่แก้ไข: เพิ่ม class "seat-item" และ data-* ต่างๆ ให้ครบ ---
                echo '
        <div class="seat seat-item ' . $sofaClass . ' ' . $statusClass . '" 
            style="' . $style . '" 
            
            id="seat-guest-' . $g['id'] . '"
            data-id="' . $g['id'] . '" 
            data-guest-id="' . $g['id'] . '"
            
            data-seat-no="' . $seatNo . '"
            data-row-no="' . $rowNo . '"
            data-status="' . $g['status'] . '"

            onclick="openEditModal(this)"
            onmouseenter="showTooltip(this)" 
            onmousemove="moveTooltip(event)" 
            onmouseleave="hideTooltip()">
        
            ' . ($rowNo ? '<div class="seat-badge-row">R' . $rowNo . '</div>' : '') . '
            ' . ($seatNo ? '<div class="seat-badge-num">#' . $seatNo . '</div>' : '') . '

            <input type="hidden" class="d-name" value="' . $sName . '">
            <input type="hidden" class="d-role" value="' . $sRole . '">
            <input type="hidden" class="d-status" value="' . $g['status'] . '">
            <input type="hidden" class="d-img" value="' . $g['image_path'] . '">
            
            ' . ($imgSrc ? '<img src="' . $imgSrc . '" class="seat-img">' : '<div class="seat-img d-flex align-items-center justify-content-center text-muted"><i class="bi bi-person"></i></div>') . '
            
            <div class="seat-name display-name">' . $g['name'] . '</div>
            <div class="seat-role display-role">' . $g['role'] . '</div>
        </div>';
            }

            function renderFullRow($guestsInRow, $groupId, $rowIndex, $color, $displayNumber)
            {
                echo '<div class="theater-row">';
                echo '<div class="row-number me-2">' . $displayNumber . '</div>';
                echo '<div class="seat-block sortable-area" data-group-id="' . $groupId . '" data-row-idx="' . $rowIndex . '-Full">';

                // --- แก้ไขตรงนี้ (เพิ่มตัวนับ $i) ---
                $i = 1;
                foreach ($guestsInRow as $g) {
                    renderSeat($g, $color, $displayNumber, $i); // ส่งค่า $displayNumber และ $i
                    $i++;
                }
                // --------------------------------

                echo '</div>';
                echo '<div class="row-number ms-2">' . $displayNumber . '</div>';
                echo '</div>';
            }

            function renderSplitRow($guestsInRow, $groupId, $rowIndex, $color, $displayNumber)
            {
                $total = count($guestsInRow);
                if ($total == 0) return;
                if ($total % 2 != 0) {
                    $guestsInRow[] = ['is_ghost' => true];
                    $total++;
                }

                $half = $total / 2;
                $leftSide = array_slice($guestsInRow, 0, $half);
                $rightSide = array_slice($guestsInRow, $half);

                echo '<div class="theater-row">';
                echo '<div class="row-number me-2">' . $displayNumber . '</div>';

                // --- แก้ไขตรงนี้ (เพิ่มตัวนับ $i ต่อเนื่องกัน) ---
                $i = 1;

                echo '<div class="seat-block sortable-area" data-group-id="' . $groupId . '" data-row-idx="' . $rowIndex . '-L">';
                foreach ($leftSide as $g) {
                    renderSeat($g, $color, $displayNumber, $i);
                    $i++;
                }
                echo '</div>';

                echo '<div class="aisle-gap"></div>';

                echo '<div class="seat-block sortable-area" data-group-id="' . $groupId . '" data-row-idx="' . $rowIndex . '-R">';
                foreach ($rightSide as $g) {
                    renderSeat($g, $color, $displayNumber, $i);
                    $i++;
                }
                echo '</div>';
                // ------------------------------------------------

                echo '<div class="row-number ms-2">' . $displayNumber . '</div>';
                echo '</div>';
            }

            // --- RENDER ZONES (Copy from previous code) ---
            $colorIndex = 0;
            $isFirstExecGroup = true;
            foreach ($groups['exec'] as $group):
                $bgColor = $colorPalette[$colorIndex % count($colorPalette)];
                $colorIndex++;
            ?>
                <div class="mb-4">
                    <h5 class="text-start ms-5 small fw-bold group-header" style="color:<?php echo $bgColor; ?>; filter: brightness(0.6);">
                        <i class="bi bi-circle-fill"></i> <?php echo $group['name']; ?>
                    </h5>
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = ? ORDER BY sort_order ASC");
                    $stmt->execute([$group['id']]);
                    $allGuests = $stmt->fetchAll();
                    foreach ($allGuests as &$gx) {
                        $gx['seat_type'] = $group['seat_type'];
                    }
                    $seatsPerLine = $group['seats_in_row'];
                    $chunks = array_chunk($allGuests, $seatsPerLine);

                    foreach ($chunks as $idx => $rowGuests) {
                        if ($isFirstExecGroup && $idx == 0) {
                            renderFullRow($rowGuests, $group['id'], $idx, $bgColor, $globalRowCounter);
                        } else {
                            renderSplitRow($rowGuests, $group['id'], $idx, $bgColor, $globalRowCounter);
                        }
                        $globalRowCounter++;
                    }
                    $isFirstExecGroup = false;
                    ?>
                    <?php if ($can_edit): ?>
                        <div class="text-center mt-3 pt-2 border-top">
                            <button onclick="addNewGuest(<?php echo $group['id']; ?>)" class="btn btn-sm btn-outline-primary w-100 border-dashed">
                                <i class="bi bi-plus-circle-dotted"></i> เพิ่มที่นั่งในกลุ่มนี้
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <hr>

            <?php foreach ($groups['part'] as $group):
                $bgColor = $colorPalette[$colorIndex % count($colorPalette)];
                $colorIndex++;
            ?>
                <div class="mb-4">
                    <h5 class="text-start ms-5 small fw-bold group-header" style="color:<?php echo $bgColor; ?>; filter: brightness(0.6);">
                        <i class="bi bi-square-fill"></i> <?php echo $group['name']; ?>
                    </h5>
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = ? ORDER BY sort_order ASC");
                    $stmt->execute([$group['id']]);
                    $allGuests = $stmt->fetchAll();
                    $chunkSize = $global_seats_per_row;
                    $chunks = array_chunk($allGuests, $chunkSize);
                    foreach ($chunks as $idx => $rowGuests) {
                        renderSplitRow($rowGuests, $group['id'], $idx, $bgColor, $globalRowCounter);
                        $globalRowCounter++;
                    }
                    ?>
                    <?php if ($can_edit): ?>
                        <div class="text-center mt-3 pt-2 border-top">
                            <button onclick="addNewGuest(<?php echo $group['id']; ?>)" class="btn btn-sm btn-outline-primary w-100 border-dashed">
                                <i class="bi bi-plus-circle-dotted"></i> เพิ่มที่นั่งในกลุ่มนี้
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white p-2">
                    <h6 class="modal-title">แก้ไขที่นั่ง</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="editId" name="guest_id">

                        <div class="text-center mb-2 position-relative">
                            <img id="previewImg" src="" style="width:80px; height:80px; object-fit:cover; border-radius:12px; border: 1px solid #ddd; display:none;">
                            <div id="noImgPlaceholder" class="text-muted small" style="display:none;">ไม่มีรูปภาพ</div>
                        </div>

                        <div class="mb-2">
                            <label class="small text-muted fw-bold">ชื่อ-นามสกุล</label>
                            <input type="text" class="form-control form-control-sm" id="editName" name="name">
                        </div>
                        <div class="mb-2">
                            <label class="small text-muted fw-bold">ตำแหน่ง/สังกัด</label>
                            <input type="text" class="form-control form-control-sm" id="editRole" name="role">
                        </div>
                        <div class="mb-2">
                            <label class="small text-muted">สถานะ</label>
                            <select class="form-select form-select-sm" id="editStatus" name="status">
                                <option value="normal">ปกติ</option>
                                <option value="reserved">จอง (Reserved)</option>
                                <option value="empty">ว่าง (Empty)</option>
                            </select>
                        </div>

                        <div class="mb-3 p-2 bg-light rounded border">
                            <label class="small text-muted d-block mb-1">รูปภาพ</label>
                            <input type="file" class="form-control form-control-sm mb-2" id="fileInput" name="guest_image" accept="image/*">

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="deleteImageCheck" name="delete_image" value="1">
                                <label class="form-check-label small text-danger" for="deleteImageCheck">
                                    ลบรูปภาพออก
                                </label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-danger me-auto  btn-sm w-100" onclick="confirmDeleteGuest()">
                            <i class="bi bi-trash"></i> ลบที่นั่งนี้
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm w-100">บันทึก</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="structureModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title"><i class="bi bi-diagram-3"></i> จัดการโครงสร้างแผนผัง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="structureForm">
                        <div class="alert alert-info py-2 mb-3">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <label class="fw-bold">จำนวนคอลัมน์สูงสุด (ความกว้างผัง)</label>
                                </div>
                                <div class="col-md-6">
                                    <input type="number" name="seats_per_row" class="form-control"
                                        value="<?php echo $global_seats_per_row; ?>" min="5" max="100">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="fw-bold mb-3">รายการกลุ่มที่นั่ง (แก้ไขชื่อ หรือ ปรับจำนวน)</h6>
                        <div id="groupListContainer">
                            <?php foreach ($raw_groups as $g):
                                // นับจำนวนที่นั่งจริงในกลุ่มนี้
                                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE group_id = ?");
                                $stmtCount->execute([$g['id']]);
                                $currentQty = $stmtCount->fetchColumn();
                            ?>
                                <div class="row g-2 mb-2 group-item" data-id="<?php echo $g['id']; ?>">
                                    <div class="col-md-2">
                                        <select name="groups[<?php echo $g['id']; ?>][type]" class="form-select form-select-sm bg-light" disabled>
                                            <option value="exec" <?php echo $g['zone_type'] == 'exec' ? 'selected' : ''; ?>>ประธาน/VIP</option>
                                            <option value="part" <?php echo $g['zone_type'] == 'part' ? 'selected' : ''; ?>>ผู้ร่วมงาน</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="groups[<?php echo $g['id']; ?>][name]"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($g['name']); ?>" placeholder="ชื่อกลุ่ม">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">จำนวน</span>
                                            <input type="number" name="groups[<?php echo $g['id']; ?>][qty]"
                                                class="form-control text-center"
                                                value="<?php echo $currentQty; ?>" data-old-qty="<?php echo $currentQty; ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="text-end mt-2">
                            <small class="text-muted">* การลดจำนวน จะลบที่นั่งจากท้ายสุดของกลุ่ม</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success" onclick="saveStructure()">
                        <i class="bi bi-save"></i> บันทึกการเปลี่ยนแปลง
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        // ฟังก์ชันเปิด Modal
        function openStructureModal() {
            var myModal = new bootstrap.Modal(document.getElementById('structureModal'));
            myModal.show();
        }

        // ฟังก์ชันบันทึกข้อมูล (เวอร์ชันปลอดภัย มีการแจ้งเตือนก่อนลบ)
        function saveStructure() {
            const form = document.getElementById('structureForm');
            const formData = new FormData(form);

            // เตรียมข้อมูล
            const data = {
                action: 'update_structure',
                id: <?php echo $plan_id; ?>,
                seats_per_row: formData.get('seats_per_row'),
                groups: []
            };

            let warningMessage = "";
            let hasReduction = false;

            // วนลูปเก็บข้อมูล และเช็คว่ามีการลดจำนวนหรือไม่
            document.querySelectorAll('.group-item').forEach(item => {
                const id = item.getAttribute('data-id');
                const nameInput = item.querySelector('input[name*="[name]"]');
                const qtyInput = item.querySelector('input[name*="[qty]"]');

                const name = nameInput.value;
                const newQty = parseInt(qtyInput.value) || 0;
                const oldQty = parseInt(qtyInput.getAttribute('data-old-qty')) || 0;

                // เช็คว่าลดลงไหม?
                if (newQty < oldQty) {
                    const diff = oldQty - newQty;
                    hasReduction = true;
                    // สร้างข้อความเตือน (ใช้ \n เพื่อขึ้นบรรทัดใหม่)
                    warningMessage += `• กลุ่ม "${name}": จะหายไป ${diff} ที่นั่ง (จากท้ายสุด)\n`;
                }

                data.groups.push({
                    id: id,
                    name: name,
                    qty: newQty
                });
            });

            // ฟังก์ชันสำหรับส่งข้อมูล (แยกออกมาเรียกใช้)
            const performSave = () => {
                // แสดง Loading ระหว่างบันทึก
                Swal.fire({
                    title: 'กำลังบันทึก...',
                    didOpen: () => Swal.showLoading()
                });

                fetch('api_plan_manager.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(res => res.json())
                    .then(resData => {
                        if (resData.success) {
                            Swal.fire('บันทึกสำเร็จ', 'โครงสร้างผังถูกอัปเดตแล้ว', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', resData.message || 'บันทึกไม่สำเร็จ', 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                    });
            };

            // --- ตัดสินใจว่าจะบันทึกเลย หรือ เตือนก่อน ---
            if (hasReduction) {
                // กรณีมีการลดจำนวน -> เตือนก่อน!
                Swal.fire({
                    title: '⚠️ ข้อมูลบางส่วนจะถูกลบ!',
                    html: `<div class="text-start">คุณมีการปรับลดจำนวนที่นั่ง ซึ่งจะมีผลกระทบดังนี้:<br><pre class="mt-2 text-danger border p-2 bg-light">${warningMessage}</pre>ยืนยันที่จะทำรายการหรือไม่?</div>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'ยืนยันการลบ',
                    cancelButtonText: 'ยกเลิก / กลับไปแก้ไข'
                }).then((result) => {
                    if (result.isConfirmed) {
                        performSave(); // กดยืนยันถึงจะบันทึก
                    }
                });
            } else {
                // กรณีไม่มีการลบ (เพิ่มหรือเท่าเดิม) -> บันทึกเลย
                performSave();
            }
        }
    </script>
    <div id="print-toolbar">
        <span>เลือกแล้ว <b id="sel-count">0</b> รายชื่อ</span>
        <button class="btn btn-sm btn-light text-dark fw-bold" onclick="printSelected()">
            <i class="bi bi-printer-fill"></i> พิมพ์ที่เลือก
        </button>
        <button class="btn btn-sm btn-outline-light" onclick="toggleSelectMode()">ยกเลิก</button>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 1. รับค่าจาก PHP
        const PLAN_ID = <?php echo $plan_id; ?>;
        const CAN_EDIT = <?php echo $can_edit ? 'true' : 'false'; ?>;
        // --- 1. Tooltip Logic ---
        const tooltip = document.getElementById('seat-tooltip');
        const tooltipImg = document.getElementById('tooltip-img');
        const tooltipName = document.getElementById('tooltip-name');
        const tooltipRole = document.getElementById('tooltip-role');
        // ฟังก์ชันแสดง Tooltip เมื่อเลื่อนเมาส์เข้ามที่นั่ง
        // function showTooltip(el) {
        //     if (el.classList.contains('status-empty')) return;

        //     const name = el.querySelector('.d-name').value;
        //     const role = el.querySelector('.d-role').value;
        //     const imgPath = el.querySelector('.d-img').value;

        //     tooltipName.innerText = name;
        //     tooltipRole.innerText = role;

        //     if (imgPath && imgPath !== 'null' && imgPath !== '') {
        //         tooltipImg.src = 'uploads/' + imgPath;
        //         tooltipImg.style.display = 'inline-block';
        //     } else {
        //         tooltipImg.style.display = 'none';
        //     }
        //     tooltip.style.display = 'block';
        // }
        function showTooltip(el) {
            if (el.classList.contains('status-empty')) return;

            const name = el.querySelector('.d-name').value;
            const role = el.querySelector('.d-role').value;
            const imgPath = el.querySelector('.d-img').value;

            tooltipName.innerText = name;
            tooltipRole.innerText = role;

            // --- ส่วนที่เพิ่ม: บังคับจัดรูปแบบตรงนี้เลยครับ ---
            tooltipName.style.fontWeight = 'bold'; // สั่งให้ชื่อเป็นตัวหนา
            tooltipName.style.fontSize = '1.1rem'; // (แถม) ขยายชื่อให้ใหญ่นิดนึง

            tooltipRole.style.fontWeight = 'normal'; // สั่งให้ตำแหน่งเป็นตัวปกติ
            tooltipRole.style.color = '#0f0f0f'; // (แถม) ปรับสีตำแหน่งให้จางลงนิดนึงจะได้ไม่แข่งกับชื่อ
            // ----------------------------------------------

            if (imgPath && imgPath !== 'null' && imgPath !== '') {
                tooltipImg.src = 'uploads/' + imgPath;
                tooltipImg.style.display = 'inline-block';
            } else {
                tooltipImg.style.display = 'none';
            }
            tooltip.style.display = 'block';
        }

        function moveTooltip(e) {
            tooltip.style.left = (e.clientX + 20) + 'px';
            tooltip.style.top = (e.clientY + 20) + 'px';
        }

        function hideTooltip() {
            tooltip.style.display = 'none';
        }

        // --- 2. Modal & Edit Logic ---
        let currentSeatEl = null;
        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        const deleteCheck = document.getElementById('deleteImageCheck');
        const previewImg = document.getElementById('previewImg');
        const noImgPlaceholder = document.getElementById('noImgPlaceholder');
        const fileInput = document.getElementById('fileInput');

        function openEditModal(el) {
            if (typeof CAN_EDIT !== 'undefined' && CAN_EDIT) {
                if (el.classList.contains('ghost')) return;
                currentSeatEl = el;

                // Reset Form
                document.getElementById('editForm').reset();
                deleteCheck.checked = false;
                fileInput.disabled = false;
                previewImg.style.opacity = '1';

                // ดึงค่ามาใส่
                document.getElementById('editId').value = el.getAttribute('data-id');
                document.getElementById('editName').value = el.querySelector('.d-name').value;
                document.getElementById('editRole').value = el.querySelector('.d-role').value;
                document.getElementById('editStatus').value = el.querySelector('.d-status').value;

                const imgPath = el.querySelector('.d-img').value;
                const guestId = el.getAttribute('data-id');
                currentGuestIdToDelete = guestId;

                if (imgPath && imgPath !== 'null' && imgPath !== '') {
                    // ใส่ ?t=... เพื่อแก้ Cache รูป preview
                    previewImg.src = 'uploads/' + imgPath + '?t=' + new Date().getTime();
                    previewImg.style.display = 'inline-block';
                    noImgPlaceholder.style.display = 'none';
                } else {
                    previewImg.style.display = 'none';
                    noImgPlaceholder.style.display = 'block';
                }

                modal.show();
            }
        }

        deleteCheck.addEventListener('change', function() {
            if (this.checked) {
                previewImg.style.opacity = '0.2';
                fileInput.disabled = true;
            } else {
                previewImg.style.opacity = '1';
                fileInput.disabled = false;
            }
        });

        // --- ส่วนแก้ไขใหม่: การบันทึกข้อมูล ---
        document.getElementById('editForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('api_update_guest.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text()) // อ่านเป็น Text ก่อนเพื่อดูว่ามี Error PHP ปนมาไหม
                .then(text => {
                    try {
                        const data = JSON.parse(text); // พยายามแปลงเป็น JSON

                        if (data.success) {
                            // 1. อัปเดตหน้าจอ
                            updateSeatUI(data.image_path);
                            // 2. ปิด Modal
                            modal.hide();
                        } else {
                            alert('Server Error: ' + data.error);
                        }
                    } catch (err) {
                        // ถ้าแปลง JSON ไม่ได้ แปลว่ามี Error อื่นปนมา ให้แสดงออกมาดู
                        console.error('Server Response:', text);
                        alert('เกิดข้อผิดพลาดจากระบบ (Server Error):\n' + text.substring(0, 100) + '...');
                    }
                })
                .catch(err => {
                    alert('การเชื่อมต่อขัดข้อง (Connection Error)');
                    console.error(err);
                });
        };

        function updateSeatUI(newImgPath) {
            if (!currentSeatEl) return;

            const name = document.getElementById('editName').value;
            const role = document.getElementById('editRole').value;
            const status = document.getElementById('editStatus').value;

            // อัปเดตข้อความ
            currentSeatEl.querySelector('.display-name').innerText = name;
            currentSeatEl.querySelector('.display-role').innerText = role;

            // อัปเดต Hidden Value
            currentSeatEl.querySelector('.d-name').value = name;
            currentSeatEl.querySelector('.d-role').value = role;
            currentSeatEl.querySelector('.d-status').value = status;

            // อัปเดตรูปภาพ (จัดการกรณีมีรูป/ไม่มีรูป/ลบรูป)
            const validImgPath = (newImgPath && newImgPath !== 'null') ? newImgPath : '';
            currentSeatEl.querySelector('.d-img').value = validImgPath;

            let imgTag = currentSeatEl.querySelector('img.seat-img');
            let iconDiv = currentSeatEl.querySelector('div.seat-img');

            if (validImgPath) {
                const newSrc = 'uploads/' + validImgPath + '?t=' + new Date().getTime(); // Anti-cache

                if (imgTag) {
                    imgTag.src = newSrc;
                } else {
                    // ถ้าเดิมไม่มีรูป (เป็นไอคอน) ให้ลบไอคอนแล้วใส่รูป
                    if (iconDiv) iconDiv.remove();

                    imgTag = document.createElement('img');
                    imgTag.className = 'seat-img';
                    imgTag.src = newSrc;

                    // แทรกรูปไปไว้ก่อนชื่อ
                    const nameEl = currentSeatEl.querySelector('.display-name');
                    currentSeatEl.insertBefore(imgTag, nameEl);
                }
            } else {
                // ถ้าไม่มีรูป (ถูกลบ)
                if (imgTag) {
                    imgTag.remove();

                    iconDiv = document.createElement('div');
                    iconDiv.className = 'seat-img d-flex align-items-center justify-content-center text-muted';
                    iconDiv.innerHTML = '<i class="bi bi-person"></i>';

                    const nameEl = currentSeatEl.querySelector('.display-name');
                    currentSeatEl.insertBefore(iconDiv, nameEl);
                }
            }

            // อัปเดตสถานะสี
            currentSeatEl.classList.remove('status-reserved', 'status-empty');
            if (status === 'reserved') currentSeatEl.classList.add('status-reserved');
            if (status === 'empty') currentSeatEl.classList.add('status-empty');
        }

        // --- 3. Drag & Drop + Export ---
        const containers = document.querySelectorAll('.sortable-area');
        containers.forEach(el => {
            if (CAN_EDIT) {
                new Sortable(el, {
                    group: 'shared',
                    animation: 150,
                    ghostClass: 'bg-light',
                    onEnd: function(evt) {
                        saveOrderGlobal(evt.to.getAttribute('data-group-id'));
                    }
                });
            }
        });

        function saveOrderGlobal(groupId) {
            const allSeatsInGroup = document.querySelectorAll(`.sortable-area[data-group-id="${groupId}"] .seat:not(.ghost)`);
            const items = Array.from(allSeatsInGroup).map(seat => seat.getAttribute('data-id'));
            fetch('api_reorder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    group_id: groupId,
                    items: items
                })
            });
        }

        function exportImage() {
            const area = document.getElementById('chart-area');
            hideTooltip();
            const originalOverflow = area.style.overflow;
            const originalWidth = area.style.width;
            area.style.overflow = 'visible';
            area.style.width = 'fit-content';
            html2canvas(area, {
                scale: 2
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Seating-Plan-Final.png';
                link.href = canvas.toDataURL();
                link.click();
                area.style.overflow = originalOverflow;
                area.style.width = originalWidth;
            });
        }

        // ฟังก์ชันแก้ไขชื่อแผนผัง (ตามที่ขอไว้ก่อนหน้า)
        function editPageTitle() {
            const currentName = document.getElementById('pageTitle').innerText;
            const planId = <?php echo isset($plan_id) ? $plan_id : 0; ?>;

            const newName = prompt("แก้ไขชื่อแผนผัง:", currentName);
            if (newName && newName.trim() !== "") {
                fetch('api_plan_manager.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'rename',
                            id: planId,
                            name: newName
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('pageTitle').innerText = newName;
                        } else {
                            alert('Error updating name');
                        }
                    });
            }
        }

        let isSelectionMode = false;
        let selectedSeats = new Set();
        var isSelectMode = false;

        // 2. ฟังก์ชันเปิด/ปิด โหมดเลือก
        function toggleSelectMode() {
            isSelectMode = !isSelectMode; // สลับสถานะ จริง/เท็จ

            const seats = document.querySelectorAll('.seat'); // หาที่นั่งทั้งหมด

            if (isSelectMode) {
                // --- กรณีเปิดโหมด ---
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: '🟢 เปิดโหมดเลือก',
                    text: 'จิ้มที่นั่งที่ต้องการพิมพ์ (สามารถจิ้มซ้ำเพื่อยกเลิก)',
                    showConfirmButton: false,
                    timer: 3000
                });
                document.body.style.cursor = 'crosshair'; // เปลี่ยน cursor เป็นรูปเป้าเล็ง
            } else {
                // --- กรณีปิดโหมด ---
                // ล้างค่าที่เลือกไว้ทั้งหมด
                seats.forEach(s => {
                    s.classList.remove('selected-print');
                    s.style.border = '';
                    s.style.transform = '';
                });

                document.body.style.cursor = 'default';
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '🔴 ปิดโหมดเลือก',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
        // 1. เริ่ม/หยุด โหมดเลือก


        // 2. จัดการการคลิก (Select/Deselect)
        function seatSelectionHandler(e) {
            if (!isSelectionMode) return;

            // หยุดไม่ให้ Modal เด้งขึ้นมา
            e.stopPropagation();
            e.preventDefault();

            const seat = e.currentTarget;
            const id = seat.getAttribute('data-id');

            if (selectedSeats.has(id)) {
                selectedSeats.delete(id);
                seat.classList.remove('selected');
            } else {
                selectedSeats.add(id);
                seat.classList.add('selected');
            }
            updateSelectionUI();
        }

        function updateSelectionUI() {
            document.getElementById('sel-count').innerText = selectedSeats.size;
        }

        // 3. ฟังก์ชันดึงข้อมูลจากหน้าจอและส่งไปพิมพ์
        function gatherSeatData(onlySelected = false) {
            const seats = document.querySelectorAll('.seat:not(.ghost)');
            let data = [];

            seats.forEach(seat => {
                // ถ้าเลือกโหมดเฉพาะที่เลือก แล้วที่นั่งนี้ไม่ได้เลือก -> ข้าม
                if (onlySelected && !selectedSeats.has(seat.getAttribute('data-id'))) return;

                // ดึงข้อมูลจาก DOM ที่เรา render ไว้
                // หมายเหตุ: ต้องมั่นใจว่าใน renderSeat() มี class เหล่านี้อยู่
                const name = seat.querySelector('.d-name')?.value || seat.innerText;
                const role = seat.querySelector('.d-role')?.value || '';
                const rowTxt = seat.querySelector('.seat-badge-row')?.innerText.replace('R', '') || '-';
                const seatTxt = seat.querySelector('.seat-badge-num')?.innerText.replace('#', '') || '-';

                // กรองเฉพาะที่มีคนนั่ง (ไม่ว่าง/ไม่จอง) หรือตามต้องการ
                const status = seat.querySelector('.d-status')?.value;
                // ถ้า status เป็น empty อาจจะไม่พิมพ์ หรือพิมพ์บัตรเปล่า แล้วแต่ตกลง
                // ในที่นี้สมมติพิมพ์หมดถ้ามีชื่อ

                data.push({
                    name: name,
                    role: role,
                    rowNo: rowTxt,
                    seatNo: seatTxt
                });
            });
            return data;
        }

        // 4. ส่งข้อมูลไปหน้า print_stickers.php (POST)
        function sendToPrint(guests) {
            if (guests.length === 0) {
                Swal.fire('ไม่มีรายการ', 'กรุณาเลือกที่นั่งอย่างน้อย 1 ที่', 'warning');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'print_stickers.php';
            form.target = '_blank'; // เปิดแท็บใหม่

            // ส่ง JSON ไป
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'json_data'; // หมายเหตุ: PHP รับแบบ Raw POST body ก็ได้ หรือรับแบบ form field ก็ได้

            // ** แก้ PHP ด้านบนนิดนึง ให้รับ $_POST['json_data'] ได้ด้วย **
        }

        // --- แก้ไขฟังก์ชัน sendToPrint ใหม่ เพื่อให้เข้ากับ PHP ---
        function postData(url, data) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            form.target = '_blank';

            const jsonInput = document.createElement('input');
            jsonInput.type = 'hidden';
            jsonInput.name = 'payload'; // ชื่อ field
            jsonInput.value = JSON.stringify(data);
            form.appendChild(jsonInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // ฟังก์ชันสั่งพิมพ์ (ทั้งหมด)
        function printAll(mode, targetFile = 'print_stickers.php') {
            // เลือก .seat ทั้งหมด
            const guests = collectGuests(document.querySelectorAll('.seat'));
            if (guests.length === 0) return Swal.fire('ไม่พบข้อมูล', 'ไม่มีที่นั่งที่มีคนนั่ง', 'warning');
            sendToPrintPage(guests, mode, targetFile);
        }

        // ฟังก์ชันสั่งพิมพ์ (เฉพาะที่เลือก)
        function printSelected(mode, targetFile = 'print_stickers.php') {
            const selectedSeats = document.querySelectorAll('.seat.selected-print');
            if (selectedSeats.length === 0) {
                Swal.fire({
                        title: 'ยังไม่ได้เลือก',
                        text: 'กรุณาเปิดโหมดเลือกก่อน',
                        icon: 'info'
                    })
                    .then(() => {
                        if (!isSelectMode) toggleSelectMode();
                    });
                return;
            }
            const guests = collectGuests(selectedSeats);
            sendToPrintPage(guests, mode, targetFile);
        }

        // ฟังก์ชันส่ง Form ไปหน้าพิมพ์
        function sendToPrintPage(guestsData, mode, targetFile) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = targetFile;
            form.target = '_blank';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'payload';
            input.value = JSON.stringify({
                title: document.title,
                guests: guestsData,
                mode: mode
            });

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // --- ส่วนที่เพิ่ม: ฟังก์ชันปรับขนาด (Zoom) ---
        const zoomSlider = document.getElementById('zoomSlider');
        if (zoomSlider) {
            zoomSlider.addEventListener('input', function(e) {
                const size = e.target.value + 'px';
                const fontSize = (e.target.value / 3.5) + 'px'; // คำนวณขนาดตัวอักษร

                // ปรับทุกที่นั่งในหน้าจอ
                document.querySelectorAll('.seat').forEach(seat => {
                    seat.style.width = size;
                    seat.style.height = size;

                    // ปรับขนาดตัวหนังสือชื่อ
                    const nameDiv = seat.querySelector('.seat-name');
                    if (nameDiv) nameDiv.style.fontSize = fontSize;
                });
            });
        }

        function addNewGuest(groupId) {
            // แสดง Loading เล็กน้อย หรือกันกดซ้ำ
            Swal.fire({
                title: 'กำลังเพิ่มที่นั่ง...',
                didOpen: () => Swal.showLoading()
            });

            fetch('api_plan_manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'add_guest',
                        id: <?php echo $plan_id; ?>, // ส่ง plan_id ไปเช็คสิทธิ์
                        group_id: groupId
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.close();
                        location.reload(); // รีโหลดหน้าเพื่อแสดงที่นั่งใหม่
                    } else {
                        Swal.fire('Error', data.message || 'ไม่สามารถเพิ่มได้', 'error');
                    }
                });
        }
        // ฟังก์ชันลบคน (เรียกใช้ตอนกดลบ)
        function deleteGuest(guestId) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ที่นั่งนี้จะหายไปทันที",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ลบทิ้ง',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api_plan_manager.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'delete_guest',
                                id: <?php echo $plan_id; ?>,
                                guest_id: guestId
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // ลบ Element ออกจากหน้าจอทันที หรือ Reload
                                location.reload();
                            } else {
                                Swal.fire('Error', 'ลบไม่สำเร็จ', 'error');
                            }
                        });
                }
            });
        }

        // ตัวแปรเก็บ ID ที่กำลังแก้ไข
        let currentEditingGuestId = 0;
        let currentGuestIdToDelete = 0;

        function editGuest(id, name, role, ...others) {
            currentEditingGuestId = id; // จำ ID ไว้
            // ... logic เดิมที่ set ค่าใส่ form ...
            var myModal = new bootstrap.Modal(document.getElementById('editGuestModal'));
            myModal.show();
        }

        function confirmDeleteGuest() {
            // 1. เช็คว่ามี ID ไหม
            if (!currentGuestIdToDelete || currentGuestIdToDelete == 0) {
                Swal.fire('Error', 'ไม่พบรหัสที่นั่ง', 'error');
                return;
            }

            // 2. ถามยืนยัน
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลและรูปภาพจะหายไปถาวร",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ลบทิ้ง',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {

                    // 3. ส่งข้อมูลไป API
                    fetch('api_plan_manager.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'delete_guest',
                                id: <?php echo $plan_id; ?>, // ต้องส่ง Plan ID ไปเช็คสิทธิ์
                                guest_id: currentGuestIdToDelete // ID ที่นั่งที่จะลบ
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                location.reload(); // ลบเสร็จรีโหลดหน้าจอ
                            } else {
                                Swal.fire('ลบไม่สำเร็จ', data.message || 'Error', 'error');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                        });
                }
            });
        }
        // ฟังก์ชันปรับขนาด Grid (จำนวนคอลัมน์)
        function updateGridSize(newSize) {
            if (newSize < 5) return; // กันค่าน้อยเกินไป

            // 1. เปลี่ยน CSS หน้าจอทันที (ไม่ต้องรอรีโหลด)
            const gridContainer = document.querySelector('.seat-grid'); // หรือ ID ของ div ที่คลุมที่นั่ง
            if (gridContainer) {
                gridContainer.style.gridTemplateColumns = `repeat(${newSize}, 1fr)`;
            }

            // 2. ส่งค่าไปบันทึกในฐานข้อมูล
            fetch('api_plan_manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'update_settings',
                        id: <?php echo $plan_id; ?>,
                        seats_per_row: newSize
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert('บันทึกค่าไม่สำเร็จ');
                    } else {
                        console.log('Grid updated to ' + newSize);
                    }
                });
        }

        // ฟังก์ชัน เปิด/ปิด Toolbar
        function toggleToolbar() {
            const toolbar = document.getElementById('mainToolbar');
            toolbar.classList.toggle('active');
        }
        // ใช้ addEventListener แบบนี้จะชัวร์กว่า onclick
        document.addEventListener('click', function(e) {
            // ถ้าไม่ได้เปิดโหมดเลือก ให้จบการทำงานทันที (ปล่อยให้คลิกเปิด Modal ตามปกติ)
            if (!isSelectMode) return;

            // ค้นหาว่าสิ่งที่คลิก คือ .seat หรือไม่ (รวมถึงลูกหลานของมัน)
            const seat = e.target.closest('.seat');

            // ถ้าเจอที่นั่ง และ ที่นั่งนั้นไม่ใช่ที่ว่าง/ที่ผี
            if (seat && !seat.classList.contains('ghost') && !seat.classList.contains('status-empty')) {

                // *** คำสั่งสำคัญ: ห้ามเปิด Modal แก้ไข ***
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                // สลับ Class (เลือก/ไม่เลือก)
                seat.classList.toggle('selected-print');

                // Feedback เสียง หรือ Console (ถ้าต้องการ)
                console.log('Toggle Seat:', seat.getAttribute('data-id'));
            }
        }, true); // true = ใช้ Capture Phase (ดักจับก่อน event อื่นเสมอ)

        // ฟังก์ชันรวบรวมข้อมูลแขก (ตัวแก้ปัญหาหลัก)
        function collectGuests(seatNodes) {
            const data = [];
            seatNodes.forEach(seat => {
                // ข้ามที่นั่งว่าง หรือ ghost
                if (!seat || seat.classList.contains('status-empty') || seat.classList.contains('ghost')) return;

                // พยายามดึงข้อมูลจากหลายๆ ที่ (รองรับทั้งไฟล์เก่าและใหม่)
                // 1. ชื่อ
                let name = seat.getAttribute('data-name');
                if (!name) name = seat.querySelector('.d-name')?.value; // ดึงจาก hidden input เดิม
                if (!name) name = seat.querySelector('.seat-name')?.innerText;

                // 2. ตำแหน่ง
                let role = seat.getAttribute('data-role');
                if (!role) role = seat.querySelector('.d-role')?.value; // ดึงจาก hidden input เดิม
                if (!role) role = seat.querySelector('.seat-role')?.innerText;

                // 3. เลขที่นั่ง (#)
                let seatNo = seat.getAttribute('data-seat-no');
                if (!seatNo) {
                    let badge = seat.querySelector('.seat-badge-num');
                    if (badge) seatNo = badge.innerText.replace('#', '');
                }

                // 4. แถว (R)
                let rowNo = seat.getAttribute('data-row-no');
                if (!rowNo) {
                    let badge = seat.querySelector('.seat-badge-row');
                    if (badge) rowNo = badge.innerText.replace('R', '');
                }

                if (name) {
                    data.push({
                        name: name.trim(),
                        role: role ? role.trim() : '',
                        seatNo: seatNo || '-',
                        rowNo: rowNo || '-'
                    });
                }
            });
            return data;
        }
        // --- 3. ส่วนเสริมอื่นๆ (Scroll to Highlight) ---
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const highlightId = urlParams.get('highlight');
            if (highlightId) {
                // พยายามหาจาก ID หรือ Data Attribute
                let targetSeat = document.getElementById('seat-guest-' + highlightId);
                if (!targetSeat) targetSeat = document.querySelector(`.seat[data-id="${highlightId}"]`);

                if (targetSeat) {
                    setTimeout(() => {
                        targetSeat.scrollIntoView({
                            behavior: "smooth",
                            block: "center"
                        });
                    }, 500);
                    targetSeat.classList.add('highlight-target');
                    // targetSeat.style.animation = 'pulse-red 2s infinite'; // บังคับ Animation
                }
            }
        });
    </script>

</body>

</html>