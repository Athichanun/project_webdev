<?php
session_start();
include "mainConnect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../front_end/logIn.html");
    exit();
}

$user = trim($_POST['username'] ?? '');
$pass = trim($_POST['password'] ?? '');

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

    header("Location: ../front_end/main.html");
    exit();
} else {
    header("Location: ../front_end/logIn.html?error=invalid");
    exit();
}
?>
