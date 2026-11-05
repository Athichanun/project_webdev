<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
include "mainConnect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "รูปแบบการส่งข้อมูลไม่ถูกต้อง"]);
    exit();
}
$roomId        = isset($_POST['room_id']) ? trim($_POST['room_id']) : '';
$cusName       = isset($_POST['cus_name']) ? trim($_POST['cus_name']) : '';
$phone         = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email         = isset($_POST['email']) ? trim($_POST['email']) : '';
$checkIn       = isset($_POST['check_in']) ? trim($_POST['check_in']) : '';
$checkOut      = isset($_POST['check_out']) ? trim($_POST['check_out']) : '';
$paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Waiting';


$paymentStatus = ($paymentMethod === 'Waiting') ? 'Waiting' : 'Paid';

if (empty($roomId) || empty($cusName) || empty($phone) || empty($checkIn) || empty($checkOut)) {
    echo json_encode(["success" => false, "message" => "กรุณากรอกข้อมูลให้ครบถ้วน"]);
    exit();
}

try {
    $pdo->beginTransaction();
    $stmtRoom = $pdo->prepare("SELECT price FROM rooms WHERE room_id = ?");
    $stmtRoom->execute([$roomId]);
    $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        throw new Exception("ไม่พบข้อมูลห้องพักที่เลือก");
    }

    $stmtOverlap = $pdo->prepare("SELECT booking_id FROM Booking WHERE room_id = ? AND check_in < ? AND check_out > ? AND (payment_status IS NULL OR payment_status != 'Cancelled') LIMIT 1");
    $stmtOverlap->execute([$roomId, $checkOut, $checkIn]);
    if ($stmtOverlap->fetch()) {
        throw new Exception("ขออภัย ห้องพักนี้มีผู้จองในช่วงวันดังกล่าวแล้ว กรุณาเลือกวันที่หรือห้องพักอื่น");
    }

    $datetime1 = new DateTime($checkIn);
    $datetime2 = new DateTime($checkOut);
    $nights = (int)$datetime1->diff($datetime2)->format('%r%a');

    if ($nights <= 0) {
        throw new Exception("วันที่เช็คเอาท์ต้องมากกว่าวันที่เช็คอินอย่างน้อย 1 วัน");
    }

    $loggedInCusId = isset($_COOKIE['memberId']) ? (int)$_COOKIE['memberId'] : null;
    $isMember = !empty($loggedInCusId) || (isset($_POST['is_member']) && $_POST['is_member'] == '1');
    $oldUserCount = 0;

    if ($loggedInCusId) {
        $cusId = $loggedInCusId;
        try {
            $stmtCusData = $pdo->prepare("SELECT is_member, old_user FROM Customer WHERE cus_id = ?");
            $stmtCusData->execute([$cusId]);
            $cusInfo = $stmtCusData->fetch(PDO::FETCH_ASSOC);
            if ($cusInfo) {
                if (isset($cusInfo['is_member'])) {
                    $isMember = ($cusInfo['is_member'] == 1);
                }
                if (isset($cusInfo['old_user'])) {
                    $oldUserCount = (int)$cusInfo['old_user'];
                }
            }
        } catch (PDOException $e) {
            $oldUserCount = isset($_COOKIE['old_user']) ? (int)$_COOKIE['old_user'] : 0;
        }

        $stmtUpdateCus = $pdo->prepare("UPDATE Customer SET cus_name = ?, phone = ?, email = ? WHERE cus_id = ?");
        $stmtUpdateCus->execute([$cusName, $phone, $email, $cusId]);
    } else {
        $stmtCheckCus = $pdo->prepare("SELECT cus_id, is_member, old_user FROM Customer WHERE email = ? OR phone = ? LIMIT 1");
        $stmtCheckCus->execute([$email, $phone]);
        $existingCus = $stmtCheckCus->fetch(PDO::FETCH_ASSOC);

        if ($existingCus) {
            $cusId = $existingCus['cus_id'];
            if (isset($existingCus['is_member'])) {
                $isMember = ($existingCus['is_member'] == 1);
            }
            if (isset($existingCus['old_user'])) {
                $oldUserCount = (int)$existingCus['old_user'];
            }
        } else {
            try {
                $stmtInsertCus = $pdo->prepare("INSERT INTO Customer (cus_name, phone, email, is_member, old_user) VALUES (?, ?, ?, 0, 0)");
                $stmtInsertCus->execute([$cusName, $phone, $email]);
            } catch (PDOException $ex) {
                $stmtInsertCus = $pdo->prepare("INSERT INTO Customer (cus_name, phone, email, is_member) VALUES (?, ?, ?, 0)");
                $stmtInsertCus->execute([$cusName, $phone, $email]);
            }
            $cusId = $pdo->lastInsertId();
            $isMember = false;
            $oldUserCount = 0;
        }
    }

    $isOldUser = ($isMember && $oldUserCount > 0);
    $discountPercent = 0;
    if ($isMember) {
        $discountPercent = $isOldUser ? 20 : 15;
    }

    $unitPrice = (float)$room['price'];
    if ($discountPercent > 0) {
        $unitPrice = round($unitPrice * (1 - $discountPercent / 100), 2);
    }
    $totalPrice = $nights * $unitPrice;

    try {
        $sqlBooking = "INSERT INTO Booking (cus_id, cus_name, phone, email, room_id, check_in, check_out, total_price, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtBooking = $pdo->prepare($sqlBooking);
        $stmtBooking->execute([$cusId, $cusName, $phone, $email, $roomId, $checkIn, $checkOut, $totalPrice, $paymentStatus]);
        $bookingId = $pdo->lastInsertId();
    } catch (PDOException $e) {
        $sqlBooking = "INSERT INTO Booking (cus_id, room_id, check_in, check_out, total_price, payment_status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtBooking = $pdo->prepare($sqlBooking);
        $stmtBooking->execute([$cusId, $roomId, $checkIn, $checkOut, $totalPrice, $paymentStatus]);
        $bookingId = $pdo->lastInsertId();
    }

    $currentDate = date('Y-m-d H:i:s');
    $stmtHistory = $pdo->prepare("INSERT INTO History (booking_id, Date_booking) VALUES (?, ?)");
    $stmtHistory->execute([$bookingId, $currentDate]);

    try {
        $stmtIncOld = $pdo->prepare("UPDATE Customer SET old_user = COALESCE(old_user, 0) + 1 WHERE cus_id = ?");
        $stmtIncOld->execute([$cusId]);
    } catch (PDOException $e) {
        error_log("Failed to increment old_user: " . $e->getMessage());
    }

    if ($loggedInCusId && $loggedInCusId == $cusId) {
        $newOldUserCount = $oldUserCount + 1;
        setcookie("old_user", $newOldUserCount, time() + 86400, "/");
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "booking_id" => $bookingId,
        "total_price" => $totalPrice,
        "payment_status" => $paymentStatus,
        "discount_percent" => $discountPercent,
        "is_member" => $isMember,
        "is_old_user" => $isOldUser,
        "old_user" => $oldUserCount + 1
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