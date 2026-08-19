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
$action    = $_POST['action'] ?? $_GET['action'] ?? '';

// ตรวจสอบสิทธิ์ Admin, IT Support, HR
$can_manage = in_array($user_role, ['admin', 'it_support', 'hr'], true);

// ฟังก์ชันช่วยอัปโหลดไฟล์รูปภาพหลายไฟล์
function uploadMultipleNewsImages($filesArray) {
    $uploaded_names = [];
    $allowed_exts   = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $upload_dir     = 'uploads/news/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($filesArray['name']) && is_array($filesArray['name'])) {
        $count = count($filesArray['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($filesArray['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $filesArray['tmp_name'][$i];
                $file_ext = strtolower(pathinfo($filesArray['name'][$i], PATHINFO_EXTENSION));

                if (in_array($file_ext, $allowed_exts)) {
                    $new_name = 'news_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
                    if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                        $uploaded_names[] = $new_name;
                    }
                }
            }
        }
    }
    return $uploaded_names;
}

// -------------------------------------------------------------
// 1. โพสต์ข่าวสารใหม่ (รองรับหลายรูป)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'post_news') {
    if ($can_manage) {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        $uploaded_images = [];
        if (isset($_FILES['news_images'])) {
            $uploaded_images = uploadMultipleNewsImages($_FILES['news_images']);
        }

        $image_json = !empty($uploaded_images) ? json_encode($uploaded_images, JSON_UNESCAPED_UNICODE) : null;

        if (!empty($title) && !empty($content)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO news (title, content, image, created_by, created_at) VALUES (:t, :c, :img, :u, NOW())");
                $stmt->execute([
                    't'   => $title, 
                    'c'   => $content, 
                    'img' => $image_json, 
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
        $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ในการดำเนินการนี้';
    }

    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index_mobile.php';
    header("Location: " . $redirect_url);
    exit();
}

// -------------------------------------------------------------
// 2. แก้ไขข่าวสาร (รองรับลบรูปเก่า + เพิ่มรูปใหม่)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit_news') {
    if ($can_manage) {
        $news_id = filter_input(INPUT_POST, 'news_id', FILTER_VALIDATE_INT);
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        // รูปเดิมที่เหลืออยู่ (หลังจากผู้ใช้กดลบในหน้า Modal)
        $existing_raw = $_POST['existing_images'] ?? '[]';
        $final_images = json_decode($existing_raw, true);
        if (!is_array($final_images)) {
            $final_images = [];
        }

        // อัปโหลดรูปใหม่ที่เลือกเพิ่ม
        if (isset($_FILES['news_images'])) {
            $new_uploaded = uploadMultipleNewsImages($_FILES['news_images']);
            $final_images = array_merge($final_images, $new_uploaded);
        }

        $image_json = !empty($final_images) ? json_encode(array_values($final_images), JSON_UNESCAPED_UNICODE) : null;

        if ($news_id && !empty($title) && !empty($content)) {
            try {
                $stmt = $pdo->prepare("UPDATE news SET title = :t, content = :c, image = :img WHERE id = :id");
                $stmt->execute([
                    't'   => $title,
                    'c'   => $content,
                    'img' => $image_json,
                    'id'  => $news_id
                ]);

                $_SESSION['success_msg'] = 'แก้ไขข่าวสารเรียบร้อยแล้ว';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'เกิดข้อผิดพลาดจากฐานข้อมูล: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error_msg'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        }
    } else {
        $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ในการแก้ไขข่าวสาร';
    }

    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index_mobile.php';
    header("Location: " . $redirect_url);
    exit();
}

// -------------------------------------------------------------
// 3. ลบข่าวสาร
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'delete_news') {
    if ($can_manage) {
        $news_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($news_id) {
            try {
                // ดึงรายการรูปเพื่อลบไฟล์ออกจากเครื่อง
                $stmt_get = $pdo->prepare("SELECT image FROM news WHERE id = :id");
                $stmt_get->execute(['id' => $news_id]);
                $row = $stmt_get->fetch(PDO::FETCH_ASSOC);

                if (!empty($row['image'])) {
                    $imgs = json_decode($row['image'], true);
                    if (is_array($imgs)) {
                        foreach ($imgs as $f) {
                            $path = 'uploads/news/' . $f;
                            if (file_exists($path)) @unlink($path);
                        }
                    } else {
                        $path = 'uploads/news/' . $row['image'];
                        if (file_exists($path)) @unlink($path);
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM news WHERE id = :id");
                $stmt->execute(['id' => $news_id]);
                $_SESSION['success_msg'] = 'ลบประกาศข่าวสารเรียบร้อยแล้ว';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ในการลบประกาศข่าวสาร';
    }

    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index_mobile.php';
    header("Location: " . $redirect_url);
    exit();
}

header("Location: index_mobile.php");
exit();