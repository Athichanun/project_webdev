<?php
ob_start();
include "mainConnect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../front_end/logIn.html");
    exit();
}

$user = $_POST['username'];
$pass = $_POST['password'];

$sql = "SELECT * FROM user WHERE username = ? AND password = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user, $pass]);
$result = $stmt->fetch();

if ($result) {
    setcookie("memberId", 1, time() + 3600, "/");
    echo("success");
    header("Location: ../front_end/main.html");
    exit();
} else {
    echo("fail");
    header("Location: ../front_end/logIn.html");
    exit();
}
