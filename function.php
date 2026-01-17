<?php

function generateSeatingPlan($planId) {
    // 1. ดึงค่า Config ของผัง
    // $planConfig = SELECT * FROM plans WHERE id = $planId;
    $seatsPerRow = 40; // สมมติค่าจาก DB
    
    // 2. ดึงรายชื่อผู้เข้าร่วม โดยเรียงตาม Priority และ Sort Order
    // query: SELECT * FROM participants ORDER BY group_priority ASC, sort_order ASC
    
    // จำลอง Data ที่ดึงมา (ตัวอย่าง: 15 ทีม ทีมละ 3 คน)
    $allParticipants = [
        ['name' => 'ทีม A', 'is_team' => true, 'members' => 3],
        ['name' => 'ทีม B', 'is_team' => true, 'members' => 3],
        // ... จนถึงทีมที่ 15
        ['name' => 'ทีม O', 'is_team' => true, 'members' => 3],
    ];

    // 3. FLATTEN DATA: แปลง "ทีม" ให้เป็น "รายหัว" (Individual Units)
    $queue = [];
    foreach ($allParticipants as $p) {
        if ($p['is_team']) {
            for ($i = 1; $i <= $p['members']; $i++) {
                $queue[] = [
                    'label' => $p['name'] . " (คนทึ่ $i)",
                    'origin_team' => $p['name']
                ];
            }
        } else {
            $queue[] = ['label' => $p['name'], 'origin_team' => null];
        }
    }

    // $queue ตอนนี้จะมีสมาชิกทั้งหมด = 15 x 3 = 45 คน เรียงเป็นแถวเดียว

    // 4. ALLOCATION: หยอดลงหลุม (เก้าอี้)
    $finalSeating = []; // อาเรย์เก็บผลลัพธ์แบบ 2 มิติ [แถว][ที่นั่ง]
    
    $currentRow = 1;
    $currentSeat = 1;

    foreach ($queue as $person) {
        // บันทึกตำแหน่ง
        $finalSeating[$currentRow][$currentSeat] = $person;

        // ขยับเคอร์เซอร์ไปที่นั่งถัดไป
        $currentSeat++;

        // ตรวจสอบเงื่อนไขจบแถว (Overflow Logic)
        if ($currentSeat > $seatsPerRow) {
            $currentRow++;   // ขึ้นแถวใหม่
            $currentSeat = 1; // รีเซ็ตเลขที่นั่งเป็น 1
        }
    }

    return $finalSeating;
}
?>