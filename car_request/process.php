<?php
session_start();
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'employee';

// 🎯 เช็กสิทธิ์จัดการและอนุมัติรถยนต์ (Admin, IT, HR)
$can_manage_cars = in_array($user_role, ['admin', 'it_support', 'it', 'hr']);

// 🎯 ฟังก์ชันแปลงวันที่ไทย (วว/ดด/ปปปป) + เวลา เป็น MySQL Datetime
function formatThaiDateToMySQL($dateStr, $timeStr = '00:00') {
    if (empty($dateStr)) return '';
    
    if (strpos($dateStr, 'T') !== false) {
        return str_replace('T', ' ', $dateStr) . ':00';
    }
    
    $parts = explode('/', trim($dateStr));
    if (count($parts) === 3) {
        $day   = sprintf('%02d', (int)$parts[0]);
        $month = sprintf('%02d', (int)$parts[1]);
        $year  = (int)$parts[2];
        
        if ($year > 2400) {
            $year -= 543;
        }
        
        return sprintf('%04d-%02d-%02d %s:00', $year, $month, $day, $timeStr);
    }
    
    return $dateStr;
}

// 🎯 ฟังก์ชัน Redirect กลับหน้า car_index.php โดยตรงเพื่อความเสถียร
function redirectBack() {
    header("Location: car_index.php");
    exit();
}

$action = $_POST['action'] ?? '';

// -------------------------------------------------------------
// 1. ยื่นคำขอจองรถ
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'request_car') {
    $car_id          = trim($_POST['car_id'] ?? '');
    $destination     = trim($_POST['destination'] ?? '');
    $passenger_count = !empty($_POST['passenger_count']) ? (int)$_POST['passenger_count'] : 0;
    $passengers_name = trim($_POST['passengers_name'] ?? '');
    $booking_type    = $_POST['booking_type'] ?? 'now';
    
    $start_mileage   = 0; 
    
    $raw_start_date  = $_POST['start_date'] ?? '';
    $raw_start_time  = $_POST['start_time'] ?? '';

    $start_datetime  = formatThaiDateToMySQL($raw_start_date, $raw_start_time);

    if (empty($car_id) || empty($destination) || empty($raw_start_date) || empty($raw_start_time)) {
        $_SESSION['error_msg'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        redirectBack();
    }

    try {
        $stmt_check_overlap = $pdo->prepare("
            SELECT COUNT(*) FROM car_requests 
            WHERE car_id = :car_id 
              AND status IN ('approved', 'pending')
              AND actual_end_datetime IS NULL
              AND DATE(start_datetime) = DATE(:start_dt)
        ");
        $stmt_check_overlap->execute([
            'car_id'   => $car_id,
            'start_dt' => $start_datetime
        ]);

        if ($stmt_check_overlap->fetchColumn() > 0) {
            $_SESSION['error_msg'] = 'รถคันนี้มีคิวจองในวันดังกล่าวแล้ว กรุณาเลือกวันอื่นหรือรถคันอื่น';
            redirectBack();
        }

        $stmt_insert = $pdo->prepare("
            INSERT INTO car_requests (user_id, car_id, destination, passenger_count, passengers_name, start_mileage, start_datetime, status)
            VALUES (:user_id, :car_id, :destination, :passenger_count, :passengers_name, :start_mileage, :start_dt, 'pending')
        ");
        $stmt_insert->execute([
            'user_id'         => $user_id,
            'car_id'          => $car_id,
            'destination'     => $destination,
            'passenger_count' => $passenger_count,
            'passengers_name' => $passengers_name,
            'start_mileage'   => $start_mileage,
            'start_dt'        => $start_datetime
        ]);

        $_SESSION['success_msg'] = 'ยื่นคำขอจองรถเรียบร้อยแล้ว รอการอนุมัติ';
        redirectBack();

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'เกิดข้อผิดพลาดจากฐานข้อมูล: ' . $e->getMessage();
        redirectBack();
    }
}

// -------------------------------------------------------------
// 1.5 บันทึกรับรถ/เริ่มเดินทาง
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'start_trip') {
    $request_id    = (int)($_POST['request_id'] ?? 0);
    $start_mileage = (int)($_POST['start_mileage'] ?? 0);

    if ($request_id > 0 && $start_mileage > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE car_requests SET start_mileage = :sm WHERE id = :id");
            $stmt->execute(['sm' => $start_mileage, 'id' => $request_id]);
            $_SESSION['success_msg'] = 'บันทึกไมล์ออกเดินทางเรียบร้อยแล้ว เดินทางปลอดภัยครับ';
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = 'กรุณากรอกเลขไมล์เริ่มต้นให้ถูกต้อง';
    }
    redirectBack();
}

// -------------------------------------------------------------
// 2. อนุมัติคำขอจองรถ (Admin / HR / IT)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve_booking') {
    if ($can_manage_cars) {
        $request_id = (int)($_POST['request_id'] ?? 0);
        if ($request_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE car_requests SET status = 'approved', approved_by = :admin_id WHERE id = :id");
                $stmt->execute(['admin_id' => $user_id, 'id' => $request_id]);
                $_SESSION['success_msg'] = 'อนุมัติคำขอจองรถเรียบร้อยแล้ว';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ในการอนุมัติคำขอนี้';
    }
    redirectBack();
}

// -------------------------------------------------------------
// 3. ปฏิเสธคำขอจองรถ (Admin / HR / IT)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reject_booking') {
    if ($can_manage_cars) {
        $request_id    = (int)($_POST['request_id'] ?? 0);
        $reject_reason = trim($_POST['reject_reason'] ?? '');
        if ($request_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE car_requests SET status = 'rejected', reject_reason = :reason, approved_by = :admin_id WHERE id = :id");
                $stmt->execute(['reason' => $reject_reason, 'admin_id' => $user_id, 'id' => $request_id]);
                $_SESSION['success_msg'] = 'ปฏิเสธคำขอจองรถเรียบร้อยแล้ว';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ในการปฏิเสธคำขอนี้';
    }
    redirectBack();
}

// -------------------------------------------------------------
// 4. บันทึกการคืนรถ และคำนวณระยะทาง
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'return_car') {
    $request_id  = (int)($_POST['request_id'] ?? 0);
    $end_mileage = (int)($_POST['end_mileage'] ?? 0);

    if ($request_id > 0 && $end_mileage > 0) {
        try {
            $stmt_req = $pdo->prepare("SELECT car_id, start_mileage FROM car_requests WHERE id = :id");
            $stmt_req->execute(['id' => $request_id]);
            $req_data = $stmt_req->fetch(PDO::FETCH_ASSOC);

            if ($req_data) {
                $car_id        = $req_data['car_id'];
                $start_mileage = (int)$req_data['start_mileage'];

                if ($end_mileage < $start_mileage) {
                    $_SESSION['error_msg'] = 'เลขไมล์เมื่อคืนรถต้องไม่น้อยกว่าเลขไมล์เริ่มต้น (' . $start_mileage . ' กม.)';
                    redirectBack();
                }

                $stmt_u1 = $pdo->prepare("
                    UPDATE car_requests 
                    SET status = 'completed', 
                        end_mileage = :end_m, 
                        actual_end_datetime = NOW() 
                    WHERE id = :id
                ");
                $stmt_u1->execute(['end_m' => $end_mileage, 'id' => $request_id]);

                $stmt_u2 = $pdo->prepare("UPDATE cars SET current_mileage = :end_m WHERE id = :id");
                $stmt_u2->execute(['end_m' => $end_mileage, 'id' => $car_id]);

                $distance = $end_mileage - $start_mileage;
                $_SESSION['success_msg'] = 'บันทึกคืนรถเรียบร้อยแล้ว (ระยะทางที่ใช้ไป ' . number_format($distance) . ' กม.)';
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = 'กรุณากรอกเลขไมล์คืนรถให้ถูกต้อง';
    }
    redirectBack();
}

redirectBack();