<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=169DB_1;charset=utf8", "169Muser1", "dXzB5zUX");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}