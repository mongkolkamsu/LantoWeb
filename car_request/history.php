<?php
session_start();
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. รับค่า วัน, เดือน, ปี และสถานะจากตัวกรอง
$selected_day    = isset($_GET['day']) ? (int)$_GET['day'] : 0; 
$selected_month  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selected_year   = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// รายชื่อเดือนภาษาไทย
$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

// 3. ดึงรายการการจองรถตามเงื่อนไข วัน, เดือน, ปี, สถานะ (ดึง passengers_name มาด้วย)
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
        WHERE MONTH(cr.start_datetime) = :month 
          AND YEAR(cr.start_datetime) = :year
    ";

    $params = [
        'month' => $selected_month,
        'year'  => $selected_year
    ];

    if ($selected_day > 0) {
        $sql .= " AND DAY(cr.start_datetime) = :day";
        $params['day'] = $selected_day;
    }

    if ($selected_status === 'driving') {
        $sql .= " AND cr.status = 'approved' AND cr.start_mileage > 0 AND cr.actual_end_datetime IS NULL";
    } elseif ($selected_status === 'completed') {
        $sql .= " AND cr.status = 'completed'";
    } elseif ($selected_status === 'approved') {
        $sql .= " AND cr.status = 'approved' AND cr.start_mileage = 0";
    } elseif ($selected_status === 'pending') {
        $sql .= " AND cr.status = 'pending'";
    } elseif ($selected_status === 'rejected') {
        $sql .= " AND cr.status = 'rejected'";
    }

    $sql .= " ORDER BY cr.start_datetime DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests_list = [];
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
    <title>ประวัติและตารางการใช้รถ - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex justify-center text-slate-800 antialiased p-0 md:py-6">

    <!-- 📱 Main Container -->
    <div class="w-full min-h-screen bg-white/40 backdrop-blur-xl flex flex-col justify-between relative overflow-y-auto p-5 pb-10
        md:max-w-md md:min-h-[812px] md:h-auto md:rounded-[40px] md:border md:border-white/60 md:shadow-2xl">
        
        <div>
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-200/60">
                <a href="index.php" class="w-9 h-9 bg-white border border-slate-200 text-slate-600 rounded-full flex items-center justify-center shadow-xs active:scale-90 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="text-sm font-bold tracking-wide text-slate-700">ประวัติและตารางใช้รถ</h2>
                <div class="w-9"></div>
            </div>

            <!-- 🔍 ตัวกรอง เลือกวัน / เดือน / ปี -->
            <form id="filterForm" method="GET" action="history.php" class="mt-3.5 bg-white/80 backdrop-blur-md p-3 rounded-2xl border border-slate-200/80 shadow-xs relative z-30">
                <input type="hidden" name="day" id="input_day" value="<?php echo $selected_day; ?>">
                <input type="hidden" name="month" id="input_month" value="<?php echo $selected_month; ?>">
                <input type="hidden" name="year" id="input_year" value="<?php echo $selected_year; ?>">
                <input type="hidden" name="status" id="input_status" value="<?php echo $selected_status; ?>">

                <div class="grid grid-cols-3 gap-1.5 text-xs font-bold">
                    
                    <!-- เลือกวัน -->
                    <div class="relative">
                        <label class="block text-slate-500 text-[10px] mb-1">เลือกวัน</label>
                        <button type="button" onclick="toggleDropdown('dropdownDay')" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 font-bold text-slate-700 flex items-center justify-between hover:bg-slate-100 transition-colors cursor-pointer">
                            <span class="truncate"><?php echo ($selected_day > 0) ? 'วันที่ ' . $selected_day : 'ทุกวัน'; ?></span>
                            <span class="text-[9px] text-slate-400">▼</span>
                        </button>

                        <div id="dropdownDay" class="hidden absolute top-full left-0 mt-1 min-w-[130px] bg-white/95 backdrop-blur-xl border border-slate-200/80 rounded-2xl shadow-xl z-50 max-h-48 overflow-y-auto p-1.5 space-y-0.5">
                            <div onclick="selectDay(0)" 
                                class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer transition-colors <?php echo ($selected_day == 0) ? 'bg-blue-600 text-white font-bold' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-600'; ?>">
                                ทุกวัน
                            </div>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <div onclick="selectDay(<?php echo $d; ?>)" 
                                    class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer transition-colors <?php echo ($d == $selected_day) ? 'bg-blue-600 text-white font-bold' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-600'; ?>">
                                    วันที่ <?php echo $d; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- เลือกเดือน -->
                    <div class="relative">
                        <label class="block text-slate-500 text-[10px] mb-1">เลือกเดือน</label>
                        <button type="button" onclick="toggleDropdown('dropdownMonth')" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 font-bold text-slate-700 flex items-center justify-between hover:bg-slate-100 transition-colors cursor-pointer">
                            <span class="truncate"><?php echo $thai_months[$selected_month]; ?></span>
                            <span class="text-[9px] text-slate-400">▼</span>
                        </button>

                        <div id="dropdownMonth" class="hidden absolute top-full left-0 mt-1 min-w-[130px] bg-white/95 backdrop-blur-xl border border-slate-200/80 rounded-2xl shadow-xl z-50 max-h-48 overflow-y-auto p-1.5 space-y-0.5">
                            <?php foreach ($thai_months as $m_num => $m_name): ?>
                                <div onclick="selectMonth(<?php echo $m_num; ?>)" 
                                    class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer transition-colors <?php echo ($m_num == $selected_month) ? 'bg-blue-600 text-white font-bold' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-600'; ?>">
                                    <?php echo $m_name; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- เลือกปี -->
                    <div class="relative">
                        <label class="block text-slate-500 text-[10px] mb-1">เลือกปี (พ.ศ.)</label>
                        <button type="button" onclick="toggleDropdown('dropdownYear')" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 font-bold text-slate-700 flex items-center justify-between hover:bg-slate-100 transition-colors cursor-pointer">
                            <span class="truncate"><?php echo $selected_year + 543; ?></span>
                            <span class="text-[9px] text-slate-400">▼</span>
                        </button>

                        <div id="dropdownYear" class="hidden absolute top-full right-0 mt-1 min-w-[110px] bg-white/95 backdrop-blur-xl border border-slate-200/80 rounded-2xl shadow-xl z-50 max-h-48 overflow-y-auto p-1.5 space-y-0.5">
                            <?php 
                            $curr_year = (int)date('Y');
                            for ($y = $curr_year + 1; $y >= $curr_year - 2; $y--): 
                            ?>
                                <div onclick="selectYear(<?php echo $y; ?>)" 
                                    class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer transition-colors <?php echo ($y == $selected_year) ? 'bg-blue-600 text-white font-bold' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-600'; ?>">
                                    <?php echo $y + 543; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                </div>
            </form>

            <!-- 🎯 แท็บกรองสถานะด่วน -->
            <div class="mt-3 flex flex-wrap gap-1.5">
                <a href="history.php?day=<?php echo $selected_day; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&status=all" 
                   class="px-3 py-1.5 rounded-xl text-[11px] font-bold shrink-0 transition-all <?php echo ($selected_status === 'all') ? 'bg-blue-600 text-white shadow-xs' : 'bg-white/80 text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
                    ทั้งหมด
                </a>
                <a href="history.php?day=<?php echo $selected_day; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&status=driving" 
                   class="px-3 py-1.5 rounded-xl text-[11px] font-bold shrink-0 transition-all <?php echo ($selected_status === 'driving') ? 'bg-blue-600 text-white shadow-xs' : 'bg-white/80 text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
                    กำลังใช้งาน
                </a>
                
                <a href="history.php?day=<?php echo $selected_day; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&status=approved" 
                   class="px-3 py-1.5 rounded-xl text-[11px] font-bold shrink-0 transition-all <?php echo ($selected_status === 'approved') ? 'bg-blue-600 text-white shadow-xs' : 'bg-white/80 text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
                    อนุมัติแล้ว
                </a>
                <a href="history.php?day=<?php echo $selected_day; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&status=pending" 
                   class="px-3 py-1.5 rounded-xl text-[11px] font-bold shrink-0 transition-all <?php echo ($selected_status === 'pending') ? 'bg-blue-600 text-white shadow-xs' : 'bg-white/80 text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
                    รออนุมัติ
                </a>
                <a href="history.php?day=<?php echo $selected_day; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&status=completed" 
                   class="px-3 py-1.5 rounded-xl text-[11px] font-bold shrink-0 transition-all <?php echo ($selected_status === 'completed') ? 'bg-blue-600 text-white shadow-xs' : 'bg-white/80 text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
                    คืนรถแล้ว
                </a>
                <a href="history.php?day=<?php echo $selected_day; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&status=rejected" 
                   class="px-3 py-1.5 rounded-xl text-[11px] font-bold shrink-0 transition-all <?php echo ($selected_status === 'rejected') ? 'bg-blue-600 text-white shadow-xs' : 'bg-white/80 text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
                    ไม่อนุมัติ
                </a>
            </div>

            <!-- 📋 รายการการจอง -->
            <div class="mt-3 space-y-2.5">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                        รายการจอง <?php echo ($selected_day > 0 ? 'วันที่ ' . $selected_day . ' ' : '') . $thai_months[$selected_month] . ' ' . ($selected_year + 543); ?>
                    </h3>
                    <span class="text-[10px] font-extrabold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-100">
                        <?php echo count($requests_list); ?> รายการ
                    </span>
                </div>

                <!-- Container แสดงผลการ์ด พร้อม Scroll -->
                <div class="max-h-[440px] overflow-y-auto space-y-3 pr-1 pb-4">
                    <?php if (empty($requests_list)): ?>
                        <div class="bg-white/80 p-8 rounded-2xl text-center text-slate-400 border border-slate-200/60 text-xs font-light">
                            ไม่พบข้อมูลการจองรถตามเงื่อนไขที่เลือก
                        </div>
                    <?php else: ?>
                        <?php foreach ($requests_list as $req): 
                            $img_src  = !empty($req['car_image']) ? '../uploads/cars/' . $req['car_image'] : '../assets/images/sport-car.png';
                            $start_dt = !empty($req['start_datetime']) ? new DateTime($req['start_datetime']) : null;
                            $actual_end_dt = !empty($req['actual_end_datetime']) ? new DateTime($req['actual_end_datetime']) : null;
                            
                            $start_m = (int)($req['start_mileage'] ?? 0);
                            $end_m   = (int)($req['end_mileage'] ?? 0);
                            $dist    = ($end_m > $start_m) ? ($end_m - $start_m) : 0;
                            
                            $is_currently_driving = ($req['status'] === 'approved' && $start_m > 0 && empty($req['actual_end_datetime']));
                        ?>
                            <div class="bg-white/90 rounded-2xl p-4 border border-slate-200/80 shadow-xs space-y-3 text-xs backdrop-blur-md">
                                
                                <!-- หัวการ์ด: ข้อมูลรถ และ ป้ายสถานะ -->
                                <div class="flex items-start justify-between border-b border-slate-100 pb-2.5 gap-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 shrink-0 flex items-center justify-center">
                                            <img src="<?php echo $img_src; ?>" onerror="this.src='../assets/images/sport-car.png'" class="w-full h-full object-contain drop-shadow-xs">
                                        </div>
                                        <div>
                                            <h4 class="font-extrabold text-slate-800 text-xs leading-tight"><?php echo htmlspecialchars($req['brand_model']); ?></h4>
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
                                <div class="space-y-2 text-[11px] text-slate-600 bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                                    <div class="flex items-center justify-between">
                                        <span>👤 <strong>ผู้จอง:</strong> <?php echo htmlspecialchars($req['requester_firstname']); ?><?php echo !empty($req['employee_code']) ? ' (' . htmlspecialchars($req['employee_code']) . ')' : ''; ?></span>
                                        <?php if (!empty($req['passenger_count']) && $req['passenger_count'] > 0): ?>
                                            <span class="text-[10px] text-slate-500 font-semibold bg-white px-2 py-0.5 rounded-md border border-slate-200/60">👥 <?php echo $req['passenger_count']; ?> คน</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- 🎯 เพิ่มส่วนแสดงรายชื่อผู้ร่วมเดินทางในหน้าประวัติ -->
                                    <?php if (!empty($req['passengers_name'])): ?>
                                        <p class="text-[11px]">👥 <strong>ผู้ร่วมเดินทาง:</strong> <span class="text-slate-800 font-semibold"><?php echo htmlspecialchars($req['passengers_name']); ?></span></p>
                                    <?php endif; ?>
                                    
                                    <p>📍 <strong>สถานที่ / วัตถุประสงค์:</strong> <span class="text-slate-800 font-semibold"><?php echo htmlspecialchars($req['destination']); ?></span></p>
                                    
                                    <!-- บล็อกวัน-เวลา -->
                                    <div class="pt-2 border-t border-slate-200/60 font-semibold text-slate-700 space-y-1">
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
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>

    <script src="../assets/js/alerts.js"></script>
    <script>
        function toggleDropdown(id) {
            const target = document.getElementById(id);
            ['dropdownDay', 'dropdownMonth', 'dropdownYear'].forEach(dId => {
                if (dId !== id) {
                    const el = document.getElementById(dId);
                    if (el) el.classList.add('hidden');
                }
            });
            target.classList.toggle('hidden');
        }

        function selectDay(dNum) {
            document.getElementById('input_day').value = dNum;
            document.getElementById('filterForm').submit();
        }

        function selectMonth(mNum) {
            document.getElementById('input_month').value = mNum;
            document.getElementById('filterForm').submit();
        }

        function selectYear(yNum) {
            document.getElementById('input_year').value = yNum;
            document.getElementById('filterForm').submit();
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#filterForm')) {
                const d = document.getElementById('dropdownDay');
                const m = document.getElementById('dropdownMonth');
                const y = document.getElementById('dropdownYear');
                if (d) d.classList.add('hidden');
                if (m) m.classList.add('hidden');
                if (y) y.classList.add('hidden');
            }
        });

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