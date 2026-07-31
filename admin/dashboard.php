<?php
session_start();
require_once '../config/db.php'; // เชื่อมต่อฐานข้อมูลหลัก

// 🔑 1. SECURITY LAYER: ตรวจสอบสิทธิ์ความปลอดภัยก่อนเข้าใช้งาน
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'it_support', 'hr'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'];
$admin_fullname = $_SESSION['fullname'];
$today = date('Y-m-d');

// ฟังก์ชันสร้าง Dropdown ขอบมนพรีเมียมจากไฟล์ส่วนกลาง
function renderRoundedDropdown($id, $input_name, $placeholder, $options_array, $value = '') {
    ?>
    <div class="relative text-left text-xs font-medium" id="custom-dropdown-<?php echo $id; ?>">
        <input type="hidden" id="<?php echo $id; ?>" name="<?php echo $input_name; ?>" value="<?php echo htmlspecialchars($value); ?>">
        <button type="button" onclick="toggleDropdown('<?php echo $id; ?>')" id="trigger-<?php echo $id; ?>"
            class="bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-600 flex justify-between items-center gap-2 shadow-2xs hover:border-slate-300 transition-all cursor-pointer">
            <span id="label-<?php echo $id; ?>" class="<?php echo ($value !== '') ? 'text-slate-800 font-semibold' : 'text-slate-500'; ?>"><?php echo $placeholder; ?></span>
            <svg class="w-3 h-3 text-slate-400 transition-transform duration-200" id="arrow-<?php echo $id; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        <div id="list-<?php echo $id; ?>" class="hidden absolute top-full right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto z-50 p-1 w-40 transition-all">
            <?php if (empty($options_array)): ?>
                <div class="px-3 py-2 text-slate-400">ไม่มีข้อมูล</div>
            <?php else: ?>
                <?php foreach ($options_array as $opt): ?>
                    <div onclick="selectDropdownOption('<?php echo $id; ?>', '<?php echo $opt['id']; ?>', '<?php echo htmlspecialchars($opt['name']); ?>')"
                         class="dropdown-item px-3 py-2 rounded-lg text-slate-700 hover:bg-blue-50 hover:text-blue-700 transition-colors cursor-pointer">
                        <span><?php echo $opt['name']; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

try {
    // 📊 2. REAL DATA EXTRACTOR: คิวรี่นับสถิติสดประจำวันแบบ Real-time (ปรับปรุงใหม่)
    // การ์ด 1: พนักงานทั้งหมด (ยกเว้น admin)
    $stmt_emp = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'");
    $total_employees = $stmt_emp->fetchColumn();

    // 📥 การ์ด 2: นับจำนวนพนักงาน (ไม่ซ้ำคน) ที่สแกนเข้างานแล้วในวันนี้จริง
    $stmt_today_att = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE log_type = 'check_in' AND DATE(scan_time) = :today");
    $stmt_today_att->execute(['today' => $today]);
    $today_present_count = $stmt_today_att->fetchColumn();

    // ⏳ การ์ด 3: นับจำนวนคำร้องขอลาทั้งหมดที่ยังค้างสถานะ "รออนุมัติ" (Pending)
    $stmt_pending_count = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
    $pending_leaves_count = $stmt_pending_count->fetchColumn();

    // 🛑 การ์ด 4: คำนวณยอดพนักงานที่ ขาด/ลา ในวันนี้ (จำนวนพนักงานทั้งหมด ลบด้วย จำนวนคนที่มา)
    $today_absent_count = max(0, $total_employees - $today_present_count);

    $total_leaves_count = 0;
    $pending_leaves_list = [];
    try {
        $stmt_leave = $pdo->query("SELECT COUNT(*) FROM leave_requests");
        $total_leaves_count = $stmt_leave->fetchColumn();

        // 🎯 แก้ไขคิวรี่ตารางลา: ล็อกเงื่อนไขให้ดึงคำขอเฉพาะของ "วันนี้" เท่านั้นตามสั่ง (DATE(l.created_at) = :today)
        $stmt_l_list = $pdo->prepare("
            SELECT 
                l.id,
                DATE_FORMAT(l.created_at, '%d/%m/%Y') as sub_date,
                u.employee_code,
                u.fullname,
                d.name AS dept_name,
                l.leave_type as type,
                l.start_date,
                l.end_date,
                GREATEST(1, DATEDIFF(l.end_date, l.start_date) + 1) as days,
                l.leave_duration,
                l.leave_hours,
                l.reason,
                l.status,
                l.attachment
            FROM leave_requests l
            INNER JOIN users u ON l.user_id = u.id
            LEFT JOIN departments d ON u.department = d.id
            WHERE l.status = 'pending' AND DATE(l.created_at) = :today
            ORDER BY l.id DESC LIMIT 5
        ");
        $stmt_l_list->execute(['today' => $today]);
        $pending_leaves_list = $stmt_l_list->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $total_leaves_count = 0;
        $pending_leaves_list = [];
    }

    $stmt_branch = $pdo->query("SELECT COUNT(*) FROM branches WHERE is_active = 1");
    $total_active_branches = $stmt_branch->fetchColumn();

    // ⏱️ 3. ข้อมูลสรุปยอดวันนี้จริง แยกสถานะตามรูปที่ 2 (คำนวณสดจากฐานข้อมูลจริง)
    // เข้าก่อนเวลา
    $stmt_early = $pdo->prepare("
        SELECT COUNT(*) FROM attendance a
        INNER JOIN users u ON a.user_id = u.id
        LEFT JOIN work_shifts w ON u.work_shift = w.id
        WHERE a.log_type = 'check_in' AND DATE(a.scan_time) = :today 
        AND TIME(a.scan_time) < SUBTIME(IFNULL(w.start_time, '08:30:00'), '00:10:00')
    ");
    $stmt_early->execute(['today' => $today]);
    $today_early_count = $stmt_early->fetchColumn();

    // เข้าตรงเวลา (สแกนเข้างานภายในช่วงเวลา 10 นาทีก่อนเข้างาน จนถึงเวลาเริ่มงาน)
    $stmt_ontime = $pdo->prepare("
        SELECT COUNT(*) FROM attendance a
        INNER JOIN users u ON a.user_id = u.id
        LEFT JOIN work_shifts w ON u.work_shift = w.id
        WHERE a.log_type = 'check_in' AND DATE(a.scan_time) = :today 
        AND TIME(a.scan_time) >= SUBTIME(IFNULL(w.start_time, '08:30:00'), '00:10:00') 
        AND TIME(a.scan_time) <= IFNULL(w.start_time, '08:30:00')
    ");
    $stmt_ontime->execute(['today' => $today]);
    $today_ontime_count = $stmt_ontime->fetchColumn();

    // เข้าสาย (สแกนเข้างานหลังเวลาเริ่มงานจริง)
    $stmt_today_late = $pdo->prepare("
        SELECT COUNT(*) FROM attendance a
        INNER JOIN users u ON a.user_id = u.id
        LEFT JOIN work_shifts w ON u.work_shift = w.id
        WHERE a.log_type = 'check_in' AND DATE(a.scan_time) = :today 
        AND TIME(a.scan_time) > IFNULL(w.start_time, '08:30:00')
    ");
    $stmt_today_late->execute(['today' => $today]);
    $today_late_count = $stmt_today_late->fetchColumn();

    $stmt_in = $pdo->prepare("
        SELECT a.log_type, a.scan_time, a.photo_log, u.fullname, u.employee_code, b.name as branch_name, w.start_time 
        FROM attendance a 
        INNER JOIN users u ON a.user_id = u.id 
        LEFT JOIN branches b ON a.branch_id = b.id 
        LEFT JOIN work_shifts w ON u.work_shift = w.id
        WHERE a.log_type = 'check_in' AND DATE(a.scan_time) = :today
        ORDER BY a.scan_time DESC LIMIT 6
    ");
    $stmt_in->execute(['today' => $today]);
    $recent_in = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

    $stmt_out = $pdo->prepare("
        SELECT a.log_type, a.scan_time, a.photo_log, u.fullname, u.employee_code, b.name as branch_name, w.start_time 
        FROM attendance a 
        INNER JOIN users u ON a.user_id = u.id 
        LEFT JOIN branches b ON a.branch_id = b.id 
        LEFT JOIN work_shifts w ON u.work_shift = w.id
        WHERE a.log_type = 'check_out' AND DATE(a.scan_time) = :today
        ORDER BY a.scan_time DESC LIMIT 6
    ");
    $stmt_out->execute(['today' => $today]);
    $recent_out = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

    // 📉 5. กราฟข้อมูลย้อนหลังตามจำนวนวัน (7 หรือ 30 วัน) และวันที่เลือกในปฏิทิน
    $graph_range = $_GET['graph_range'] ?? '7d'; // ค่าเริ่มต้น 7 วันล่าสุด
    
    $chart_labels   = [];
    $chart_in_early  = [];  
    $chart_in_normal = []; 
    $chart_in_late   = [];   
    $thai_months     = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];

    if ($graph_range === 'this_month') {
        // กรณีเลือก "เดือนนี้": ดึงตั้งแต่วันที่ 1 ถึงวันปัจจุบันของเดือนนี้
        $start_ts = strtotime(date('Y-m-01'));
        $end_ts   = strtotime(date('Y-m-d'));
    } elseif ($graph_range === 'last_month') {
        // กรณีเลือก "เดือนที่แล้ว": ดึงวันแรกถึงวันสิ้นสุดของเดือนก่อน
        $start_ts = strtotime(date('Y-m-01', strtotime('-1 month')));
        $end_ts   = strtotime(date('Y-m-t', strtotime('-1 month')));
    } elseif ($graph_range === '30d') {
        // 30 วันล่าสุด
        $start_ts = strtotime('-29 days');
        $end_ts   = strtotime(date('Y-m-d'));
    } else {
        // 7 วันล่าสุด (Default)
        $start_ts = strtotime('-6 days');
        $end_ts   = strtotime(date('Y-m-d'));
    }

    // ลูปดึงข้อมูลรายวันตามช่วงเวลาที่กำหนด
    for ($current_ts = $start_ts; $current_ts <= $end_ts; $current_ts += 86400) {
        $target_date = date('Y-m-d', $current_ts);
        $m_num       = (int)date('n', $current_ts);
        $chart_labels[] = date('d ', $current_ts) . $thai_months[$m_num];

        // 1. เข้าก่อนเวลา
        $s1 = $pdo->prepare("SELECT COUNT(*) FROM attendance a INNER JOIN users u ON a.user_id = u.id LEFT JOIN work_shifts w ON u.work_shift = w.id WHERE a.log_type = 'check_in' AND DATE(a.scan_time) = :d AND TIME(a.scan_time) < SUBTIME(IFNULL(w.start_time, '08:30:00'), '00:10:00')");
        $s1->execute(['d' => $target_date]);
        $chart_in_early[] = (int)$s1->fetchColumn();

        // 2. เข้าตรงเวลา
        $s2 = $pdo->prepare("SELECT COUNT(*) FROM attendance a INNER JOIN users u ON a.user_id = u.id LEFT JOIN work_shifts w ON u.work_shift = w.id WHERE a.log_type = 'check_in' AND DATE(a.scan_time) = :d AND TIME(a.scan_time) >= SUBTIME(IFNULL(w.start_time, '08:30:00'), '00:10:00') AND TIME(a.scan_time) <= IFNULL(w.start_time, '08:30:00')");
        $s2->execute(['d' => $target_date]);
        $chart_in_normal[] = (int)$s2->fetchColumn();

        // 3. เข้าสาย
        $s3 = $pdo->prepare("SELECT COUNT(*) FROM attendance a INNER JOIN users u ON a.user_id = u.id LEFT JOIN work_shifts w ON u.work_shift = w.id WHERE a.log_type = 'check_in' AND DATE(a.scan_time) = :d AND TIME(a.scan_time) > IFNULL(w.start_time, '08:30:00')");
        $s3->execute(['d' => $target_date]);
        $chart_in_late[] = (int)$s3->fetchColumn();
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดของระบบ: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Lanto Web Management System</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#f4f6fa] text-slate-800 antialiased flex flex-col md:flex-row min-h-screen md:h-screen md:overflow-hidden">

    <?php
    // 🎯 ดึงชื่อไฟล์ปัจจุบันมาเช็ก Active Menu อัตโนมัติ
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>

    <!-- 👤 SIDEBAR NAVIGATION (Light & Clean Theme) -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- 💻 2. MAIN WORKSPACE -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full min-h-screen md:h-screen overflow-y-auto space-y-4 sm:space-y-6 pb-20 md:pb-8">
        
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Dashboard ภาพรวมองค์กร</h1>
                <p class="text-slate-400 text-xs mt-0.5 font-medium">ดูสถานะการมาทำงาน คำขอคงค้าง และรายงานระบบไอทีของเครือ Lanto</p>
            </div>
            <div class="flex items-center gap-2.5 self-end sm:self-center">
                <a href="../index_mobile.php" class="bg-white border border-slate-200 text-slate-600 font-bold text-xs px-3 py-2 rounded-xl shadow-2xs hover:bg-slate-50 hover:text-blue-600 transition-all flex items-center gap-1.5 active:scale-95">
                    <span>📱</span> กลับหน้าหลักพนักงาน
                </a>
                <div class="bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-2xs text-xs font-bold text-slate-600 flex items-center gap-1.5">
                    สวัสดีคุณ, <span class="text-blue-600 font-extrabold"><?php echo htmlspecialchars($admin_fullname); ?></span> 👋
                </div>
            </div>
        </div>

        
        <!-- 📊 3. MODERN PREMIUM KPI CARDS (ไอคอน SVG Vector มาตรฐาน คมชัด 100%) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
            
            <!-- Card 1: พนักงานทั้งหมด -->
            <a href="manage_employees.php" class="bg-gradient-to-br from-indigo-500/10 via-white to-white border border-indigo-200/90 hover:border-indigo-400 p-4 rounded-2xl shadow-2xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative group overflow-hidden flex flex-col justify-between">
                <div class="absolute -right-6 -top-6 w-20 h-20 bg-indigo-500/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] font-black tracking-wider uppercase text-indigo-700 bg-indigo-100/80 px-2.5 py-1 rounded-lg border border-indigo-200/50 flex items-center gap-1.5">
                            <!-- 🎯 ไอคอน Users Vector -->
                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <span>พนักงานทั้งหมด</span>
                        </span>
                        <div class="w-9 h-9 bg-gradient-to-tr from-indigo-600 to-blue-600 text-white rounded-xl flex items-center justify-center shadow-md shadow-indigo-500/20 group-hover:scale-110 transition-transform shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <h2 class="text-3xl font-black text-slate-900 tracking-tight"><?php echo number_format($total_employees); ?></h2>
                        <span class="text-xs font-bold text-indigo-600">คน</span>
                    </div>
                </div>
                <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-[10px] font-bold text-slate-400 group-hover:text-indigo-600 transition-colors">
                    <span>จัดการบัญชีผู้ใช้งาน</span>
                    <span class="text-xs transition-transform group-hover:translate-x-1">→</span>
                </div>
            </a>

            <!-- Card 2: ประวัติการมาทำงานวันนี้ -->
            <a href="attendance_history.php" class="bg-gradient-to-br from-emerald-500/10 via-white to-white border border-emerald-200/90 hover:border-emerald-400 p-4 rounded-2xl shadow-2xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative group overflow-hidden flex flex-col justify-between">
                <div class="absolute -right-6 -top-6 w-20 h-20 bg-emerald-500/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] font-black tracking-wider uppercase text-emerald-800 bg-emerald-100/80 px-2.5 py-1 rounded-lg border border-emerald-200/50 flex items-center gap-1.5">
                            <!-- 🎯 ไอคอน Clock Vector -->
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span>สแกนเข้าวันนี้</span>
                        </span>
                        <div class="w-9 h-9 bg-gradient-to-tr from-emerald-600 to-teal-600 text-white rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20 group-hover:scale-110 transition-transform shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-center gap-3">
                        <div class="bg-emerald-50 border border-emerald-200/60 px-2.5 py-1 rounded-xl">
                            <span class="text-xl font-black text-emerald-700"><?php echo $today_present_count; ?></span>
                            <span class="text-[10px] font-bold text-emerald-800 block">มาปกติ</span>
                        </div>
                        <div class="bg-amber-50 border border-amber-200/60 px-2.5 py-1 rounded-xl">
                            <span class="text-xl font-black text-amber-700"><?php echo $today_late_count; ?></span>
                            <span class="text-[10px] font-bold text-amber-800 block">มาสาย</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-[10px] font-bold text-slate-400 group-hover:text-emerald-700 transition-colors">
                    <span>ตรวจสอบเวลาเข้า-ออก</span>
                    <span class="text-xs transition-transform group-hover:translate-x-1">→</span>
                </div>
            </a>

            <!-- Card 3: คำร้องรออนุมัติ -->
            <a href="manage_leaves.php" class="bg-gradient-to-br from-amber-500/10 via-white to-white border border-amber-200/90 hover:border-amber-400 p-4 rounded-2xl shadow-2xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative group overflow-hidden flex flex-col justify-between">
                <div class="absolute -right-6 -top-6 w-20 h-20 bg-amber-500/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] font-black tracking-wider uppercase text-amber-800 bg-amber-100/80 px-2.5 py-1 rounded-lg border border-amber-200/50 flex items-center gap-1.5">
                            <?php if ($pending_leaves_count > 0): ?>
                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                            <?php endif; ?>
                            <!-- 🎯 ไอคอน Document Text Vector -->
                            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                            <span>คำร้องรออนุมัติ</span>
                        </span>
                        <div class="w-9 h-9 bg-gradient-to-tr from-amber-500 to-orange-500 text-white rounded-xl flex items-center justify-center shadow-md shadow-amber-500/20 group-hover:scale-110 transition-transform shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <h2 class="text-3xl font-black text-slate-900 tracking-tight"><?php echo number_format($pending_leaves_count); ?></h2>
                        <span class="text-xs font-bold text-amber-700">รายการ</span>
                    </div>
                </div>
                <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-[10px] font-bold text-slate-400 group-hover:text-amber-700 transition-colors">
                    <span>พิจารณาคำขอการลา</span>
                    <span class="text-xs transition-transform group-hover:translate-x-1">→</span>
                </div>
            </a>

            <!-- Card 4: ตั้งค่าระบบ -->
            <a href="system_settings.php" class="bg-gradient-to-br from-slate-500/10 via-white to-white border border-slate-200/90 hover:border-slate-400 p-4 rounded-2xl shadow-2xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative group overflow-hidden flex flex-col justify-between">
                <div class="absolute -right-6 -top-6 w-20 h-20 bg-slate-500/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] font-black tracking-wider uppercase text-slate-700 bg-slate-100/80 px-2.5 py-1 rounded-lg border border-slate-200/50 flex items-center gap-1.5">
                            <!-- 🎯 ไอคอน Cog Vector -->
                            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            <span>ตั้งค่าระบบ</span>
                        </span>
                        <div class="w-9 h-9 bg-gradient-to-tr from-slate-700 to-slate-900 text-white rounded-xl flex items-center justify-center shadow-md shadow-slate-500/20 group-hover:scale-110 transition-transform shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h2 class="text-base font-extrabold text-slate-900 tracking-tight">โครงสร้างองค์กร</h2>
                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5">บริษัท • แผนก • สาขา • กะงาน</p>
                    </div>
                </div>
                <div class="mt-3 border-t border-slate-100 pt-2 flex items-center justify-between text-[10px] font-bold text-slate-400 group-hover:text-slate-800 transition-colors">
                    <span>จัดการการตั้งค่าหลัก</span>
                    <span class="text-xs transition-transform group-hover:translate-x-1">→</span>
                </div>
            </a>

        </div>

        <!-- 📊 4. ส่วนตารางข้อมูลและไทม์ไลน์ฟีดเวลางานจริง (ปรับโครงสร้างรวบเซตซ้าย 60% ขวา 40% ยาวลงมาเสมอกัน) -->
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-stretch">
            
            <!-- 🔑 ตัวหุ้มฝั่งซ้าย (ความกว้าง 60% = 3 ใน 5 ช่อง) มัดตารางลากับกราฟแนวโน้มให้ซ้อนกันแนวตั้ง -->
            <div class="xl:col-span-3 flex flex-col gap-6">
                
                <!-- 1. กล่องรายการการลา (ด้านบนฝั่งซ้าย) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col justify-between space-y-4 flex-1">
                    <div>
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-2">
                            <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">📑 รายการการลา (วันนี้)</h3>
                            <a href="manage_leaves.php" class="text-xs text-blue-600 font-bold hover:underline">ดูทั้งหมด →</a>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="text-slate-400 font-semibold border-b border-slate-100">
                                        <th class="pb-3">วันที่ส่งใบลา</th>
                                        <th class="pb-3">รหัสพนักงาน</th>
                                        <th class="pb-3">ชื่อ-นามสกุล</th>
                                        <th class="pb-3">ประเภทการลา</th>
                                        <th class="pb-3">วันที่ลา</th>
                                        <th class="pb-3">จำนวนวัน/ชั่วโมง</th>
                                        <th class="pb-3">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                                    <?php if (empty($pending_leaves_list)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-8 text-slate-400 font-light">🚫 ยังไม่มีรายการคำขออนุมัติใบลาส่งเข้ามาในวันนี้</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_leaves_list as $leave): 
                                            // 1. แปลงวันที่ลา พ.ศ.
                                            $start_ts = strtotime($leave['start_date']);
                                            $end_ts   = strtotime($leave['end_date']);
                                            $start_th = date('d/m/', $start_ts) . (date('Y', $start_ts) + 543);
                                            $end_th   = date('d/m/', $end_ts) . (date('Y', $end_ts) + 543);
                                            $leave_range = ($leave['start_date'] === $leave['end_date']) ? $start_th : ($start_th . ' - ' . $end_th);

                                            // 2. แปลงประเภทการลาเป็นภาษาไทย
                                            $type_map = [
                                                'sick'       => 'ลาป่วย',
                                                'business'   => 'ลากิจ',
                                                'personal'   => 'ลากิจส่วนตัว',
                                                'vacation'   => 'ลาพักร้อน',
                                                'annual'     => 'ลาพักร้อนประจำปี',
                                                'maternity'  => 'ลาคลอด',
                                                'ordination' => 'ลาอุปสมบท',
                                                'other'      => 'ลาอื่นๆ'
                                            ];
                                            $type_th = $type_map[strtolower($leave['type'] ?? '')] ?? ($leave['type'] ?? 'ลาหยุด');

                                            // 3. ระยะเวลาการลา
                                            if (($leave['leave_duration'] ?? 'full') === 'hourly') {
                                                $duration_display = htmlspecialchars($leave['leave_hours'] ?? '0') . ' ชั่วโมง';
                                            } else {
                                                $duration_display = htmlspecialchars($leave['days'] ?? '1') . ' วัน';
                                            }

                                            // 4. รูปภาพเอกสารแนบ
                                            $attachment_img = !empty($leave['attachment']) ? '../uploads/leaves/' . $leave['attachment'] : '';
                                            
                                            // 5. เหตุผลการลา (ป้องกันค่าว่างหรือพัง)
                                            $clean_reason = !empty($leave['reason']) ? trim($leave['reason']) : '-';
                                            
                                            // 6. แผนกและรหัสพนักงาน
                                            $dept_display = !empty($leave['dept_name']) ? $leave['dept_name'] : 'ไม่ระบุแผนก';
                                            $emp_code_display = !empty($leave['employee_code']) ? $leave['employee_code'] : '-';
                                            $fullname_display = !empty($leave['fullname']) ? $leave['fullname'] : '-';
                                        ?>
                                        <tr onclick="openLeaveDetailModal(
                                                '<?php echo htmlspecialchars(addslashes($leave['fullname'] ?? '')); ?>',       // 1. ชื่อพนักงาน
                                                '<?php echo htmlspecialchars(addslashes($leave['employee_code'] ?? '')); ?>',  // 2. รหัสพนักงาน
                                                '<?php echo htmlspecialchars(addslashes($leave['dept_name'] ?? 'ไม่ระบุแผนก')); ?>', // 3. แผนก
                                                '<?php echo htmlspecialchars(addslashes($type_th)); ?>',                      // 4. ประเภทการลา
                                                '<?php echo htmlspecialchars(addslashes($leave_range)); ?>',                    // 5. ช่วงวันที่ลา
                                                '<?php echo htmlspecialchars(addslashes($duration_display)); ?>',               // 6. ระยะเวลา
                                                '<?php echo htmlspecialchars(addslashes($leave['sub_date'] ?? '')); ?>',        // 7. วันที่ส่งคำขอ
                                                '<?php echo htmlspecialchars(addslashes($clean_reason ?? '-')); ?>',            // 8. เหตุผล
                                                '<?php echo htmlspecialchars(addslashes($attachment_img ?? '')); ?>',           // 9. รูปภาพ
                                                '<?php echo htmlspecialchars(addslashes($leave['status'] ?? 'pending')); ?>',   // 10. สถานะ
                                                '<?php echo htmlspecialchars(addslashes($leave['id'] ?? '')); ?>'               // 11. ID ใบลา
                                            )" 
                                            class="hover:bg-blue-50/50 transition-colors cursor-pointer active:scale-98">
                                            
                                            <td class="py-3.5 text-slate-400 whitespace-nowrap"><?php echo htmlspecialchars($leave['sub_date']); ?></td>
                                            <td class="py-3.5 font-extrabold text-slate-600 whitespace-nowrap"><?php echo htmlspecialchars($leave['employee_code'] ?? '-'); ?></td>
                                            <td class="py-3.5 font-bold text-slate-800 whitespace-nowrap"><?php echo htmlspecialchars($leave['fullname'] ?? '-'); ?></td>
                                            <td class="py-3.5 text-blue-600 font-bold whitespace-nowrap"><?php echo htmlspecialchars($type_th); ?></td>
                                            <td class="py-3.5 text-slate-500 font-semibold whitespace-nowrap"><?php echo $leave_range; ?></td>
                                            <td class="py-3.5 text-slate-700 font-bold whitespace-nowrap"><?php echo $duration_display; ?></td>
                                            <td class="py-3.5 whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1.5 text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md font-bold border border-amber-200/40">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>รออนุมัติ
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-slate-50 border border-slate-200/60 rounded-xl p-3 text-[10px] text-slate-400 flex items-center gap-2 font-medium">
                        <span>ℹ️</span> ระบบศูนย์กลางจะคัดกรองเฉพาะคำร้องส่งตรงจากแอปมือถือพนักงานที่ยังไม่ได้รับการพิจารณาขึ้นโชว์
                    </div>
                </div>

                <!-- 2. กล่องกราฟแนวโน้มความเคลื่อนไหว (ด้านล่างฝั่งซ้าย รวบเข้ามาอยู่ในกรอบ 60% เรียบร้อย) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                            <span>📊</span> แนวโน้มความเคลื่อนไหวลงเวลางาน
                        </h3>
                        
                        <!-- 🎯 ดันกล่องดรอปดาวน์ชิดขวาสุดด้วย ml-auto flex justify-end -->
                        <div class="flex justify-end items-center ml-auto">
                            <?php 
                                $filter_opts = [
                                    ['id' => '7d', 'name' => '7 วันล่าสุด'],
                                    ['id' => '30d', 'name' => '30 วันล่าสุด'],
                                    ['id' => 'this_month', 'name' => 'เดือนนี้ (' . date('m/Y') . ')'],
                                    ['id' => 'last_month', 'name' => 'เดือนที่แล้ว']
                                ];
                                $active_label = '7 วันล่าสุด';
                                foreach ($filter_opts as $opt) {
                                    if ($opt['id'] === $graph_range) { 
                                        $active_label = $opt['name']; 
                                        break; 
                                    }
                                }
                                renderRoundedDropdown('graph_filter_select', 'graph_range', $active_label, $filter_opts, $graph_range); 
                            ?>
                        </div>
                    </div>

                    <div class="h-48 w-full relative">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

            </div> <!-- 🔑 ปิดตัวหุ้มฝั่งซ้าย -->

            <!-- 🔑 กล่องคุมฝั่งขวา (ความกว้าง 40% = 2 ใน 5 ช่อง) เป็นแท่งยาวใบเดียวดิ่งลงมาถมพื้นที่สีขาวโหว่ข้างกราฟพรีเมียม -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs xl:col-span-2 flex flex-col justify-between space-y-5">
                <div class="flex flex-col h-full justify-between">
                    <div>
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">🕒 บันทึกเวลาสแกนล่าสุดวันนี้</h3>
                            <a href="attendance_history.php" class="text-xs text-blue-600 font-bold hover:underline">ดูทั้งหมด →</a>
                        </div>
                        
                        <!-- ดีไซน์แยกคอลัมน์สแกนเข้า-ออกซ้ายขวาภายในกล่องขวา -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                            
                            <!-- ช่องย่อยที่ 1: รายการสแกนเข้า (IN) -->
                            <div class="bg-slate-50/40 border border-slate-200/50 p-3 rounded-xl flex flex-col">
                                <div class="text-[10px] font-extrabold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 inline-block mb-2">📥 รายการสแกนเข้า (IN)</div>
                                <div class="space-y-2 overflow-y-auto pr-0.5 max-h-[380px]">
                                    <?php if (empty($recent_in)): ?>
                                        <div class="text-center py-8 text-slate-400 text-[11px] font-light">🚫 ไม่มีข้อมูลเข้างานวันนี้</div>
                                    <?php else: ?>
                                        <?php foreach ($recent_in as $log): 
                                            // คำนวณสถานะสแกนเข้า
                                            $scan_time_only = date('H:i:s', strtotime($log['scan_time']));
                                            $start_time = $log['start_time'] ?? '08:30:00';
                                            $early_limit = date('H:i:s', strtotime($start_time . ' -10 minutes'));

                                            if ($scan_time_only < $early_limit) {
                                                $status_type = 'early';
                                                $status_text = 'เข้าก่อนเวลา';
                                            } else if ($scan_time_only <= $start_time) {
                                                $status_type = 'ontime';
                                                $status_text = 'เข้าตรงเวลา';
                                            } else {
                                                $status_type = 'late';
                                                $status_text = 'เข้าสาย';
                                            }

                                            // ต่อ Path ที่เก็บไฟล์รูปภาพสแกน
                                            $img_url = !empty($log['photo_log']) ? '../uploads/scan-in/' . $log['photo_log'] : '';
                                        ?>
                                        <div onclick="openAttendanceModal(
                                                '<?php echo htmlspecialchars($log['fullname']); ?>', 
                                                '<?php echo htmlspecialchars($log['employee_code']); ?>', 
                                                'check_in', 
                                                '<?php echo date('H:i:s', strtotime($log['scan_time'])); ?>', 
                                                '<?php echo htmlspecialchars($log['branch_name'] ?? ''); ?>',
                                                '<?php echo htmlspecialchars($img_url); ?>',
                                                '<?php echo $status_type; ?>',
                                                '<?php echo $status_text; ?>'
                                            )"
                                            class="p-3 bg-white rounded-xl border border-slate-100 flex flex-col gap-1 shadow-2xs transition-all hover:bg-blue-50/50 hover:border-blue-200 cursor-pointer active:scale-98">
                                            <div class="flex items-start justify-between gap-2">
                                                <h4 class="text-xs font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($log['fullname']); ?></h4>
                                                <span class="text-[10px] font-bold text-slate-500 shrink-0"><?php echo date('H:i:s', strtotime($log['scan_time'])); ?> น.</span>
                                            </div>
                                            <p class="text-[10px] text-slate-500 font-semibold">รหัส: <?php echo htmlspecialchars($log['employee_code']); ?></p>
                                            <p class="text-[10px] text-slate-400 truncate">📍 <?php echo htmlspecialchars($log['branch_name'] ?? 'นอกสถานที่'); ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ช่องย่อยที่ 2: รายการสแกนออก (OUT) -->
                            <div class="bg-slate-50/40 border border-slate-200/50 p-3 rounded-xl flex flex-col">
                                <div class="text-[10px] font-extrabold text-orange-600 bg-orange-50 px-2 py-0.5 rounded border border-orange-100 inline-block mb-2">📤 รายการสแกนออก (OUT)</div>
                                <div class="space-y-2 overflow-y-auto pr-0.5 max-h-[380px]">
                                    <?php if (empty($recent_out)): ?>
                                        <div class="text-center py-8 text-slate-400 text-[11px] font-light">🚫 ไม่มีข้อมูลออกงานวันนี้</div>
                                    <?php else: ?>
                                        <?php foreach ($recent_out as $log): 
                                            $img_url = !empty($log['photo_log']) ? '../uploads/scan-out/' . $log['photo_log'] : '';
                                        ?>
                                        <div onclick="openAttendanceModal(
                                                '<?php echo htmlspecialchars($log['fullname']); ?>', 
                                                '<?php echo htmlspecialchars($log['employee_code']); ?>', 
                                                'check_out', 
                                                '<?php echo date('H:i:s', strtotime($log['scan_time'])); ?>', 
                                                '<?php echo htmlspecialchars($log['branch_name'] ?? ''); ?>',
                                                '<?php echo htmlspecialchars($img_url); ?>',
                                                'checkout',
                                                'สแกนออกงาน'
                                            )"
                                            class="p-3 bg-white rounded-xl border border-slate-100 flex flex-col gap-1 shadow-2xs transition-all hover:bg-blue-50/50 hover:border-blue-200 cursor-pointer active:scale-98">
                                            <div class="flex items-start justify-between gap-2">
                                                <h4 class="text-xs font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($log['fullname']); ?></h4>
                                                <span class="text-[10px] font-bold text-slate-500 shrink-0"><?php echo date('H:i:s', strtotime($log['scan_time'])); ?> น.</span>
                                            </div>
                                            <p class="text-[10px] text-slate-500 font-semibold">รหัส: <?php echo htmlspecialchars($log['employee_code']); ?></p>
                                            <p class="text-[10px] text-slate-400 truncate">📍 <?php echo htmlspecialchars($log['branch_name'] ?? 'นอกสถานที่'); ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- กล่องป้ายสรุปยอดสามสีด้านล่างสุด ดิ่งลงมาจอดระนาบขอบล่างเสมอกับกล่องกราฟเป๊ะๆ -->
                    <div class="grid grid-cols-3 gap-3 pt-3 border-t border-slate-100 mt-4">
                        <div class="bg-[#f0f5ff] border border-blue-100 rounded-2xl p-2.5 flex flex-col items-center justify-center text-center shadow-3xs">
                            <div class="w-8 h-8 bg-blue-500 rounded-xl flex items-center justify-center text-white mb-1.5 shadow-md shadow-blue-500/20">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 7.5a4.5 4.5 0 100 9 4.5 4.5 0 000-9z"></path></svg>
                            </div>
                            <span class="text-[11px] font-bold text-slate-500 leading-none">เข้าก่อนเวลา</span>
                            <span class="text-sm font-extrabold text-blue-600 mt-1"><?php echo $today_early_count; ?> ครั้ง</span>
                        </div>

                        <div class="bg-[#f0fdf4] border border-emerald-100 rounded-2xl p-2.5 flex flex-col items-center justify-center text-center shadow-3xs">
                            <div class="w-8 h-8 bg-emerald-500 rounded-xl flex items-center justify-center text-white mb-1.5 shadow-md shadow-emerald-500/20">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <span class="text-[11px] font-bold text-slate-500 leading-none">เข้าตรงเวลา</span>
                            <span class="text-sm font-extrabold text-emerald-700 mt-1"><?php echo $today_ontime_count; ?> ครั้ง</span>
                        </div>

                        <div class="bg-[#fffbeb] border border-amber-100 rounded-2xl p-2.5 flex flex-col items-center justify-center text-center shadow-3xs">
                            <div class="w-8 h-8 bg-amber-500 rounded-xl flex items-center justify-center text-white mb-1.5 shadow-md shadow-amber-500/20">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <span class="text-[11px] font-bold text-slate-500 leading-none">เข้าสาย</span>
                            <span class="text-sm font-extrabold text-amber-600 mt-1"><?php echo $today_late_count; ?> ครั้ง</span>
                        </div>
                    </div>
                </div>
            </div> <!-- 🔑 ปิดกล่องคุมฝั่งขวา -->

        </div> <!-- ปิด Grid คอนเทนเนอร์หลัก -->

    </main>

    <!-- 📊 สคริปต์ควบคุมเฉพาะของหน้า Dashboard (กราฟเส้น และ ดรอปดาวน์เลือกช่วงวันกราฟ) -->
    <script>
        // 1. สั่งวาดกราฟเส้นแนวโน้ม 7 วัน / 30 วัน
        const ctx = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    { label: 'เข้าก่อนเวลา', data: <?php echo json_encode($chart_in_early); ?>, borderColor: '#3b82f6', backgroundColor: 'transparent', tension: 0.4, borderWidth: 2.5, pointRadius: 3 },
                    { label: 'เข้าตรงเวลา', data: <?php echo json_encode($chart_in_normal); ?>, borderColor: '#10b981', backgroundColor: 'transparent', tension: 0.4, borderWidth: 2.5, pointRadius: 3 },
                    { label: 'เข้าสาย', data: <?php echo json_encode($chart_in_late); ?>, borderColor: '#f43f5e', backgroundColor: 'transparent', tension: 0.4, borderWidth: 2.5, pointRadius: 3 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, labels: { boxWidth: 12, font: { size: 10, family: 'Noto Sans Thai' } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10, family: 'Noto Sans Thai' } } },
                    y: { 
                        min: 0, 
                        ticks: { font: { size: 10, family: 'Noto Sans Thai' } } 
                    }
                }
            }
        });

        // 2. ฟังก์ชันช่วยจัดการ URL Parameter ไม่ให้หลุดหรือทับซ้อนกัน
        function updateGraphUrlParam(key, value) {
            const url = new URL(window.location.href);
            if (value && value.trim() !== '') {
                url.searchParams.set(key, value.trim());
            } else {
                url.searchParams.delete(key);
            }
            window.location.href = url.toString();
        }

        // 3. ล้างค่าวันที่เลือก แต่คงค่า graph_range เอาไว้
        function clearGraphDate() {
            updateGraphUrlParam('filter_date', '');
        }

        // 4. ควบคุมการเปิด-ปิด Custom Dropdown
        function toggleDropdown(id) {
            const list = document.getElementById('list-' + id);
            const arrow = document.getElementById('arrow-' + id);
            document.querySelectorAll('[id^="list-"]').forEach(el => { if (el.id !== 'list-' + id) el.classList.add('hidden'); });
            document.querySelectorAll('[id^="arrow-"]').forEach(el => { if (el.id !== 'arrow-' + id) el.classList.remove('rotate-180'); });
            if (list) list.classList.toggle('hidden'); 
            if (arrow) arrow.classList.toggle('rotate-180');
        }

        // 5. เมื่อเลือกตัวเลือก 7 วัน / 30 วัน
        function selectDropdownOption(id, value, label) {
            document.getElementById(id).value = value;
            const labelSpan = document.getElementById('label-' + id);
            if (labelSpan) {
                labelSpan.textContent = label; 
                labelSpan.className = "text-slate-800 font-semibold";
            }
            const list = document.getElementById('list-' + id);
            const arrow = document.getElementById('arrow-' + id);
            if (list) list.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');

            // เมื่อกดเปลี่ยนค่าดรอปดาวน์ช่วงเวลา ให้รีโหลดหน้าพร้อมส่งค่า graph_range ทันที
            if (id === 'graph_filter_select') {
                window.location.href = 'dashboard.php?graph_range=' + encodeURIComponent(value);
            }
        }

        // 🎯 6. ดักจับการเลือกวันที่ในปฏิทิน (ปรับใช้ closest เพื่อให้ตรวจจับการคลิกโดนแน่นอน 100%)
        document.addEventListener('click', function(e) {
            const calendarClick = e.target.closest('.day') || e.target.closest('#calendarPopup');
            if (calendarClick) {
                const dateInput = document.getElementById('dashboard_filter_date');
                const currentVal = dateInput ? dateInput.value : '';
                setTimeout(() => {
                    if (dateInput && dateInput.value && dateInput.value !== currentVal) {
                        updateGraphUrlParam('filter_date', dateInput.value);
                    }
                }, 150);
            }
        }, true);

        // 🎯 7. เพิ่ม Event Listener ตรงเข้ากับตัว Input โดยตรง
        const filterInput = document.getElementById('dashboard_filter_date');
        if (filterInput) {
            filterInput.addEventListener('change', function() {
                if (this.value) {
                    updateGraphUrlParam('filter_date', this.value);
                }
            });
            filterInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.value.length === 10) {
                    updateGraphUrlParam('filter_date', this.value);
                }
            });
        }
    </script>

    <!-- 📅 3. ดึงโมดูลปฏิทินพรีเมียมจาก calendar_component.php มาใช้งานโดยตรง ไม่ต้องเขียนซ้ำ -->
    <?php include_once '../includes/modal_attendance_detail.php'; ?>
    <?php include_once '../includes/modal_leave_detail.php'; ?>
    <?php include_once '../includes/calendar_component.php'; ?>
   
</body>
</html>