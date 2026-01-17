<?php
// ... (ส่วน PHP เชื่อมต่อฐานข้อมูล คงเดิมเหมือนไฟล์ก่อนหน้า) ...
require 'db.php';
$plan_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

$global_seats_per_row = $plan['seats_per_row'] ?: 30;

$stmt = $pdo->prepare("SELECT * FROM plan_groups WHERE plan_id = ? ORDER BY zone_type, sort_order");
$stmt->execute([$plan_id]);
$raw_groups = $stmt->fetchAll();

$groups = ['exec' => [], 'part' => []];
foreach ($raw_groups as $g) {
    $groups[$g['zone_type']][] = $g;
}

$colorPalette = [
    '#FFCDD2', '#F8BBD0', '#E1BEE7', '#D1C4E9', '#C5CAE9',
    '#BBDEFB', '#B3E5FC', '#B2EBF2', '#B2DFDB', '#C8E6C9',
    '#DCEDC8', '#F0F4C3', '#FFF9C4', '#FFECB3', '#FFE0B2',
    '#FFCCBC', '#D7CCC8', '#CFD8DC', '#E0E0E0'
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
        body { font-family: 'Sarabun', sans-serif; background: #eef2f5; }
        
        .stage-container { 
            background: white; padding: 40px 20px; min-height: 800px; 
            box-shadow: 0 0 15px rgba(0,0,0,0.1); margin: 20px auto; 
            max-width: 95%; overflow-x: auto; white-space: nowrap; text-align: center;
        }
        
        .group-header { position: sticky; left: 0; margin-bottom: 5px; margin-top: 15px; }
        .theater-row { display: flex; justify-content: center; align-items: center; margin-bottom: 12px; padding: 5px; }
        .seat-block { display: flex; gap: 6px; }
        .aisle-gap { width: 70px; height: 10px; flex-shrink: 0; position:relative;}
        .aisle-gap::after { content: "ทางเดิน"; font-size: 8px; color: #ddd; position: absolute; top: -10px; left: 50%; transform: translateX(-50%); }

        .row-number {
            font-weight: bold; color: #333; font-size: 16px; 
            width: 40px; text-align: center; flex-shrink: 0;
            user-select: none; background: #f8f9fa; border-radius: 4px; padding: 2px 0; border: 1px solid #ddd;
        }

        .seat {
            width: 65px; height: 75px; 
            border: 1px solid rgba(0,0,0,0.1); border-radius: 8px; 
            display: inline-flex; flex-direction: column; 
            align-items: center; justify-content: flex-start;
            position: relative; user-select: none; transition: all 0.2s;
            white-space: normal; vertical-align: top; cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 4px 2px;
            overflow: hidden;
        }
        /* เพิ่ม CSS สำหรับแสดงเลขแถวและที่นั่ง */
.seat-badge-row {
    position: absolute;
    top: 2px;
    left: 4px;
    font-size: 9px;
    font-weight: bold;
    color: #555;
    opacity: 0.6;
    pointer-events: none; /* ไม่ให้บังการคลิก */
}

.seat-badge-num {
    position: absolute;
    top: 2px;
    right: 4px;
    font-size: 9px;
    font-weight: bold;
    color: #555;
    opacity: 0.6;
    pointer-events: none;
}

/* ปรับที่นั่งให้รองรับ position absolute */
.seat {
    /* ... ค่าเดิม ... */
    position: relative; /* สำคัญมาก: ต้องมีบรรทัดนี้ */
}
        .seat:hover { border-color: #666; background: #fff !important; }
        .seat.sofa { width: 90px; border-radius: 12px; border-width: 2px; }
        
        /* รูปภาพเล็กในที่นั่ง (วงกลม เหมือนเดิม) */
        .seat-img { 
            width: 24px; height: 24px; border-radius: 50%; /* วงกลม */
            object-fit: cover; margin-bottom: 3px; 
            background: rgba(255,255,255,0.8); border: 1px solid rgba(0,0,0,0.1); flex-shrink: 0;
        }

        .seat-name { 
            font-size: 9px; font-weight: 400; color: #333; line-height: 1.1; width: 100%; text-align: center;
            overflow: hidden; white-space: nowrap; text-overflow: ellipsis; margin-bottom: 1px;
        }
        .seat-role { 
            font-size: 8.5px; font-weight: 700; color: #000; line-height: 1.1; 
            max-height: 20px; overflow: hidden; width: 100%; text-align: center;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }

        .status-reserved { border: 2px solid #ffc107 !important; position: relative; }
        .status-reserved::after { content: "จอง"; position: absolute; top: -5px; right: -5px; background: #ffc107; color: black; font-size: 8px; padding: 1px 3px; border-radius: 4px; }
        .status-empty { opacity: 0.2; border: 1px dashed #999 !important; background: transparent !important; }
        .seat.ghost { visibility: hidden; pointer-events: none; border: none; background: transparent !important; box-shadow: none; }

        /* --- TOOLTIP STYLE (กล่องขยายใหญ่ขึ้น) --- */
        #seat-tooltip {
            position: fixed; display: none; z-index: 1050;
            background: white; border: 1px solid #ccc; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 20px; width: 260px; /* ขยายความกว้างกล่อง */
            text-align: center; pointer-events: none;
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
    position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
    background: #343a40; color: white; padding: 10px 20px;
    border-radius: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    display: none; z-index: 1000; align-items: center; gap: 15px;
}
        #tooltip-img { 
            width: 160px; /* เพิ่มขนาดเป็น 1 เท่าตัว (เดิม 80px) */
            height: 160px; 
            border-radius: 15px; /* เปลี่ยนเป็นสี่เหลี่ยมมุมโค้ง */
            object-fit: cover; 
            border: 1px solid #ddd; 
            margin-bottom: 15px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        #tooltip-name { font-size: 18px; font-weight: 400; margin-bottom: 5px; color: #333; }
        #tooltip-role { font-size: 16px; font-weight: 700; color: #000; }

        .floating-toolbar { position: fixed; top: 20px; right: 20px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 999; width: 200px;}

        .stage-box {
    width: 80%;               /* ความกว้าง 80% ของพื้นที่ */
    height: 60px;             /* ความสูงของกล่อง */
    background-color: #e0e0e0; /* สีพื้นหลังเทา */
    border: 2px solid #999;   /* ขอบสีเทาเข้ม */
    margin: 0 auto 40px auto; /* จัดกึ่งกลาง และเว้นระยะด้านล่าง */
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;       /* มุมมนเล็กน้อย */
    box-shadow: 0 4px 6px rgba(0,0,0,0.1); /* เงาให้ดูมีมิติ */
    
    /* ตัวหนังสือ */
    font-size: 18px;
    font-weight: bold;
    color: #555;
    letter-spacing: 2px;
    text-transform: uppercase;
}
    </style>
</head>
<body>

<div id="seat-tooltip">
    <img id="tooltip-img" src="">
    <div id="tooltip-name"></div>
    <div id="tooltip-role"></div>
</div>

<div class="floating-toolbar">
    <h6 class="fw-bold"><i class="bi bi-tools"></i> เครื่องมือ</h6>
    <button onclick="exportImage()" class="btn btn-primary btn-sm w-100 mb-2"><i class="bi bi-camera"></i> Save Image</button>
    <div class="dropdown d-inline-block ms-2">
    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="bi bi-printer"></i> พิมพ์สติกเกอร์
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" onclick="printAll()">พิมพ์ทั้งหมด (ทั้งผัง)</a></li>
        <li><a class="dropdown-item" href="#" onclick="toggleSelectMode()">เลือกพิมพ์บางคน...</a></li>
    </ul>
</div>
    <div class="mt-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm w-100 mb-2">กลับหน้าหลัก</a>
</div>
</div>

<div class="container-fluid">
    <div class="stage-container" id="chart-area">
        <h3 class="text-center mb-4 fw-bold text-dark sticky-left">
    <span id="pageTitle"><?php echo htmlspecialchars($plan['name']); ?></span>
    <button onclick="editPageTitle()" class="btn btn-sm btn-outline-secondary ms-2" style="border:none;">
        <i class="bi bi-pencil"></i>
    </button>
</h3>
        <div class="stage-box">เวที (Stage)</div>
        
        <?php 
        $globalRowCounter = 1;

        function renderSeat($g, $bgColor, $rowNo = '', $seatNo = '') {
    if (isset($g['is_ghost'])) { echo '<div class="seat ghost"></div>'; return; }

    $statusClass = ($g['status'] == 'reserved') ? 'status-reserved' : (($g['status'] == 'empty') ? 'status-empty' : '');
    $sofaClass = (isset($g['seat_type']) && $g['seat_type'] == 'sofa') ? 'sofa' : '';
    $style = "background-color: $bgColor;";
    $sName = htmlspecialchars($g['name']);
    $sRole = htmlspecialchars($g['role']);
    $imgSrc = $g['image_path'] ? 'uploads/'.$g['image_path'] : '';
    
    echo '
    <div class="seat '.$sofaClass.' '.$statusClass.'" 
            style="'.$style.'" 
            data-id="'.$g['id'].'" 
            onclick="openEditModal(this)"
            onmouseenter="showTooltip(this)" 
            onmousemove="moveTooltip(event)" 
            onmouseleave="hideTooltip()">
        
        '. ($rowNo ? '<div class="seat-badge-row">R'.$rowNo.'</div>' : '') .'
        '. ($seatNo ? '<div class="seat-badge-num">#'.$seatNo.'</div>' : '') .'

        <input type="hidden" class="d-name" value="'.$sName.'">
        <input type="hidden" class="d-role" value="'.$sRole.'">
        <input type="hidden" class="d-status" value="'.$g['status'].'">
        <input type="hidden" class="d-img" value="'.$g['image_path'].'">
        
        '. ($imgSrc ? '<img src="'.$imgSrc.'" class="seat-img">' : '<div class="seat-img d-flex align-items-center justify-content-center text-muted"><i class="bi bi-person"></i></div>') .'
        
        <div class="seat-name display-name">'.$g['name'].'</div>
        <div class="seat-role display-role">'.$g['role'].'</div>
    </div>';
}

        // ... (Functions renderFullRow, renderSplitRow และการ Loop Groups เหมือนเดิมทุกประการ) ...
        // เพื่อความกระชับ ผมขอละส่วน Loop ไว้ตรงนี้ (ใช้โค้ดเดิมจากข้อความที่แล้วได้เลยครับ)
        
        // --- ใส่ส่วน Loop เดิมตรงนี้ (เหมือนข้อความที่แล้ว) ---
        function renderFullRow($guestsInRow, $groupId, $rowIndex, $color, $displayNumber) {
    echo '<div class="theater-row">';
    echo '<div class="row-number me-2">'.$displayNumber.'</div>';
    echo '<div class="seat-block sortable-area" data-group-id="'.$groupId.'" data-row-idx="'.$rowIndex.'-Full">';
    
    // --- แก้ไขตรงนี้ (เพิ่มตัวนับ $i) ---
    $i = 1;
    foreach ($guestsInRow as $g) { 
        renderSeat($g, $color, $displayNumber, $i); // ส่งค่า $displayNumber และ $i
        $i++;
    }
    // --------------------------------
    
    echo '</div>';
    echo '<div class="row-number ms-2">'.$displayNumber.'</div>';
    echo '</div>';
}

        function renderSplitRow($guestsInRow, $groupId, $rowIndex, $color, $displayNumber) {
    $total = count($guestsInRow);
    if ($total == 0) return;
    if ($total % 2 != 0) { $guestsInRow[] = ['is_ghost' => true]; $total++; }

    $half = $total / 2;
    $leftSide = array_slice($guestsInRow, 0, $half);
    $rightSide = array_slice($guestsInRow, $half);
    
    echo '<div class="theater-row">';
    echo '<div class="row-number me-2">'.$displayNumber.'</div>';
    
    // --- แก้ไขตรงนี้ (เพิ่มตัวนับ $i ต่อเนื่องกัน) ---
    $i = 1;
    
    echo '<div class="seat-block sortable-area" data-group-id="'.$groupId.'" data-row-idx="'.$rowIndex.'-L">';
    foreach ($leftSide as $g) { 
        renderSeat($g, $color, $displayNumber, $i); 
        $i++; 
    }
    echo '</div>';
    
    echo '<div class="aisle-gap"></div>';
    
    echo '<div class="seat-block sortable-area" data-group-id="'.$groupId.'" data-row-idx="'.$rowIndex.'-R">';
    foreach ($rightSide as $g) { 
        renderSeat($g, $color, $displayNumber, $i); 
        $i++;
    }
    echo '</div>';
    // ------------------------------------------------
    
    echo '<div class="row-number ms-2">'.$displayNumber.'</div>';
    echo '</div>';
}

        // --- RENDER ZONES (Copy from previous code) ---
        $colorIndex = 0;
        $isFirstExecGroup = true;
        foreach($groups['exec'] as $group): 
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
                    foreach($allGuests as &$gx) { $gx['seat_type'] = $group['seat_type']; }
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
            </div>
        <?php endforeach; ?>

        <hr>

        <?php foreach($groups['part'] as $group): 
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

                    <button type="submit" class="btn btn-primary btn-sm w-100">บันทึก</button>
                </form>
            </div>
        </div>
    </div>
</div>
<div id="print-toolbar">
    <span>เลือกแล้ว <b id="sel-count">0</b> รายชื่อ</span>
    <button class="btn btn-sm btn-light text-dark fw-bold" onclick="printSelected()">
        <i class="bi bi-printer-fill"></i> พิมพ์ที่เลือก
    </button>
    <button class="btn btn-sm btn-outline-light" onclick="toggleSelectMode()">ยกเลิก</button>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- 1. Tooltip Logic ---
    const tooltip = document.getElementById('seat-tooltip');
    const tooltipImg = document.getElementById('tooltip-img');
    const tooltipName = document.getElementById('tooltip-name');
    const tooltipRole = document.getElementById('tooltip-role');

    function showTooltip(el) {
        if(el.classList.contains('status-empty')) return;
        
        const name = el.querySelector('.d-name').value;
        const role = el.querySelector('.d-role').value;
        const imgPath = el.querySelector('.d-img').value;

        tooltipName.innerText = name;
        tooltipRole.innerText = role;
        
        if(imgPath && imgPath !== 'null' && imgPath !== '') {
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

    function hideTooltip() { tooltip.style.display = 'none'; }

    // --- 2. Modal & Edit Logic ---
    let currentSeatEl = null;
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    const deleteCheck = document.getElementById('deleteImageCheck');
    const previewImg = document.getElementById('previewImg');
    const noImgPlaceholder = document.getElementById('noImgPlaceholder');
    const fileInput = document.getElementById('fileInput');

    function openEditModal(el) {
        if(el.classList.contains('ghost')) return;
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
        
        if(imgPath && imgPath !== 'null' && imgPath !== '') { 
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

    deleteCheck.addEventListener('change', function() {
        if(this.checked) {
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

        fetch('api_update_guest.php', { method: 'POST', body: formData })
        .then(response => response.text()) // อ่านเป็น Text ก่อนเพื่อดูว่ามี Error PHP ปนมาไหม
        .then(text => {
            try {
                const data = JSON.parse(text); // พยายามแปลงเป็น JSON
                
                if(data.success) {
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
        if(!currentSeatEl) return;
        
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
                if(iconDiv) iconDiv.remove();
                
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
        if(status === 'reserved') currentSeatEl.classList.add('status-reserved');
        if(status === 'empty') currentSeatEl.classList.add('status-empty');
    }

    // --- 3. Drag & Drop + Export ---
    const containers = document.querySelectorAll('.sortable-area');
    containers.forEach(el => {
        new Sortable(el, {
            group: 'shared', animation: 150, ghostClass: 'bg-light',
            onEnd: function (evt) { saveOrderGlobal(evt.to.getAttribute('data-group-id')); }
        });
    });

    function saveOrderGlobal(groupId) {
        const allSeatsInGroup = document.querySelectorAll(`.sortable-area[data-group-id="${groupId}"] .seat:not(.ghost)`);
        const items = Array.from(allSeatsInGroup).map(seat => seat.getAttribute('data-id'));
        fetch('api_reorder.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ group_id: groupId, items: items })
        });
    }

    function exportImage() {
        const area = document.getElementById('chart-area');
        hideTooltip(); 
        const originalOverflow = area.style.overflow;
        const originalWidth = area.style.width;
        area.style.overflow = 'visible';
        area.style.width = 'fit-content';
        html2canvas(area, { scale: 2 }).then(canvas => {
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
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'rename', id: planId, name: newName })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('pageTitle').innerText = newName;
                } else {
                    alert('Error updating name');
                }
            });
        }
    }

    let isSelectionMode = false;
let selectedSeats = new Set();

// 1. เริ่ม/หยุด โหมดเลือก
function toggleSelectMode() {
    isSelectionMode = !isSelectionMode;
    selectedSeats.clear();
    updateSelectionUI();

    const toolbar = document.getElementById('print-toolbar');
    const seats = document.querySelectorAll('.seat:not(.ghost)');

    if (isSelectionMode) {
        toolbar.style.display = 'flex';
        seats.forEach(el => {
            el.classList.add('selecting');
            // ปิด onclick เดิมชั่วคราว (แก้ไข Modal) โดยการดัก Event ที่ Capture Phase
            el.addEventListener('click', seatSelectionHandler, true);
        });
        // ปิด Sortable ชั่วคราว (ถ้าทำได้) หรือแจ้งเตือน
    } else {
        toolbar.style.display = 'none';
        seats.forEach(el => {
            el.classList.remove('selecting', 'selected');
            el.removeEventListener('click', seatSelectionHandler, true);
        });
    }
}

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
    if(guests.length === 0) {
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
    // ในโค้ด PHP ด้านบนผมเขียนรับ raw body แต่ถ้าส่งแบบ form ปกติ ต้องแก้ PHP นิดหน่อย
    // เพื่อความง่าย ขอเปลี่ยนวิธีส่งเป็น fetch + blob หรือแก้ PHP ให้รับ form
    // เอาวิธี Form + JSON String ดีกว่า ง่ายสุด

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

function printAll() {
    const guests = gatherSeatData(false); // เอาทั้งหมด
    const title = document.getElementById('pageTitle').innerText;
    postData('print_stickers.php', { title: title, guests: guests });
}

function printSelected() {
    const guests = gatherSeatData(true); // เอาเฉพาะที่เลือก
    const title = document.getElementById('pageTitle').innerText;
    postData('print_stickers.php', { title: title, guests: guests });

    // ออกจากโหมดเลือกหลังสั่งพิมพ์
    toggleSelectMode();
}
</script>

</body>
</html>