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
$user_role     = $_SESSION['role'] ?? 'employee';

// 🔒 สิทธิ์ดูประวัติทั้งหมดเฉพาะ Admin, HR และ IT Support
$allowed_all_roles = ['admin', 'hr', 'it_support'];
$can_view_all      = in_array($user_role, $allowed_all_roles);

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
$status_filter  = $_GET['status'] ?? 'all';

// 📅 ค่าเริ่มต้นช่วงวันที่
$default_start_th = formatADDateToThai(date('Y-m-01'));
$default_end_th   = formatADDateToThai(date('Y-m-t'));
$date_range_raw   = $_GET['date_range'] ?? ($default_start_th . ' - ' . $default_end_th);

$dates_arr  = explode(' - ', $date_range_raw);
$date_start = parseThaiDateToAD($dates_arr[0] ?? '');
$date_end   = parseThaiDateToAD($dates_arr[1] ?? $dates_arr[0] ?? '');

// 📦 2. คิวรี่ดึงประวัติการจอง พร้อมเบอร์โทรผู้จอง และแมสเซนเจอร์
$where_clauses = ["1=1"];
$params = [];

if (!$can_view_all) {
    $where_clauses[] = "m.requester_id = :user_id";
    $params['user_id'] = $user_id;
}

if (!empty($search_query)) {
    $where_clauses[] = "(m.title LIKE :search OR m.pickup_location LIKE :search OR m.dropoff_location LIKE :search OR m.dropoff_contact LIKE :search)";
    $params['search'] = "%{$search_query}%";
}

if ($status_filter !== 'all') {
    if ($status_filter === 'delivering') {
        $where_clauses[] = "m.status IN ('accepted', 'picking_up', 'delivering')";
    } else {
        $where_clauses[] = "m.status = :status";
        $params['status'] = $status_filter;
    }
}

if (!empty($date_start)) {
    $where_clauses[] = "m.booking_date >= :date_start";
    $params['date_start'] = $date_start;
}
if (!empty($date_end)) {
    $where_clauses[] = "m.booking_date <= :date_end";
    $params['date_end'] = $date_end;
}

$where_sql = implode(' AND ', $where_clauses);

$history_list = [];
try {
    $stmt_history = $pdo->prepare("
        SELECT m.*, 
               CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
               u.phone AS requester_phone,
               u.employee_code,
               d.name AS dept_name,
               CONCAT(msg.first_name, ' ', msg.last_name) AS messenger_name,
               msg.phone AS messenger_phone
        FROM messenger_requests m
        LEFT JOIN users u ON m.requester_id = u.id
        LEFT JOIN departments d ON u.department = d.id
        LEFT JOIN users msg ON m.messenger_id = msg.id
        WHERE {$where_sql}
        ORDER BY m.booking_date DESC, m.id DESC
        LIMIT 100
    ");
    $stmt_history->execute($params);
    $history_list = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติและตารางวิ่งงาน - Lanto Workspace</title>
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

    <!-- 🔝 Header หลักของระบบ -->
    <?php 
    $page_title    = '📋 ประวัติและตารางวิ่งงาน';
    $page_subtitle = $can_view_all ? 'ตรวจสอบรายการจองและคิววิ่งงานแมสเซนเจอร์ในระบบ' : 'รายการประวัติงานที่คุณเคยแจ้งจองไว้';
    $show_back     = true;
    $back_url      = 'msg_index.php';
    include_once '../includes/header.php'; 
    ?>

    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-4">

        <!-- 🔎 แถบตัวกรอง -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col md:flex-row justify-between items-center gap-3">
            <form method="GET" action="msg_history.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">

                <div class="w-full sm:w-60 relative">
                    <span class="absolute left-3.5 top-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </span>
                    <input type="text" name="date_range" value="<?php echo htmlspecialchars($date_range_raw); ?>" class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:border-blue-500 transition-colors h-10 cursor-pointer" placeholder="เลือกช่วงวันที่">
                </div>

                <div class="w-full sm:w-80">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="ค้นหาชื่อเรื่อง, สถานที่, ผู้รับ..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-xs font-medium focus:outline-none focus:border-blue-500 transition-colors h-10">
                </div>

                <div class="w-48 sm:w-52">
                    <?php renderRoundedDropdown('status_select', 'status', $active_status_label, $status_opts, $status_filter, false); ?>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-colors cursor-pointer active:scale-95 h-10 flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
                        </svg>
                        <span>ค้นหา</span>
                    </button>
                    <a href="msg_history.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors h-10 flex items-center justify-center gap-1.5 cursor-pointer">
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
                    รายการจองประจำช่วงวันที่เลือก
                </h3>
                <span class="text-[10px] font-extrabold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-100">
                    <?php echo count($history_list); ?> รายการ
                </span>
            </div>

            <?php if (empty($history_list)): ?>
                <div class="bg-white p-10 rounded-2xl text-center text-slate-400 border border-slate-200/80 text-xs font-light">
                    ไม่พบข้อมูลการจองงานแมสเซนเจอร์ตามเงื่อนไขที่เลือก
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <?php foreach ($history_list as $req): 
                        $st = $req['status'] ?? 'pending';
                        $booking_dt = !empty($req['booking_date']) ? new DateTime($req['booking_date']) : null;
                        $created_dt = !empty($req['created_at']) ? new DateTime($req['created_at']) : null;
                        $is_my_job   = ($req['requester_id'] == $user_id);

                        // ผู้จอง
                        $req_name = $req['requester_name'] ?? '-';
                        if (!empty($req['requester_phone'])) {
                            $req_name .= ' (' . $req['requester_phone'] . ')';
                        }

                        // ผู้ติดต่อต้นทาง
                        $pickup_contact = $req['pickup_contact'] ?? '-';
                        if (!empty($req['pickup_phone'])) {
                            $pickup_contact .= ' (' . $req['pickup_phone'] . ')';
                        }

                        // ผู้รับปลายทาง
                        $drop_contact = $req['dropoff_contact'] ?? '-';
                        if (!empty($req['dropoff_phone'])) {
                            $drop_contact .= ' (' . $req['dropoff_phone'] . ')';
                        }

                        // แมสเซนเจอร์ผู้รับงาน
                        $messenger_display = 'ยังไม่มีผู้รับงาน';
                        if (!empty($req['messenger_name'])) {
                            $messenger_display = htmlspecialchars($req['messenger_name']);
                            if (!empty($req['messenger_phone'])) {
                                $messenger_display .= ' (' . htmlspecialchars($req['messenger_phone']) . ')';
                            }
                        }

                        // 🎯 เข้ารหัสข้อมูลเป็น Base64 ป้องกัน JSON Error ล้านเปอร์เซ็นต์
                        $job_encoded = rawurlencode(json_encode($req, JSON_UNESCAPED_UNICODE));
                    ?>
                        <!-- เรียกฟังก์ชันเปิด Modal โดยแปลงกลับเป็น Object ตรงนี้เลย -->
                        <div onclick="openJobDetailModal(JSON.parse(decodeURIComponent('<?php echo $job_encoded; ?>')))" 
                        class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs space-y-3 text-xs hover:border-blue-400 transition-all cursor-pointer group active:scale-[0.99]">
                            
                            <!-- หัวการ์ด -->
                            <div class="flex items-start justify-between border-b border-slate-100 pb-2.5 gap-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 shrink-0 bg-blue-50 rounded-xl border border-blue-100 flex items-center justify-center text-base">
                                        🛵
                                    </div>
                                    <div>
                                        <h4 class="font-extrabold text-slate-800 text-xs leading-snug group-hover:text-blue-600 transition-colors">
                                            <?php echo htmlspecialchars($req['title']); ?>
                                        </h4>
                                        <?php if ($is_my_job): ?>
                                            <span class="inline-block mt-0.5 text-[9px] font-black text-blue-600 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                                                งานของฉัน
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <?php if ($st === 'completed'): ?>
                                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full font-bold text-[10px]">✅ เสร็จสิ้น</span>
                                    <?php elseif (in_array($st, ['accepted', 'picking_up', 'delivering'])): ?>
                                        <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full font-bold text-[10px]">🛵 กำลังส่ง</span>
                                    <?php elseif ($st === 'pending'): ?>
                                        <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full font-bold text-[10px]">⏳ รอรับงาน</span>
                                    <?php elseif ($st === 'cancelled'): ?>
                                        <span class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-full font-bold text-[10px]">❌ ยกเลิก</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- รายละเอียดข้อมูลการจอง (Timeline Flow 3 จุด) -->
                            <div class="space-y-2 text-[11px] text-slate-600 bg-slate-50/80 p-3.5 rounded-2xl border border-slate-100">
                                <p class="flex items-center gap-1 font-semibold text-slate-700">
                                    <span class="text-slate-400">👤 ผู้จอง:</span>
                                    <span class="text-slate-800 font-extrabold"><?php echo htmlspecialchars($req_name); ?></span>
                                </p>

                                <!-- เส้น Timeline 3 จุด -->
                                <div class="my-2 pl-3 border-l-2 border-dashed border-slate-200 space-y-3 relative ml-1">
                                    
                                    <!-- จุดที่ 1: รอรับงาน -->
                                    <div class="relative pl-2">
                                        <?php if ($st === 'pending'): ?>
                                            <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-amber-500 ring-4 ring-amber-100"></span>
                                            <p class="text-[10px] font-extrabold text-amber-600 uppercase tracking-wider">1. รอรับงาน</p>
                                            <p class="font-bold text-slate-800 text-xs mt-0.5">รอแมสเซนเจอร์กดรับงาน</p>
                                        <?php else: ?>
                                            <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-slate-300 ring-4 ring-slate-100"></span>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">1. รอรับงาน</p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- จุดที่ 2: ต้นทาง -->
                                    <div class="relative pl-2">
                                        <?php if (in_array($st, ['accepted', 'picking_up', 'delivering'])): ?>
                                            <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-blue-500 ring-4 ring-blue-100"></span>
                                            <p class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider">2. ต้นทาง (กำลังไปรับ-ส่ง)</p>
                                            <p class="font-bold text-slate-800 text-xs mt-0.5"><?php echo htmlspecialchars($req['pickup_location']); ?></p>
                                            <p class="text-[10.5px] text-slate-500 font-semibold mt-0.5">ผู้ติดต่อ: <?php echo htmlspecialchars($pickup_contact); ?></p>
                                        <?php else: ?>
                                            <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-slate-300 ring-4 ring-slate-100"></span>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">2. ต้นทาง</p>
                                            <p class="font-semibold text-slate-600 text-xs mt-0.5"><?php echo htmlspecialchars($req['pickup_location']); ?></p>
                                            <p class="text-[10.5px] text-slate-400 mt-0.5">ผู้ติดต่อ: <?php echo htmlspecialchars($pickup_contact); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- จุดที่ 3: ปลายทาง -->
                                    <div class="relative pl-2">
                                        <?php if ($st === 'completed'): ?>
                                            <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                                            <p class="text-[10px] font-extrabold text-emerald-600 uppercase tracking-wider">3. ปลายทาง (เสร็จสิ้น)</p>
                                            <p class="font-bold text-slate-800 text-xs mt-0.5"><?php echo htmlspecialchars($req['dropoff_location']); ?></p>
                                            <p class="text-[10.5px] text-slate-500 font-semibold mt-0.5">ผู้รับ: <?php echo htmlspecialchars($drop_contact); ?></p>
                                        <?php else: ?>
                                            <span class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-slate-300 ring-4 ring-slate-100"></span>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">3. ปลายทาง</p>
                                            <p class="font-semibold text-slate-600 text-xs mt-0.5"><?php echo htmlspecialchars($req['dropoff_location']); ?></p>
                                            <p class="text-[10.5px] text-slate-400 mt-0.5">ผู้รับ: <?php echo htmlspecialchars($drop_contact); ?></p>
                                        <?php endif; ?>
                                    </div>

                                </div>

                                <!-- บล็อกวัน-เวลา + แมสเซนเจอร์ -->
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

                                    <div class="flex items-center justify-between text-[10.5px]">
                                        <span class="text-slate-500">🛵 แมสเซนเจอร์ผู้รับงาน:</span>
                                        <span class="text-slate-800 font-bold"><?php echo $messenger_display; ?></span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- 📱 แถบเมนูด้านล่างสุดสำหรับมือถือ (ปรับขนาดปุ่มให้เท่ากัน นิ่ง ไม่ขยาย) -->
    <div class="sm:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200/80 px-2 py-1.5 z-40 shadow-lg grid grid-cols-3 gap-1">
        
        <!-- ปุ่มที่ 1: ตารางปฏิทิน -->
        <a href="msg_index.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">📅</span>
            <span class="mt-0.5">ตารางปฏิทิน</span>
        </a>

        <!-- ปุ่มที่ 2: ประวัติงาน -->
        <a href="msg_history.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'history.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">📋</span>
            <span class="mt-0.5">ประวัติงาน</span>
        </a>

        <!-- ปุ่มที่ 3: กระดานแมส -->
        <a href="jobs.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-2xl text-[10px] transition-all <?php echo (basename($_SERVER['PHP_SELF']) == 'jobs.php') ? 'bg-blue-50 text-blue-600 border border-blue-200/80 font-black' : 'text-slate-500 hover:bg-slate-50 border border-transparent font-bold'; ?>">
            <span class="text-base leading-none">🛵</span>
            <span class="mt-0.5">กระดานแมส</span>
        </a>

    </div>

    <?php include_once 'modal_messenger_booking.php'; ?>
    <?php include_once 'modal_job_detail.php'; ?>
    <?php include_once '../includes/calendar_component.php'; ?>

    <script src="../assets/js/alerts.js"></script>

    <!-- 🎯 สคริปต์เรียก alerts.js เมื่อมีข้อความแจ้งเตือน (ลบสคริปต์ที่พังๆ ออกไปหมดแล้ว) -->
    <script>
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