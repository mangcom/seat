<?php
// ส่วนที่เพิ่ม 1: รับค่าจากหน้า Index (Modal)
$pre_name = $_POST['plan_name'] ?? '';
$pre_date = $_POST['plan_date'] ?? ''; // รับวันที่มาด้วย
$pre_rows = $_POST['rows'] ?? 20;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าผังที่นั่ง - Seating Plan Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* ... (CSS เดิม คงไว้เหมือนเดิม) ... */
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; }
        .card-custom { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header-custom { color: white; font-weight: bold; border-radius: 10px 10px 0 0 !important; padding: 15px; }
        .bg-exec { background: linear-gradient(45deg, #c0392b, #d35400); }
        .bg-participant { background: linear-gradient(45deg, #2980b9, #3498db); }
        .group-item { background: #fff; border: 1px solid #e9ecef; padding: 20px; margin-bottom: 15px; border-radius: 8px; position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .exec-item { border-left: 5px solid #c0392b; }
        .exec-number { position: absolute; left: -12px; top: 15px; background: #c0392b; color: white; width: 30px; height: 30px; border-radius: 50%; text-align: center; font-size: 14px; line-height: 30px; font-weight: bold; box-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .part-item { border-left: 5px solid #3498db; }
        .part-number { position: absolute; left: -12px; top: 15px; background: #3498db; color: white; width: 30px; height: 30px; border-radius: 50%; text-align: center; font-size: 14px; line-height: 30px; font-weight: bold; box-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .config-box { background-color: #eaf2f8; border: 1px dashed #2980b9; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .btn-remove { cursor: pointer; float: right; font-size: 1.2rem; transition: 0.2s; }
        .btn-remove:hover { transform: scale(1.2); }
    </style>
</head>
<body>

<div class="container py-5">
    <h2 class="text-center mb-4 fw-bold text-secondary">🛠️ ตั้งค่าโครงสร้างผังที่นั่ง</h2>
    
    <form action="save_initial_plan.php" method="POST">
        
        <div class="card card-custom">
            <div class="card-header card-header-custom bg-secondary">
                1. ข้อมูลทั่วไป (General Info)
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label">ชื่อผังงาน</label>
                        <input type="text" name="plan_name" class="form-control" 
                               value="<?php echo htmlspecialchars($pre_name); ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">วันที่จัดงาน</label>
                        <input type="date" name="plan_date" class="form-control" 
                               value="<?php echo htmlspecialchars($pre_date); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">จำนวนที่นั่งรวมสูงสุด (Optional)</label>
                        <input type="number" name="total_capacity" class="form-control" placeholder="ไม่จำกัด">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-custom">
            <div class="card-header card-header-custom bg-exec d-flex justify-content-between align-items-center">
                <span>2. กำหนดโซนผู้บริหาร (Executive Groups)</span>
                <button type="button" class="btn btn-sm btn-light text-danger fw-bold" onclick="addExecGroup()">
                    <i class="bi bi-plus-circle"></i> เพิ่มกลุ่มผู้บริหาร
                </button>
            </div>
            <div class="card-body">
                <div id="exec-container"></div>
                <div class="alert alert-warning mt-3 text-center" id="exec-empty-msg">
                    <i class="bi bi-exclamation-circle"></i> ยังไม่มีกลุ่มผู้บริหาร กดปุ่ม "เพิ่มกลุ่มผู้บริหาร" ด้านบน
                </div>
            </div>
        </div>

        <div class="card card-custom">
            <div class="card-header card-header-custom bg-participant d-flex justify-content-between align-items-center">
                <span>3. กำหนดกลุ่มผู้เข้าร่วม (Participant Groups)</span>
                <button type="button" class="btn btn-sm btn-light text-primary fw-bold" onclick="addPartGroup()">
                    <i class="bi bi-plus-circle"></i> เพิ่มกลุ่มผู้เข้าร่วม
                </button>
            </div>
            <div class="card-body">
                
                <div class="config-box">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-primary m-0">
                                <i class="bi bi-sliders"></i> กำหนดจำนวนที่นั่งต่อแถว (Auto-wrap)
                            </label>
                            <div class="form-text">เฉพาะโซนนี้: เมื่อนั่งครบจำนวนนี้ ระบบจะปัดคนถัดไปขึ้นแถวใหม่ทันที</div>
                        </div>
                        <div class="col-md-4">
                            <input type="number" name="seats_per_row" class="form-control border-primary fw-bold text-center" 
                                   value="<?php echo htmlspecialchars($pre_rows); ?>" min="10" max="30" 
       oninput="if(this.value > 30) this.value = 30;" 
       required>
                        </div>
                    </div>
                </div>

                <div id="part-container"></div>
                <div class="alert alert-info mt-3 text-center" id="part-empty-msg">
                    <i class="bi bi-info-circle"></i> ยังไม่มีกลุ่มผู้เข้าร่วม กดปุ่ม "เพิ่มกลุ่มผู้เข้าร่วม" ด้านบน
                </div>
            </div>
        </div>

        <div class="d-grid gap-2 mb-5">
            <button type="submit" class="btn btn-success btn-lg shadow p-3">
                <span class="fs-5">บันทึกและสร้างผัง <i class="bi bi-arrow-right-circle-fill"></i></span>
            </button>
        </div>

    </form>
</div>

<script>
    let execCount = 0;
    let partCount = 0;

    // ... (ฟังก์ชัน addExecGroup, addPartGroup, removeGroup, togglePartMembers เหมือนเดิม) ...
    // ผมละไว้เพื่อความกระชับ ให้คุณใช้ Script เดิมต่อท้ายได้เลยครับ

    function addExecGroup() {
        execCount++;
        document.getElementById('exec-empty-msg').style.display = 'none';
        const container = document.getElementById('exec-container');
        const html = `
            <div class="group-item exec-item" id="exec-row-${execCount}">
                <div class="exec-number">${execCount}</div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-danger">Executive Group ${execCount}</span>
                    <i class="bi bi-trash btn-remove text-danger" onclick="removeGroup('exec', ${execCount})" title="ลบกลุ่มนี้"></i>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">ชื่อกลุ่ม</label>
                        <input type="text" name="exec_groups[${execCount}][name]" class="form-control" placeholder="เช่น VVIP" required>
                    </div>
                    <div class="col-md-3">
                         <label class="form-label small text-muted">ประเภทที่นั่ง</label>
                         <select class="form-select" name="exec_groups[${execCount}][seat_type]">
                            <option value="sofa">โซฟา (Sofa)</option>
                            <option value="chair">เก้าอี้ (Chair)</option>
                         </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">จำนวนแถว</label>
                        <input type="number" name="exec_groups[${execCount}][rows]" class="form-control text-center" value="1" max="10" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">ที่นั่งต่อแถว</label>
                        <input type="number" name="exec_groups[${execCount}][seats]" class="form-control text-center" value="10" max="30" min="1" placeholder="เช่น 10" min="1" required>
                    </div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

    function addPartGroup() {
        partCount++;
        document.getElementById('part-empty-msg').style.display = 'none';
        const container = document.getElementById('part-container');
        const html = `
            <div class="group-item part-item" id="part-row-${partCount}">
                <div class="part-number">${partCount}</div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-primary">Priority Group ${partCount}</span>
                    <i class="bi bi-trash btn-remove text-primary" onclick="removeGroup('part', ${partCount})" title="ลบกลุ่มนี้"></i>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">ชื่อกลุ่ม</label>
                        <input type="text" name="part_groups[${partCount}][name]" class="form-control" placeholder="เช่น ทีมแข่ง" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">รูปแบบ</label>
                        <select class="form-select" name="part_groups[${partCount}][type]" id="ptype-${partCount}" onchange="togglePartMembers(${partCount})">
                            <option value="individual">รายบุคคล</option>
                            <option value="team">เป็นทีม</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">สมาชิก/ทีม</label>
                        <input type="number" name="part_groups[${partCount}][members]" id="pmemb-${partCount}" class="form-control text-center bg-light" value="1" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-success fw-bold">จำนวน</label>
                        <input type="number" name="part_groups[${partCount}][quantity]" class="form-control border-success text-center" placeholder="จำนวน" min="1" max="30" value="10" required>
                    </div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

    function removeGroup(type, id) {
        document.getElementById(`${type}-row-${id}`).remove();
        if (type === 'exec' && document.querySelectorAll('.exec-item').length === 0) document.getElementById('exec-empty-msg').style.display = 'block';
        if (type === 'part' && document.querySelectorAll('.part-item').length === 0) document.getElementById('part-empty-msg').style.display = 'block';
    }

    function togglePartMembers(id) {
        const type = document.getElementById(`ptype-${id}`).value;
        const input = document.getElementById(`pmemb-${id}`);
        if(type === 'team') {
            input.readOnly = false;
            input.value = 3;
            input.classList.remove('bg-light');
            input.focus();
        } else {
            input.readOnly = true;
            input.value = 1;
            input.classList.add('bg-light');
        }
    }

    window.onload = function() {
        addExecGroup();
        addPartGroup();
    }
</script>
</body>
</html>