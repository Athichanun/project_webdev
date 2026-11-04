<?php
ob_start();
include "mainConnect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../front_end/register.html");
    exit();
}

$cusName         = isset($_POST['cus_name']) ? trim($_POST['cus_name']) : '';
$phone           = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email           = isset($_POST['email']) ? trim($_POST['email']) : '';
$user            = isset($_POST['username']) ? trim($_POST['username']) : '';
$pass            = isset($_POST['password']) ? trim($_POST['password']) : '';
$confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

// 1. ตรวจสอบว่ากรอกข้อมูลครบทุกช่องหรือไม่
if (empty($cusName) || empty($phone) || empty($email) || empty($user) || empty($pass)) {
    header("Location: ../front_end/register.html?error=emptyfields");
    exit();
}

// 2. ตรวจสอบรูปแบบข้อมูลตาม Pattern ที่กำหนดในหน้า register.html
// 2.1 ตรวจสอบชื่อ-นามสกุล (ภาษาไทยและเว้นวรรคระหว่างชื่อและนามสกุล)
if (!preg_match('/^[ก-๙]+(?:\s+[ก-๙]+)+$/u', $cusName)) {
    header("Location: ../front_end/register.html?error=invalidname");
    exit();
}

// 2.2 ตรวจสอบเบอร์โทรศัพท์ (ขึ้นต้นด้วย 06, 08, 09 และมี 10 หลัก)
if (!preg_match('/^0[689]\d{8}$/', $phone)) {
    header("Location: ../front_end/register.html?error=invalidphone");
    exit();
}

// 2.3 ตรวจสอบรูปแบบอีเมล
if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
    header("Location: ../front_end/register.html?error=invalidemail");
    exit();
}

// 3. ตรวจสอบรหัสผ่านตรงกันหรือไม่
if ($pass !== $confirmPassword) {
    header("Location: ../front_end/register.html?error=passwordmismatch");
    exit();
}

try {
    // 4. ตรวจสอบว่าชื่อผู้ใช้ (username) ถูกใช้งานไปแล้วหรือไม่
    $stmtCheckUser = $pdo->prepare("SELECT cus_id FROM Customer WHERE username = ? LIMIT 1");
    $stmtCheckUser->execute([$user]);
    if ($stmtCheckUser->fetch()) {
        header("Location: ../front_end/register.html?error=userexists");
        exit();
    }

    // 5. ตรวจสอบว่ามีอีเมลนี้ในระบบแล้วหรือไม่
    $stmtCheckEmail = $pdo->prepare("SELECT cus_id, username FROM Customer WHERE email = ? LIMIT 1");
    $stmtCheckEmail->execute([$email]);
    $existing = $stmtCheckEmail->fetch(PDO::FETCH_ASSOC);

    if ($existing && !empty($existing['username'])) {
        // อีเมลนี้มีบัญชีสมาชิกอยู่แล้ว
        header("Location: ../front_end/register.html?error=emailexists");
        exit();
    }

    // สร้างรหัสผ่านแบบ Hash ที่ปลอดภัย (Bcrypt)
    $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

    if ($existing && empty($existing['username'])) {
        // กรณีเคยจองห้องพักมาก่อน มีชื่อ/อีเมล แต่ยังไม่ได้สมัครสมาชิก -> อัปเดตให้เป็นสมาชิก
        try {
            $stmtUpdate = $pdo->prepare("UPDATE Customer SET cus_name = ?, phone = ?, username = ?, password = ?, is_member = 1 WHERE cus_id = ?");
            $stmtUpdate->execute([$cusName, $phone, $user, $hashedPassword, $existing['cus_id']]);
        } catch (PDOException $ex) {
            $stmtUpdate = $pdo->prepare("UPDATE Customer SET cus_name = ?, phone = ?, username = ?, password = ? WHERE cus_id = ?");
            $stmtUpdate->execute([$cusName, $phone, $user, $hashedPassword, $existing['cus_id']]);
        }
        $cusId = $existing['cus_id'];
    } else {
        // เพิ่มข้อมูลสมาชิกใหม่ลงในตาราง Customer
        try {
            $sql = "INSERT INTO Customer (cus_name, phone, email, username, password, is_member) VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cusName, $phone, $email, $user, $hashedPassword]);
        } catch (PDOException $ex) {
            $sql = "INSERT INTO Customer (cus_name, phone, email, username, password) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cusName, $phone, $email, $user, $hashedPassword]);
        }
        $cusId = $pdo->lastInsertId();
    }

    // 6. เข้าสู่ระบบให้อัตโนมัติด้วยการตั้งค่าคุกกี้
    setcookie("memberId", $cusId, time() + 86400, "/");
    setcookie("username", $user, time() + 86400, "/");
    setcookie("cus_name", $cusName, time() + 86400, "/");
    setcookie("phone", $phone, time() + 86400, "/");
    setcookie("email", $email, time() + 86400, "/");

    // 7. ไปยังหน้าแรก main.html
    header("Location: ../front_end/main.html");
    exit();

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการสมัครสมาชิก: " . $e->getMessage());
}
?>
