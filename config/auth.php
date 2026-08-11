<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$login_path = file_exists('login.php') ? 'login.php' : '../login.php';

// กรณีไม่ได้ล็อกอิน
if (!isset($_SESSION['user_id'])) {
    session_write_close(); // 🎯 ปลดล็อกก่อน Redirect
    header("Location: " . $login_path);
    exit();
}

// กรณีล็อกอินแล้ว ดึงข้อมูลผู้ใช้กลาง
$user_id       = $_SESSION['user_id'];
$fullname      = $_SESSION['fullname'] ?? 'ไม่ระบุชื่อ';
$employee_code = $_SESSION['employee_code'] ?? '-';
$user_role     = $_SESSION['role'] ?? 'employee';

// 🎯 ปลดล็อก Session ทันทีหลังจากอ่านค่าเสร็จ
session_write_close();
?>