<?php
ob_start();
include "mainConnect.php";

$checkIn = isset($_GET['check_in']) ? trim($_GET['check_in']) : '';
$checkOut = isset($_GET['check_out']) ? trim($_GET['check_out']) : '';
$guestsNum = isset($_GET['guests_num']) ? (int)$_GET['guests_num'] : 0;
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'default';
$sql = "SELECT * FROM rooms WHERE 1=1";
$params = [];

if ($guestsNum > 0) {
    $sql .= " AND max_guests >= ?";
    $params[] = $guestsNum;
}

switch ($sortBy) {
    case 'price_asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY room_name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY room_name DESC";
        break;
    case 'guests_desc':
        $sql .= " ORDER BY max_guests DESC";
        break;
    default:
        $sql .= " ORDER BY room_id ASC";
        break;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'count' => count($rooms),
        'filters' => [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests_num' => $guestsNum,
            'sort_by' => $sortBy
        ],
        'data' => $rooms
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
    ]);
}
