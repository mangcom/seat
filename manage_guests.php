<?php
require 'db.php';

$plan_id = $_GET['id'] ?? 0;
if (!$plan_id) die("Invalid Plan ID");

// ดึงข้อมูล Plan
$stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

// --- แก้ไขจุดนี้ (FIXED) ---
// ดึง Groups ทั้งหมดแบบปกติ แล้วมาวนลูปแยกกลุ่มเอง เพื่อความชัวร์
$stmt = $pdo->prepare("SELECT * FROM plan_groups WHERE plan_id = ? ORDER BY zone_type, sort_order");
$stmt->execute([$plan_id]);
$raw_groups = $stmt->fetchAll();

// เตรียมตัวแปร array ว่างไว้ก่อน
$groups = ['exec' => [], 'part' => []];

// วนลูปแยกใส่กล่อง exec หรือ part
foreach ($raw_groups as $row) {
    $groups[$row['zone_type']][] = $row;
}
// ------------------------

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการรายชื่อ - <?php echo htmlspecialchars($plan['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .preview-img { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; margin-right: 10px; border: 1px solid #ddd;}
        .section-header { background: #eee; padding: 10px; border-radius: 5px; margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body class="bg-light pb-5">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left"></i> กลับหน้าหลัก</a>
            <h2>📝 จัดการรายชื่อ: <?php echo htmlspecialchars($plan['name']); ?></h2>
        </div>
        <a href="interactive_map.php?id=<?php echo $plan_id; ?>" class="btn btn-outline-primary">ดูผังที่นั่ง <i class="bi bi-map"></i></a>
    </div>

    <form action="save_guests.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">

        <?php if (!empty($groups['exec'])): ?>
            <h4 class="text-danger mt-4"><i class="bi bi-star-fill"></i> Zone ผู้บริหาร</h4>
            <?php foreach ($groups['exec'] as $group): 
                $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = ? ORDER BY sort_order");
                $stmt->execute([$group['id']]);
                $guests = $stmt->fetchAll();
                
                $total_seats = $group['row_count'] * $group['seats_in_row'];
            ?>
                <div class="card mb-3 shadow-sm border-danger">
                    <div class="card-header bg-danger text-white">
                        <?php echo $group['name']; ?> (<?php echo $total_seats; ?> ที่นั่ง)
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php for ($i = 0; $i < $total_seats; $i++): 
                                // หาข้อมูลคนที่นั่งตำแหน่งนี้ (ถ้ามี)
                                $g = null;
                                foreach($guests as $guest) {
                                    if($guest['sort_order'] == $i) { $g = $guest; break; }
                                }
                            ?>
                                <div class="col-md-6 border-bottom pb-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-secondary me-2" style="width:30px;">#<?php echo $i+1; ?></span>
                                        
                                        <?php if(!empty($g['image_path'])): ?>
                                            <img src="uploads/<?php echo $g['image_path']; ?>" class="preview-img">
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1">
                                            <input type="text" class="form-control form-control-sm mb-1" 
                                                   name="guests[<?php echo $group['id']; ?>][<?php echo $i; ?>][name]" 
                                                   placeholder="ชื่อ-นามสกุล" value="<?php echo $g['name'] ?? ''; ?>">
                                            
                                            <input type="text" class="form-control form-control-sm mb-1" 
                                                   name="guests[<?php echo $group['id']; ?>][<?php echo $i; ?>][role]" 
                                                   placeholder="ตำแหน่ง" value="<?php echo $g['role'] ?? ''; ?>">

                                            <div class="input-group input-group-sm">
                                                <input type="file" class="form-control" 
                                                       name="guest_img_<?php echo $group['id']; ?>_<?php echo $i; ?>" accept="image/*">
                                            </div>
                                            
                                            <input type="hidden" name="guests[<?php echo $group['id']; ?>][<?php echo $i; ?>][old_img]" value="<?php echo $g['image_path'] ?? ''; ?>">
                                            <input type="hidden" name="guests[<?php echo $group['id']; ?>][<?php echo $i; ?>][id]" value="<?php echo $g['id'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($groups['part'])): ?>
            <h4 class="text-primary mt-5"><i class="bi bi-people-fill"></i> Zone ผู้เข้าร่วม</h4>
            <?php foreach ($groups['part'] as $group): 
                $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = ? ORDER BY sort_order");
                $stmt->execute([$group['id']]);
                $guests = $stmt->fetchAll();

                $qty = $group['quantity'];
            ?>
                <div class="card mb-3 shadow-sm border-primary">
                    <div class="card-header bg-primary text-white">
                        <?php echo $group['name']; ?> (<?php echo $qty; ?> รายการ)
                    </div>
                    <div class="card-body">
                        <?php for ($i = 0; $i < $qty; $i++): 
                             $g = null;
                             foreach($guests as $guest) {
                                 if($guest['sort_order'] == $i) { $g = $guest; break; }
                             }
                        ?>
                            <div class="d-flex align-items-center mb-2 p-2 border rounded bg-white">
                                <span class="badge bg-light text-dark me-2 border" style="width:30px;"><?php echo $i+1; ?></span>
                                
                                <?php if(!empty($g['image_path'])): ?>
                                    <img src="uploads/<?php echo $g['image_path']; ?>" class="preview-img">
                                <?php endif; ?>

                                <div class="row g-1 flex-grow-1">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control form-control-sm" 
                                               name="guests[<?php echo $group['id']; ?>][<?php echo $i; ?>][name]" 
                                               placeholder="ชื่อ / ทีม" value="<?php echo $g['name'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" 
                                               name="guests[<?php echo $group['id']; ?>][<?php echo $i; ?>][role]" 
                                               placeholder="รายละเอียด" value="<?php echo $g['role'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="file" class="form-control form-control-sm" 
                                               name="guest_img_<?php echo $group['id']; ?>_<?php echo $i; ?>" accept="image/*">
                                    </div>
                                </div>
                                
                                <input type="hidden" name="guests[<?php echo $group['id']; ?>][<?php echo $i; ?>][old_img]" value="<?php echo $g['image_path'] ?? ''; ?>">
                                <input type="hidden" name="guests[<?php echo $group['id']; ?>][<?php echo $i; ?>][id]" value="<?php echo $g['id'] ?? ''; ?>">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="fixed-bottom bg-white p-3 border-top shadow text-end">
            <button type="submit" class="btn btn-success btn-lg px-5 shadow">บันทึกข้อมูลและสร้างผัง <i class="bi bi-save"></i></button>
        </div>
    </form>
</div>
<br><br><br>
</body>
</html>