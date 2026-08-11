<?php
session_start();
require_once 'config/db.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งานระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'employee';
$action    = $_POST['action'] ?? '';

// -------------------------------------------------------------
// 📢 โพสต์ข่าวสารใหม่พร้อมรูปภาพ (เฉพาะ HR, IT, Admin)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'post_news') {
    if (in_array($user_role, ['admin', 'it_support', 'hr'], true)) {
        $title      = trim($_POST['title'] ?? '');
        $content    = trim($_POST['content'] ?? '');
        $image_name = null;

        // จัดการอัปโหลดรูปภาพประกอบข่าว
        if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp     = $_FILES['news_image']['tmp_name'];
            $file_ext     = strtolower(pathinfo($_FILES['news_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($file_ext, $allowed_exts)) {
                $upload_dir = 'uploads/news/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $image_name = 'news_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
                move_uploaded_file($file_tmp, $upload_dir . $image_name);
            }
        }

        if (!empty($title) && !empty($content)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO news (title, content, image, created_by, created_at) VALUES (:t, :c, :img, :u, NOW())");
                $stmt->execute([
                    't'   => $title, 
                    'c'   => $content, 
                    'img' => $image_name, 
                    'u'   => $user_id
                ]);
                $_SESSION['success_msg'] = 'ลงประกาศข่าวสารเรียบร้อยแล้ว';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'เกิดข้อผิดพลาดจากฐานข้อมูล: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error_msg'] = 'กรุณากรอกหัวข้อและเนื้อหาให้ครบถ้วน';
        }
    } else {
        $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ในการโพสต์ประกาศข่าวสาร';
    }

    // ดีดกลับหน้าก่อนหน้าอัตโนมัติ
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index_mobile.php';
    header("Location: " . $redirect_url);
    exit();
}

// หากไม่มี Action ที่ตรงกัน ให้กลับหน้าหลัก
header("Location: index_mobile.php");
exit();