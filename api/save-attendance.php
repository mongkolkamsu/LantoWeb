<?php
session_start();
require_once '../config/db.php'; // เชื่อมต่อฐานข้อมูลหลัก

header('Content-Type: application/json');

// 1. ตรวจสอบสิทธิ์ความปลอดภัยเบื้องต้น
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'เซสชันหมดอายุ กรุณาล็อกอินใหม่']);
    exit();
}

$user_id = $_SESSION['user_id'];
$log_type = $_POST['log_type'] ?? '';
$latitude = (isset($_POST['latitude']) && $_POST['latitude'] !== 'null') ? $_POST['latitude'] : null;
$longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== 'null') ? $_POST['longitude'] : null;
$image_data = $_POST['image'] ?? '';

// 🎯 จุดสำคัญที่ 1: ดักรับค่า branch_id ที่ส่งมาจากหน้าบ้าน (ถ้าไม่มีหรือเป็นค่าว่างให้เซฟเป็น null)
$branch_id = (!empty($_POST['branch_id'])) ? (int)$_POST['branch_id'] : null;

if (empty($log_type) || empty($image_data)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลสแกนไม่ครบถ้วน']);
    exit();
}

try {
    // ถอดรหัสภาพรูปถ่ายใบหน้าจากสตรีม Base64
    $image_parts = explode(";base64,", $image_data);
    $image_base64 = base64_decode($image_parts[1]);
    
    $folder = ($log_type === 'check_in') ? 'scan-in' : 'scan-out';
    $upload_dir = '../uploads/' . $folder . '/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // ตั้งชื่อไฟล์รูปภาพให้ผูกกับรหัสพนักงาน
    $file_name = 'face_' . $_SESSION['employee_code'] . '_' . time() . '.jpg';
    $file_path = $upload_dir . $file_name;
    
    file_put_contents($file_path, $image_base64);

    // 🎯 จุดสำคัญที่ 2: เพิ่มคอลัมน์ branch_id เข้าไปในโครงคำสั่ง SQL INSERT เพื่อเซฟลงตารางหลัก
    $stmt = $pdo->prepare("
        INSERT INTO attendance (user_id, log_type, branch_id, scan_time, latitude, longitude, photo_log) 
        VALUES (:user_id, :log_type, :branch_id, NOW(), :latitude, :longitude, :photo_log)
    ");
    
    $stmt->execute([
        'user_id' => $user_id,
        'log_type' => $log_type,
        'branch_id' => $branch_id, // ส่งค่าไอดีสาขาไปบันทึก
        'latitude' => $latitude,
        'longitude' => $longitude,
        'photo_log' => $file_name
    ]);

    echo json_encode(['status' => 'success', 'message' => 'บันทึกเวลางานเรียบร้อยแล้วครับ']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
}