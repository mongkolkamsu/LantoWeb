<?php
session_start();
require_once '../config/db.php';

// 🔑 ตรวจสอบสิทธิ์ความปลอดภัยก่อนทำรายการ
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'it_support', 'hr'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_id      = $_POST['leave_id'] ?? null;
    $action        = $_POST['action'] ?? null;
    $reject_reason = trim($_POST['reject_reason'] ?? '');

    if (!empty($leave_id) && in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        try {
            // 🎯 ลองอัปเดตสถานะและเหตุผลการไม่อนุมัติลงใน DB
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = :status, reject_reason = :reject_reason WHERE id = :id");
            $stmt->execute([
                'status'        => $status,
                'reject_reason' => ($status === 'rejected') ? $reject_reason : null,
                'id'            => $leave_id
            ]);
        } catch (PDOException $e) {
            // หากในโครงสร้างตาราง DB ยังไม่มีคอลัมน์ reject_reason ให้ข้ามไปอัปเดตเฉพาะ status
            try {
                $stmt = $pdo->prepare("UPDATE leave_requests SET status = :status WHERE id = :id");
                $stmt->execute([
                    'status' => $status,
                    'id'     => $leave_id
                ]);
            } catch (PDOException $ex) {}
        }
    }
}

// 🎯 โหลดกลับไปยังหน้าที่กดส่งเข้ามาทันที
$referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: " . $referer);
exit();
?>