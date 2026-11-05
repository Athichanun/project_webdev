<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
include "mainConnect.php";

$roomId  = isset($_GET['room_id'])   ? trim($_GET['room_id'])   : '';
$checkIn = isset($_GET['check_in'])  ? trim($_GET['check_in'])  : '';
$checkOut = isset($_GET['check_out']) ? trim($_GET['check_out']) : '';

if (empty($roomId) || empty($checkIn) || empty($checkOut)) {
    echo json_encode(["success" => false, "message" => "กรุณาระบุ room_id, check_in และ check_out"]);
    exit();
}


if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
    echo json_encode(["success" => false, "message" => "รูปแบบวันที่ไม่ถูกต้อง (ใช้ YYYY-MM-DD)"]);
    exit();
}

if ($checkOut <= $checkIn) {
    echo json_encode(["success" => false, "message" => "วันเช็คเอาท์ต้องมากกว่าวันเช็คอิน"]);
    exit();
}

try {
    $sql = "SELECT booking_id, check_in, check_out, payment_status 
            FROM Booking 
            WHERE room_id = ? 
              AND check_in < ? 
              AND check_out > ?
              AND (payment_status IS NULL OR payment_status != 'Cancelled')
            ORDER BY check_in ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roomId, $checkOut, $checkIn]);
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $isAvailable = (count($conflicts) === 0);

    $conflictingDates = [];
    foreach ($conflicts as $c) {
        $conflictingDates[] = [
            "check_in"  => $c['check_in'],
            "check_out" => $c['check_out']
        ];
    }

    echo json_encode([
        "success"           => true,
        "available"         => $isAvailable,
        "room_id"           => $roomId,
        "requested_check_in"  => $checkIn,
        "requested_check_out" => $checkOut,
        "conflicting_bookings" => $conflictingDates
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "เกิดข้อผิดพลาดในการตรวจสอบ: " . $e->getMessage()
    ]);
}
?>
