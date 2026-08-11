<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

// ฟังก์ชันแปลงวันที่ไทย (วว/ดด/ปปปป) เป็น YYYY-MM-DD
function parseThaiDateToDb($date_str) {
    if (empty($date_str)) return date('Y-m-d');
    $parts = explode('/', trim($date_str));
    if (count($parts) === 3) {
        $day   = sprintf("%02d", (int)$parts[0]);
        $month = sprintf("%02d", (int)$parts[1]);
        $year  = (int)$parts[2];
        if ($year > 2400) $year -= 543;
        return "$year-$month-$day";
    }
    return date('Y-m-d');
}

// 1️⃣ สร้างคำขอจองแมสเซนเจอร์ใหม่ (Requester)
if ($action === 'create_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $booking_date_raw = $_POST['booking_date'] ?? '';
        $booking_date_db  = parseThaiDateToDb($booking_date_raw);

        $title            = trim($_POST['title'] ?? '');
        $item_type        = $_POST['item_type'] ?? 'document';
        $urgent_level     = $_POST['urgent_level'] ?? 'normal';
        $details          = trim($_POST['details'] ?? '');
        $pickup_location  = trim($_POST['pickup_location'] ?? '');
        $pickup_contact   = trim($_POST['pickup_contact'] ?? '');
        $pickup_phone     = trim($_POST['pickup_phone'] ?? '');
        $dropoff_location = trim($_POST['dropoff_location'] ?? '');
        $dropoff_contact  = trim($_POST['dropoff_contact'] ?? '');
        $dropoff_phone    = trim($_POST['dropoff_phone'] ?? '');
        
        // 🗺️ เพิ่มการรับค่าลิงก์แผนที่ปลายทาง
        $dropoff_map_link = trim($_POST['dropoff_map_link'] ?? '');

        // ✅ รหัสงานสั้นและอ่านง่าย รูปแบบ: MSG-YY-MM-XXX (เช่น MSG-26-08-001)
        $job_no = 'MSG-' . date('y-m') . '-' . sprintf("%03d", rand(1, 999));

        // 📷 แก้ไขการอัปโหลดรูปภาพพัสดุ (รองรับ multiple files / item_photo[])
        $uploaded_photos = [];
        $target_dir = '../uploads/messenger_request/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        if (isset($_FILES['item_photo']) && is_array($_FILES['item_photo']['name'])) {
            foreach ($_FILES['item_photo']['name'] as $key => $filename) {
                if ($_FILES['item_photo']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $new_filename = 'item_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['item_photo']['tmp_name'][$key], $target_dir . $new_filename)) {
                            $uploaded_photos[] = $new_filename;
                        }
                    }
                }
            }
        } elseif (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] === UPLOAD_ERR_OK) {
            // สำรองกรณีส่งไฟล์เดี่ยว
            $ext = strtolower(pathinfo($_FILES['item_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $new_filename = 'item_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['item_photo']['tmp_name'], $target_dir . $new_filename)) {
                    $uploaded_photos[] = $new_filename;
                }
            }
        }

        // แปลง Array รูปภาพเป็น JSON เพื่อลง Database
        $item_photo_db = !empty($uploaded_photos) ? json_encode($uploaded_photos) : '';

        $stmt = $pdo->prepare("
            INSERT INTO messenger_requests (
                job_no, booking_date, requester_id, title, item_type, urgent_level, details, item_photo,
                pickup_location, pickup_contact, pickup_phone,
                dropoff_location, dropoff_contact, dropoff_phone, dropoff_map_link, status
            ) VALUES (
                :job_no, :b_date, :req_id, :title, :type, :urgent, :details, :photo,
                :p_loc, :p_contact, :p_phone,
                :d_loc, :d_contact, :d_phone, :d_map, 'pending'
            )
        ");

        $stmt->execute([
            'job_no'    => $job_no,
            'b_date'    => $booking_date_db,
            'req_id'    => $user_id,
            'title'     => $title,
            'type'      => $item_type,
            'urgent'    => $urgent_level,
            'details'   => $details,
            'photo'     => $item_photo_db,
            'p_loc'     => $pickup_location,
            'p_contact' => $pickup_contact,
            'p_phone'   => $pickup_phone,
            'd_loc'     => $dropoff_location,
            'd_contact' => $dropoff_contact,
            'd_phone'   => $dropoff_phone,
            'd_map'     => $dropoff_map_link
        ]);

        $_SESSION['success_msg'] = 'สร้างคำขอส่งเอกสาร/พัสดุสำเร็จ รหัสงาน: ' . $job_no;
        header("Location: msg_history.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        header("Location: msg_index.php");
        exit();
    }
}

// 2️⃣ แมสเซนเจอร์กดรับงาน
elseif ($action === 'accept_job') {
    $job_id = (int)($_GET['id'] ?? 0);
    try {
        $chk = $pdo->prepare("SELECT status FROM messenger_requests WHERE id = :id AND status = 'pending'");
        $chk->execute(['id' => $job_id]);
        if ($chk->fetch()) {
            $stmt = $pdo->prepare("UPDATE messenger_requests SET messenger_id = :m_id, status = 'accepted', accepted_at = NOW() WHERE id = :id");
            $stmt->execute(['m_id' => $user_id, 'id' => $job_id]);
            $_SESSION['success_msg'] = 'รับงานเรียบร้อยแล้ว';
        } else {
            $_SESSION['error_msg'] = 'งานนี้มีผู้รับไปแล้วหรือถูกยกเลิก';
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
    header("Location: jobs.php");
    exit();
}

// 3️⃣ อัปเดตสถานะงาน / ถ่ายรูปส่งงาน
elseif ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id     = (int)($_POST['job_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $remark     = trim($_POST['remark'] ?? '');

    try {
        $proof_photo = '';
        if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['proof_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $proof_photo = 'proof_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $target_dir = '../uploads/messenger_request/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                move_uploaded_file($_FILES['proof_photo']['tmp_name'], $target_dir . $proof_photo);
            }
        }

        $sql = "UPDATE messenger_requests SET status = :status, remark = :remark";
        $params = ['status' => $new_status, 'remark' => $remark, 'id' => $job_id, 'm_id' => $user_id];

        if ($proof_photo !== '') {
            $sql .= ", proof_photo = :proof";
            $params['proof'] = $proof_photo;
        }

        if ($new_status === 'completed') {
            $sql .= ", completed_at = NOW()";
        }

        $sql .= " WHERE id = :id AND messenger_id = :m_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success_msg'] = 'อัปเดตสถานะงานเรียบร้อยแล้ว';
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
    header("Location: job_detail.php?id=" . $job_id);
    exit();
}
// 4️⃣ อัปเดตสถานะแบบรวดเร็ว 1 คลิกจากหน้ากระดานงาน (Jobs)
elseif ($action === 'quick_update_status') {
    $job_id     = (int)($_GET['id'] ?? 0);
    $new_status = $_GET['status'] ?? '';

    try {
        $sql = "UPDATE messenger_requests SET status = :status";
        $params = ['status' => $new_status, 'id' => $job_id, 'm_id' => $user_id];

        if ($new_status === 'completed') {
            $sql .= ", completed_at = NOW()";
        }

        $sql .= " WHERE id = :id AND messenger_id = :m_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success_msg'] = 'อัปเดตสถานะงานเรียบร้อยแล้ว';
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
    header("Location: jobs.php");
    exit();
}