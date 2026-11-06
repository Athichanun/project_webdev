<?php
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');
include "mainConnect.php";


$isAdmin = (isset($_COOKIE['isAdmin']) && $_COOKIE['isAdmin'] === '1') || (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] == 1);

if (!$isAdmin) {
    echo json_encode([
        "success" => false,
        "message" => "ไม่มีสิทธิ์เข้าถึงข้อมูล (เฉพาะผู้ดูแลระบบเท่านั้น)"
    ]);
    exit();
}

try {

    $sql = "SELECT 
                b.booking_id,
                b.cus_id,
                COALESCE(b.cus_name, c.cus_name, 'ไม่ระบุชื่อ') AS cus_name,
                COALESCE(b.phone, c.phone, '-') AS phone,
                COALESCE(b.email, c.email, '-') AS email,
                b.room_id,
                COALESCE(r.room_name, 'ไม่ระบุห้อง') AS room_name,
                COALESCE(r.room_type, 'other') AS room_type,
                b.check_in,
                b.check_out,
                b.total_price,
                COALESCE(b.payment_status, 'Waiting') AS payment_status,
                COALESCE(b.is_promo, 0) AS is_promo,
                b.promo_id,
                COALESCE(p.title, 'ราคาปกติ / ส่วนลดสมาชิก') AS promo_title,
                h.Date_booking
            FROM Booking b
            LEFT JOIN Customer c ON b.cus_id = c.cus_id
            LEFT JOIN rooms r ON b.room_id = r.room_id
            LEFT JOIN promotions p ON b.promo_id = p.promo_id
            LEFT JOIN History h ON b.booking_id = h.booking_id
            ORDER BY b.check_in DESC, b.booking_id DESC";

    $stmt = $pdo->query($sql);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $stmtPromos = $pdo->query("SELECT promo_id, title FROM promotions ORDER BY promo_id ASC");
    $promotions = $stmtPromos->fetchAll(PDO::FETCH_ASSOC);

  
    $stmtRooms = $pdo->query("SELECT room_id, room_name, room_type FROM rooms ORDER BY room_id ASC");
    $rooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "bookings" => $bookings,
        "promotions" => $promotions,
        "rooms" => $rooms
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "เกิดข้อผิดพลาดในการดึงข้อมูลรายงาน: " . $e->getMessage()
    ]);
}
?>