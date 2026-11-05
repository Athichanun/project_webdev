<?php
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');
include "mainConnect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit();
}

$cusId = $_SESSION['memberId'] ?? ($_COOKIE['memberId'] ?? 0);
$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;

if (!$cusId || !$bookingId) {
    echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบและระบุรายการที่ต้องการยกเลิก"]);
    exit();
}

try {
    $stmtHist = $pdo->prepare("DELETE FROM History WHERE booking_id = ?");
    $stmtHist->execute([$bookingId]);

    $stmtBook = $pdo->prepare("DELETE FROM Booking WHERE booking_id = ? AND cus_id = ?");
    $stmtBook->execute([$bookingId, $cusId]);

    if ($stmtBook->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "ยกเลิกรายการจองสำเร็จ"]);
    } else {
        echo json_encode(["success" => false, "message" => "ไม่พบรายการจองหรือไม่มีสิทธิ์ยกเลิก"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
}
?>

