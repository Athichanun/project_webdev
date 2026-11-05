<?php
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');
include "mainConnect.php";

$cusId = 0;
if (isset($_COOKIE['memberId']) && (int)$_COOKIE['memberId'] > 0) {
    $cusId = (int)$_COOKIE['memberId'];
} elseif (isset($_SESSION['memberId']) && (int)$_SESSION['memberId'] > 0) {
    $cusId = (int)$_SESSION['memberId'];
}

if (!$cusId) {
    echo json_encode([
        "success" => false,
        "message" => "กรุณาเข้าสู่ระบบก่อนดูรายการจอง"
    ]);
    exit();
}

$status = isset($_GET['status']) ? trim($_GET['status']) : 'Waiting';

try {
    $sql = "SELECT 
                b.*,
                p.title AS promo_title,
                p.description AS promo_description,
                p.discount_badge AS promo_badge,
                COALESCE(b.cus_name, c.cus_name) AS cus_name,
                COALESCE(b.phone, c.phone) AS phone,
                COALESCE(b.email, c.email) AS email,
                r.room_name,
                r.room_type,
                r.price AS room_price,
                r.path_photo,
                r.max_guests,
                h.Date_booking
            FROM Booking b
            LEFT JOIN Customer c ON b.cus_id = c.cus_id
            LEFT JOIN rooms r ON b.room_id = r.room_id
            LEFT JOIN History h ON b.booking_id = h.booking_id
            LEFT JOIN promotions p ON b.promo_id = p.promo_id
            WHERE b.cus_id = ?";
    
    $params = [$cusId];

    if (!empty($status) && $status !== 'all') {
        $sql .= " AND b.payment_status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY b.booking_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($bookings),
        "status_filter" => $status,
        "data" => $bookings
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "เกิดข้อผิดพลาดในการดึงข้อมูลการจอง: " . $e->getMessage()
    ]);
}
?>