<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=169DB_8;charset=utf8", "169Muser8", "8Xf660Eu");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
