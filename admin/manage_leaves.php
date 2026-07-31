<?php
session_start();
require_once '../config/db.php'; 
require_once '../includes/rounded_dropdown.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'it_support', 'hr'])) {
    header("Location: ../login.php");
    exit();
}

mb_internal_encoding("UTF-8");

$role = $_SESSION['role'];
$admin_fullname = $_SESSION['fullname'] ?? 'ผู้ดูแลระบบ';

// 🎯 รับค่าตัวกรองค้นหา
$status_filter      = $_GET['status'] ?? 'all';
$search_query       = trim($_GET['search'] ?? '');
$request_date_input = trim($_GET['request_date'] ?? '');
$leave_type_filter  = trim($_GET['leave_type'] ?? 'all');

// 📄 ตั้งค่า Pagination (แสดงทีละ 7 รายการ)
$limit = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// 🎯 ฟังก์ชันถอดรหัสวันที่ไทย/ช่วงวัน/เดือน สำหรับค้นหาใน DB
function parseDateRangeToDb($input) {
    if (empty($input)) return [null, null];
    
    $input = trim($input);
    $thai_months = [
        'มกราคม' => 1, 'กุมภาพันธ์' => 2, 'มีนาคม' => 3, 'เมษายน' => 4,
        'พฤษภาคม' => 5, 'มิถุนายน' => 6, 'กรกฎาคม' => 7, 'สิงหาคม' => 8,
        'กันยายน' => 9, 'ตุลาคม' => 10, 'พฤศจิกายน' => 11, 'ธันวาคม' => 12
    ];

    $parts = preg_split('/\s*[-–]\s*/u', $input);

    $parseSingleDate = function($str, $is_end = false) use ($thai_months) {
        $str = trim($str);
        foreach ($thai_months as $m_name => $m_num) {
            if (mb_strpos($str, $m_name) !== false) {
                preg_match('/\d{4}/', $str, $matches);
                $y_be = isset($matches[0]) ? (int)$matches[0] : ((int)date('Y') + 543);
                $y_ad = $y_be - 543;
                if (!$is_end) {
                    return sprintf('%04d-%02d-01', $y_ad, $m_num);
                } else {
                    $last_day = date('t', strtotime("$y_ad-$m_num-01"));
                    return sprintf('%04d-%02d-%02d', $y_ad, $m_num, $last_day);
                }
            }
        }
        $d_parts = explode('/', $str);
        if (count($d_parts) === 3) {
            $d = (int)$d_parts[0];
            $m = (int)$d_parts[1];
            $y = (int)$d_parts[2];
            if ($y > 2400) $y -= 543;
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
        return null;
    };

    if (count($parts) === 2) {
        $start = $parseSingleDate($parts[0], false);
        $end   = $parseSingleDate($parts[1], true);
        return [$start, $end];
    } else {
        $start = $parseSingleDate($input, false);
        $end   = $parseSingleDate($input, true);
        return [$start, $end];
    }
}

list($start_date_db, $end_date_db) = parseDateRangeToDb($request_date_input);

// 🎯 ดึงข้อมูลการลาจาก DB
$leave_requests = [];
$count_pending  = 0;
$count_approved = 0;
$count_rejected = 0;
$count_all      = 0;
$total_records  = 0;
$total_pages    = 1;

try {
    $stmt_p = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
    $count_pending = $stmt_p->fetchColumn() ?: 0;

    $stmt_a = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved'");
    $count_approved = $stmt_a->fetchColumn() ?: 0;

    $stmt_r = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'rejected'");
    $count_rejected = $stmt_r->fetchColumn() ?: 0;

    $count_all = $count_pending + $count_approved + $count_rejected;

    // 1. สร้างเงื่อนไข WHERE สำหรับกรองข้อมูล
    $where_sql = " WHERE 1=1";
    $params = [];

    if ($status_filter !== 'all') {
        $where_sql .= " AND l.status = :status";
        $params['status'] = $status_filter;
    }

    if (!empty($search_query)) {
        $where_sql .= " AND (u.fullname LIKE :search OR u.employee_code LIKE :search)";
        $params['search'] = "%{$search_query}%";
    }

    if (!empty($start_date_db) && !empty($end_date_db)) {
        if ($start_date_db === $end_date_db) {
            $where_sql .= " AND DATE(l.created_at) = :start_date";
            $params['start_date'] = $start_date_db;
        } else {
            $where_sql .= " AND DATE(l.created_at) BETWEEN :start_date AND :end_date";
            $params['start_date'] = $start_date_db;
            $params['end_date']   = $end_date_db;
        }
    }

    if (!empty($leave_type_filter) && $leave_type_filter !== 'all') {
        $where_sql .= " AND l.leave_type = :leave_type";
        $params['leave_type'] = $leave_type_filter;
    }

    // 2. นับจำนวนรายการทั้งหมดตามตัวกรอง เพื่อคำนวณจำนวนหน้า
    $count_sql = "SELECT COUNT(*) 
                  FROM leave_requests l
                  JOIN users u ON l.user_id = u.id
                  LEFT JOIN departments d ON u.department = d.id" . $where_sql;
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn() ?: 0;
    $total_pages = max(1, ceil($total_records / $limit));

    // 3. ดึงข้อมูลการลาเฉพาะหน้าปัจจุบัน (LIMIT 7 OFFSET ...)
    $sql = "SELECT l.*, 
                   u.fullname, 
                   u.employee_code, 
                   u.profile_image, 
                   d.name AS dept_name,
                   (DATEDIFF(l.end_date, l.start_date) + 1) AS total_days
            FROM leave_requests l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN departments d ON u.department = d.id" . $where_sql . "
            ORDER BY l.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $leave_requests = [];
}

function buildFilterUrl($new_status, $search, $req_date, $l_type, $target_page = 1) {
    $p = ['status' => $new_status];
    if (!empty($search)) $p['search'] = $search;
    if (!empty($req_date)) $p['request_date'] = $req_date;
    if (!empty($l_type) && $l_type !== 'all') $p['leave_type'] = $l_type;
    if ($target_page > 1) $p['page'] = $target_page;
    return 'manage_leaves.php?' . http_build_query($p);
}

// 🗺️ แปลงรหัสการลา DB เป็นภาษาไทย
$leave_type_map = [
    'sick'       => 'ลาป่วย',
    'business'   => 'ลากิจ',
    'personal'   => 'ลากิจส่วนตัว',
    'vacation'   => 'ลาพักร้อน',
    'annual'     => 'ลาพักร้อนประจำปี',
    'maternity'  => 'ลาคลอด',
    'ordination' => 'ลาอุปสมบท',
    'other'      => 'ลาอื่นๆ'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำร้องอนุมัติใบลา - Lanto Web Management System</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        <!-- Header Topbar -->
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">ระบบจัดการการลาพนักงาน</h1>
                <p class="text-slate-400 text-xs mt-0.5 font-medium">พิจารณาคำขอลาและตรวจสอบประวัติการลาของพนักงานในระบบ</p>
            </div>
            <div class="flex items-center gap-2.5 self-end sm:self-center">
                <div class="bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-2xs text-xs font-bold text-slate-600 flex items-center gap-1.5">
                    สวัสดีคุณ, <span class="text-blue-600 font-extrabold"><?php echo htmlspecialchars($admin_fullname); ?></span> 👋
                </div>
            </div>
        </div>

        <!-- 📊 3. KPI STAT CARDS (สไตล์ Clean Box แบบ system_settings.php) -->
        <div class="bg-white p-2.5 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                
                <!-- 1️⃣ คำขอทั้งหมด -->
                <?php $is_all = ($status_filter === 'all'); ?>
                <a href="<?php echo buildFilterUrl('all', $search_query, $request_date_input, $leave_type_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_all ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📋</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">คำขอทั้งหมด</p>
                            <p class="text-[10px] opacity-70 font-medium">รวมสถิติใบลาทั้งหมด</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $count_all; ?></span>
                </a>

                <!-- 2️⃣ รอการอนุมัติ -->
                <?php $is_pending = ($status_filter === 'pending'); ?>
                <a href="<?php echo buildFilterUrl('pending', $search_query, $request_date_input, $leave_type_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_pending ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">⏳</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">รอการอนุมัติ</p>
                            <p class="text-[10px] opacity-70 font-medium">รายการที่รอการพิจารณา</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $count_pending; ?></span>
                </a>

                <!-- 3️⃣ อนุมัติแล้ว -->
                <?php $is_approved = ($status_filter === 'approved'); ?>
                <a href="<?php echo buildFilterUrl('approved', $search_query, $request_date_input, $leave_type_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_approved ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">✅</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">อนุมัติแล้ว</p>
                            <p class="text-[10px] opacity-70 font-medium">คำขอที่อนุมัติเรียบร้อย</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $count_approved; ?></span>
                </a>

                <!-- 4️⃣ ไม่อนุมัติ -->
                <?php $is_rejected = ($status_filter === 'rejected'); ?>
                <a href="<?php echo buildFilterUrl('rejected', $search_query, $request_date_input, $leave_type_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_rejected ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">❌</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">ไม่อนุมัติ</p>
                            <p class="text-[10px] opacity-70 font-medium">รายการที่ปฏิเสธคำขอ</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $count_rejected; ?></span>
                </a>

            </div>
        </div>

        <!-- 🔍 4. แถบค้นหา & ฟิลเตอร์ -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
            <form method="GET" action="manage_leaves.php" class="flex flex-wrap items-center gap-3 w-full">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                
                <div class="w-full sm:w-64 relative flex items-center">
                    <div class="absolute left-3.5 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <input type="text" name="request_date" value="<?php echo htmlspecialchars($request_date_input); ?>" placeholder="วว/ดด/ปปปป, ช่วงวัน หรือ เดือน"
                        class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:border-blue-500 transition-colors h-10 cursor-pointer">
                </div>

                <div class="w-full sm:w-100">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="ค้นหาชื่อ หรือ รหัสพนักงาน..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:border-blue-500 transition-colors h-10">
                </div>

                

                <div class="w-full sm:w-60">
                    <?php 
                        $leave_type_options = [
                            ['id' => 'all', 'name' => 'ทุกประเภทการลา'],
                            ['id' => 'sick', 'name' => 'ลาป่วย (Sick Leave)'],
                            ['id' => 'business', 'name' => 'ลากิจ (Business Leave)'],
                            ['id' => 'personal', 'name' => 'ลากิจส่วนตัว (Personal Leave)'],
                            ['id' => 'vacation', 'name' => 'ลาพักร้อน (Vacation Leave)'],
                            ['id' => 'maternity', 'name' => 'ลาคลอด (Maternity Leave)'],
                            ['id' => 'ordination', 'name' => 'ลาอุปสมบท (Ordination Leave)'],
                            ['id' => 'other', 'name' => 'ลาอื่นๆ (Other)']
                        ];

                        $active_type_name = 'ทุกประเภทการลา';
                        foreach ($leave_type_options as $opt) {
                            if ($opt['id'] === $leave_type_filter) {
                                $active_type_name = $opt['name'];
                                break;
                            }
                        }

                        renderRoundedDropdown('leave_type_select', 'leave_type', $active_type_name, $leave_type_options, $leave_type_filter);
                    ?>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-colors cursor-pointer active:scale-95 h-10 flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
                        </svg>
                        <span>ค้นหา</span>
                    </button>
                    <a href="?" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors h-10 flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                        </svg>
                        <span>ล้างค่า</span>
                    </a>
                </div>
            </form>
        </div>

        <!-- 📋 5. รายการคำขอลา (รูปแบบตาราง แสดงทีละ 7 รายการ) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden mt-4 flex flex-col justify-between">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse min-w-[900px]">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 font-semibold border-b border-slate-100 uppercase tracking-wider">
                            <th class="p-4">วันที่ส่งคำขอ</th>
                            <th class="p-4">รหัสพนักงาน</th>
                            <th class="p-4">ชื่อ-นามสกุล</th>
                            <th class="p-4">ประเภทการลา</th>
                            <th class="p-4">วันที่ลา</th>
                            <th class="p-4">จำนวนวัน/ชั่วโมง</th>
                            <th class="p-4 text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                        <?php if (empty($leave_requests)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10 text-slate-400 font-light">🚫 ไม่พบรายการคำขอลาในหมวดหมู่นี้</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leave_requests as $req): 
                                $badge_class = 'bg-amber-50 text-amber-700 border-amber-200/60';
                                $status_label = '⏳ รออนุมัติ';
                                if ($req['status'] === 'approved') {
                                    $badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-200/60';
                                    $status_label = '✅ อนุมัติแล้ว';
                                } elseif ($req['status'] === 'rejected') {
                                    $badge_class = 'bg-rose-50 text-rose-700 border-rose-200/60';
                                    $status_label = '❌ ไม่อนุมัติ';
                                }

                                // 1. แปลงวันที่ส่งคำขอ (พ.ศ.)
                                $created_display = date('d/m/', strtotime($req['created_at'])) . (date('Y', strtotime($req['created_at'])) + 543) . ' ' . date('H:i', strtotime($req['created_at'])) . ' น.';
                                
                                // 2. แปลงประเภทการลา
                                $raw_type = strtolower($req['leave_type'] ?? '');
                                $display_leave_type = $leave_type_map[$raw_type] ?? ($req['leave_type'] ?? 'ลาหยุด');

                                // 3. แปลงช่วงวันที่ลาให้เป็น พ.ศ.
                                $start_ts = strtotime($req['start_date']);
                                $end_ts   = strtotime($req['end_date']);
                                
                                $start_th = date('d/m/', $start_ts) . (date('Y', $start_ts) + 543);
                                $end_th   = date('d/m/', $end_ts) . (date('Y', $end_ts) + 543);

                                if ($req['start_date'] === $req['end_date']) {
                                    $leave_date_range = $start_th;
                                } else {
                                    $leave_date_range = $start_th . ' - ' . $end_th;
                                }

                                // 4. ดึงจำนวนวัน/ชั่วโมง จากการคำนวณ SQL มาแสดงตรงๆ
                                if (($req['leave_duration'] ?? 'full') === 'hourly') {
                                    $duration_display = htmlspecialchars($req['leave_hours'] ?? '0') . ' ชั่วโมง';
                                } else {
                                    $duration_display = max(1, (int)($req['total_days'] ?? 1)) . ' วัน';
                                }

                                // 5. รูปโปรไฟล์ & เอกสารแนบ
                                $avatar_src = !empty($req['profile_image']) ? '../uploads/profiles/' . $req['profile_image'] : '';
                                $attachment_src = !empty($req['attachment']) ? '../uploads/leaves/' . $req['attachment'] : '';
                            ?>
                                <tr onclick="openLeaveDetailModal(
                                        '<?php echo htmlspecialchars(addslashes($req['fullname'])); ?>',
                                        '<?php echo htmlspecialchars(addslashes($req['employee_code'])); ?>',
                                        '<?php echo htmlspecialchars(addslashes($req['dept_name'] ?? 'ไม่ระบุแผนก')); ?>',
                                        '<?php echo htmlspecialchars(addslashes($display_leave_type)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($leave_date_range)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($duration_display)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($created_display)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($req['reason'] ?? '-')); ?>',
                                        '<?php echo htmlspecialchars(addslashes($attachment_src)); ?>',
                                        '<?php echo $req['status']; ?>',
                                        '<?php echo $req['id']; ?>'
                                    )" 
                                    class="hover:bg-blue-50/50 transition-colors cursor-pointer active:scale-98">
                                    
                                    <td class="p-4 text-slate-500 whitespace-nowrap"><?php echo $created_display; ?></td>
                                    
                                    <td class="p-4 font-extrabold text-slate-700 whitespace-nowrap"><?php echo htmlspecialchars($req['employee_code']); ?></td>
                                    
                                    <td class="p-4 flex items-center gap-3 whitespace-nowrap">
                                        <div class="w-9 h-9 rounded-full bg-blue-100 border border-blue-200 overflow-hidden flex items-center justify-center font-bold text-blue-600 shrink-0">
                                            <?php if (!empty($avatar_src)): ?>
                                                <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="w-full h-full object-cover" onerror="this.remove();">
                                            <?php else: ?>
                                                <?php echo mb_substr($req['fullname'], 0, 1); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($req['fullname']); ?></p>
                                            <p class="text-[10px] text-slate-400 mt-0.5 max-w-[200px] truncate"><?php echo htmlspecialchars($req['dept_name'] ?? 'ไม่ระบุแผนก'); ?></p>
                                        </div>
                                    </td>
                                    
                                    <td class="p-4 text-blue-600 font-bold whitespace-nowrap"><?php echo htmlspecialchars($display_leave_type); ?></td>
                                    
                                    <td class="p-4 text-slate-600 whitespace-nowrap"><?php echo $leave_date_range; ?></td>
                                    
                                    <td class="p-4 text-slate-700 font-bold whitespace-nowrap"><?php echo $duration_display; ?></td>
                                    
                                    <td class="p-4 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold border <?php echo $badge_class; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 📄 6. PAGINATION FOOTER (ปุ่มเปลี่ยนหน้าแสดงผล) -->
            <?php if ($total_records > 0): ?>
            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-semibold text-slate-500">
                <div>
                    แสดงผล <span class="text-slate-800 font-bold"><?php echo min($offset + 1, $total_records); ?></span> 
                    ถึง <span class="text-slate-800 font-bold"><?php echo min($offset + $limit, $total_records); ?></span> 
                    จากทั้งหมด <span class="text-blue-600 font-extrabold"><?php echo $total_records; ?></span> รายการ
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="flex items-center gap-1.5">
                    <!-- ปุ่มย้อนกลับ -->
                    <?php if ($page > 1): ?>
                        <a href="<?php echo buildFilterUrl($status_filter, $search_query, $request_date_input, $leave_type_filter, $page - 1); ?>" 
                           class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all shadow-2xs active:scale-95">
                            ‹ ย้อนกลับ
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 bg-slate-100 border border-slate-200/60 text-slate-300 rounded-xl cursor-not-allowed select-none">
                            ‹ ย้อนกลับ
                        </span>
                    <?php endif; ?>

                    <!-- ตัวเลขหน้า -->
                    <div class="flex items-center gap-1">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="w-8 h-8 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center shadow-md shadow-blue-500/20">
                                    <?php echo $i; ?>
                                </span>
                            <?php elseif ($i == 1 || $i == $total_pages || abs($i - $page) <= 1): ?>
                                <a href="<?php echo buildFilterUrl($status_filter, $search_query, $request_date_input, $leave_type_filter, $i); ?>" 
                                   class="w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-600 font-bold flex items-center justify-center hover:bg-slate-100 transition-all shadow-2xs active:scale-95">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif (abs($i - $page) == 2): ?>
                                <span class="text-slate-400 px-1 select-none">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <!-- ปุ่มถัดไป -->
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo buildFilterUrl($status_filter, $search_query, $request_date_input, $leave_type_filter, $page + 1); ?>" 
                           class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all shadow-2xs active:scale-95">
                            ถัดไป ›
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 bg-slate-100 border border-slate-200/60 text-slate-300 rounded-xl cursor-not-allowed select-none">
                            ถัดไป ›
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>

    </main>

    <!-- 📑 ดึง Pop-up Modal รายละเอียดการลามาใช้งาน -->
    <?php include_once '../includes/modal_leave_detail.php'; ?>

    <!-- 📅 ดึงคอมโพเนนต์ปฏิทินมาใช้งาน -->
    <?php include_once '../includes/calendar_component.php'; ?>

</body>
</html>