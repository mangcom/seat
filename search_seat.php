<?php
require 'db.php';

// --- 1. ส่วนรับค่า AJAX ---
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');

    $plan_id = $_GET['plan_id'] ?? 0;
    $keyword = trim($_GET['keyword'] ?? '');

    if (!$plan_id) {
        echo json_encode([]);
        exit;
    }

    // SQL: เพิ่ม g.image_path เพื่อเอารูปมาแสดง
    $sql = "SELECT g.name, g.role, g.sort_order, g.status, g.image_path,
                   pg.name as row_name, pg.zone_type
            FROM guests g
            JOIN plan_groups pg ON g.group_id = pg.id
            WHERE pg.plan_id = ? 
            AND (g.name LIKE ? OR g.role LIKE ?)
            ORDER BY pg.zone_type ASC, pg.sort_order ASC, g.sort_order ASC
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $searchTerm = "%" . $keyword . "%";
    $stmt->execute([$plan_id, $searchTerm, $searchTerm]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- 2. ส่วนดึงรายชื่อผัง ---
$sql_plans = "SELECT id, name, plan_date 
              FROM plans 
              WHERE is_deleted = 0 AND is_active = 1 
              ORDER BY id DESC";
$plans = $pdo->query($sql_plans)->fetchAll();

$target_id = $_GET['id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ค้นหาที่นั่ง - Search Seat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f6f9;
        }

        .search-container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* วงกลมเลขที่นั่ง */
        .seat-badge {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #0043a8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
        }

        /* รูปโปรไฟล์ */
        .profile-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .profile-icon {
            font-size: 3.5rem;
            color: #dee2e6;
        }

        /* ข้อความระบุตำแหน่ง (แถว.. ลำดับ..) */
        .seat-location-text {
            background-color: #e9ecef;
            color: #212529;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: 600;
            display: inline-block;
        }

        .zone-label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .zone-value {
            font-weight: bold;
            color: #495057;
        }
    </style>
</head>

<body>

    <div class="container py-4 search-container">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0"><i class="bi bi-search"></i> ค้นหาที่นั่ง</h4>
            <a href="<?php echo $target_id ? 'interactive_map.php?id=' . $target_id : 'index.php'; ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> กลับ
            </a>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-muted small">เลือกงาน (Event)</label>
                        <select id="planSelect" class="form-select border-primary bg-light">
                            <option value="">-- เลือกงาน --</option>
                            <?php foreach ($plans as $p): ?>
                                <?php
                                $selected = ($p['id'] == $target_id) ? 'selected' : '';
                                $dateStr = $p['plan_date'] ? " (" . date('d/m/Y', strtotime($p['plan_date'])) . ")" : "";
                                ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($p['name']) . $dateStr; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold text-muted small">พิมพ์ชื่อ หรือ ตำแหน่ง</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="พิมพ์ชื่อเพื่อค้นหา..." autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="loading" class="text-center d-none py-3">
            <div class="spinner-border text-primary"></div>
        </div>

        <div id="resultsList" class="list-group shadow-sm">
            <div class="text-center py-5 text-muted bg-white rounded">
                <i class="bi bi-person-badge fs-1 opacity-25"></i>
                <p class="mt-2">กรุณาเลือกงาน และพิมพ์ชื่อเพื่อค้นหา</p>
            </div>
        </div>

    </div>

    <script>
        const planSelect = document.getElementById('planSelect');
        const searchInput = document.getElementById('searchInput');
        const resultsList = document.getElementById('resultsList');
        const loading = document.getElementById('loading');
        let timeout = null;

        if (planSelect.value) searchInput.focus();

        planSelect.addEventListener('change', () => {
            searchInput.value = '';
            searchInput.focus();
            resultsList.innerHTML = '';
        });

        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(performSearch, 300);
        });

        function performSearch() {
            const planId = planSelect.value;
            const keyword = searchInput.value.trim();

            if (!planId) {
                alert('กรุณาเลือกงานก่อนครับ');
                planSelect.focus();
                return;
            }
            if (keyword.length === 0) {
                resultsList.innerHTML = '';
                return;
            }

            loading.classList.remove('d-none');
            resultsList.classList.add('d-none');

            fetch(`search_seat.php?ajax_search=1&plan_id=${planId}&keyword=${encodeURIComponent(keyword)}`)
                .then(res => res.json())
                .then(data => renderResults(data))
                .catch(err => console.error(err))
                .finally(() => {
                    loading.classList.add('d-none');
                    resultsList.classList.remove('d-none');
                });
        }

        function renderResults(data) {
            if (data.length === 0) {
                resultsList.innerHTML = '<div class="list-group-item py-4 text-center text-muted">ไม่พบข้อมูล</div>';
                return;
            }

            let html = '';
            data.forEach(g => {
                const seatNo = parseInt(g.sort_order) + 1; // ลำดับที่
                const rowName = g.row_name; // ชื่อแถว (ตามที่ตั้งในผัง)

                // --- ข้อความระบุตำแหน่งแบบเต็ม ---
                const locationText = `แถว ${rowName} ลำดับที่ ${seatNo}`;

                // --- จัดการรูปภาพ ---
                let imgHtml = '';
                if (g.image_path) {
                    imgHtml = `<img src="uploads/${g.image_path}" class="profile-img me-3">`;
                } else {
                    imgHtml = `<div class="me-3"><i class="bi bi-person-circle profile-icon"></i></div>`;
                }

                // ชื่อโซน
                let zoneName = 'ทั่วไป';
                if (g.zone_type === 'exec') zoneName = 'ผู้บริหาร';
                else if (g.zone_type === 'part') zoneName = 'ผู้เข้าร่วม';

                let statusBadge = g.status === 'checked_in' ? '<span class="badge bg-success ms-2">มาแล้ว</span>' : '';

                html += `
                <div class="list-group-item p-3 border-0 border-bottom bg-white hover-shadow">
                    <div class="row align-items-center">
                        
                        <div class="col-8 d-flex align-items-center">
                            ${imgHtml}
                            <div>
                                <h5 class="fw-bold text-primary mb-1 text-truncate">${g.name} ${statusBadge}</h5>
                                <div class="text-secondary small mb-2 text-truncate" style="max-width: 250px;">
                                    <i class="bi bi-briefcase"></i> ${g.role || '-'}
                                </div>
                                
                                <span class="zone-label">โซน: <span class="zone-value">${zoneName}</span></span>
                            </div>
                        </div>

                        <div class="col-4 text-end d-flex flex-column align-items-end justify-content-center">
                            <div class="seat-badge mb-2 shadow-sm">${seatNo}</div>
                            <div class="seat-location-text text-nowrap">${locationText}</div>
                        </div>

                    </div>
                </div>`;
            });
            resultsList.innerHTML = html;
        }
    </script>
</body>

</html>