<?php
ob_start();
include "mainConnect.php";

$roomType = isset($_GET['room_type']) ? $_GET['room_type'] : 'all';
$priceRange = isset($_GET['price_range']) ? $_GET['price_range'] : 'all';
$guests = isset($_GET['guests']) ? $_GET['guests'] : 'all';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'default';

$sql = "SELECT * FROM rooms WHERE 1=1";
$params = [];

if ($roomType !== 'all') {
    $sql .= " AND room_type = ?";
    $params[] = $roomType;
}

if ($priceRange !== 'all') {
    switch ($priceRange) {
        case '0-2000':
            $sql .= " AND price < 2000";
            break;
        case '2000-5000':
            $sql .= " AND price >= 2000 AND price <= 5000";
            break;
        case '5000+':
            $sql .= " AND price > 5000";
            break;
    }
}

if ($guests !== 'all') {
    switch ($guests) {
        case '1-2':
            $sql .= " AND max_guests >= 1 AND max_guests <= 2";
            break;
        case '3-4':
            $sql .= " AND max_guests >= 3 AND max_guests <= 4";
            break;
        case '5+':
            $sql .= " AND max_guests >= 5";
            break;
    }
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

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'count' => count($rooms),
    'filters' => [
        'room_type' => $roomType,
        'price_range' => $priceRange,
        'guests' => $guests,
        'sort_by' => $sortBy
    ],
    'data' => $rooms
]);
