<?php
// กำหนดค่าคงที่ส่วนกลางสำหรับโปรเจกต์ Lanto Web
define('SITE_NAME', 'Lanto Web');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL (ปรับเปลี่ยน User/Password ตาม phpMyAdmin ของคุณได้เลย)
$host = 'localhost';
$db   = 'lanto_web';
$user = 'root';         // ปกติ XAMPP/WampServer จะเป็น root
$pass = '';             // ปกติ XAMPP จะว่างเปล่า (ถ้าเป็น MAMP อาจจะเป็น root)
$charset = 'utf8mb4';

// กำหนด Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// ตั้งค่า Option ของ PDO เพื่อความปลอดภัยและเสถียรภาพ
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ให้เปิดแจ้งเตือนเป็น Exception เมื่อโค้ด SQL มีปัญหา
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ให้ดึงข้อมูลออกมาในรูปแบบ Array Associative (ตามชื่อคอลัมน์)
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ปิดการจำลอง Prepare เพื่อป้องกัน SQL Injection แบบ 100%
];

try {
    // เริ่มต้นการเชื่อมต่อฐานข้อมูลผ่าน PDO
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // เปิดใช้งาน Session สำหรับระบบ Login และตรวจเช็คสิทธิ์ (Role)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
} catch (\PDOException $e) {
    // หากเชื่อมต่อไม่สำเร็จ ให้แสดงข้อความแจ้งเตือนความผิดพลาด
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}
?>