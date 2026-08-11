<?php

session_start();
// 🧹 บังคับล้างค่าข้อความแจ้งเตือนที่ค้างในเซสชันทิ้งทันทีหากไม่มีการส่ง POST มาใหม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['success_msg'])) {
    unset($_SESSION['success_msg']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['error_msg'])) {
    unset($_SESSION['error_msg']);
}
require_once '../config/db.php';
require_once '../config/auth.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$fullname  = $_SESSION['fullname'] ?? '';
$user_role = $_SESSION['role'] ?? 'employee';

// 🎯 เช็กสิทธิ์จัดการรถยนต์ (Admin, IT, HR)
$can_manage_cars = in_array($user_role, ['admin', 'it_support', 'it', 'hr']);

$pending_requests = [];
$pending_count = 0;
if ($can_manage_cars) {
    try {
        $sql_p = "
            SELECT cr.*, c.brand_model, c.license_plate, c.province,
                   u.first_name AS requester_name,
                   u.employee_code,
                   cr.passengers_name
            FROM car_requests cr
            JOIN cars c ON cr.car_id = c.id
            JOIN users u ON cr.user_id = u.id
            WHERE cr.status = 'pending'
            ORDER BY cr.id ASC
        ";
        $pending_requests = $pdo->query($sql_p)->fetchAll(PDO::FETCH_ASSOC);
        $pending_count    = count($pending_requests);
    } catch (PDOException $e) {
        $pending_requests = [];
    }
}

// 2. ประมวลผลเพิ่มรถใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_car') {
    if ($can_manage_cars) {
        $brand_model   = trim($_POST['brand_model'] ?? '');
        $license_plate = trim($_POST['license_plate'] ?? '');
        $province      = trim($_POST['province'] ?? 'กรุงเทพมหานคร');
        $seats         = (int)($_POST['seats'] ?? 4);

        if (!empty($brand_model) && !empty($license_plate)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO cars (brand_model, license_plate, province, seats, is_active) VALUES (:b, :l, :p, :s, 1)");
                $stmt->execute(['b' => $brand_model, 'l' => $license_plate, 'p' => $province, 's' => $seats]);
                $_SESSION['success_msg'] = 'เพิ่มข้อมูลรถยนต์เรียบร้อยแล้ว';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error_msg'] = 'กรุณากรอกยี่ห้อ/รุ่น และทะเบียนรถให้ครบถ้วน';
        }
    }
    header("Location: car_index.php");
    exit();
}

// 3. ประมวลผลแก้ไขรถยนต์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_car') {
    if ($can_manage_cars) {
        $car_id        = (int)($_POST['car_id'] ?? 0);
        $brand_model   = trim($_POST['brand_model'] ?? '');
        $license_plate = trim($_POST['license_plate'] ?? '');
        $province      = trim($_POST['province'] ?? 'กรุงเทพมหานคร');
        $seats         = (int)($_POST['seats'] ?? 4);
        $is_active     = (int)($_POST['is_active'] ?? 1);

        if ($car_id > 0 && !empty($brand_model) && !empty($license_plate)) {
            try {
                $stmt = $pdo->prepare("UPDATE cars SET brand_model = :b, license_plate = :l, province = :p, seats = :s, is_active = :a WHERE id = :id");
                $stmt->execute([
                    'b'  => $brand_model, 
                    'l'  => $license_plate, 
                    'p'  => $province, 
                    's'  => $seats, 
                    'a'  => $is_active, 
                    'id' => $car_id
                ]);
                $_SESSION['success_msg'] = 'อัปเดตข้อมูลรถยนต์เรียบร้อยแล้ว';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
    header("Location: car_index.php");
    exit();
}

// 4. ดึงรายการรถยนต์ และคิวจองล่วงหน้าทั้งหมด
$cars_list = [];
$upcoming_by_car = [];
try {
    $sql = "
        SELECT c.*, 
               cr_curr.id AS active_request_id,
               cr_curr.user_id AS request_user_id,
               cr_curr.start_mileage,
               cr_curr.status AS booking_status,
               u_curr.first_name AS driver_firstname,
               u_curr.employee_code AS driver_code
        FROM cars c
        LEFT JOIN car_requests cr_curr ON c.id = cr_curr.car_id 
             AND cr_curr.status = 'approved' 
             AND cr_curr.start_mileage > 0 
             AND cr_curr.actual_end_datetime IS NULL
        LEFT JOIN users u_curr ON cr_curr.user_id = u_curr.id
        GROUP BY c.id
        ORDER BY CASE 
            WHEN c.brand_model LIKE '%Ford-ขาว%' THEN 1
            WHEN c.brand_model LIKE '%Ford-เทา%' THEN 2
            WHEN c.license_plate = '5 ยฐ 7105' THEN 3
            WHEN c.license_plate = '5 ยฐ 7103' THEN 4
            ELSE 5 
        END ASC
    ";
    $cars_list = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // ดึงคิวจองล่วงหน้าทั้งหมดที่ยังไม่จบภารกิจ
    $sql_upcoming = "
        SELECT cr.*, 
               u.first_name AS requester_firstname,
               u.employee_code AS requester_code
        FROM car_requests cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.status IN ('approved', 'pending')
          AND cr.actual_end_datetime IS NULL
          AND cr.start_mileage = 0
        ORDER BY cr.start_datetime ASC
    ";
    $raw_upcoming = $pdo->query($sql_upcoming)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_upcoming as $u_item) {
        $upcoming_by_car[$u_item['car_id']][] = $u_item;
    }
} catch (PDOException $e) {
    $cars_list = [];
    $upcoming_by_car = [];
}

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>จองรถองค์กร - Lanto Workspace</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* 🎯 อนิเมชั่นรถวิ่งสั่นสะเทือนสไตล์โช้ครถยนต์ */
        @keyframes carDrive {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-2px) rotate(-0.5deg); }
            50% { transform: translateY(0.5px) rotate(0.3deg); }
            75% { transform: translateY(-1px) rotate(-0.2deg); }
        }
        .animate-car-drive { animation: carDrive 0.4s infinite ease-in-out; }

        /* 🎯 อนิเมชั่นเส้นถนนปะเลื่อนใต้ท้องรถ */
        @keyframes roadMove {
            0% { background-position: 0 0; }
            100% { background-position: -24px 0; }
        }
        .animate-road-move { 
            background: repeating-linear-gradient(90deg, #3b82f6 0 8px, transparent 8px 16px); 
            animation: roadMove 0.25s linear infinite; 
        }
    </style>
</head>
<body class="bg-[#f4f6fa] text-slate-800 antialiased pb-20 sm:pb-12">

    <!-- 🔝 Header กลางของระบบ -->
    <?php 
    $page_title    = '🚗 จองรถองค์กร (Car Request)';
    $page_subtitle = 'เลือกจองรถยนต์องค์กรและติดตามสถานะคิวการใช้รถ';
    $show_back     = true;
    $back_url      = '../index.php';
    include_once '../includes/header.php'; 
    ?>

    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

        <!-- 👑 กล่องควบคุมสำหรับผู้ดูแล -->
        <?php if ($can_manage_cars): ?>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-base shrink-0">
                        ⚙️
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-extrabold text-slate-800">แผงจัดการรถยนต์องค์กร</h3>
                        <p class="text-[10.5px] text-slate-400 font-semibold">เพิ่มข้อมูลรถยนต์ในระบบ หรือพิจารณาอนุมัติคำขอจองรถจากพนักงาน</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
                    <button type="button" onclick="openAddCarModal()" 
                        class="flex-1 sm:flex-none px-3.5 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl transition-all shadow-xs active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer h-9">
                        <span class="text-xs leading-none">➕</span>
                        <span>เพิ่มรถยนต์</span>
                    </button>

                    <button type="button" onclick="openPendingApprovalModal()" 
                        class="flex-1 sm:flex-none px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl transition-all shadow-xs active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer relative h-9">
                        <span class="text-xs leading-none">✅</span>
                        <span>อนุมัติคำขอ</span>
                        <?php if ($pending_count > 0): ?>
                            <span class="px-1.5 py-0.2 bg-rose-500 text-white font-black rounded-full text-[9px] animate-bounce shadow-2xs">
                                <?php echo $pending_count; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- 🚘 รายการการ์ดรถยนต์ -->
        <div class="space-y-3">
            <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider px-1">เลือกรถยนต์ที่ต้องการใช้งาน</h3>

            <?php if (empty($cars_list)): ?>
                <div class="bg-white p-10 rounded-2xl text-center text-slate-400 border border-slate-200/80 text-xs shadow-2xs font-light">
                    🚫 ขณะนี้ยังไม่มีรายการรถยนต์ในระบบ
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 sm:gap-5">
                    <?php foreach ($cars_list as $car): 
                        $car_id         = $car['id'];
                        $is_active_car  = ((int)$car['is_active'] === 1);
                        $is_driving     = !empty($car['active_request_id']); 
                        $car_user_id    = $car['request_user_id'] ?? 0;
                        
                        // 🎯 เช็กแยกรูปภาพตามรุ่นรถยนต์
                        if (!empty($car['car_image']) && file_exists('../uploads/cars/' . $car['car_image'])) {
                            $img_src = '../uploads/cars/' . $car['car_image'];
                        } elseif (strpos($car['brand_model'], 'Ford-ขาว') !== false) {
                            $img_src = '../assets/images/ford_white.png';
                        } elseif (strpos($car['brand_model'], 'Ford-เทา') !== false) {
                            $img_src = '../assets/images/ford_grey.png';
                        } elseif (stripos($car['brand_model'], 'Mg') !== false) {
                            $img_src = '../assets/images/mg_car.png';
                        } else {
                            $img_src = '../assets/images/sport-car.png';
                        }
                        
                        $car_upcoming   = $upcoming_by_car[$car_id] ?? [];
                        $has_upcoming   = !empty($car_upcoming);

                        $first_approved_queue = null;
                        foreach ($car_upcoming as $q_item) {
                            if ($q_item['status'] === 'approved' && (int)$q_item['start_mileage'] === 0) {
                                $first_approved_queue = $q_item;
                                break;
                            }
                        }

                        $can_book = ($is_active_car && !$is_driving);
                    ?>
                        <!-- 🎯 ปรับโครงสร้างการ์ดให้กระชับ สมดุลสำหรับทั้งมือถือและ PC -->
                        <div <?php if ($can_book): ?>onclick="openBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars(addslashes($car['brand_model'])); ?>', '<?php echo htmlspecialchars(addslashes($car['license_plate'] . ' ' . $car['province'])); ?>', <?php echo (int)($car['current_mileage'] ?? 0); ?>)"<?php endif; ?>
                            class="bg-white rounded-2xl sm:rounded-3xl p-3.5 sm:p-6 border border-slate-200/80 shadow-2xs flex items-center justify-between gap-2.5 sm:gap-4 transition-all <?php echo $can_book ? 'hover:border-blue-400 cursor-pointer active:scale-[0.98] hover:shadow-md' : 'opacity-85 bg-slate-50/50'; ?>">
                            
                            <div class="flex items-center gap-2.5 sm:gap-5 min-w-0 flex-1">
                                <!-- 🎯 รูปภาพรถ ย่อขนาดบนมือถือไม่ให้บีบพื้นที่อื่น -->
                                <div class="relative flex flex-col items-center shrink-0">
                                    <div class="w-20 h-14 sm:w-32 sm:h-22 flex items-center justify-center p-0.5 transition-all">
                                        <img src="<?php echo $img_src; ?>" onerror="this.src='../assets/images/sport-car.png'" 
                                            class="w-full h-full object-contain drop-shadow-md <?php echo $is_driving ? 'animate-car-drive' : ''; ?>">
                                    </div>
                                    <?php if ($is_driving): ?>
                                        <div class="w-14 sm:w-24 h-[2px] sm:h-[3px] animate-road-move mt-0.5 sm:mt-1 rounded-full"></div>
                                    <?php endif; ?>
                                </div>

                                <!-- 🎯 ข้อมูลรายละเอียดรถยนต์ -->
                                <div class="space-y-1 sm:space-y-1.5 min-w-0 flex-1">
                                    <h4 class="font-extrabold text-slate-800 text-xs sm:text-base leading-tight truncate"><?php echo htmlspecialchars($car['brand_model']); ?></h4>
                                    
                                    <div class="flex items-center gap-1 sm:gap-2 flex-wrap">
                                        <span class="inline-block px-1.5 py-0.5 sm:px-2.5 sm:py-1 bg-slate-100 text-slate-600 font-bold rounded-md sm:rounded-lg text-[9.5px] sm:text-xs border border-slate-200">
                                            <?php echo htmlspecialchars($car['license_plate'] . (!empty($car['province']) ? ' ' . $car['province'] : '')); ?>
                                        </span>
                                        <span class="text-[9.5px] sm:text-xs text-slate-400 font-semibold shrink-0">👥 <?php echo $car['seats']; ?> ที่นั่ง</span>
                                    </div>

                                    <div>
                                        <?php if (!$is_active_car): ?>
                                            <span class="inline-flex items-center gap-1 sm:gap-1.5 text-[9.5px] sm:text-xs font-extrabold text-slate-700 bg-slate-100 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full border border-slate-300">
                                                <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-slate-500 shrink-0"></span>
                                                <span>งดใช้งาน / ซ่อมบำรุง</span>
                                            </span>
                                        <?php elseif ($is_driving): ?>
                                            <span class="inline-flex items-center gap-1 sm:gap-1.5 text-[9.5px] sm:text-xs font-extrabold text-rose-900 bg-rose-50 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full border border-rose-200/80 leading-tight">
                                                <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-rose-600 animate-ping shrink-0"></span>
                                                <span>กำลังใช้งานโดย: <strong class="text-rose-950 font-black"><?php echo htmlspecialchars($car['driver_firstname'] ?? ''); ?><?php echo !empty($car['driver_code']) ? ' (' . htmlspecialchars($car['driver_code']) . ')' : ''; ?></strong></span>
                                            </span>
                                        <?php else: ?>
                                            <div class="space-y-1">
                                                <span class="inline-flex items-center gap-1 sm:gap-1.5 text-[9.5px] sm:text-xs font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full border border-emerald-200/80">
                                                    <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                                    <span>พร้อมใช้งาน</span>
                                                </span>

                                                <?php if ($has_upcoming): ?>
                                                    <div class="space-y-1 pt-0.5 sm:pt-1">
                                                        <?php foreach ($car_upcoming as $idx => $q_item): 
                                                            $dt = new DateTime($q_item['start_datetime']);
                                                            $q_num = $idx + 1;
                                                            $is_pending = ($q_item['status'] === 'pending');
                                                            
                                                            $req_display = htmlspecialchars($q_item['requester_firstname'] ?? '');
                                                            if (!empty($q_item['requester_code'])) {
                                                                $req_display .= ' (' . htmlspecialchars($q_item['requester_code']) . ')';
                                                            }
                                                        ?>
                                                            <div>
                                                                <?php if ($is_pending): ?>
                                                                    <span class="inline-flex items-center gap-1 text-[8.5px] sm:text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-full border border-amber-200">
                                                                        ⏳ <?php echo $q_num; ?>. <?php echo $req_display; ?>: <?php echo $dt->format('d/m/Y H:i'); ?> น.
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center gap-1 text-[8.5px] sm:text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded-full border border-blue-200">
                                                                        📌 <?php echo $q_num; ?>. <?php echo $req_display; ?>: <?php echo $dt->format('d/m/Y H:i'); ?> น.
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 🎯 ปุ่มดำเนินการด้านขวา -->
                            <div class="shrink-0">
                                <?php if ($is_driving && ($car_user_id == $user_id || $can_manage_cars)): ?>
                                    <button type="button" 
                                        onclick="event.stopPropagation(); openReturnCarModal(<?php echo $car['active_request_id']; ?>, '<?php echo htmlspecialchars(addslashes($car['brand_model'])); ?>', <?php echo (int)($car['start_mileage'] ?? $car['current_mileage'] ?? 0); ?>)" 
                                        class="px-3 py-1.5 sm:px-4 sm:py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl text-xs sm:text-sm shadow-md shadow-emerald-500/20 active:scale-95 transition-all cursor-pointer">
                                        คืนรถ
                                    </button>

                                <?php elseif ($first_approved_queue && $first_approved_queue['user_id'] == $user_id): ?>
                                    <button type="button" 
                                        onclick="event.stopPropagation(); openStartTripModal(<?php echo $first_approved_queue['id']; ?>, '<?php echo htmlspecialchars(addslashes($car['brand_model'])); ?>', <?php echo (int)($car['current_mileage'] ?? 0); ?>)" 
                                        class="px-3 py-1.5 sm:px-4 sm:py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl text-xs sm:text-sm shadow-md shadow-blue-500/20 active:scale-95 transition-all cursor-pointer flex items-center gap-1 sm:gap-1.5">
                                        <span>🚗</span> รับรถ
                                    </button>

                                <?php elseif ($can_manage_cars && !$is_driving): ?>
                                    <button type="button" 
                                        onclick="event.stopPropagation(); openEditCarModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars(addslashes($car['brand_model'])); ?>', '<?php echo htmlspecialchars(addslashes($car['license_plate'])); ?>', '<?php echo htmlspecialchars(addslashes($car['province'] ?? 'กรุงเทพมหานคร')); ?>', <?php echo $car['seats']; ?>, <?php echo (int)$car['is_active']; ?>)" 
                                        class="px-3 py-1.5 sm:px-4 sm:py-2.5 bg-purple-50 hover:bg-purple-100 text-purple-700 font-extrabold rounded-xl text-xs border border-purple-200/80 active:scale-95 transition-all cursor-pointer flex items-center gap-1 sm:gap-1.5">
                                        <span>✏️</span> แก้ไข
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- 📦 เรียกใช้ Modals และ Calendar Component จากไฟล์ภายนอก -->
    <?php include_once 'modals_car.php'; ?>
    <?php include_once '../includes/calendar_component.php'; ?>
    <?php include_once '../includes/time_picker_component.php'; ?>

    <script src="../assets/js/alerts.js"></script>

    <script>
        function openBookingModal(carId, carName, carPlate, currentMileage = 0) {
            document.getElementById('modal_car_id').value = carId;
            document.getElementById('modal_car_name').innerText = 'จอง ' + carName;
            document.getElementById('modal_car_plate').innerText = 'ทะเบียน: ' + carPlate;
            
            const mileageInput = document.getElementById('modal_start_mileage');
            if (mileageInput) mileageInput.value = currentMileage;
            
            switchBookingMode('now');
            document.getElementById('bookingModal').classList.remove('hidden');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
        }

        function switchBookingMode(mode) {
            const bookingTypeInput = document.getElementById('modal_booking_type');
            if (bookingTypeInput) bookingTypeInput.value = mode;
            
            const btnNow = document.getElementById('tab_now');
            const btnAdvance = document.getElementById('tab_advance');
            const mileageBox = document.getElementById('container_start_mileage');
            const mileageInput = document.getElementById('modal_start_mileage');
            
            const dateInput = document.getElementById('modal_start_date');
            const timeInput = document.getElementById('modal_start_time');

            if (mode === 'now') {
                if (btnNow) btnNow.className = "py-2 rounded-xl bg-blue-600 text-white shadow-xs transition-all cursor-pointer";
                if (btnAdvance) btnAdvance.className = "py-2 rounded-xl text-slate-500 hover:text-slate-800 transition-all cursor-pointer";
                
                if (mileageBox) mileageBox.classList.remove('hidden');
                if (mileageInput) mileageInput.setAttribute('required', 'required');
                
                const now = new Date();
                const d = String(now.getDate()).padStart(2, '0');
                const m = String(now.getMonth() + 1).padStart(2, '0');
                const y = now.getFullYear() + 543;
                if (dateInput) dateInput.value = `${d}/${m}/${y}`;
                
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                if (timeInput) timeInput.value = `${hh}:${mm}`;

            } else {
                if (btnAdvance) btnAdvance.className = "py-2 rounded-xl bg-blue-600 text-white shadow-xs transition-all cursor-pointer";
                if (btnNow) btnNow.className = "py-2 rounded-xl text-slate-500 hover:text-slate-800 transition-all cursor-pointer";
                
                if (mileageBox) mileageBox.classList.add('hidden');
                if (mileageInput) mileageInput.removeAttribute('required');
                
                if (dateInput) dateInput.value = '';
                if (timeInput) timeInput.value = '';
            }
        }

        function openStartTripModal(reqId, carName, currentMileage = 0) {
            const reqIdInput = document.getElementById('start_trip_request_id');
            const titleEl = document.getElementById('start_modal_title');
            const startMileInput = document.getElementById('input_trip_start_mileage');
            
            if (reqIdInput) reqIdInput.value = reqId;
            if (titleEl) titleEl.innerText = 'รับรถ ' + carName;
            if (startMileInput) startMileInput.value = currentMileage;
            
            const modal = document.getElementById('startTripModal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeStartTripModal() {
            const modal = document.getElementById('startTripModal');
            if (modal) modal.classList.add('hidden');
        }

        function openAddCarModal() {
            const m = document.getElementById('addCarModal');
            if (m) m.classList.remove('hidden');
        }

        function closeAddCarModal() {
            const m = document.getElementById('addCarModal');
            if (m) m.classList.add('hidden');
        }

        function openEditCarModal(carId, brandModel, licensePlate, province, seats, isActive = 1) {
            document.getElementById('edit_car_id').value = carId;
            document.getElementById('edit_brand_model').value = brandModel;
            document.getElementById('edit_license_plate').value = licensePlate;
            document.getElementById('edit_province').value = province;
            document.getElementById('edit_seats').value = seats;
            
            selectEditStatus(isActive, isActive == 1 ? '🟢 พร้อมใช้งาน' : '🔴 งดใช้งาน / ซ่อมบำรุง');
            
            document.getElementById('editCarModal').classList.remove('hidden');
        }

        function closeEditCarModal() {
            document.getElementById('editCarModal').classList.add('hidden');
            const menu = document.getElementById('editStatusDropdownMenu');
            if (menu) menu.classList.add('hidden');
        }

        function toggleEditStatusDropdown() {
            const menu = document.getElementById('editStatusDropdownMenu');
            if (menu) menu.classList.toggle('hidden');
        }

        function selectEditStatus(val, label) {
            const input = document.getElementById('edit_is_active');
            const labelEl = document.getElementById('edit_is_active_label');
            if (input) input.value = val;
            if (labelEl) labelEl.innerText = label;
            
            const menu = document.getElementById('editStatusDropdownMenu');
            if (menu) menu.classList.add('hidden');
        }

        function openPendingApprovalModal() {
            const m = document.getElementById('pendingApprovalModal');
            if (m) m.classList.remove('hidden');
        }

        function closePendingApprovalModal() {
            const m = document.getElementById('pendingApprovalModal');
            if (m) m.classList.add('hidden');
        }

        function openRejectModalFromIndex(reqId) {
            document.getElementById('index_reject_request_id').value = reqId;
            document.getElementById('indexRejectModal').classList.remove('hidden');
        }

        function closeIndexRejectModal() {
            document.getElementById('indexRejectModal').classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($success_msg)): ?>
                if (window.LantoAlert) LantoAlert.success('สำเร็จ', '<?php echo addslashes($success_msg); ?>');
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                if (window.LantoAlert) LantoAlert.error('แจ้งเตือน', '<?php echo addslashes($error_msg); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>