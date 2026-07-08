<?php
// 1. ดึงค่าคอนฟิกและเปิดใช้งาน Session
require_once 'config/db.php';

// 2. ล้างข้อมูลตัวแปร Session ทั้งหมดที่มีอยู่ในระบบ
$_SESSION = array();

// 3. ทำลาย Session บนเซิร์ฟเวอร์ทิ้งอย่างเด็ดขาด
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. พากลับไปยังหน้า Login คลีนๆ สบายตา
header("Location: login.php");
exit();
?>