<?php
session_start();
require_once '../config/db.php'; // เชื่อมต่อฐานข้อมูลหลัก

// 1. ตรวจสอบสิทธิ์ความปลอดภัย
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

mb_internal_encoding("UTF-8");

$user_id = $_SESSION['user_id'];
$leave_type = $_POST['leave_type'] ?? '';
$leave_duration = $_POST['leave_duration'] ?? 'full';
$leave_hours = isset($_POST['leave_hours']) ? (int)$_POST['leave_hours'] : 0;
$start_leave_raw = $_POST['start_leave'] ?? '';
$end_leave_raw = $_POST['end_leave'] ?? '';
$reason = $_POST['reason'] ?? '';

// 🎯 แก้ไขจุดนี้: เติมช่องว่างหลังคำว่า function เรียบร้อยแล้ว ระบบอ่านคำสั่งได้ปกติแล้วครับ
function คอลLantoAlert($type, $title, $message, $redirectUrl = null) {
    $callbackAction = $redirectUrl ? "window.location.href = '$redirectUrl';" : "window.history.back();";
    echo "
    <!DOCTYPE html>
    <html lang='th'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <script src='https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4'></script>
        <link href='https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap' rel='stylesheet'>
        <style>body { font-family: 'Prompt', sans-serif; }</style>
    </head>
    <body class='bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen'>
        <!-- เรียกใช้สคริปต์แจ้งเตือนจากส่วนกลาง -->
        <script src='../assets/js/alerts.js'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof LantoAlert !== 'undefined') {
                    LantoAlert.{$type}('{$title}', '{$message}', function() {
                        {$callbackAction}
                    });
                } else {
                    alert('{$message}');
                    {$callbackAction}
                }
            });
        </script>
    </body>
    </html>";
    exit();
}

// ฟังก์ชันแปลงวันที่ พ.ศ. -> ค.ศ.
function convertThaiDateToMysql($thai_date) {
    $parts = explode('/', $thai_date);
    if (count($parts) === 3) {
        $day = $parts[0];
        $month = $parts[1];
        $year = (int)$parts[2] - 543;
        return "$year-$month-$day";
    }
    return null;
}

$start_date = convertThaiDateToMysql($start_leave_raw);
$end_date = convertThaiDateToMysql($end_leave_raw);

// ตรวจสอบข้อมูลทั่วไป
if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
    คอลLantoAlert('warning', 'ข้อมูลไม่ครบถ้วน', 'กรุณากรอกรายละเอียดข้อมูลการลาให้ครบถ้วนก่อนส่งระบบครับ');
}

// ระบบจัดการตรวจสอบและอัปโหลดไฟล์รูปภาพหลักฐาน
if (!isset($_FILES['leave_attachment']) || $_FILES['leave_attachment']['error'] !== UPLOAD_ERR_OK) {
    คอลLantoAlert('error', 'ไม่พบไฟล์หลักฐาน', 'จำเป็นต้องแนบรูปภาพเอกสารหรือใบรับรองแพทย์ประกอบการลาทุกครั้งครับ');
}

$file_uploaded = $_FILES['leave_attachment'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
$file_ext = strtolower(pathinfo($file_uploaded['name'], PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_extensions)) {
    คอลLantoAlert('warning', 'รูปแบบไฟล์ไม่ถูกต้อง', 'ระบบรองรับเฉพาะรูปภาพนามสกุล jpg, jpeg, png และ webp เท่านั้นครับ');
}

// สร้างโฟลเดอร์รองรับรูปภาพหากยังไม่มีในระบบ
$upload_dir = '../uploads/leaves/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ตั้งชื่อรูปภาพใหม่ป้องกันชื่อซ้ำกันในระบบ
$new_file_name = 'LEAVE_' . $user_id . '_' . time() . '.' . $file_ext;
$dest_path = $upload_dir . $new_file_name;

if (!move_uploaded_file($file_uploaded['tmp_name'], $dest_path)) {
    คอลLantoAlert('error', 'อัปโหลดล้มเหลว', 'ไม่สามารถย้ายไฟล์เข้าสู่คลังระบบได้ กรุณาลองใหม่อีกครั้งครับ');
}

// ยิงข้อมูลเข้าคลังฐานข้อมูล
try {
    $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, leave_duration, leave_hours, start_date, end_date, reason, attachment, status) 
                           VALUES (:user_id, :leave_type, :leave_duration, :leave_hours, :start_date, :end_date, :reason, :attachment, 'pending')");
    
    $stmt->execute([
        'user_id' => $user_id,
        'leave_type' => $leave_type,
        'leave_duration' => $leave_duration,
        'leave_hours' => $leave_hours,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'reason' => $reason,
        'attachment' => $new_file_name
    ]);

    // บันทึกสำเร็จ: เรียกใช้กล่องเขียวแจ้งสำเร็จจาก alerts.js[cite: 13]
    คอลLantoAlert('success', 'ส่งใบลาสำเร็จ', 'ส่งใบอนุมัติแจ้งลาพร้อมหลักฐานสำเร็จแล้วครับ รอดำเนินการตรวจสอบจาก HR', 'leave.php');

} catch (PDOException $e) {
    // หากฐานข้อมูลพัง ให้ลบรูปที่พึ่งอัปโหลดทิ้งเพื่อลดขยะ
    if (file_exists($dest_path)) {
        unlink($dest_path);
    }
    คอลLantoAlert('error', 'ระบบขัดข้อง', 'เกิดข้อผิดพลาดในการบันทึกข้อมูลเข้าสู่ฐานข้อมูลหลัก กรุณาลองใหม่อีกครั้งครับ');
}