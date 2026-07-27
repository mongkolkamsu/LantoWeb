<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('SITE_NAME', 'Lanto Web');

// 🎯 เช็คอัตโนมัติว่ารันบนเครื่องตัวเอง (XAMPP) หรือรันบนเซิร์ฟเวอร์จริง (Plesk)
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    
    // 💻 [ค่าสำหรับรันใน XAMPP เครื่องคุณ]
    $host = 'localhost';
    $db   = 'lanto_web'; // ใส่ชื่อฐานข้อมูลที่คุณใช้ใน XAMPP (เช่น lanto_web)
    $user = 'root';
    $pass = '';          // XAMPP ปกติรหัสผ่านว่างเปล่า
    
} else {
    
    // 🚀 [ค่าสำหรับรันบน Plesk Server จริง]
    $host = 'localhost';
    $db   = 'hrlan_app';
    $user = 'hrlan_app';
    $pass = 'adminlantoapp';
    
}

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}
?>