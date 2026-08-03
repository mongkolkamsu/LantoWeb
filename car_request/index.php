<?php
session_start();
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$fullname  = $_SESSION['fullname'] ?? '';
$user_role = $_SESSION['role'] ?? 'employee';

// 🎯 เช็กสิทธิ์ว่าผู้ใช้คนนี้สามารถจัดการรถยนต์ได้หรือไม่ (Admin, IT, HR)
$can_manage_cars = in_array($user_role, ['admin', 'it_support', 'it', 'hr']);

$pending_requests = [];
$pending_count = 0;
if ($can_manage_cars) {
    try {
        $sql_p = "
            SELECT cr.*, c.brand_model, c.license_plate, c.province,
                   CONCAT(u.first_name, ' ', u.last_name) AS requester_name
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
    header("Location: index.php");
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
    header("Location: index.php");
    exit();
}

// 4. ดึงรายการรถยนต์ (🎯 จัดเรียงรถพร้อมใช้งานขึ้นก่อน -> รถติดจอง -> รถงดใช้งาน)
$cars_list = [];
try {
    $sql = "
        SELECT c.*, 
               cr.id AS active_request_id,
               cr.user_id AS request_user_id,
               cr.start_mileage,
               cr.status AS booking_status,
               cr.start_datetime, cr.destination,
               CONCAT(u.first_name, ' ', u.last_name) AS driver_fullname,
               u.employee_code AS driver_code
        FROM cars c
        LEFT JOIN car_requests cr ON c.id = cr.car_id 
             AND cr.status IN ('approved', 'pending')
        LEFT JOIN users u ON cr.user_id = u.id
        GROUP BY c.id
        ORDER BY c.is_active DESC, 
                 CASE WHEN cr.id IS NULL THEN 0 ELSE 1 END ASC, 
                 c.id DESC
    ";
    $cars_list = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cars_list = [];
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
    <title>เลือกจองรถองค์กร - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { display: none; }

        @keyframes carIdle { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
        .animate-car-idle { animation: carIdle 2s infinite ease-in-out; }

        @keyframes carDrive {
            0% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(1px, -1.5px) rotate(0.8deg); }
            50% { transform: translate(0, 0) rotate(0deg); }
            75% { transform: translate(-1px, 1px) rotate(-0.8deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }
        .animate-car-drive { animation: carDrive 0.35s infinite ease-in-out; }

        @keyframes roadMove { 0% { background-position: 0 0; } 100% { background-position: -20px 0; } }
        .animate-road-move { background: linear-gradient(90deg, #3b82f6 50%, transparent 50%); background-size: 8px 2px; animation: roadMove 0.3s linear infinite; }
    </style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex justify-center text-slate-800 antialiased p-0 md:py-6">

    <!-- 📱 Main Container -->
    <div class="w-full min-h-screen bg-white/40 backdrop-blur-xl flex flex-col justify-between relative overflow-y-auto p-5 pb-28
        md:max-w-md md:min-h-[812px] md:h-auto md:rounded-[40px] md:border md:border-white/60 md:shadow-2xl">
        
        <div>
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-200/60">
                <a href="../index.php?view=mobile" class="w-9 h-9 bg-white border border-slate-200 text-slate-600 rounded-full flex items-center justify-center shadow-xs active:scale-90 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="text-sm font-bold tracking-wide text-slate-700">จองรถองค์กร (Car Request)</h2>
                <a href="history.php" class="text-xs font-bold text-blue-600 hover:underline">ประวัติ</a>
            </div>

            <!-- 👑 ปุ่มจัดการดูแลระบบ -->
            <?php if ($can_manage_cars): ?>
                <div class="mt-3.5 grid grid-cols-2 gap-2">
                    <button type="button" onclick="openAddCarModal()" 
                        class="py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1 shadow-md shadow-purple-500/20 active:scale-95 transition-all cursor-pointer">
                        <span>➕</span> เพิ่มรถยนต์
                    </button>

                    <button type="button" onclick="openPendingApprovalModal()" 
                        class="py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1 shadow-md shadow-emerald-500/20 active:scale-95 transition-all cursor-pointer">
                        <span>✅</span> อนุมัติคำขอ
                        <?php if ($pending_count > 0): ?>
                            <span class="ml-1 px-1.5 py-0.5 bg-rose-500 text-white font-extrabold rounded-full text-[9px] animate-bounce">
                                <?php echo $pending_count; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </div>
            <?php endif; ?>

            <!-- 🚘 รายการการ์ดรถยนต์ -->
            <div class="mt-4 space-y-3">
                <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider px-1">เลือกรถยนต์ที่ต้องการใช้งาน</h3>

                <?php if (empty($cars_list)): ?>
                    <div class="bg-white/80 p-8 rounded-2xl text-center text-slate-400 border border-slate-200/60 text-xs font-light">
                        🚫 ขณะนี้ยังไม่มีรายการรถยนต์ในระบบ
                    </div>
                <?php else: ?>
                    <?php foreach ($cars_list as $car): 
                        $is_active_car  = ((int)$car['is_active'] === 1);
                        $is_in_use      = !empty($car['active_request_id']);
                        $booking_status = $car['booking_status'] ?? '';
                        $is_driving     = ($is_in_use && $booking_status === 'approved');
                        $img_src        = !empty($car['car_image']) ? '../uploads/cars/' . $car['car_image'] : '../assets/images/sport-car.png';
                        $car_user_id    = $car['request_user_id'] ?? 0;
                        $can_book       = ($is_active_car && !$is_in_use);
                    ?>
                        <div <?php if ($can_book): ?>onclick="openBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars(addslashes($car['brand_model'])); ?>', '<?php echo htmlspecialchars(addslashes($car['license_plate'] . ' ' . $car['province'])); ?>', <?php echo (int)($car['current_mileage'] ?? 0); ?>)"<?php endif; ?>
                            class="bg-white rounded-2xl p-3.5 border border-slate-200/80 shadow-xs flex items-center justify-between gap-3 transition-all <?php echo $can_book ? 'hover:border-blue-400 cursor-pointer active:scale-[0.98]' : 'opacity-85 bg-slate-50/50'; ?>">
                            
                            <div class="flex items-center gap-3">
                                <div class="relative flex flex-col items-center shrink-0">
                                    <div class="w-13 h-13 flex items-center justify-center p-0.5 transition-all">
                                        <img src="<?php echo $img_src; ?>" onerror="this.src='../assets/images/sport-car.png'" 
                                            class="w-full h-full object-contain drop-shadow-xs <?php echo $is_driving ? 'animate-car-drive' : ''; ?>">
                                    </div>
                                    <?php if ($is_driving): ?>
                                        <div class="w-12 h-[2px] animate-road-move mt-1 rounded-full"></div>
                                    <?php endif; ?>
                                </div>

                                <div class="space-y-1">
                                    <h4 class="font-extrabold text-slate-800 text-xs leading-tight"><?php echo htmlspecialchars($car['brand_model']); ?></h4>
                                    
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-600 font-bold rounded-md text-[10px] border border-slate-200">
                                            <?php echo htmlspecialchars($car['license_plate'] . (!empty($car['province']) ? ' ' . $car['province'] : '')); ?>
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-semibold">👥 <?php echo $car['seats']; ?> ที่นั่ง</span>
                                    </div>

                                    <div>
                                        <?php if (!$is_active_car): ?>
                                            <span class="inline-flex items-center gap-1 text-[9.5px] font-extrabold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200/80">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                งดใช้งาน / ซ่อมบำรุง
                                            </span>
                                        <?php elseif ($is_driving): ?>
                                            <span class="inline-flex items-center gap-1 text-[9.5px] font-extrabold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200/80">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                                กำลังใช้งานอยู่โดย: <strong class="text-slate-900"><?php echo htmlspecialchars($car['driver_fullname'] ?? ''); ?></strong>
                                            </span>
                                        <?php elseif ($is_in_use && $booking_status === 'pending'): ?>
                                            <span class="inline-flex items-center gap-1 text-[9.5px] font-extrabold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200/80">
                                                รออนุมัติการจองโดย: <strong class="text-slate-900"><?php echo htmlspecialchars($car['driver_fullname'] ?? ''); ?></strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[9.5px] font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200/80">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                พร้อมใช้งาน
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ปุ่มด้านขวาของการ์ดรถ -->
                            <div class="shrink-0">
                                <?php if ($is_driving && ($car_user_id == $user_id || $can_manage_cars)): ?>
                                    <button type="button" 
                                        onclick="event.stopPropagation(); openReturnCarModal(<?php echo $car['active_request_id']; ?>, '<?php echo htmlspecialchars(addslashes($car['brand_model'])); ?>', <?php echo (int)($car['start_mileage'] ?? $car['current_mileage'] ?? 0); ?>)" 
                                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-500/20 active:scale-95 transition-all cursor-pointer">
                                        คืนรถ
                                    </button>
                                <?php elseif ($can_manage_cars && !$is_driving): ?>
                                    <button type="button" 
                                        onclick="event.stopPropagation(); openEditCarModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars(addslashes($car['brand_model'])); ?>', '<?php echo htmlspecialchars(addslashes($car['license_plate'])); ?>', '<?php echo htmlspecialchars(addslashes($car['province'] ?? 'กรุงเทพมหานคร')); ?>', <?php echo $car['seats']; ?>, <?php echo (int)$car['is_active']; ?>)" 
                                        class="px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold rounded-xl text-xs border border-purple-200/80 active:scale-95 transition-all cursor-pointer flex items-center gap-1">
                                        <span>✏️</span> แก้ไข
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <?php include '../includes/navbar.php'; ?>
    </div>

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
            if (mileageInput) {
                mileageInput.value = currentMileage;
            }
            
            document.getElementById('bookingModal').classList.remove('hidden');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
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