<?php
// กำหนดค่าคงที่ส่วนกลางสำหรับโปรเจกต์ Lanto Web
define('SITE_NAME', 'Lanto Web');

// 🎯 แก้ไขตรงนี้ให้เป็นค่าของ Plesk จริงได้เลยครับ (เวลารันในคอมอาจจะเข้าไม่ได้แปบนึง แต่บนเซิร์ฟเวอร์จะผ่านฉลุย)
$host = 'localhost';
$db   = 'hrlan_app';         //  เปลี่ยนจาก lanto_web เป็น hrlan_app
$user = 'hrlan_app';         //  เปลี่ยนจาก root เป็น hrlan_app
$pass = 'adminlantoapp';     //  เปลี่ยนจากค่าว่าง เป็น adminlantoapp
$charset = 'utf8mb4';

// กำหนด Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// ตั้งค่า Option ของ PDO เพื่อความปลอดภัยและเสถียรภาพ
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
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