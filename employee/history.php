<?php
session_start();
require_once '../config/db.php'; // เชื่อมต่อฐานข้อมูลหลัก
require_once '../config/auth.php';
// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องล็อกอินก่อน)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

mb_internal_encoding("UTF-8");

$user_id       = $_SESSION['user_id'];
$fullname      = $_SESSION['fullname'] ?? 'ไม่ระบุชื่อ';
$employee_code = $_SESSION['employee_code'] ?? '-';
$profile_image = $_SESSION['profile_image'] ?? '';

$avatar_url = !empty($profile_image) ? '../uploads/profiles/' . htmlspecialchars($profile_image, ENT_QUOTES, 'UTF-8') : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&h=150&q=80';

// ป้องกันการรับค่าว่างดักจับไม่ให้แปลงค่าเป็นเลข 0
$selected_month = (isset($_GET['month']) && $_GET['month'] !== '') ? (int)$_GET['month'] : (int)date('m');
$selected_year  = (isset($_GET['year']) && $_GET['year'] !== '') ? (int)$_GET['year'] : (int)date('Y');

// อาร์เรย์ชื่อเดือนภาษาไทยสำหรับแสดงผลใน Dropdown
$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

// ดึงเวลาเปิด-ปิดกะงานของพนักงานคนนี้จากตาราง
$shift_start = "08:30:00";
$shift_end = "17:30:00";
try {
    $stmt_u = $pdo->prepare("SELECT work_shift FROM users WHERE id = :id LIMIT 1");
    $stmt_u->execute(['id' => $user_id]);
    $user_shift_string = $stmt_u->fetchColumn();

    $stmt_s = $pdo->query("SELECT name, start_time, end_time FROM work_shifts WHERE is_active = 1");
    $all_shifts = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_shifts as $s) {
        $clean_name = explode(' ', $s['name'])[0];
        if (strpos($user_shift_string, $clean_name) !== false) {
            $shift_start = $s['start_time'];
            $shift_end = $s['end_time'];
            break;
        }
    }
} catch (PDOException $e) {}

// คิวรีดึงข้อมูลประวัติทั้งหมดของเดือนที่เลือก (เรียงจากใหม่ไปเก่า)
$logs = [];
try {
    $stmt_logs = $pdo->prepare("
        SELECT a.id, a.log_type, a.scan_time, a.latitude, a.longitude, a.photo_log, b.name AS branch_name
        FROM attendance a
        LEFT JOIN branches b ON a.branch_id = b.id
        WHERE a.user_id = :user_id 
          AND MONTH(a.scan_time) = :month 
          AND YEAR(a.scan_time) = :year 
        ORDER BY a.scan_time DESC
    ");
    $stmt_logs->execute([
        'user_id' => $user_id,
        'month'   => $selected_month,
        'year'    => $selected_year
    ]);
    $logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ตัวแปรนับสถิติ 3 ช่องหลัก
$count_on_time   = 0; 
$count_late      = 0; 
$count_early_in  = 0; 

// จัดกลุ่มข้อมูลแยกตาม "วันที่" (Daily Grouping)
$grouped_days = [];

foreach ($logs as $log) {
    $scan_timestamp = strtotime($log['scan_time']);
    $date_key = date('Y-m-d', $scan_timestamp); 
    $log_timeStr = date('H:i:s', $scan_timestamp);

    if (!isset($grouped_days[$date_key])) {
        $grouped_days[$date_key] = [
            'date_raw' => $date_key,
            'date_display' => date('d/m/', $scan_timestamp) . (date('Y', $scan_timestamp) + 543),
            'check_in' => null,
            'check_out' => null
        ];
    }

    $status_title = "เข้าตรงเวลา";
    $status_color = "bg-emerald-50 text-emerald-700 border-emerald-200/60";
    $filter_category = "on_time";

    if ($log['log_type'] === 'check_in') {
        if ($log_timeStr <= $shift_start) {
            if (strtotime($shift_start) - strtotime($log_timeStr) <= 900) {
                $status_title = "เข้าตรงเวลา";
                $status_color = "text-[10px] font-bold bg-emerald-50 text-emerald-700 border-emerald-200/60";
                $filter_category = "on_time";
                $count_on_time++;
            } else {
                $status_title = "เข้าก่อนเวลา";
                $status_color = "text-[10px] font-bold bg-blue-50 text-blue-700 border-blue-200/60";
                $filter_category = "early_in";
                $count_early_in++;
            }
        } else {
            $status_title = "เข้าสาย";
            $status_color = "text-[10px] font-bold bg-amber-50 text-amber-700 border-amber-200/60";
            $filter_category = "late";
            $count_late++;
        }
    } else {
        if ($log_timeStr < $shift_end) {
            $status_title = "ออกก่อนเวลา"; 
            $status_color = "text-[10px] font-bold bg-amber-50 text-amber-700 border-amber-200/60";
            $filter_category = "late";
        } else {
            $status_title = "เลิกงานตามเวลา";
            $status_color = "text-[10px] font-bold bg-emerald-50 text-emerald-700 border-emerald-200/60";
            $filter_category = "on_time";
        }
    }

    $log['status_title'] = $status_title;
    $log['status_color'] = $status_color;
    $log['filter_category'] = $filter_category;
    $log['time_display'] = date('H:i:s', $scan_timestamp);
    $log['clean_branch'] = !empty($log['branch_name']) ? $log['branch_name'] : 'นอกสถานที่ / ไม่ระบุ';

    if ($log['log_type'] === 'check_in') {
        if (!$grouped_days[$date_key]['check_in']) {
            $grouped_days[$date_key]['check_in'] = $log;
        }
    } else {
        if (!$grouped_days[$date_key]['check_out']) {
            $grouped_days[$date_key]['check_out'] = $log;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ประวัติการลงเวลางาน - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-[#f4f6fa] min-h-screen text-slate-800 antialiased flex">

    <?php include_once 'sidebar.php'; ?>

    <!-- 🖥️ ส่วนเนื้อหาฝั่งขวา PC -->
    <div class="flex-1 flex flex-col min-w-0 justify-between md:ml-64">
        <div class="w-full flex flex-col">
            <!-- 🔝 เรียกใช้งาน Header (พร้อมปุ่มย้อนกลับ) -->
            <?php 
            $page_title    = 'ประวัติการลงเวลางาน';
            $page_subtitle = 'ตรวจสอบประวัติเวลาปฏิบัติงานและการเข้า-ออกงาน';
            $show_back     = true;
            $back_url      = '../index_pc.php';
            include_once '../includes/header.php'; 
            ?>

            <!-- 💻 Main PC Container -->
            <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-5 md:space-y-6 pb-28 md:pb-10">
                
                <!-- แถบด้านบน: ฟอร์มเลือกเดือน/ปี และ แผงกล่องสถิติ -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-stretch">
                    
                    <!-- ฟอร์มเลือกเดือน / ปี -->
                    <form id="filter-form" method="GET" action="history.php" class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-3">
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">🗓️ คัดกรองข้อมูลประจำเดือน</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1 pl-0.5">เลือกเดือน</label>
                                <?php 
                                include_once '../includes/rounded_dropdown.php';
                                $month_opts = [];
                                foreach ($thai_months as $m_num => $m_name) {
                                    $month_opts[] = ['id' => $m_num, 'name' => $m_name];
                                }
                                renderRoundedDropdown('month_select', 'month', $thai_months[$selected_month], $month_opts, $selected_month);
                                ?>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1 pl-0.5">เลือกปี พ.ศ.</label>
                                <?php 
                                $year_opts = [];
                                $cur_year = (int)date('Y');
                                for ($y = $cur_year - 5; $y <= $cur_year + 1; $y++) {
                                    $year_opts[] = ['id' => $y, 'name' => ($y + 543)];
                                }
                                renderRoundedDropdown('year_select', 'year', ($selected_year + 543), $year_opts, $selected_year);
                                ?>
                            </div>
                        </div>
                    </form>

                    <!-- 📊 แผงกล่องสถิติสรุปแบบ 3 กล่อง (ขยายกว้างเต็มพื้นที่ PC) -->
                    <div class="md:col-span-2 grid grid-cols-3 gap-3">
                        
                        <!-- 1. เข้าก่อนเวลา -->
                        <div onclick="filterLogs('early_in', this)" class="stat-card bg-white border border-slate-200/80 p-4 rounded-3xl flex flex-col items-center justify-center text-center cursor-pointer hover:border-blue-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 bg-blue-500 text-white rounded-2xl flex items-center justify-center shadow-xs mb-2 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 7.5a4.5 4.5 0 100 9 4.5 4.5 0 000-9z"></path>
                                </svg>
                            </div>
                            <p class="text-slate-500 text-xs font-bold">เข้าก่อนเวลา</p>
                            <p class="text-blue-700 text-lg font-black mt-0.5"><?php echo $count_early_in; ?> <span class="text-xs font-normal text-slate-400">ครั้ง</span></p>
                        </div>

                        <!-- 2. เข้าตรงเวลา -->
                        <div onclick="filterLogs('on_time', this)" class="stat-card bg-white border border-slate-200/80 p-4 rounded-3xl flex flex-col items-center justify-center text-center cursor-pointer hover:border-emerald-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 bg-emerald-500 text-white rounded-2xl flex items-center justify-center shadow-xs mb-2 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="text-slate-500 text-xs font-bold">เข้าตรงเวลา</p>
                            <p class="text-emerald-700 text-lg font-black mt-0.5"><?php echo $count_on_time; ?> <span class="text-xs font-normal text-slate-400">ครั้ง</span></p>
                        </div>

                        <!-- 3. เข้าสาย -->
                        <div onclick="filterLogs('late', this)" class="stat-card bg-white border border-slate-200/80 p-4 rounded-3xl flex flex-col items-center justify-center text-center cursor-pointer hover:border-amber-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 bg-amber-500 text-white rounded-2xl flex items-center justify-center shadow-xs mb-2 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <p class="text-slate-500 text-xs font-bold">เข้าสาย</p>
                            <p class="text-amber-700 text-lg font-black mt-0.5"><?php echo $count_late; ?> <span class="text-xs font-normal text-slate-400">ครั้ง</span></p>
                        </div>

                    </div>

                </div>

                <!-- กล่องรายการสรุปผลการลงเวลางานประจำเดือนแบบการ์ดรายวัน -->
                <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                            <span>📋 บันทึกประวัติการสแกนเวลารายวัน</span> 
                            <span id="list-title-status" class="text-xs text-blue-600 font-normal"></span>
                        </h3>
                        <button id="btn-clear-filter" onclick="resetLogFilter()" class="hidden text-xs text-blue-600 bg-blue-50 px-3 py-1 rounded-xl font-bold border border-blue-100 hover:bg-blue-100 transition-colors cursor-pointer">แสดงทั้งหมด</button>
                    </div>
                    
                    <div id="logs-container" class="space-y-3">
                        <?php if (empty($grouped_days)): ?>
                            <div class="bg-slate-50 text-center py-12 rounded-2xl text-slate-400 border border-slate-100 text-xs font-light">
                                🚫 ไม่พบข้อมูลบันทึกเวลางานในเดือนนี้
                            </div>
                        <?php else: ?>
                            <?php foreach ($grouped_days as $day): 
                                $in = $day['check_in'];
                                $out = $day['check_out'];
                                
                                $combined_category = 'on_time';
                                if ($in && $in['filter_category'] === 'late') $combined_category = 'late';
                                if ($out && $out['filter_category'] === 'late') $combined_category = 'late';
                                if ($in && $in['filter_category'] === 'early_in') $combined_category = 'early_in';
                            ?>
                                <!-- 🗂️ การ์ดสรุปผล 1 วัน -->
                                <div class="log-item bg-slate-50 hover:bg-slate-100/60 rounded-2xl p-4 border border-slate-200/60 shadow-3xs space-y-3 transition-all duration-200" data-category="<?php echo $combined_category; ?>">
                                    
                                    <!-- หัวการ์ด: แสดงวันที่ -->
                                    <div class="flex justify-between items-center border-b border-slate-200/60 pb-2">
                                        <span class="text-xs font-bold text-slate-800 flex items-center gap-2">
                                            📅 วันที่ <?php echo $day['date_display']; ?>
                                        </span>
                                        <span class="text-[11px] text-slate-400 font-medium">บันทึกเวลาปฏิบัติงานประจำวัน</span>
                                    </div>

                                    <!-- บอดี้การ์ด: แบ่งครึ่งซ้าย (เข้า) - ขวา (ออก) -->
                                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
    
                                        <!-- 📥 ฝั่งซ้าย: ข้อมูลเข้างาน (IN) -->
                                        <div class="bg-white p-2.5 sm:p-3.5 rounded-xl border border-slate-200/80 flex flex-col justify-between shadow-2xs min-h-[78px] transition-all <?php echo $in ? 'cursor-pointer hover:border-blue-400 active:scale-[0.98]' : 'bg-slate-50/50 border-dashed'; ?>"
                                            <?php if ($in): ?>
                                                onclick="openProofModal('check_in', '<?php echo $in['photo_log']; ?>', '<?php echo htmlspecialchars($in['clean_branch'], ENT_QUOTES); ?>', '<?php echo $in['status_title']; ?>', '<?php echo $in['status_color']; ?>', '<?php echo $day['date_display']; ?>', '<?php echo $in['time_display']; ?>')"
                                            <?php endif; ?>>
                                            
                                            <!-- แถวบน: ป้ายบอกประเภท + เวลา -->
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="text-[10px] sm:text-xs font-bold bg-blue-600 text-white px-2 py-0.5 rounded-md shrink-0">เข้า (IN)</span>
                                                <span class="text-xs sm:text-sm font-black text-slate-800 tracking-tight"><?php echo $in ? $in['time_display'] : '-'; ?></span>
                                            </div>

                                            <!-- แถวล่าง: สถานที่ + สถานะ -->
                                            <div class="mt-2 space-y-1">
                                                <p class="text-[11px] sm:text-xs font-medium text-slate-600 truncate" title="<?php echo $in ? $in['clean_branch'] : 'ไม่มีข้อมูล'; ?>">
                                                    <?php echo $in ? $in['clean_branch'] : 'ไม่มีข้อมูล'; ?>
                                                </p>
                                                <?php if ($in): ?>
                                                    <div>
                                                        <span class="inline-block text-[9px] sm:text-[10px] px-1.5 py-0.2 rounded font-bold border <?php echo $in['status_color']; ?>">
                                                            <?php echo $in['status_title']; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- 📤 ฝั่งขวา: ข้อมูลออกงาน (OUT) -->
                                        <div class="bg-white p-2.5 sm:p-3.5 rounded-xl border border-slate-200/80 flex flex-col justify-between shadow-2xs min-h-[78px] transition-all <?php echo $out ? 'cursor-pointer hover:border-blue-400 active:scale-[0.98]' : 'bg-slate-50/50 border-dashed'; ?>"
                                            <?php if ($out): ?>
                                                onclick="openProofModal('check_out', '<?php echo $out['photo_log']; ?>', '<?php echo htmlspecialchars($out['clean_branch'], ENT_QUOTES); ?>', '<?php echo $out['status_title']; ?>', '<?php echo $out['status_color']; ?>', '<?php echo $day['date_display']; ?>', '<?php echo $out['time_display']; ?>')"
                                            <?php endif; ?>>
                                            
                                            <!-- แถวบน: ป้ายบอกประเภท + เวลา -->
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="text-[10px] sm:text-xs font-bold bg-slate-700 text-white px-2 py-0.5 rounded-md shrink-0">ออก (OUT)</span>
                                                <span class="text-xs sm:text-sm font-black text-slate-800 tracking-tight"><?php echo $out ? $out['time_display'] : '-'; ?></span>
                                            </div>

                                            <!-- แถวล่าง: สถานที่ + สถานะ -->
                                            <div class="mt-2 space-y-1">
                                                <p class="text-[11px] sm:text-xs font-medium text-slate-600 truncate" title="<?php echo $out ? $out['clean_branch'] : 'ไม่มีข้อมูล'; ?>">
                                                    <?php echo $out ? $out['clean_branch'] : 'ไม่มีข้อมูล'; ?>
                                                </p>
                                                <?php if ($out): ?>
                                                    <div>
                                                        <span class="inline-block text-[9px] sm:text-[10px] px-1.5 py-0.2 rounded font-bold border <?php echo $out['status_color']; ?>">
                                                            <?php echo $out['status_title']; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div id="no-filtered-data" class="hidden bg-slate-50 text-center py-12 rounded-2xl text-slate-400 border border-slate-100 text-xs font-light">
                            🚫 ไม่พบรายการประวัติในหมวดหมู่นี้
                        </div>
                    </div>
                </div>
            </main>
        </div>

    </div>

    <!-- 📱 แถบเมนูด้านล่างแสดงเฉพาะบนมือถือ -->
    <div class="md:hidden">
        <?php include '../includes/navbar.php'; ?>
    </div>

    <!-- 📥 ดึงกล่องป็อปอัปหลักฐานแสดงข้อมูลสแกนเวลา -->
    <?php include_once '../includes/modal_proof.php'; ?>

    <script>
        let currentActiveCategory = null;

        document.addEventListener('click', function(e) {
            if (e.target.closest('#list-month_select .dropdown-item') || e.target.closest('#list-year_select .dropdown-item')) {
                setTimeout(() => {
                    document.getElementById('filter-form').submit();
                }, 500); 
            }
        });

        const ringColorMap = {
            'early_in': ['ring-2', 'ring-blue-500'],
            'on_time':  ['ring-2', 'ring-emerald-500'],
            'late':     ['ring-2', 'ring-amber-500']
        };

        function filterLogs(category, cardElement) {
            const logItems = document.querySelectorAll('.log-item');
            const cards = document.querySelectorAll('.stat-card');
            const clearBtn = document.getElementById('btn-clear-filter');
            const noDataMessage = document.getElementById('no-filtered-data');
            const titleStatus = document.getElementById('list-title-status');
            let matchCount = 0;

            if (currentActiveCategory === category) {
                resetLogFilter();
                return;
            }

            currentActiveCategory = category;

            cards.forEach(c => {
                c.classList.remove('ring-2', 'ring-blue-500', 'ring-emerald-500', 'ring-amber-500', 'scale-[1.02]');
                c.classList.add('opacity-60');
            });

            cardElement.classList.remove('opacity-60');
            if (ringColorMap[category]) {
                cardElement.classList.add(...ringColorMap[category], 'scale-[1.02]');
            }
            clearBtn.classList.remove('hidden');

            logItems.forEach(item => {
                if (item.getAttribute('data-category') === category) {
                    item.style.display = 'block';
                    matchCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (matchCount === 0) {
                noDataMessage.classList.remove('hidden');
            } else {
                noDataMessage.classList.add('hidden');
            }

            const categoryNames = { 'on_time': 'เข้าตรงเวลา', 'late': 'เข้าสาย / ออกก่อนเวลา', 'early_in': 'เข้าก่อนเวลา' };
            if (titleStatus) {
                titleStatus.innerText = `- เฉพาะ: ${categoryNames[category]} (${matchCount} วัน)`;
            }
        }

        function resetLogFilter() {
            const logItems = document.querySelectorAll('.log-item');
            const cards = document.querySelectorAll('.stat-card');
            const clearBtn = document.getElementById('btn-clear-filter');
            const noDataMessage = document.getElementById('no-filtered-data');
            const titleStatus = document.getElementById('list-title-status');

            currentActiveCategory = null;
            clearBtn.classList.add('hidden');
            noDataMessage.classList.add('hidden');

            cards.forEach(c => {
                c.classList.remove('ring-2', 'ring-blue-500', 'ring-emerald-500', 'ring-amber-500', 'scale-[1.02]', 'opacity-60');
            });

            logItems.forEach(item => {
                item.style.display = 'block';
            });

            if (titleStatus) {
                titleStatus.innerText = `(${logItems.length} วัน)`;
            }
        }
    </script>
    <script src="../assets/js/alerts.js"></script>
</body>
</html>