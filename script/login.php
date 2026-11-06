<?php
session_start();
include "mainConnect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../front_end/logIn.html");
    exit();
}

$user = trim($_POST['username'] ?? '');
$pass = trim($_POST['password'] ?? '');

// ==========================================
// 1. เช็คจากตาราง Admin ก่อน
// ==========================================
$sqlAdmin = "SELECT * FROM Admin WHERE admin_name = ? LIMIT 1";
$stmtAdmin = $pdo->prepare($sqlAdmin);
$stmtAdmin->execute([$user]);
$adminResult = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

$isAdminValid = false;
if ($adminResult && !empty($adminResult['admin_password'])) {
    if (password_verify($pass, $adminResult['admin_password']) || $pass === $adminResult['admin_password']) {
        $isAdminValid = true;
    }
}

if ($isAdminValid) {
    $adminId = $adminResult['admin_id'];

    $_SESSION['adminId'] = $adminId;
    $_SESSION['username'] = $adminResult['admin_name'];
    $_SESSION['isAdmin'] = 1;

    setcookie("adminId", (string)$adminId, time() + 86400, "/");
    setcookie("username", (string)$adminResult['admin_name'], time() + 86400, "/");
    setcookie("cus_name", "Admin (" . (string)$adminResult['admin_name'] . ")", time() + 86400, "/");
    setcookie("isAdmin", "1", time() + 86400, "/");

    // ล้างคุกกี้ฝั่งลูกค้าออกเพื่อไม่ให้ตีกัน
    setcookie("memberId", "", time() - 3600, "/");
    setcookie("old_user", "0", time() - 3600, "/");

    header("Location: ../front_end/main.html");
    exit();
}

// ==========================================
// 2. เช็คตาราง Customer ตามปกติ (โค้ดเดิม 100%)
// ==========================================
$sql = "SELECT * FROM Customer WHERE username = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$isValid = false;
if ($result && !empty($result['password'])) {
    if (password_verify($pass, $result['password']) || $pass === $result['password']) {
        $isValid = true;
    }
}

if ($isValid) {
    $cusId = $result['cus_id'];

    $_SESSION['memberId'] = $cusId;
    $_SESSION['username'] = $result['username'];
    $_SESSION['cus_name'] = $result['cus_name'] ?? $user;
    $_SESSION['phone'] = $result['phone'] ?? '';
    $_SESSION['email'] = $result['email'] ?? '';
    $_SESSION['old_user'] = (int)($result['old_user'] ?? 0);
    unset($_SESSION['isAdmin']);

    setcookie("memberId", (string)$cusId, time() + 86400, "/");
    setcookie("username", (string)$result['username'], time() + 86400, "/");
    if (!empty($result['cus_name'])) {
        setcookie("cus_name", (string)$result['cus_name'], time() + 86400, "/");
    }
    if (!empty($result['phone'])) {
        setcookie("phone", (string)$result['phone'], time() + 86400, "/");
    }
    if (!empty($result['email'])) {
        setcookie("email", (string)$result['email'], time() + 86400, "/");
    }
    setcookie("old_user", (string)((int)($result['old_user'] ?? 0)), time() + 86400, "/");

    // ล้างคุกกี้ Admin ทิ้ง (ถ้ามีค้างอยู่)
    setcookie("isAdmin", "", time() - 3600, "/");
    setcookie("adminId", "", time() - 3600, "/");

    header("Location: ../front_end/main.html");
    exit();
} else {
    header("Location: ../front_end/logIn.html?error=invalid");
    exit();
}
?>