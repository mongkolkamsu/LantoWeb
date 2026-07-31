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

// 🎯 ดักรับค่า branch_id ที่ส่งมาจากหน้าบ้าน
$branch_id = (!empty($_POST['branch_id'])) ? (int)$_POST['branch_id'] : null;

if (empty($log_type) || empty($image_data)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลสแกนไม่ครบถ้วน']);
    exit();
}

// 🎯 2. ดักจับและป้องกันการสแกนซ้ำ/เบิ้ล (Double Scan & Cooldown Protection)
$today = date('Y-m-d');
$has_in = false;
$has_out = false;
$last_scan_time = 0;

try {
    $stmt_chk = $pdo->prepare("SELECT log_type, scan_time FROM attendance WHERE user_id = :user_id AND DATE(scan_time) = :today ORDER BY scan_time ASC");
    $stmt_chk->execute(['user_id' => $user_id, 'today' => $today]);
    $logs = $stmt_chk->fetchAll(PDO::FETCH_ASSOC);

    foreach ($logs as $l) {
        if ($l['log_type'] === 'check_in')  $has_in = true;
        if ($l['log_type'] === 'check_out') $has_out = true;
        $last_scan_time = strtotime($l['scan_time']);
    }

    // ❌ 2.1 หากลงเวลาทั้งเข้าและออกครบแล้ว ห้ามสแกนเพิ่มอีก
    if ($has_in && $has_out) {
        echo json_encode(['status' => 'error', 'message' => 'คุณได้ลงเวลาเข้า-ออกงานประจำวันนี้ครบถ้วนแล้ว']);
        exit();
    }

    // ❌ 2.2 ป้องกันการสแกนซ้ำประเภทเดิม (เช่น สแกนเข้าซ้ำ ทั้งที่สแกนเข้างานไปแล้ว)
    if ($log_type === 'check_in' && $has_in) {
        echo json_encode(['status' => 'error', 'message' => 'คุณได้ทำการสแกนเข้างานประจำวันนี้ไปแล้ว']);
        exit();
    }

    // ❌ 2.3 ป้องกันการสแกนกดเบิ้ลถี่เกินไป (Cooldown 60 วินาที)
    if ($last_scan_time > 0 && (time() - $last_scan_time < 60)) {
        echo json_encode(['status' => 'error', 'message' => 'คุณเพิ่งทำการสแกนไป กรุณารออย่างน้อย 1 นาทีเพื่อสแกนใหม่อีกครั้ง']);
        exit();
    }

} catch (PDOException $e) {
    // ซ่อนข้อผิดพลาด
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

    // บันทึกลงตาราง attendance
    $stmt = $pdo->prepare("
        INSERT INTO attendance (user_id, log_type, branch_id, scan_time, latitude, longitude, photo_log) 
        VALUES (:user_id, :log_type, :branch_id, NOW(), :latitude, :longitude, :photo_log)
    ");
    
    $stmt->execute([
        'user_id' => $user_id,
        'log_type' => $log_type,
        'branch_id' => $branch_id,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'photo_log' => $file_name
    ]);

    echo json_encode(['status' => 'success', 'message' => 'บันทึกเวลางานเรียบร้อยแล้วครับ']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
}