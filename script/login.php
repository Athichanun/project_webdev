<?php
ob_start();
include "mainConnect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../front_end/logIn.html");
    exit();
}

$user = $_POST['username'];
$pass = $_POST['password'];

$sql = "SELECT * FROM Customer WHERE username = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบรหัสผ่านด้วย password_verify (และรองรับรหัสผ่านแบบข้อความเดิม เพื่ออัปเกรดเป็น Hash อัตโนมัติ)
$isValidPassword = false;
if ($result && !empty($result['password'])) {
    if (password_verify($pass, $result['password'])) {
        $isValidPassword = true;
    } elseif ($pass === $result['password']) {
        // หากในฐานข้อมูลยังเป็นข้อความธรรมดา ให้ล็อกอินได้และอัปเกรดเป็น Hash ทันที
        $isValidPassword = true;
        $newHash = password_hash($pass, PASSWORD_DEFAULT);
        $updateHash = $pdo->prepare("UPDATE Customer SET password = ? WHERE cus_id = ?");
        $updateHash->execute([$newHash, $result['cus_id']]);
    }
}

if ($isValidPassword) {
    $cusId = !empty($result['cus_id']) ? $result['cus_id'] : (!empty($result['id']) ? $result['id'] : 1);
    setcookie("memberId", $cusId, time() + 86400, "/");
    setcookie("username", $result['username'] ?? $user, time() + 86400, "/");
    if (!empty($result['cus_name'])) {
        setcookie("cus_name", $result['cus_name'], time() + 86400, "/");
    }
    if (!empty($result['phone'])) {
        setcookie("phone", $result['phone'], time() + 86400, "/");
    }
    if (!empty($result['email'])) {
        setcookie("email", $result['email'], time() + 86400, "/");
    }
    header("Location: ../front_end/main.html");
    exit();
} else {
    header("Location: ../front_end/logIn.html?error=invalid");
    exit();
}
