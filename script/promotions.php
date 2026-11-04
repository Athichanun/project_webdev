<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
include "mainConnect.php";

try {
    $stmt = $pdo->query("SELECT * FROM promotions ORDER BY is_featured DESC, promo_id ASC");
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($promotions),
        "data" => $promotions
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "เกิดข้อผิดพลาดในการดึงข้อมูลโปรโมชั่น: " . $e->getMessage()
    ]);
}
?>