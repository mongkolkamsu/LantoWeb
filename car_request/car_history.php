<?php
ob_start();
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/rounded_dropdown.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 🛠️ ฟังก์ชันแปลงวันที่ พ.ศ. (วว/ดด/ปปปป) เป็น ค.ศ. (YYYY-MM-DD)
function parseThaiDateToAD($dateStr) {
    if (empty($dateStr)) return '';
    $dateStr = trim($dateStr);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $matches)) {
        $d = sprintf('%02d', $matches[1]);
        $m = sprintf('%02d', $matches[2]);
        $y = (int)$matches[3];
        if ($y > 2400) { $y -= 543; }
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
    return '';
}

// 🛠️ ฟังก์ชันแปลง ค.ศ. (YYYY-MM-DD) เป็น พ.ศ. (วว/ดด/ปปปป)
function formatADDateToThai($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if (!$ts) return $dateStr;
    $d = date('d', $ts);
    $m = date('m', $ts);
    $y = (int)date('Y', $ts) + 543;
    return "{$d}/{$m}/{$y}";
}

// 🔍 2. รับค่าตัวกรองจาก Query String ($_GET)
$search_query    = trim($_GET['search'] ?? '');
$selected_status = trim($_GET['status'] ?? 'all');

// 📅 ค่าเริ่มต้นช่วงวันที่
$default_start_th = formatADDateToThai(date('Y-m-01'));
$default_end_th   = formatADDateToThai(date('Y-m-t'));
$date_range_raw   = $_GET['date_range'] ?? ($default_start_th . ' - ' . $default_end_th);

$dates_arr  = explode(' - ', $date_range_raw);
$date_start = parseThaiDateToAD($dates_arr[0] ?? '');
$date_end   = parseThaiDateToAD($dates_arr[1] ?? $dates_arr[0] ?? '');

// 3. จัดการเงื่อนไข SQL และ Parameter ให้สัมพันธ์กันอย่างถูกต้องแม่นยำ
// 3. จัดการเงื่อนไข SQL และ Parameter
$where_clauses = ["1=1"];
$params = [];

// ถ้ามีการพิมพ์คำค้นหา
if (!empty($search_query)) {
    // 🎯 แยกชื่อพารามิเตอร์ให้ไม่ซ้ำกัน เพื่อป้องกันปัญหา Invalid parameter number ของ PDO
    $where_clauses[] = "(c.brand_model LIKE :s_model OR c.license_plate LIKE :s_plate OR cr.destination LIKE :s_dest OR u.first_name LIKE :s_name OR cr.passengers_name LIKE :s_pass)";
    
    $params['s_model'] = "%{$search_query}%";
    $params['s_plate'] = "%{$search_query}%";
    $params['s_dest']  = "%{$search_query}%";
    $params['s_name']  = "%{$search_query}%";
    $params['s_pass']  = "%{$search_query}%";
} else {
    // ถ้าไม่ได้พิมพ์ค้นหา ค่อยกรองตามช่วงวันที่
    if (!empty($date_start)) {
        $where_clauses[] = "DATE(cr.start_datetime) >= :date_start";
        $params['date_start'] = $date_start;
    }
    if (!empty($date_end)) {
        $where_clauses[] = "DATE(cr.start_datetime) <= :date_end";
        $params['date_end'] = $date_end;
    }
}

// ตัวกรองสถานะการจองรถ
if ($selected_status === 'driving') {
    $where_clauses[] = "cr.status = 'approved' AND cr.start_mileage > 0 AND cr.actual_end_datetime IS NULL";
} elseif ($selected_status === 'completed') {
    $where_clauses[] = "cr.status = 'completed'";
} elseif ($selected_status === 'approved') {
    $where_clauses[] = "cr.status = 'approved' AND cr.start_mileage = 0";
} elseif ($selected_status === 'pending') {
    $where_clauses[] = "cr.status = 'pending'";
} elseif ($selected_status === 'rejected') {
    $where_clauses[] = "cr.status = 'rejected'";
}

$where_sql = implode(' AND ', $where_clauses);

$requests_list = [];
try {
    $sql = "
        SELECT cr.*, 
               c.brand_model, c.license_plate, c.province, c.car_image,
               u.first_name AS requester_firstname,
               u.employee_code
        FROM car_requests cr
        JOIN cars c ON cr.car_id = c.id
        JOIN users u ON cr.user_id = u.id
        WHERE {$where_sql}
        ORDER BY cr.start_datetime DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // แสดงรายละเอียด Error บนหน้าจอหากยังเกิดปัญหา
    echo "<div style='background: red; color: white; padding: 10px;'>SQL Error: " . $e->getMessage() . "</div>";
    $requests_list = [];
}

$status_opts = [
    ['id' => 'all', 'name' => 'ทุกสถานะ'],
    ['id' => 'driving', 'name' => '🚘 กำลังใช้งาน'],
    ['id' => 'approved', 'name' => '📌 อนุมัติแล้ว'],
    ['id' => 'pending', 'name' => '⏳ รออนุมัติ'],
    ['id' => 'completed', 'name' => '✅ คืนรถแล้ว'],
    ['id' => 'rejected', 'name' => '❌ ไม่อนุมัติ']
];

$active_status_label = 'ทุกสถานะ';
foreach ($status_opts as $opt) {
    if ($opt['id'] === $selected_status) {
        $active_status_label = $opt['name'];
        break;
    }
}

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติและตารางการใช้รถ - Lanto Workspace</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#f4f6fa] text-slate-800 antialiased pb-20 sm:pb-12">

    <!-- 🔝 Header กลางของระบบ -->
    <?php 
    $page_title    = '📋 ประวัติและตารางใช้รถยนต์';
    $page_subtitle = 'เรียกดู ค้นหา และติดตามประวัติคิวการใช้รถยนต์องค์กรทั้งหมด';
    $show_back     = true;
    $back_url      = 'car_index.php';
    include_once '../includes/header.php'; 
    ?>

    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

        <!-- 🔎 แถบตัวกรอง -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col md:flex-row justify-between items-center gap-3">
            <form method="GET" action="car_history.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">

                <!-- 📅 เลือกช่วงวันที่ -->
                <div class="w-full sm:w-60 relative">
                    <span class="absolute left-3.5 top-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </span>
                    <input type="text" name="date_range" value="<?php echo htmlspecialchars($date_range_raw); ?>" class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:border-blue-500 transition-colors h-10 cursor-pointer" placeholder="เลือกช่วงวันที่">
                </div>

                <!-- 🔍 ช่องค้นหา -->
                <div class="w-full sm:w-80">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="ค้นหาชื่อเรื่อง, ทะเบียน, ผู้จอง, จุดหมาย..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-xs font-medium focus:outline-none focus:border-blue-500 transition-colors h-10">
                </div>

                <!-- 🎯 ดร็อปดาวน์เลือกสถานะ -->
                <div class="w-48 sm:w-52">
                    <?php renderRoundedDropdown('status_select', 'status', $active_status_label, $status_opts, $selected_status, false); ?>
                </div>

                <!-- 🔘 ปุ่มค้นหา / ปุ่มล้างค่า -->
                <div class="flex items-center gap-2 ml-auto">
                    <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-colors cursor-pointer active:scale-95 h-10 flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
                        </svg>
                        <span>ค้นหา</span>
                    </button>
                    <a href="car_history.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors h-10 flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                        </svg>
                        <span>ล้างค่า</span>
                    </a>
                </div>
            </form>
        </div>

        <!-- 📋 รายการการจอง -->
        <div class="space-y-3 pt-1">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                    รายการจองรถยนต์ประจำช่วงวันที่เลือก
                </h3>
                <span class="text-[10px] font-extrabold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-100">
                    <?php echo count($requests_list); ?> รายการ
                </span>
            </div>

            <?php if (empty($requests_list)): ?>
                <div class="bg-white p-10 rounded-2xl text-center text-slate-400 border border-slate-200/80 text-xs shadow-2xs font-light">
                    🚫 ไม่พบข้อมูลการจองรถตามเงื่อนไขที่เลือก
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <?php foreach ($requests_list as $req): 
                        $img_src       = !empty($req['car_image']) ? '../uploads/cars/' . $req['car_image'] : '../assets/images/sport-car.png';
                        $start_dt      = !empty($req['start_datetime']) ? new DateTime($req['start_datetime']) : null;
                        $actual_end_dt = !empty($req['actual_end_datetime']) ? new DateTime($req['actual_end_datetime']) : null;
                        $created_dt    = !empty($req['created_at']) ? new DateTime($req['created_at']) : null;
                        
                        $start_m = (int)($req['start_mileage'] ?? 0);
                        $end_m   = (int)($req['end_mileage'] ?? 0);
                        $dist    = ($end_m > $start_m) ? ($end_m - $start_m) : 0;
                        
                        $is_currently_driving = ($req['status'] === 'approved' && $start_m > 0 && empty($req['actual_end_datetime']));
                    ?>
                        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs space-y-3 text-xs">
                            
                            <!-- หัวการ์ด: ข้อมูลรถ และ ป้ายสถานะ -->
                            <div class="flex items-start justify-between border-b border-slate-100 pb-2.5 gap-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 shrink-0 flex items-center justify-center">
                                        <img src="<?php echo $img_src; ?>" onerror="this.src='../assets/images/sport-car.png'" class="w-full h-full object-contain drop-shadow-xs">
                                    </div>
                                    <div>
                                        <h4 class="font-extrabold text-slate-800 text-xs sm:text-sm leading-tight"><?php echo htmlspecialchars($req['brand_model']); ?></h4>
                                        <span class="inline-block mt-0.5 text-[9.5px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-md border border-slate-200">
                                            <?php echo htmlspecialchars($req['license_plate'] . ' ' . $req['province']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <?php if ($req['status'] === 'completed'): ?>
                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-700 border border-slate-200/90 rounded-full font-bold text-[9.5px] shadow-2xs">คืนรถเรียบร้อย</span>
                                    <?php elseif ($is_currently_driving): ?>
                                        <span class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-full font-bold text-[9.5px] shadow-2xs">กำลังใช้งาน</span>
                                    <?php elseif ($req['status'] === 'approved'): ?>
                                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full font-bold text-[9.5px] shadow-2xs">อนุมัติแล้ว</span>
                                    <?php elseif ($req['status'] === 'rejected'): ?>
                                        <span class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-full font-bold text-[9.5px] shadow-2xs">ไม่อนุมัติ</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full font-bold text-[9.5px] shadow-2xs">รออนุมัติ</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- รายละเอียดผู้จองและสถานที่ -->
                            <div class="space-y-2 text-[11px] text-slate-600 bg-slate-50/80 p-3.5 rounded-2xl border border-slate-100">
                                <div class="flex items-center justify-between">
                                    <span>👤 <strong>ผู้จอง:</strong> <?php echo htmlspecialchars($req['requester_firstname']); ?><?php echo !empty($req['employee_code']) ? ' (' . htmlspecialchars($req['employee_code']) . ')' : ''; ?></span>
                                    <?php if (!empty($req['passenger_count']) && $req['passenger_count'] > 0): ?>
                                        <span class="text-[10px] text-slate-500 font-semibold bg-white px-2 py-0.5 rounded-md border border-slate-200/60">👥 <?php echo $req['passenger_count']; ?> คน</span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($req['passengers_name'])): ?>
                                    <p class="text-[11px]">👥 <strong>ผู้ร่วมเดินทาง:</strong> <span class="text-slate-800 font-semibold"><?php echo htmlspecialchars($req['passengers_name']); ?></span></p>
                                <?php endif; ?>
                                
                                <p>📍 <strong>สถานที่ / วัตถุประสงค์:</strong> <span class="text-slate-800 font-semibold"><?php echo htmlspecialchars($req['destination']); ?></span></p>
                                
                                <!-- บล็อกวัน-เวลา -->
                                <div class="pt-2 border-t border-slate-200/60 font-semibold text-slate-700 space-y-1">
                                    <?php if ($created_dt): ?>
                                        <div class="flex items-center justify-between text-[10.5px]">
                                            <span class="text-slate-400 font-medium">วันที่ยื่นเรื่อง:</span>
                                            <span class="text-slate-600 font-bold"><?php echo $created_dt->format('d/m/') . ($created_dt->format('Y') + 543) . ' เวลา ' . $created_dt->format('H:i') . ' น.'; ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="flex items-center justify-between text-[10.5px]">
                                        <span class="text-slate-500">เริ่มเดินทาง:</span>
                                        <span class="text-blue-900 font-bold"><?php echo $start_dt ? $start_dt->format('d/m/') . ($start_dt->format('Y') + 543) . ' เวลา ' . $start_dt->format('H:i') . ' น.' : '-'; ?></span>
                                    </div>
                                    <?php if ($actual_end_dt): ?>
                                        <div class="flex items-center justify-between text-[10.5px]">
                                            <span class="text-slate-500">คืนรถเมื่อ:</span>
                                            <span class="text-slate-800 font-bold"><?php echo $actual_end_dt->format('d/m/') . ($actual_end_dt->format('Y') + 543) . ' เวลา ' . $actual_end_dt->format('H:i') . ' น.'; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- บล็อกเลขไมล์เดินทาง -->
                                <?php if ($start_m > 0): ?>
                                    <div class="pt-2 border-t border-slate-200/60 text-[10.5px] flex items-center justify-between gap-1">
                                        <div class="text-slate-500">
                                            ไมล์เริ่ม: <strong class="text-slate-800 font-bold"><?php echo number_format($start_m); ?></strong>
                                            <?php if ($end_m > 0): ?>
                                                | คืน: <strong class="text-slate-800 font-bold"><?php echo number_format($end_m); ?></strong>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($end_m > 0): ?>
                                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-100 rounded-md font-extrabold text-[10px]">
                                                ใช้ไป <?php echo number_format($dist); ?> กม.
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($req['reject_reason'])): ?>
                                    <p class="text-rose-600 pt-1.5 border-t border-rose-100">❌ <strong>เหตุผลที่ไม่อนุมัติ:</strong> <?php echo htmlspecialchars($req['reject_reason']); ?></p>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <?php include_once '../includes/calendar_component.php'; ?>
    <script src="../assets/js/alerts.js"></script>

    <script>
        print_r(); // ล้างบรรทัดเก่าที่มีปัญหา
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (!empty($success_msg)): ?>
                if (typeof LantoAlert !== 'undefined') {
                    LantoAlert.success('ทำรายการสำเร็จ', '<?php echo addslashes($success_msg); ?>');
                }
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.href);
                }
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                if (typeof LantoAlert !== 'undefined') {
                    LantoAlert.error('เกิดข้อผิดพลาด', '<?php echo addslashes($error_msg); ?>');
                }
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.href);
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>