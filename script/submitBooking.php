<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
include "mainConnect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "รูปแบบการส่งข้อมูลไม่ถูกต้อง"]);
    exit();
}

// รับค่าจากฟอร์มหน้า booking.html
$roomId        = isset($_POST['room_id']) ? trim($_POST['room_id']) : '';
$cusName       = isset($_POST['cus_name']) ? trim($_POST['cus_name']) : '';
$phone         = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email         = isset($_POST['email']) ? trim($_POST['email']) : '';
$checkIn       = isset($_POST['check_in']) ? trim($_POST['check_in']) : '';
$checkOut      = isset($_POST['check_out']) ? trim($_POST['check_out']) : '';
$paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Waiting';

// แปลงค่าสถานะการจ่ายเงินให้ตรงกับ Database (Paid หรือ Waiting)
$paymentStatus = ($paymentMethod === 'Waiting') ? 'Waiting' : 'Paid';

if (empty($roomId) || empty($cusName) || empty($phone) || empty($checkIn) || empty($checkOut)) {
    echo json_encode(["success" => false, "message" => "กรุณากรอกข้อมูลให้ครบถ้วน"]);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. เช็คราคาและสถานะห้องพักจากตาราง rooms
    $stmtRoom = $pdo->prepare("SELECT price, is_reserved FROM rooms WHERE room_id = ?");
    $stmtRoom->execute([$roomId]);
    $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        throw new Exception("ไม่พบข้อมูลห้องพักที่เลือก");
    }
    if ($room['is_reserved'] == 1) {
        throw new Exception("ห้องพักนี้ถูกจองไปแล้ว กรุณาเลือกห้องอื่น");
    }

    // 2. คำนวณจำนวนคืนและราคารวม
    $datetime1 = new DateTime($checkIn);
    $datetime2 = new DateTime($checkOut);
    $nights = (int)$datetime1->diff($datetime2)->format('%r%a');

    if ($nights <= 0) {
        throw new Exception("วันที่เช็คเอาท์ต้องมากกว่าวันที่เช็คอินอย่างน้อย 1 วัน");
    }

    // 3. จัดการข้อมูลลูกค้าในตาราง Customer
    $loggedInCusId = isset($_COOKIE['memberId']) ? (int)$_COOKIE['memberId'] : null;
    $isMember = !empty($loggedInCusId) || (isset($_POST['is_member']) && $_POST['is_member'] == '1');

    // ถ้าล็อกอินแล้ว ให้ลดราคาห้องพักทั้งหมด 15% จากราคาเดิมในฐานข้อมูล
    $unitPrice = (float)$room['price'];
    if ($isMember) {
        $unitPrice = round($unitPrice * 0.85, 2);
    }
    $totalPrice = $nights * $unitPrice;

    if ($loggedInCusId) {
        $cusId = $loggedInCusId;
        // อัปเดตข้อมูลลูกค้าที่ล็อกอินอยู่
        $stmtUpdateCus = $pdo->prepare("UPDATE Customer SET cus_name = ?, phone = ?, email = ? WHERE cus_id = ?");
        $stmtUpdateCus->execute([$cusName, $phone, $email, $cusId]);
    } else {
        $stmtCheckCus = $pdo->prepare("SELECT cus_id FROM Customer WHERE email = ? OR phone = ? LIMIT 1");
        $stmtCheckCus->execute([$email, $phone]);
        $existingCus = $stmtCheckCus->fetch(PDO::FETCH_ASSOC);

        if ($existingCus) {
            $cusId = $existingCus['cus_id'];
        } else {
            $stmtInsertCus = $pdo->prepare("INSERT INTO Customer (cus_name, phone, email, is_member) VALUES (?, ?, ?, 0)");
            $stmtInsertCus->execute([$cusName, $phone, $email]);
            $cusId = $pdo->lastInsertId();
        }
    }

    // 4. บันทึกการจองลงตาราง Booking (รวม cus_name, phone, email)
    try {
        $sqlBooking = "INSERT INTO Booking (cus_id, cus_name, phone, email, room_id, check_in, check_out, total_price, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtBooking = $pdo->prepare($sqlBooking);
        $stmtBooking->execute([$cusId, $cusName, $phone, $email, $roomId, $checkIn, $checkOut, $totalPrice, $paymentStatus]);
        $bookingId = $pdo->lastInsertId();
    } catch (PDOException $e) {
        // กรณีตาราง Booking ยังไม่ได้รันคำสั่งเพิ่มคอลัมน์ cus_name, phone, email
        $sqlBooking = "INSERT INTO Booking (cus_id, room_id, check_in, check_out, total_price, payment_status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtBooking = $pdo->prepare($sqlBooking);
        $stmtBooking->execute([$cusId, $roomId, $checkIn, $checkOut, $totalPrice, $paymentStatus]);
        $bookingId = $pdo->lastInsertId();
    }

    // 5. บันทึกประวัติลงตาราง History
    $currentDate = date('Y-m-d H:i:s');
    $stmtHistory = $pdo->prepare("INSERT INTO History (booking_id, Date_booking) VALUES (?, ?)");
    $stmtHistory->execute([$bookingId, $currentDate]);

    // 6. อัปเดตสถานะห้องพักในตาราง rooms เป็นไม่ว่าง (is_reserved = 1)
    $stmtUpdateRoom = $pdo->prepare("UPDATE rooms SET is_reserved = 1 WHERE room_id = ?");
    $stmtUpdateRoom->execute([$roomId]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "booking_id" => $bookingId,
        "total_price" => $totalPrice,
        "payment_status" => $paymentStatus
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>