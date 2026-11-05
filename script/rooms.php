<?php
ob_start();
include "mainConnect.php";

$roomType = isset($_GET['room_type']) ? $_GET['room_type'] : 'all';
$priceRange = isset($_GET['price_range']) ? $_GET['price_range'] : 'all';
$guests = isset($_GET['guests']) ? $_GET['guests'] : 'all';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'default';
$checkIn = isset($_GET['check_in']) ? trim($_GET['check_in']) : '';
$checkOut = isset($_GET['check_out']) ? trim($_GET['check_out']) : '';

$sql = "SELECT * FROM rooms WHERE 1=1";
$params = [];

if (!empty($checkIn) && !empty($checkOut)) {
    $sql .= " AND NOT EXISTS (
        SELECT 1 FROM Booking b 
        WHERE b.room_id = rooms.room_id 
          AND b.check_in < ? 
          AND b.check_out > ?
          AND (b.payment_status IS NULL OR b.payment_status != 'Cancelled')
    )";
    $params[] = $checkOut;
    $params[] = $checkIn;
}

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
$memberId = isset($_COOKIE['memberId']) ? (int)$_COOKIE['memberId'] : 0;
$isMember = ($memberId > 0) || (isset($_GET['is_member']) && $_GET['is_member'] == '1');
$oldUserCount = 0;

if ($memberId > 0) {
    try {
        $stmtCus = $pdo->prepare("SELECT is_member, old_user FROM Customer WHERE cus_id = ? LIMIT 1");
        $stmtCus->execute([$memberId]);
        $cusData = $stmtCus->fetch(PDO::FETCH_ASSOC);
        if ($cusData) {
            if (isset($cusData['is_member'])) {
                $isMember = ($cusData['is_member'] == 1);
            }
            if (isset($cusData['old_user'])) {
                $oldUserCount = (int)$cusData['old_user'];
            }
        }
    } catch (PDOException $e) {
        $oldUserCount = isset($_COOKIE['old_user']) ? (int)$_COOKIE['old_user'] : 0;
    }
} else {
    if (isset($_GET['old_user'])) {
        $oldUserCount = (int)$_GET['old_user'];
    } elseif (isset($_COOKIE['old_user'])) {
        $oldUserCount = (int)$_COOKIE['old_user'];
    }
}

$isOldUser = ($isMember && $oldUserCount > 0);
$discountPercent = 0;
if ($isMember) {
    $discountPercent = $isOldUser ? 20 : 15;
}

foreach ($rooms as &$room) {
    $rawPrice = (float)$room['price'];
    $room['original_price'] = $rawPrice;
    if ($discountPercent > 0) {
        $room['price'] = round($rawPrice * (1 - $discountPercent / 100), 2);
        $room['is_member_discount'] = true;
        $room['discount_percent'] = $discountPercent;
        $room['is_old_user'] = $isOldUser;
    } else {
        $room['is_member_discount'] = false;
        $room['discount_percent'] = 0;
        $room['is_old_user'] = false;
    }

    if (!empty($checkIn) && !empty($checkOut)) {
        $room['date_available'] = true;
    }
}
unset($room);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'count' => count($rooms),
    'is_member' => $isMember,
    'is_old_user' => $isOldUser,
    'discount_percent' => $discountPercent,
    'old_user_count' => $oldUserCount,
    'filters' => [
        'room_type' => $roomType,
        'price_range' => $priceRange,
        'guests' => $guests,
        'sort_by' => $sortBy,
        'check_in' => $checkIn,
        'check_out' => $checkOut
    ],
    'data' => $rooms
]);
?>