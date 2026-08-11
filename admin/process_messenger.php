<?php
require_once '../config/auth.php';

// 🔒 ตรวจสอบสิทธิ์เฉพาะ Admin, HR, และ IT
if (!in_array($user_role, ['admin', 'hr', 'it_support'], true)) {
    $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ทำรายการนี้';
    header("Location: messengers.php");
    exit();
}

$action         = $_POST['action'] ?? $_GET['action'] ?? '';
$target_user_id = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

if ($target_user_id > 0) {
    try {
        if ($action === 'add_messenger') {
            // 🎯 เปลี่ยน Role หลักเป็น 'messenger'
            $stmt = $pdo->prepare("UPDATE users SET role = 'messenger' WHERE id = :id");
            $stmt->execute(['id' => $target_user_id]);
            $_SESSION['success_msg'] = 'แต่งตั้งเป็นแมสเซนเจอร์เรียบร้อยแล้ว';

        } elseif ($action === 'remove_messenger') {
            // 🎯 ปรับ Role กลับเป็นพนักงานทั่วไป 'employee'
            $stmt = $pdo->prepare("UPDATE users SET role = 'employee' WHERE id = :id");
            $stmt->execute(['id' => $target_user_id]);
            $_SESSION['success_msg'] = 'ถอดสิทธิ์แมสเซนเจอร์เรียบร้อยแล้ว';
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

header("Location: messengers.php");
exit();