<?php
session_start();
require_once '../config/db.php'; 
require_once '../includes/rounded_dropdown.php';
require_once '../config/auth.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'it_support', 'hr'])) {
    header("Location: ../login.php");
    exit();
}

mb_internal_encoding("UTF-8");

$role = $_SESSION['role'];
$admin_fullname = $_SESSION['fullname'] ?? 'ผู้ดูแลระบบ';
$today_ad = date('Y-m-d');
$today_th = date('d/m/') . (date('Y') + 543);

// 🎯 รับค่าตัวกรองค้นหา
$status_filter      = $_GET['status'] ?? 'all';
$search_query       = trim($_GET['search'] ?? '');
$request_date_input = trim($_GET['request_date'] ?? $today_th);
$dept_filter        = $_GET['dept'] ?? '';
$branch_filter      = $_GET['branch'] ?? '';

// 📄 ตั้งค่า Pagination
$limit = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// 🎯 ฟังก์ชันถอดรหัสวันที่ไทย
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

// ถ้าแปลงค่าไม่ได้ ให้ดึงวันที่ปัจจุบันของเครื่องเสมอ
if (empty($start_date_db)) {
    $start_date_db = date('Y-m-d');
    $end_date_db   = date('Y-m-d');
}
// 🎯 กำหนดค่าเริ่มต้นกัน Error Undefined
$raw_records = [];
$total_logs_count = 0;
$early_count  = 0;
$ontime_count = 0;
$late_count   = 0;
$absent_count = 0;
try {
    // 1. ดึงแผนกและสาขาสำหรับ Dropdown
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $branches    = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. คิวรี่ดึงพนักงานและประวัติสแกน (เพิ่มการดึงรูปภาพสแกนเข้า/ออก)
    $sql_att = "
        SELECT 
            u.id AS user_id,
            u.employee_code,
            CONCAT(u.first_name, ' ', u.last_name) AS fullname,
            u.profile_image,
            d.name AS dept_name,
            b.name AS branch_name,
            w.name AS shift_name,
            w.start_time AS shift_start,
            w.end_time AS shift_end,
            in_log.scan_time AS check_in_time,
            in_log.photo_log AS check_in_img,
            out_log.scan_time AS check_out_time,
            out_log.photo_log AS check_out_img
        FROM users u
        LEFT JOIN departments d ON u.department = d.id
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN work_shifts w ON u.work_shift = w.id
        LEFT JOIN attendance in_log ON u.id = in_log.user_id 
            AND DATE(in_log.scan_time) BETWEEN :date_start1 AND :date_end1
            AND in_log.log_type = 'check_in'
        LEFT JOIN attendance out_log ON u.id = out_log.user_id 
            AND DATE(out_log.scan_time) BETWEEN :date_start2 AND :date_end2
            AND out_log.log_type = 'check_out'
        WHERE u.role != 'admin' AND u.is_active = 1
    ";

    $params = [
        'date_start1' => $start_date_db,
        'date_end1'   => $end_date_db,
        'date_start2' => $start_date_db,
        'date_end2'   => $end_date_db,
    ];

    if (!empty($search_query)) {
        // 🎯 เอาคำว่า "AS fullname" ออกจาก WHERE
        $sql_att .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE :search OR u.employee_code LIKE :search)";
        $params['search'] = "%{$search_query}%";
    }

    if (!empty($dept_filter)) {
        $sql_att .= " AND u.department = :dept";
        $params['dept'] = $dept_filter;
    }

    if (!empty($branch_filter)) {
        $sql_att .= " AND u.branch_id = :branch";
        $params['branch'] = $branch_filter;
    }

    $stmt = $pdo->prepare($sql_att);
    $stmt->execute($params);
    $raw_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    

    $processed_records = [];

    foreach ($raw_records as $rec) {
        $check_in = $rec['check_in_time'];
        $shift_start = $rec['shift_start'] ?? '08:30:00';
        $rec_status = 'absent';

        if (!empty($check_in)) {
            $in_time = date('H:i:s', strtotime($check_in));
            $early_threshold = date('H:i:s', strtotime($shift_start . ' -15 minutes'));

            if ($in_time < $early_threshold) {
                $rec_status = 'early';
                $early_count++;
            } elseif ($in_time <= $shift_start) {
                $rec_status = 'ontime';
                $ontime_count++;
            } else {
                $rec_status = 'late';
                $late_count++;
            }
        } else {
            $absent_count++;
        }

        $rec['calculated_status'] = $rec_status;

        // กรองตามการ์ดสถานะที่เลือก
        if ($status_filter === 'all' || $status_filter === $rec_status) {
            $processed_records[] = $rec;
        }
    }

    // ✅ คำนวณยอดรวมการสแกนเข้างานทั้งหมด (เข้าก่อน + ตรงเวลา + สาย)
    $total_logs_count = $early_count + $ontime_count + $late_count;

    // 📄 แบ่งหน้า Pagination
    $total_records = count($processed_records);
    $total_pages   = max(1, ceil($total_records / $limit));
    $attendance_list = array_slice($processed_records, $offset, $limit);

} catch (PDOException $e) {
    $attendance_list = [];
    $total_records   = 0;
    $total_pages     = 1;
}

// ตัวเลือก Dropdown แผนก/สาขา
$dept_opts = array_merge([['id' => '', 'name' => 'ทุกแผนก']], $departments);
$active_dept_label = 'ทุกแผนก';
foreach ($departments as $d) {
    if ((string)$d['id'] === (string)$dept_filter) { $active_dept_label = $d['name']; break; }
}

$branch_opts = array_merge([['id' => '', 'name' => 'ทุกสาขา']], $branches);
$active_branch_label = 'ทุกสาขา';
foreach ($branches as $b) {
    if ((string)$b['id'] === (string)$branch_filter) { $active_branch_label = $b['name']; break; }
}

function buildFilterUrl($new_status, $search, $req_date, $dept, $branch, $target_page = 1) {
    $p = ['status' => $new_status];
    if (!empty($search)) $p['search'] = $search;
    if (!empty($req_date)) $p['request_date'] = $req_date;
    if (!empty($dept)) $p['dept'] = $dept;
    if (!empty($branch)) $p['branch'] = $branch;
    if ($target_page > 1) $p['page'] = $target_page;
    return 'attendance_history.php?' . http_build_query($p);
}
$page_title    = 'ประวัติการเข้าออกงาน';
$page_subtitle = 'ตรวจสอบบันทึกเวลาการเข้า-ออกงาน พฤติกรรมลงเวลา และสถานะการเข้ากะของพนักงาน';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการเข้าออกงาน - Lanto Web Management System</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
    <!-- 💻 WORKSPACE WRAPPER ฝั่งขวา -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">

        <!-- 🔝 HEADER ADMIN -->
        <?php include_once '../includes/header_admin.php'; ?>
    <!-- 💻 2. MAIN WORKSPACE -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full min-h-screen md:h-screen overflow-y-auto space-y-4 sm:space-y-6 pb-20 md:pb-8">

        <!-- 📊 3. KPI STAT CARDS (สไตล์ Clean Box แบบ system_settings.php) -->
        <div class="bg-white p-2.5 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-2.5">
                
                <!-- 1️⃣ รายการสแกนทั้งหมด -->
                <?php $is_all = ($status_filter === 'all'); ?>
                <a href="<?php echo buildFilterUrl('all', $search_query, $request_date_input, $dept_filter, $branch_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_all ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📋</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">รายการสแกนทั้งหมด</p>
                            <p class="text-[10px] opacity-70 font-medium">ประวัติการลงเวลาทั้งหมด</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $total_logs_count ?? 0; ?></span>
                </a>

                <!-- 2️⃣ เข้าก่อนเวลา -->
                <?php $is_early = ($status_filter === 'early'); ?>
                <a href="<?php echo buildFilterUrl('early', $search_query, $request_date_input, $dept_filter, $branch_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_early ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">⚡</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">เข้าก่อนเวลา</p>
                            <p class="text-[10px] opacity-70 font-medium">สแกนก่อนเวลาเริ่มกะ</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $early_count ?? 0; ?></span>
                </a>

                <!-- 3️⃣ มาตรงเวลา -->
                <?php $is_ontime = ($status_filter === 'ontime'); ?>
                <a href="<?php echo buildFilterUrl('ontime', $search_query, $request_date_input, $dept_filter, $branch_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_ontime ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">✅</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">มาตรงเวลา</p>
                            <p class="text-[10px] opacity-70 font-medium">เข้างานตรงตามกะเวลา</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $ontime_count ?? 0; ?></span>
                </a>

                <!-- 4️⃣ เข้างานสาย -->
                <?php $is_late = ($status_filter === 'late'); ?>
                <a href="<?php echo buildFilterUrl('late', $search_query, $request_date_input, $dept_filter, $branch_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_late ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">⏰</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">เข้างานสาย</p>
                            <p class="text-[10px] opacity-70 font-medium">สแกนหลังเวลาเริ่มกะ</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $late_count ?? 0; ?></span>
                </a>

                <!-- 5️⃣ ขาดสแกน -->
                <?php $is_absent = ($status_filter === 'absent'); ?>
                <a href="<?php echo buildFilterUrl('absent', $search_query, $request_date_input, $dept_filter, $branch_filter); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_absent ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🛑</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">ขาดสแกน</p>
                            <p class="text-[10px] opacity-70 font-medium">พนักงานที่ยังไม่ลงเวลา</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $absent_count ?? 0; ?></span>
                </a>

            </div>
        </div>

        <!-- 🟡 4. แถบตัวกรองและค้นหา -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
            <form method="GET" action="attendance_history.php" class="flex flex-wrap items-center gap-3 w-full">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">

                <!-- เลือกวันที่ -->
                <div class="w-full sm:w-60 relative flex items-center">
                    <div class="absolute left-3.5 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <input type="text" name="request_date" value="<?php echo htmlspecialchars($request_date_input); ?>" placeholder="วว/ดด/ปปปป, ช่วงวัน หรือ เดือน"
                        class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:border-blue-500 transition-colors h-10 cursor-pointer">
                </div>

                <!-- ค้นหาชื่อ/รหัส -->
                <div class="w-full sm:w-100">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="ค้นหาชื่อ หรือ รหัสพนักงาน..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:border-blue-500 transition-colors h-10">
                </div>

                <!-- เลือกแผนก -->
                <div class="w-full sm:w-120">
                    <?php renderRoundedDropdown('dept_select', 'dept', $active_dept_label, $dept_opts, $dept_filter); ?>
                </div>

                <!-- เลือกสาขา -->
                <div class="w-full sm:w-55">
                    <?php renderRoundedDropdown('branch_select', 'branch', $active_branch_label, $branch_opts, $branch_filter); ?>
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

        <!-- 🔴 5. ตารางประวัติการเข้าออกงาน -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden mt-4 flex flex-col justify-between">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse min-w-[1000px]">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 font-semibold border-b border-slate-100 uppercase tracking-wider">
                            <th class="p-4 whitespace-nowrap">วันที่สแกน</th>
                            <th class="p-4 whitespace-nowrap">รหัสพนักงาน</th>
                            <th class="p-4 whitespace-nowrap">ชื่อ-นามสกุล</th>
                            <th class="p-4 whitespace-nowrap">แผนก</th>
                            <th class="p-4 whitespace-nowrap">กะการทำงาน</th>
                            <th class="p-4 whitespace-nowrap">สาขา</th>
                            <th class="p-4 text-center whitespace-nowrap">เข้างาน</th>
                            <th class="p-4 text-center whitespace-nowrap">ออกงาน</th>
                            <th class="p-4 text-center whitespace-nowrap">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                        <?php if (empty($attendance_list)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-10 text-slate-400 font-light">🚫 ไม่พบประวัติการเข้าออกงานตรงตามเงื่อนไขที่เลือก</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance_list as $row): 
                                $avatar_src = !empty($row['profile_image']) ? '../uploads/profiles/' . $row['profile_image'] : '';
                                
                                $scan_date_raw = !empty($row['check_in_time']) ? $row['check_in_time'] : $start_date_db;
                                $scan_date_display = date('d/m/', strtotime($scan_date_raw)) . (date('Y', strtotime($scan_date_raw)) + 543);

                                $in_display  = !empty($row['check_in_time']) ? date('H:i:s น.', strtotime($row['check_in_time'])) : '-';
                                $out_display = !empty($row['check_out_time']) ? date('H:i:s น.', strtotime($row['check_out_time'])) : '-';
                                $st = $row['calculated_status'];

                                $in_img_url  = !empty($row['check_in_img']) ? '../uploads/scan-in/' . $row['check_in_img'] : '';
                                $out_img_url = !empty($row['check_out_img']) ? '../uploads/scan-out/' . $row['check_out_img'] : '';

                                // 🎯 สร้างข้อมูล JSON สำหรับส่งเข้า Modal
                                $json_data = htmlspecialchars(json_encode([
                                    'fullname'    => $row['fullname'],
                                    'empCode'     => $row['employee_code'],
                                    'deptName'    => $row['dept_name'] ?? 'ไม่ระบุแผนก',
                                    'branchName'  => $row['branch_name'] ?? 'สำนักงานใหญ่',
                                    'shiftName'   => $row['shift_name'] ?? 'กะปกติ',
                                    'shiftStart'  => !empty($row['shift_start']) ? date('H:i', strtotime($row['shift_start'])) : '08:30',
                                    'scanDate'    => $scan_date_display,
                                    'inTime'      => $in_display,
                                    'outTime'     => $out_display,
                                    'status'      => $st,
                                    'avatarUrl'   => $avatar_src,
                                    'inImgUrl'    => $in_img_url,
                                    'outImgUrl'   => $out_img_url,
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr onclick='openAttendanceAdminDetailModal(<?php echo $json_data; ?>)' class="hover:bg-blue-50/50 transition-colors cursor-pointer active:scale-98">
                                
                                <td class="p-4 text-slate-500 whitespace-nowrap"><?php echo $scan_date_display; ?></td>
                                <td class="p-4 font-extrabold text-slate-700 whitespace-nowrap"><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                <td class="p-4 flex items-center gap-3 whitespace-nowrap">
                                    <div class="w-9 h-9 rounded-full bg-blue-100 border border-blue-200 overflow-hidden flex items-center justify-center font-bold text-blue-600 shrink-0">
                                        <?php if (!empty($avatar_src)): ?>
                                            <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="w-full h-full object-cover" onerror="this.remove();">
                                        <?php else: ?>
                                            <?php echo mb_substr($row['fullname'], 0, 1); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($row['fullname']); ?></p>
                                    </div>
                                </td>
                                <td class="p-4 font-bold text-slate-700 whitespace-nowrap"><?php echo htmlspecialchars($row['dept_name'] ?? 'ไม่ระบุแผนก'); ?></td>
                                <td class="p-4 whitespace-nowrap">
                                    <span class="inline-block px-2.5 py-0.5 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        <?php echo htmlspecialchars($row['shift_name'] ?? 'กะปกติ'); ?> 
                                        (<?php echo !empty($row['shift_start']) ? date('H:i', strtotime($row['shift_start'])) : '08:30'; ?>)
                                    </span>
                                </td>
                                <td class="p-4 text-slate-600 whitespace-nowrap">📍 <?php echo htmlspecialchars($row['branch_name'] ?? 'สำนักงานใหญ่'); ?></td>
                                <td class="p-4 text-center font-bold text-slate-800 whitespace-nowrap"><?php echo $in_display; ?></td>
                                <td class="p-4 text-center font-bold text-slate-800 whitespace-nowrap"><?php echo $out_display; ?></td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <?php if ($st === 'early'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold bg-sky-50 text-sky-700 border border-sky-200/80">
                                            🔵 เข้าก่อนเวลา
                                        </span>
                                    <?php elseif ($st === 'ontime'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                            🟢 ตรงเวลา
                                        </span>
                                    <?php elseif ($st === 'late'): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200/80">
                                            🟡 สาย
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/80">
                                            🔴 ขาดสแกน
                                        </span>
                                    <?php endif; ?>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 📄 6. PAGINATION FOOTER -->
            <?php if ($total_records > 0): ?>
            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-semibold text-slate-500">
                <div>
                    แสดงผล <span class="text-slate-800 font-bold"><?php echo min($offset + 1, $total_records); ?></span> 
                    ถึง <span class="text-slate-800 font-bold"><?php echo min($offset + $limit, $total_records); ?></span> 
                    จากทั้งหมด <span class="text-blue-600 font-extrabold"><?php echo $total_records; ?></span> รายการ
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="flex items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo buildFilterUrl($status_filter, $search_query, $request_date_input, $dept_filter, $branch_filter, $page - 1); ?>" 
                           class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all shadow-2xs active:scale-95">
                            ‹ ย้อนกลับ
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 bg-slate-100 border border-slate-200/60 text-slate-300 rounded-xl cursor-not-allowed select-none">
                            ‹ ย้อนกลับ
                        </span>
                    <?php endif; ?>

                    <div class="flex items-center gap-1">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="w-8 h-8 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center shadow-md shadow-blue-500/20">
                                    <?php echo $i; ?>
                                </span>
                            <?php elseif ($i == 1 || $i == $total_pages || abs($i - $page) <= 1): ?>
                                <a href="<?php echo buildFilterUrl($status_filter, $search_query, $request_date_input, $dept_filter, $branch_filter, $i); ?>" 
                                   class="w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-600 font-bold flex items-center justify-center hover:bg-slate-100 transition-all shadow-2xs active:scale-95">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif (abs($i - $page) == 2): ?>
                                <span class="text-slate-400 px-1 select-none">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo buildFilterUrl($status_filter, $search_query, $request_date_input, $dept_filter, $branch_filter, $page + 1); ?>" 
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

    <!-- 📅 ดึงคอมโพเนนต์ปฏิทินและ Pop-up Modal มาใช้งาน -->
    <?php include_once '../includes/calendar_component.php'; ?>
    <?php include_once '../includes/modal_attendance_admin_detail.php'; ?>

</body>
</html>