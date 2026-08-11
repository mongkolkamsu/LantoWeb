<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('SITE_NAME', 'Lanto Web');

// 🎯 ดึงชื่อ Host โดยตัดเรื่อง Port (เช่น :8080) ออก
$http_host = isset($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] : '';

// 🎯 เช็คอัตโนมัติว่ารันบนเครื่องตัวเอง (XAMPP) หรือรันบนเซิร์ฟเวอร์จริง (Plesk)
if ($http_host === 'localhost' || $http_host === '127.0.0.1') {
    
    // 💻 [ค่าสำหรับรันใน XAMPP เครื่องคุณ]
    $host = 'localhost';
    $db   = 'lanto_web'; 
    $user = 'root';
    $pass = '';          
    
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
    // แสดงข้อผิดพลาดชัดเจนเพื่อการแก้ไข
    header("HTTP/1.1 500 Internal Server Error");
    die("<div style='font-family: sans-serif; padding: 20px; border: 1px solid #f87171; background: #fef2f2; color: #991b1b; rounded: 12px;'>
            <h3>⚠️ การเชื่อมต่อฐานข้อมูลล้มเหลว</h3>
            <p><b>Host:</b> {$host} | <b>Database:</b> {$db}</p>
            <p><b>Error Details:</b> " . htmlspecialchars($e->getMessage()) . "</p>
         </div>");
}
?>