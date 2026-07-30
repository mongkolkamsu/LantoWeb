<?php
session_start();
require_once '../config/db.php'; // เชื่อมต่อฐานข้อมูลหลัก

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องล็อกอินก่อน)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

mb_internal_encoding("UTF-8");

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? '';

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
} catch (PDOException $e) {
    // ซ่อน Error
}

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
} catch (PDOException $e) {
    // ซ่อน Error
}

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
        // 🎯 สถิติการเข้างาน: สะสมเฉพาะสแกนเข้า (IN) เท่านั้น
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
        // 🎯 ฝั่งสแกนออก (OUT): กำหนดเฉพาะชื่อป้ายสถานะ ไม่เอาไปบวกเพิ่มในสถิติเข้าสาย
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
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] h-screen overflow-hidden touch-none flex items-center justify-center text-slate-800 select-none antialiased p-0 md:p-4">

    <!-- 📱 Main Mobile App Shell Layout -->
    <div class="w-full h-full bg-white/40 backdrop-blur-xl flex flex-col justify-between relative overflow-hidden p-5 pb-24
        md:max-w-md md:mx-auto md:min-h-[812px] md:rounded-[40px] md:border md:border-white/60 md:shadow-2xl">
        
        <div>
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-200/60">
                <a href="../index.php?view=mobile" class="w-10 h-10 bg-white/80 border border-slate-200 text-slate-600 rounded-full flex items-center justify-center shadow-xs active:scale-90 transition-transform cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="text-[15px] font-bold tracking-wide text-slate-700">รายงานประวัติการมาทำงาน</h2>
                <div class="w-10"></div>
            </div>

            <!-- ฟอร์มคัดกรองการแสดงผล -->
            <form id="filter-form" method="GET" action="history.php" class="my-4 bg-white/80 p-3.5 rounded-2xl border border-slate-200/60 shadow-xs grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[13px] font-bold text-slate-800 uppercase mb-1.5 pl-0.5">เลือกเดือน</label>
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
                    <label class="block text-[13px] font-bold text-slate-800 uppercase mb-1.5 pl-0.5">เลือกปี พ.ศ.</label>
                    <?php 
                    $year_opts = [];
                    $cur_year = (int)date('Y');
                    for ($y = $cur_year - 5; $y <= $cur_year + 1; $y++) {
                        $year_opts[] = ['id' => $y, 'name' => ($y + 543)];
                    }
                    renderRoundedDropdown('year_select', 'year', ($selected_year + 543), $year_opts, $selected_year);
                    ?>
                </div>
            </form>

            <!-- 📊 แผงกล่องสถิติสรุปแบบ 3 คอลัมน์ (ย่อขนาดให้เตี้ยและกระชับขึ้น) -->
            <div class="grid grid-cols-3 gap-2 mb-3.5">
                
                <!-- 1. เข้าก่อนเวลา -->
                <div onclick="filterLogs('early_in', this)" class="stat-card bg-blue-50/70 border border-blue-100 p-1.5 rounded-xl flex flex-col items-center justify-center text-center cursor-pointer hover:scale-[1.02] active:scale-[0.98] transition-all">
                    <div class="w-6 h-6 bg-blue-500 text-white rounded-lg flex items-center justify-center shadow-xs mb-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 7.5a4.5 4.5 0 100 9 4.5 4.5 0 000-9z"></path>
                        </svg>
                    </div>
                    <p class="text-slate-600 text-[9px] font-bold leading-tight">เข้าก่อนเวลา</p>
                    <p class="text-blue-700 text-xs font-black mt-0.5"><?php echo $count_early_in; ?> ครั้ง</p>
                </div>

                <!-- 2. เข้าตรงเวลา -->
                <div onclick="filterLogs('on_time', this)" class="stat-card bg-emerald-50/70 border border-emerald-100 p-1.5 rounded-xl flex flex-col items-center justify-center text-center cursor-pointer hover:scale-[1.02] active:scale-[0.98] transition-all">
                    <div class="w-6 h-6 bg-emerald-500 text-white rounded-lg flex items-center justify-center shadow-xs mb-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-slate-600 text-[9px] font-bold leading-tight">เข้าตรงเวลา</p>
                    <p class="text-emerald-700 text-xs font-black mt-0.5"><?php echo $count_on_time; ?> ครั้ง</p>
                </div>

                <!-- 3. เข้าสาย -->
                <div onclick="filterLogs('late', this)" class="stat-card bg-amber-50/70 border border-amber-100 p-1.5 rounded-xl flex flex-col items-center justify-center text-center cursor-pointer hover:scale-[1.02] active:scale-[0.98] transition-all">
                    <div class="w-6 h-6 bg-amber-500 text-white rounded-lg flex items-center justify-center shadow-xs mb-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <p class="text-slate-600 text-[9px] font-bold leading-tight">เข้าสาย</p>
                    <p class="text-amber-700 text-xs font-black mt-0.5"><?php echo $count_late; ?> ครั้ง</p>
                </div>

            </div>

            <!-- กล่องรายการสรุปผลการลงเวลางานประจำเดือนแบบการ์ดรายวัน -->
            <div class="space-y-2">
                <div class="flex justify-between items-center px-1 mb-1">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1">
                        <span>บันทึกรายวัน</span> 
                        <span id="list-title-status" class="text-[10px] text-blue-600 font-normal"></span>
                    </h3>
                    <button id="btn-clear-filter" onclick="resetLogFilter()" class="hidden text-[10px] text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md font-medium border border-blue-100 cursor-pointer">แสดงทั้งหมด</button>
                </div>
                
                <!-- 🎯 ล็อกความสูง max-h-[340px] เพื่อให้พอดีสำหรับการแสดงผลประมาณ 3 การ์ด -->
                <div id="logs-container" class="space-y-2.5 max-h-[390px] overflow-y-auto pr-1">
                    <?php if (empty($grouped_days)): ?>
                        <div class="bg-white/60 text-center py-10 rounded-2xl text-slate-400 border border-slate-200/50 text-xs font-light">
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
                            <div class="log-item bg-white rounded-2xl p-3 border border-slate-100 shadow-xs space-y-2 transition-all duration-200" data-category="<?php echo $combined_category; ?>">
                                
                                <!-- หัวการ์ด: แสดงวันที่ -->
                                <div class="flex justify-between items-center border-b border-slate-100 pb-1.5">
                                    <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                                        📅 <?php echo $day['date_display']; ?>
                                    </span>
                                    <span class="text-[10px] text-slate-500 font-bold">บันทึกเวลาปฏิบัติงานประจำวัน</span>
                                </div>

                                <!-- บอดี้การ์ด: แบ่งครึ่งซ้าย (เข้า) - ขวา (ออก) -->
                                <div class="grid grid-cols-2 gap-2">
                                    
                                    <!-- 📥 ฝั่งซ้าย: ข้อมูลเข้างาน (IN) - กดที่กล่องเพื่อเปิดหลักฐานได้ทันที -->
                                    <div class="bg-slate-100/90 p-2 rounded-xl border border-slate-200/80 flex flex-col justify-between space-y-1.5 shadow-2xs <?php echo $in ? 'cursor-pointer active:scale-95 hover:border-blue-300 transition-all' : ''; ?>"
                                        <?php if ($in): ?>
                                            onclick="openProofModal('check_in', '<?php echo $in['photo_log']; ?>', '<?php echo htmlspecialchars($in['clean_branch'], ENT_QUOTES); ?>', '<?php echo $in['status_title']; ?>', '<?php echo $in['status_color']; ?>', '<?php echo $day['date_display']; ?>', '<?php echo $in['time_display']; ?>')"
                                        <?php endif; ?>>
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] font-bold bg-blue-600 text-white px-2 py-0.5 rounded-md">เข้า (IN)</span>
                                            <?php if ($in): ?>
                                                <span class="text-xs font-bold text-slate-900 tracking-tight"><?php echo $in['time_display']; ?></span>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 font-light">-</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($in): ?>
                                            <div class="flex items-center justify-between pt-0.5">
                                                <span class="inline-block text-[9px] px-2 py-0.5 rounded-md font-semibold border <?php echo $in['status_color']; ?>">
                                                    <?php echo $in['status_title']; ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-400 italic">ยังไม่มีข้อมูลลงชื่อเข้า</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- 📤 ฝั่งขวา: ข้อมูลออกงาน (OUT) - กดที่กล่องเพื่อเปิดหลักฐานได้ทันที -->
                                    <div class="bg-slate-100/90 p-2 rounded-xl border border-slate-200/80 flex flex-col justify-between space-y-1.5 shadow-2xs <?php echo $out ? 'cursor-pointer active:scale-95 hover:border-blue-300 transition-all' : ''; ?>"
                                        <?php if ($out): ?>
                                            onclick="openProofModal('check_out', '<?php echo $out['photo_log']; ?>', '<?php echo htmlspecialchars($out['clean_branch'], ENT_QUOTES); ?>', '<?php echo $out['status_title']; ?>', '<?php echo $out['status_color']; ?>', '<?php echo $day['date_display']; ?>', '<?php echo $out['time_display']; ?>')"
                                        <?php endif; ?>>
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] font-bold bg-slate-700 text-white px-2 py-0.5 rounded-md">ออก (OUT)</span>
                                            <?php if ($out): ?>
                                                <span class="text-xs font-bold text-slate-900 tracking-tight"><?php echo $out['time_display']; ?></span>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 font-light">-</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($out): ?>
                                            <div class="flex items-center justify-between pt-0.5">
                                                <span class="inline-block text-[9px] px-2 py-0.5 rounded-md font-semibold border <?php echo $out['status_color']; ?>">
                                                    <?php echo $out['status_title']; ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-500">ยังไม่ลงชื่อออกงาน</span>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div id="no-filtered-data" class="hidden bg-white/60 text-center py-10 rounded-2xl text-slate-400 border border-slate-200/50 text-xs font-light">
                        🚫 ไม่พบรายการประวัติในหมวดหมู่นี้
                    </div>
                </div>
            </div>
        </div>

        <!-- เรียกใช้แถบเมนูส่วนกลาง -->
        <?php include '../includes/navbar.php'; ?>                    
    </div>

    <!-- 📥 ดึงกล่องป็อปอัปหลักฐานแสดงข้อมูลสแกนเวลาจากไฟล์คอมโพเนนต์ย่อย -->
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

        // 🎨 กำหนดชุดสีขอบไฮไลต์ตามหัวข้อที่คลิกเลือก
        const ringColorMap = {
            'early_in': ['ring-2', 'ring-blue-500'],      // เข้าก่อนเวลา -> ขอบสีฟ้า
            'on_time':  ['ring-2', 'ring-emerald-500'],   // เข้าตรงเวลา -> ขอบสีเขียว
            'late':     ['ring-2', 'ring-amber-500']       // เข้าสาย -> ขอบสีแดง
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

            // เคลียร์คลาสขอบและจางการ์ดอื่นลง
            cards.forEach(c => {
                c.classList.remove('ring-2', 'ring-blue-500', 'ring-emerald-500', 'ring-rose-500', 'scale-[1.02]');
                c.classList.add('opacity-60');
            });

            // ใส่สีขอบที่ตรงกับธีมการ์ดนั้นๆ
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
                c.classList.remove('ring-2', 'ring-blue-500', 'ring-emerald-500', 'ring-rose-500', 'scale-[1.02]', 'opacity-60');
            });

            logItems.forEach(item => {
                item.style.display = 'block';
            });

            if (titleStatus) {
                titleStatus.innerText = `(${logItems.length} วัน)`;
            }
        }
    </script>
</body>
</html>