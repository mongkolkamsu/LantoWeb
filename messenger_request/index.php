<?php

session_start();
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
require_once '../config/db.php';
require_once '../includes/rounded_dropdown.php';
require_once '../config/auth.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id       = $_SESSION['user_id'];
$fullname      = $_SESSION['fullname'] ?? '';
$employee_code = $_SESSION['employee_code'] ?? '';

// 📅 การจัดการเดือนและปีสำหรับปฏิทิน
$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year  = (int)($_GET['year'] ?? date('Y'));

$prev_month_ts = strtotime("-1 month", strtotime("$selected_year-$selected_month-01"));
$next_month_ts = strtotime("+1 month", strtotime("$selected_year-$selected_month-01"));

$prev_month = date('n', $prev_month_ts);
$prev_year  = date('Y', $prev_month_ts);
$next_month = date('n', $next_month_ts);
$next_year  = date('Y', $next_month_ts);

$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

$thai_days = [
    0 => 'อาทิตย์', 1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ',
    4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์'
];

// ข้อความวัน เดือน ปี ปัจจุบัน
$today_w     = date('w');
$today_d     = date('j');
$today_m     = (int)date('n');
$today_y_th  = date('Y') + 543;
$current_date_text = "วัน{$thai_days[$today_w]} ที่ {$today_d} เดือน {$thai_months[$today_m]} ปี {$today_y_th}";

// ดึงข้อมูลผู้ใช้งาน
$user_info = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, d.name AS dept_name, b.name AS branch_name 
        FROM users u 
        LEFT JOIN departments d ON u.department = d.id 
        LEFT JOIN branches b ON u.branch_id = b.id 
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 📊 ดึงรายการจองทั้งหมดของเดือนที่เลือก
$monthly_jobs = [];
try {
    $start_db = sprintf("%04d-%02d-01", $selected_year, $selected_month);
    $end_db   = date('Y-m-t', strtotime($start_db));

    $stmt_jobs = $pdo->prepare("
        SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) AS requester_name
        FROM messenger_requests m
        INNER JOIN users u ON m.requester_id = u.id
        WHERE m.status != 'cancelled' 
          AND m.booking_date BETWEEN :s_date AND :e_date
        ORDER BY m.id ASC
    ");
    $stmt_jobs->execute(['s_date' => $start_db, 'e_date' => $end_db]);
    
    while ($row = $stmt_jobs->fetch(PDO::FETCH_ASSOC)) {
        $b_date = $row['booking_date'];
        if (!isset($monthly_jobs[$b_date])) {
            $monthly_jobs[$b_date] = [];
        }
        $monthly_jobs[$b_date][] = $row;
    }
} catch (PDOException $e) {}

$back_url = (isset($_GET['view']) && $_GET['view'] === 'mobile') ? '../index_mobile.php' : '../index_pc.php';

// ตัวเลือก Dropdown สำหรับส่งเข้า Modal
$item_type_options = [
    ['id' => 'document', 'name' => '📄 เอกสาร / ซองจดหมาย'],
    ['id' => 'parcel', 'name' => '📦 กล่องพัสดุ / ของชิ้นเล็ก'],
    ['id' => 'other', 'name' => '🏷️ อื่นๆ']
];

$urgent_options = [
    ['id' => 'normal', 'name' => '🟢 ปกติ (ส่งภายในวัน)'],
    ['id' => 'urgent', 'name' => '🟡 ด่วน (ส่งภายใน 2-3 ชั่วโมง)'],
    ['id' => 'express', 'name' => '🔴 ด่วนที่สุด (ส่งทันที)']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messenger Calendar - Lanto Workspace</title>
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
<body class="bg-[#f8fafc] min-h-screen text-slate-800 antialiased pb-20 sm:pb-12">

    <!-- 🔝 1. ดึง Header กลางของระบบ (แสดงโปรไฟล์และการแจ้งเตือน) -->
    <?php 
    $page_title    = '📦 ตารางคิวจองแมสเซนเจอร์';
    $page_subtitle = 'ดูคิวงานและคลิกเลือกวันที่ในปฏิทินเพื่อจองวิ่งส่งเอกสาร';
    $show_back     = true;
    $back_url      = (isset($_GET['view']) && $_GET['view'] === 'mobile') ? '../index_mobile.php' : '../index_pc.php';
    include_once '../includes/header.php'; 
    ?>

    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">
        
        <!-- 📅 แถบควบคุมปฏิทิน -->
        <div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-4 border-b border-slate-100 pb-4">
                
                <!-- 1. ฝั่งซ้าย: วันที่ปัจจุบัน & นาฬิกา -->
                <div class="space-y-1 text-center lg:text-left">
                    <h2 class="text-base sm:text-xl font-black text-slate-800 flex items-center justify-center lg:justify-start gap-2">
                        <span class="text-blue-600">🗓️</span> <?php echo $current_date_text; ?>
                    </h2>
                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-2 text-xs font-bold">
                        <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 px-3 py-1 rounded-xl border border-blue-200/80 shadow-3xs">
                            ⏰ เวลา <span id="live_clock_display" class="text-sm tracking-wide font-black"><?php echo date('H:i:s'); ?></span> น.
                        </span>
                        <span class="text-[11px] font-bold text-slate-400 sm:hidden">
                            👈 เลื่อนซ้าย-ขวาเพื่อดูตารางปฏิทิน
                        </span>
                    </div>
                </div>

                <!-- 🎯 2. ตรงกลาง: ปุ่มเปลี่ยนเดือน (แสดงชื่อเดือนที่อยู่ตอนนี้ตรงกลาง) -->
                <div class="flex items-center justify-center gap-10 bg-slate-100/80 p-2 rounded-2xl border border-slate-200/80 shadow-3xs -translate-x-10">
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="px-3.5 py-2 bg-white hover:bg-slate-200 text-slate-700 rounded-xl transition-colors font-bold text-xs shadow-2xs shrink-0">
                        ‹ ก่อนหน้า
                    </a>
                    <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" title="คลิกเพื่อกลับมาเดือนปัจจุบัน" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all font-black text-xs shadow-md shadow-blue-500/20 active:scale-95 shrink-0">
                        <?php echo $thai_months[$selected_month] . ' ' . ($selected_year + 543); ?>
                    </a>
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="px-3.5 py-2 bg-white hover:bg-slate-200 text-slate-700 rounded-xl transition-colors font-bold text-xs shadow-2xs shrink-0">
                        ถัดไป ›
                    </a>
                </div>

                <!-- 3. ฝั่งขวา: ปุ่มกระทำหลัก (จองแมสเซนเจอร์ / ประวัติงาน / กระดานแมส) -->
                <div class="hidden sm:flex items-center justify-center lg:justify-end gap-2 shrink-0">
                    <button type="button" onclick="openBookingModal('<?php echo date('Y-m-d'); ?>', '<?php echo date('d/m/') . (date('Y') + 543); ?>')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-extrabold shadow-md shadow-blue-500/20 transition-all cursor-pointer flex items-center gap-1.5 active:scale-95">
                        <span>➕</span> <span>จองแมสเซนเจอร์</span>
                    </button>
                    <a href="jobs.php" class="px-3.5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold shadow-2xs transition-colors">
                        🛵 กระดานแมส
                    </a>
                </div>

            </div>

            <!-- 🗓️ ตารางปฏิทินขนาดใหญ่ (ล็อกความสูง h-[120px] ป้องกันปฏิทินยาวเกินไป) -->
            <div class="overflow-x-auto">
                <div class="min-w-[680px] bg-slate-100/80 p-3 rounded-3xl border border-slate-200/90">
                    
                    <!-- 🎯 แถบชื่อวันเรียงสีตามวันประจำสัปดาห์ -->
                    <div class="grid grid-cols-7 gap-2 mb-2 text-center text-xs font-black uppercase tracking-wider">
                        <div class="py-2.5 bg-amber-50 text-amber-700 rounded-xl border border-amber-200/80 shadow-3xs">จันทร์</div>
                        <div class="py-2.5 bg-pink-50 text-pink-700 rounded-xl border border-pink-200/80 shadow-3xs">อังคาร</div>
                        <div class="py-2.5 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200/80 shadow-3xs">พุธ</div>
                        <div class="py-2.5 bg-orange-50 text-orange-700 rounded-xl border border-orange-200/80 shadow-3xs">พฤหัสบดี</div>
                        <div class="py-2.5 bg-sky-50 text-sky-700 rounded-xl border border-sky-200/80 shadow-3xs">ศุกร์</div>
                        <div class="py-2.5 bg-purple-50 text-purple-700 rounded-xl border border-purple-200/80 shadow-3xs">เสาร์</div>
                        <div class="py-2.5 bg-rose-50 text-rose-600 rounded-xl border border-rose-200/80 shadow-3xs">อาทิตย์</div>
                    </div>

                    <div class="grid grid-cols-7 gap-2">
                        <?php
                        $first_day_w = date('w', strtotime("$selected_year-$selected_month-01"));
                        $first_day_of_month = ($first_day_w == 0) ? 6 : ($first_day_w - 1);
                        $days_in_month      = date('t', strtotime("$selected_year-$selected_month-01"));
                        $today_ad           = date('Y-m-d');

                        // 🎨 กำหนดสีตัวเลขประจำวันตามวันสัปดาห์ (0=อาทิตย์, 1=จันทร์, 2=อังคาร, 3=พุธ, 4=พฤหัสบดี, 5=ศุกร์, 6=เสาร์)
                        $day_color_map = [
                            0 => 'text-rose-600 font-extrabold',    // อาทิตย์ (แดง)
                            1 => 'text-amber-600 font-extrabold',   // จันทร์ (เหลือง)
                            2 => 'text-pink-600 font-extrabold',    // อังคาร (ชมพู)
                            3 => 'text-emerald-600 font-extrabold', // พุธ (เขียว)
                            4 => 'text-orange-600 font-extrabold',  // พฤหัสบดี (ส้ม)
                            5 => 'text-sky-600 font-extrabold',     // ศุกร์ (ฟ้า/น้ำเงิน)
                            6 => 'text-purple-600 font-extrabold',  // เสาร์ (ม่วง)
                        ];

                        // ช่องว่างต้นเดือน
                        for ($i = 0; $i < $first_day_of_month; $i++) {
                            echo '<div class="bg-slate-200/40 border border-slate-200/50 rounded-2xl min-h-[135px] p-1.5 opacity-30 pointer-events-none"></div>';
                        }

                        // ลูปสร้างช่องวันที่รองรับจอมือถือ
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date_ad = sprintf("%04d-%02d-%02d", $selected_year, $selected_month, $day);
                            $date_th_display = sprintf("%02d/%02d/%04d", $day, $selected_month, $selected_year + 543);
                            
                            $is_today    = ($date_ad === $today_ad);
                            $day_jobs    = $monthly_jobs[$date_ad] ?? [];
                            $job_count   = count($day_jobs);
                            
                            $day_of_week = date('w', strtotime($date_ad));
                            $is_weekend  = ($day_of_week == 0 || $day_of_week == 6);

                            // 📊 คำนวณสรุปยอดงานแยกตามสถานะ
                            $pending_count    = count(array_filter($day_jobs, fn($j) => ($j['status'] ?? '') === 'pending'));
                            $delivering_count = count(array_filter($day_jobs, fn($j) => in_array($j['status'] ?? '', ['accepted', 'picking_up', 'delivering'])));
                            $completed_count  = count(array_filter($day_jobs, fn($j) => ($j['status'] ?? '') === 'completed'));
                            
                            // ยอดงานที่ยังต้องวิ่งส่งจริง (รอรับ + กำลังส่ง)
                            $active_count = $pending_count + $delivering_count;

                            if ($is_today) {
                                $cell_bg = 'bg-gradient-to-br from-blue-50/90 via-sky-50/80 to-blue-100/60 border-2 border-blue-500 ring-2 ring-blue-500/20 shadow-md';
                            } elseif ($is_weekend) {
                                $cell_bg = 'bg-slate-50/90 border border-slate-200/90 hover:bg-blue-50/40 hover:border-blue-400 hover:shadow-md';
                            } else {
                                $cell_bg = 'bg-white border border-slate-200/90 hover:bg-blue-50/40 hover:border-blue-400 hover:shadow-md';
                            }

                            // 🎯 ใช้ min-h-[135px] h-auto และ p-1.5 sm:p-2.5 เพื่อป้องกันเนื้อหาล้นขอบจอมือถือ
                            echo '<div onclick="openBookingModal(\'' . $date_ad . '\', \'' . $date_th_display . '\')" class="' . $cell_bg . ' rounded-2xl min-h-[135px] h-auto p-1.5 sm:p-2.5 transition-all cursor-pointer flex flex-col justify-between relative shadow-2xs space-y-1">';
                            
                            // 1. ส่วนหัว: แสดงเลขวันที่ + ป้าย 'วันนี้'
                            echo '<div class="flex items-center justify-between gap-1">';
                            if ($is_today) {
                                echo '<span class="text-[11px] sm:text-xs font-black text-blue-600 bg-blue-100 px-1.5 py-0.5 rounded-lg border border-blue-200 shrink-0">' . $day . '</span>';
                            } else {
                                $num_color = $day_color_map[$day_of_week] ?? 'text-slate-800 font-extrabold';
                                echo '<span class="text-[11px] sm:text-xs ' . $num_color . ' bg-slate-100/80 px-1.5 py-0.5 rounded-lg border border-slate-200/60 shrink-0">' . $day . '</span>';
                            }

                            if ($is_today) {
                                echo '<span class="text-[8px] sm:text-[9px] font-black bg-blue-600 text-white px-1 py-0.5 rounded shadow-2xs shrink-0">วันนี้</span>';
                            }
                            echo '</div>';

                            // 2. ส่วนกลาง: รายการสถานะงาน (กระชับขนาดบรรทัดและฟอนต์สำหรับจอมือถือ)
                            if ($job_count > 0) {
                                echo '<div class="space-y-1 my-auto text-[8.5px] sm:text-[9.5px] font-extrabold">';
                                
                                if ($pending_count > 0) {
                                    echo '<div class="bg-amber-50/90 text-amber-900 border border-amber-200/80 px-1 py-0.5 sm:px-2 rounded-lg flex justify-between items-center shadow-3xs">';
                                    echo '<span class="text-amber-700 flex items-center gap-0.5">⏳ รอรับงาน</span><span class="font-black text-amber-800">' . $pending_count . '</span>';
                                    echo '</div>';
                                }

                                if ($delivering_count > 0) {
                                    echo '<div class="bg-blue-50/90 text-blue-900 border border-blue-200/80 px-1 py-0.5 sm:px-2 rounded-lg flex justify-between items-center shadow-3xs">';
                                    echo '<span class="text-blue-700 flex items-center gap-0.5">🛵 กำลังส่ง</span><span class="font-black text-blue-800">' . $delivering_count . '</span>';
                                    echo '</div>';
                                }

                                if ($completed_count > 0) {
                                    echo '<div class="bg-emerald-50/90 text-emerald-900 border border-emerald-200/80 px-1 py-0.5 sm:px-2 rounded-lg flex justify-between items-center shadow-3xs">';
                                    echo '<span class="text-emerald-700 flex items-center gap-0.5">✅ เสร็จสิ้น</span><span class="font-black text-emerald-800">' . $completed_count . '</span>';
                                    echo '</div>';
                                }

                                echo '</div>';

                                // 3. ส่วนล่างสุด: ป้ายสรุปสั้น
                                echo '<div class="pt-0.5 flex justify-center items-center mt-auto">';
                                if ($active_count > 0) {
                                    echo '<span class="text-[8.5px] sm:text-[9.5px] font-black bg-amber-500 text-white px-2 py-0.5 rounded-full shadow-2xs">งานค้าง ' . $active_count . '/' . $job_count . '</span>';
                                } else {
                                    echo '<span class="text-[8.5px] sm:text-[9.5px] font-black bg-emerald-600 text-white px-2 py-0.5 rounded-full shadow-2xs">งานครบ (' . $job_count . ')</span>';
                                }
                                echo '</div>';

                            } else {
                                echo '<div class="text-[9.5px] sm:text-[10px] font-bold text-slate-300 text-center my-auto flex flex-col items-center gap-0.5 opacity-60 hover:opacity-100 transition-opacity">';
                                echo '<span>--ว่าง--</span>';
                                echo '</div>';
                            }

                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>

    </main>

    <!-- 📱 แถบเมนูด้านล่างสุดสำหรับมือถือ (ปรับขนาดปุ่มให้เท่ากัน นิ่ง ไม่ขยาย) -->
    <div class="sm:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200/80 px-2 py-1.5 z-40 shadow-lg grid grid-cols-3 gap-1">
        
        <!-- ปุ่มที่ 1: ตารางปฏิทิน -->
        <a href="index.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">📅</span>
            <span class="mt-0.5">ตารางปฏิทิน</span>
        </a>

        <!-- ปุ่มที่ 2: ประวัติงาน -->
        <a href="history.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'history.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">📋</span>
            <span class="mt-0.5">ประวัติงาน</span>
        </a>

        <!-- ปุ่มที่ 3: กระดานแมส -->
        <a href="jobs.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'jobs.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">🛵</span>
            <span class="mt-0.5">กระดานแมส</span>
        </a>

    </div>

    <!-- 🎯 ดึงไฟล์ Modal ฟอร์มจอง + Modal รายละเอียดงานกลางมาใช้งาน -->
    <?php include_once 'modal_messenger_booking.php'; ?>
    <?php include_once 'modal_job_detail.php'; ?>

    <script src="../assets/js/alerts.js"></script>

    <script>
        function updateLiveClock() {
            const now = new Date();
            const clockEl = document.getElementById('live_clock_display');
            if (clockEl) {
                clockEl.textContent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
            }
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();

        document.addEventListener("DOMContentLoaded", function() {
            <?php if (!empty($success_msg)): ?>
                if (typeof LantoAlert !== 'undefined') {
                    LantoAlert.success('ทำรายการสำเร็จ', '<?php echo htmlspecialchars($success_msg); ?>');
                }
                // 🎯 ล้างค่าใน History ไม่ให้เด้งซ้ำตอนกด Back หรือ Refresh
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.href);
                }
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                if (typeof LantoAlert !== 'undefined') {
                    LantoAlert.error('เกิดข้อผิดพลาด', '<?php echo htmlspecialchars($error_msg); ?>');
                }
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.href);
                }
            <?php endif; ?>
        });
    </script>

</body>
</html>