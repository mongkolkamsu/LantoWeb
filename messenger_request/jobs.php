<?php
ob_start();
session_start();
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

require_once '../config/db.php';
require_once '../config/auth.php';             
require_once '../includes/rounded_dropdown.php';
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

// 🔍 1. รับค่าตัวกรองจาก Query String ($_GET)
$search_query   = trim($_GET['search'] ?? '');
$date_range_raw = $_GET['date_range'] ?? '';
$status_filter  = trim($_GET['status'] ?? 'all');

$dates_arr  = explode(' - ', $date_range_raw);
$date_start = parseThaiDateToAD($dates_arr[0] ?? '');
$date_end   = parseThaiDateToAD($dates_arr[1] ?? $dates_arr[0] ?? '');

// รายชื่อสถานะสำหรับดร็อปดาวน์
$status_opts = [
    ['id' => 'all', 'name' => 'ทุกสถานะ'],
    ['id' => 'pending', 'name' => '⏳ รอรับงาน'],
    ['id' => 'delivering', 'name' => '🛵 กำลังส่ง'],
    ['id' => 'completed', 'name' => '✅ เสร็จสิ้น'],
    ['id' => 'cancelled', 'name' => '❌ ยกเลิก']
];

$active_status_label = 'ทุกสถานะ';
foreach ($status_opts as $opt) {
    if ($opt['id'] === $status_filter) {
        $active_status_label = $opt['name'];
        break;
    }
}

// ดึงงานตามเงื่อนไขตัวกรอง
$pending_jobs = [];
$my_jobs      = [];

try {
    // 1. งานที่รอคนรับ (แสดงเฉพาะเมื่อเลือกสถานะเป็น all หรือ pending)
    if ($status_filter === 'all' || $status_filter === 'pending') {
        $where_pending = ["m.status = 'pending'"];
        $params_pending = [];

        if (!empty($search_query)) {
            $where_pending[] = "(m.title LIKE :search OR m.pickup_location LIKE :search OR m.dropoff_location LIKE :search OR m.dropoff_contact LIKE :search)";
            $params_pending['search'] = "%{$search_query}%";
        }

        if (!empty($date_start)) {
            $where_pending[] = "m.booking_date >= :date_start";
            $params_pending['date_start'] = $date_start;
        }
        if (!empty($date_end)) {
            $where_pending[] = "m.booking_date <= :date_end";
            $params_pending['date_end'] = $date_end;
        }

        $where_pending_sql = implode(' AND ', $where_pending);

        $stmt1 = $pdo->prepare("
            SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) AS requester_name, u.phone AS requester_phone, d.name AS dept_name,
                   CONCAT(msg.first_name, ' ', msg.last_name) AS messenger_name, msg.phone AS messenger_phone
            FROM messenger_requests m
            INNER JOIN users u ON m.requester_id = u.id
            LEFT JOIN departments d ON u.department = d.id
            LEFT JOIN users msg ON m.messenger_id = msg.id
            WHERE {$where_pending_sql}
            ORDER BY m.id DESC
        ");
        $stmt1->execute($params_pending);
        $pending_jobs = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. งานของแมสเซนเจอร์ (กรองตามสถานะที่เลือก เช่น กำลังส่ง, เสร็จสิ้น, ยกเลิก)
    if ($status_filter === 'all' || in_array($status_filter, ['delivering', 'completed', 'cancelled'])) {
        $my_status_condition = "m.status IN ('accepted', 'picking_up', 'delivering')";
        if ($status_filter === 'delivering') {
            $my_status_condition = "m.status IN ('accepted', 'picking_up', 'delivering')";
        } elseif ($status_filter === 'completed') {
            $my_status_condition = "m.status = 'completed'";
        } elseif ($status_filter === 'cancelled') {
            $my_status_condition = "m.status = 'cancelled'";
        }

        $where_my = ["m.messenger_id = :m_id", $my_status_condition];
        $params_my = ['m_id' => $user_id];

        if (!empty($search_query)) {
            $where_my[] = "(m.title LIKE :search OR m.pickup_location LIKE :search OR m.dropoff_location LIKE :search OR m.dropoff_contact LIKE :search)";
            $params_my['search'] = "%{$search_query}%";
        }

        if (!empty($date_start)) {
            $where_my[] = "m.booking_date >= :date_start";
            $params_my['date_start'] = $date_start;
        }
        if (!empty($date_end)) {
            $where_my[] = "m.booking_date <= :date_end";
            $params_my['date_end'] = $date_end;
        }

        $where_my_sql = implode(' AND ', $where_my);

        $stmt2 = $pdo->prepare("
            SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) AS requester_name, u.phone AS requester_phone,
                   CONCAT(msg.first_name, ' ', msg.last_name) AS messenger_name, msg.phone AS messenger_phone
            FROM messenger_requests m
            INNER JOIN users u ON m.requester_id = u.id
            LEFT JOIN users msg ON m.messenger_id = msg.id
            WHERE {$where_my_sql}
            ORDER BY m.id DESC
        ");
        $stmt2->execute($params_my);
        $my_jobs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messenger Job Board - Lanto Workspace</title>
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
    $page_title    = '🛵 กระดานรับงานแมสเซนเจอร์';
    $page_subtitle = 'เลือกรับงานจัดส่งพัสดุและอัปเดตสถานะงานที่คุณกำลังดำเนินการ';
    $show_back     = true;
    $back_url      = 'index.php';
    include_once '../includes/header.php'; 
    ?>

    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

        <!-- 🔎 แถบตัวกรองข้อมูล (เพิ่มดร็อปดาวน์เลือกสถานะแล้ว) -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col md:flex-row justify-between items-center gap-3">
            <form method="GET" action="jobs.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                
                <div class="w-full sm:w-60 relative">
                    <span class="absolute left-3.5 top-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </span>
                    <input type="text" name="date_range" value="<?php echo htmlspecialchars($date_range_raw); ?>" class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:border-blue-500 transition-colors h-10 cursor-pointer" placeholder="เลือกช่วงวันที่ต้องการวิ่งงาน">
                </div>

                <div class="w-full sm:w-80">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="ค้นหาชื่อเรื่อง, สถานที่, ผู้ติดต่อ..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-xs font-medium focus:outline-none focus:border-blue-500 transition-colors h-10">
                </div>

                <!-- 🎯 ดร็อปดาวน์เลือกสถานะ -->
                <div class="w-48 sm:w-52">
                    <?php renderRoundedDropdown('status_select', 'status', $active_status_label, $status_opts, $status_filter, false); ?>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-colors cursor-pointer active:scale-95 h-10 flex items-center gap-1.5 shadow-2xs">
                        <span>ค้นหา</span>
                    </button>
                    <a href="jobs.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors h-10 flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                        </svg>
                        <span>ล้างค่า</span>
                    </a>
                </div>
            </form>
        </div>

        <!-- Section 1: งานที่ฉันกำลังวิ่งอยู่ -->
        <?php if (!empty($my_jobs)): ?>
        <div class="space-y-3">
            <h2 class="text-xs font-bold text-blue-600 uppercase tracking-wider flex items-center gap-1.5 px-1">
                <span>🔥</span> <span>งานที่ฉันกำลังดำเนินการอยู่ (<?php echo count($my_jobs); ?>)</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                <?php foreach ($my_jobs as $job): 
                    $job_encoded = rawurlencode(json_encode($job, JSON_UNESCAPED_UNICODE));
                    
                    $next_st = 'completed';
                    $btn_text = '✅ เสร็จ (จัดส่งสำเร็จ) ';
                    $btn_bg = 'bg-emerald-600 hover:bg-emerald-700';

                    $req_name = ($job['requester_name'] ?? '-') . (!empty($job['requester_phone']) ? ' (' . $job['requester_phone'] . ')' : '');
                    $pickup_contact = ($job['pickup_contact'] ?? '-') . (!empty($job['pickup_phone']) ? ' (' . $job['pickup_phone'] . ')' : '');
                    $drop_contact = ($job['dropoff_contact'] ?? '-') . (!empty($job['dropoff_phone']) ? ' (' . $job['dropoff_phone'] . ')' : '');

                    $created_dt = !empty($job['created_at']) ? new DateTime($job['created_at']) : null;
                    $booking_dt = !empty($job['booking_date']) ? new DateTime($job['booking_date']) : null;
                    $is_my_job  = ($job['messenger_id'] == $user_id);
                ?>
                    <div onclick="openJobDetailModal(JSON.parse(decodeURIComponent('<?php echo $job_encoded; ?>')))" 
                        class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs space-y-3 text-xs hover:border-blue-400 transition-all cursor-pointer group">
                        
                        <div class="flex items-start justify-between border-b border-slate-100 pb-2.5 gap-2">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 shrink-0 bg-blue-50 rounded-xl border border-blue-100 flex items-center justify-center text-base">
                                    🛵
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-800 text-xs sm:text-sm leading-snug group-hover:text-blue-600 transition-colors">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </h4>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                                            <?php echo htmlspecialchars($job['job_no']); ?>
                                        </span>
                                        <?php if ($is_my_job): ?>
                                            <span class="text-[9px] font-black text-emerald-600 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
                                                งานของฉัน
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="shrink-0">
                                <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full font-bold text-[10px]">
                                    🛵 กำลังส่ง
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2 text-[11px] text-slate-600 bg-slate-50/80 p-3.5 rounded-2xl border border-slate-100">
                            <p class="flex items-center gap-1 font-semibold text-slate-700">
                                <span class="text-slate-400">👤 ผู้จอง:</span>
                                <span class="text-slate-800 font-extrabold"><?php echo htmlspecialchars($req_name); ?></span>
                            </p>

                            <div class="my-2 pl-3 border-l-2 border-dashed border-slate-200 space-y-3 relative ml-1">
                                <div class="relative pl-2">
                                    <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                                    <p class="text-[10px] font-extrabold text-emerald-600 uppercase tracking-wider">1. รับงานแล้ว</p>
                                </div>
                                <div class="relative pl-2">
                                    <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-blue-500 ring-4 ring-blue-100"></span>
                                    <p class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider">2. ต้นทาง (กำลังส่ง)</p>
                                    <p class="font-bold text-slate-800 text-xs mt-0.5"><?php echo htmlspecialchars($job['pickup_location']); ?></p>
                                    <p class="text-[10.5px] text-slate-500 font-semibold mt-0.5">ผู้ติดต่อ: <?php echo htmlspecialchars($pickup_contact); ?></p>
                                </div>
                                <div class="relative pl-2">
                                    <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-slate-300 ring-4 ring-slate-100"></span>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">3. ปลายทาง</p>
                                    <p class="font-semibold text-slate-600 text-xs mt-0.5"><?php echo htmlspecialchars($job['dropoff_location']); ?></p>
                                    <p class="text-[10.5px] text-slate-400 mt-0.5">ผู้รับ: <?php echo htmlspecialchars($drop_contact); ?></p>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-200/60 font-semibold text-slate-700 space-y-1">
                                <?php if ($created_dt): ?>
                                    <div class="flex items-center justify-between text-[10.5px]">
                                        <span class="text-slate-400 font-medium">วันที่ยื่นเรื่อง:</span>
                                        <span class="text-slate-600 font-bold"><?php echo $created_dt->format('d/m/') . ($created_dt->format('Y') + 543) . ' เวลา ' . $created_dt->format('H:i') . ' น.'; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($booking_dt): ?>
                                    <div class="flex items-center justify-between text-[10.5px]">
                                        <span class="text-slate-500">วันที่ต้องการวิ่งงาน:</span>
                                        <span class="text-blue-700 font-bold"><?php echo $booking_dt->format('d/m/') . ($booking_dt->format('Y') + 543); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div onclick="event.stopPropagation(); confirmQuickUpdate(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['job_no']); ?>', '<?php echo $next_st; ?>')" class="block text-center py-2.5 <?php echo $btn_bg; ?> text-white text-xs font-extrabold rounded-xl shadow-md transition-all active:scale-98 cursor-pointer">
                            <?php echo $btn_text; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Section 2: รายการงานที่รอแมสเซนเจอร์รับงาน -->
        <div class="space-y-3">
            <h2 class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5 px-1">
                <span>📋</span> <span>รายการงานที่รอคนวิ่งส่ง (<?php echo count($pending_jobs); ?>)</span>
            </h2>

            <?php if (empty($pending_jobs)): ?>
                <div class="bg-white p-10 rounded-2xl border border-slate-200/80 text-center text-slate-400 text-xs shadow-2xs font-light">
                    🚫 ขณะนี้ยังไม่มีรายการงานที่รอแมสเซนเจอร์รับส่งตามเงื่อนไขที่เลือก
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <?php foreach ($pending_jobs as $job): 
                        $job_encoded = rawurlencode(json_encode($job, JSON_UNESCAPED_UNICODE));
                        $req_name = ($job['requester_name'] ?? '-') . (!empty($job['requester_phone']) ? ' (' . $job['requester_phone'] . ')' : '');
                        $pickup_contact = ($job['pickup_contact'] ?? '-') . (!empty($job['pickup_phone']) ? ' (' . $job['pickup_phone'] . ')' : '');
                        $drop_contact = ($job['dropoff_contact'] ?? '-') . (!empty($job['dropoff_phone']) ? ' (' . $job['dropoff_phone'] . ')' : '');

                        $created_dt = !empty($job['created_at']) ? new DateTime($job['created_at']) : null;
                        $booking_dt = !empty($job['booking_date']) ? new DateTime($job['booking_date']) : null;
                    ?>
                        <div onclick="openJobDetailModal(JSON.parse(decodeURIComponent('<?php echo $job_encoded; ?>')))" 
                            class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs space-y-3 text-xs hover:border-blue-400 transition-all cursor-pointer group">
                            
                            <div class="flex items-start justify-between border-b border-slate-100 pb-2.5 gap-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 shrink-0 bg-amber-50 rounded-xl border border-amber-100 flex items-center justify-center text-base">
                                        ⏳
                                    </div>
                                    <div>
                                        <h4 class="font-extrabold text-slate-800 text-xs sm:text-sm leading-snug group-hover:text-blue-600 transition-colors">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </h4>
                                        <span class="inline-block mt-0.5 text-[10px] font-black text-blue-600 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                                            <?php echo htmlspecialchars($job['job_no']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full font-bold text-[10px]">
                                        ⏳ รอรับงาน
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2 text-[11px] text-slate-600 bg-slate-50/80 p-3.5 rounded-2xl border border-slate-100">
                                <p class="flex items-center gap-1 font-semibold text-slate-700">
                                    <span class="text-slate-400">👤 ผู้จอง:</span>
                                    <span class="text-slate-800 font-extrabold"><?php echo htmlspecialchars($req_name); ?></span>
                                </p>

                                <div class="my-2 pl-3 border-l-2 border-dashed border-slate-200 space-y-3 relative ml-1">
                                    <div class="relative pl-2">
                                        <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-amber-500 ring-4 ring-amber-100"></span>
                                        <p class="text-[10px] font-extrabold text-amber-600 uppercase tracking-wider">1. รอรับงาน</p>
                                        <p class="font-bold text-slate-800 text-xs mt-0.5">รอแมสเซนเจอร์กดรับงาน</p>
                                    </div>
                                    <div class="relative pl-2">
                                        <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-slate-300 ring-4 ring-slate-100"></span>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">2. ต้นทาง</p>
                                        <p class="font-semibold text-slate-600 text-xs mt-0.5"><?php echo htmlspecialchars($job['pickup_location']); ?></p>
                                        <p class="text-[10.5px] text-slate-400 mt-0.5">ผู้ติดต่อ: <?php echo htmlspecialchars($pickup_contact); ?></p>
                                    </div>
                                    <div class="relative pl-2">
                                        <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-slate-300 ring-4 ring-slate-100"></span>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">3. ปลายทาง</p>
                                        <p class="font-semibold text-slate-600 text-xs mt-0.5"><?php echo htmlspecialchars($job['dropoff_location']); ?></p>
                                        <p class="text-[10.5px] text-slate-400 mt-0.5">ผู้รับ: <?php echo htmlspecialchars($drop_contact); ?></p>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-200/60 font-semibold text-slate-700 space-y-1">
                                    <?php if ($created_dt): ?>
                                        <div class="flex items-center justify-between text-[10.5px]">
                                            <span class="text-slate-400 font-medium">วันที่ยื่นเรื่อง:</span>
                                            <span class="text-slate-600 font-bold"><?php echo $created_dt->format('d/m/') . ($created_dt->format('Y') + 543) . ' เวลา ' . $created_dt->format('H:i') . ' น.'; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($booking_dt): ?>
                                        <div class="flex items-center justify-between text-[10.5px]">
                                            <span class="text-slate-500">วันที่ต้องการวิ่งงาน:</span>
                                            <span class="text-blue-700 font-bold"><?php echo $booking_dt->format('d/m/') . ($booking_dt->format('Y') + 543); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div onclick="event.stopPropagation(); confirmAcceptJob(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['job_no']); ?>')" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs rounded-xl text-center shadow-2xs transition-all active:scale-98 cursor-pointer">
                                ✋ กดรับงานนี้
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- 📱 แถบเมนูด้านล่างสุดสำหรับมือถือ -->
    <div class="sm:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200/80 px-2 py-1.5 z-40 shadow-lg grid grid-cols-3 gap-1">
        <a href="index.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">📅</span>
            <span class="mt-0.5">ตารางปฏิทิน</span>
        </a>
        <a href="history.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'history.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">📋</span>
            <span class="mt-0.5">ประวัติงาน</span>
        </a>
        <a href="jobs.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'jobs.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">🛵</span>
            <span class="mt-0.5">กระดานแมส</span>
        </a>
    </div>

    <?php include_once 'modal_job_detail.php'; ?>
    <?php include_once '../includes/calendar_component.php'; ?>

    <script src="../assets/js/alerts.js"></script>

    <script>
        function confirmAcceptJob(jobId, jobNo) {
            if (typeof LantoAlert !== 'undefined' && typeof LantoAlert.confirm === 'function') {
                LantoAlert.confirm(
                    'ยืนยันการรับงาน',
                    `คุณต้องการกดรับงานเลขที่ ${jobNo} ใช่หรือไม่?`,
                    function() {
                        window.location.href = `process.php?action=accept_job&id=${jobId}`;
                    },
                    null,
                    'approve'
                );
            } else if (confirm(`ยืนยันกดรับงานเลขที่ ${jobNo} ใช่หรือไม่?`)) {
                window.location.href = `process.php?action=accept_job&id=${jobId}`;
            }
        }

        function confirmQuickUpdate(jobId, jobNo, nextStatus) {
            const statusLabel = 'เสร็จ (จัดส่งสำเร็จ)';
            
            if (typeof LantoAlert !== 'undefined' && typeof LantoAlert.confirm === 'function') {
                LantoAlert.confirm(
                    'ยืนยันเปลี่ยนสถานะงาน',
                    `คุณต้องการเปลี่ยนสถานะงาน ${jobNo} เป็น "${statusLabel}" ใช่หรือไม่?`,
                    function() {
                        window.location.href = `process.php?action=quick_update_status&id=${jobId}&status=${nextStatus}`;
                    },
                    null,
                    'approve'
                );
            } else if (confirm(`ยืนยันเปลี่ยนสถานะงาน ${jobNo} เป็น "${statusLabel}" ใช่หรือไม่?`)) {
                window.location.href = `process.php?action=quick_update_status&id=${jobId}&status=${nextStatus}`;
            }
        }

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