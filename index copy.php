<?php
session_start();
require_once 'config/db.php'; 
require_once 'includes/rounded_dropdown.php'; 
require_once 'includes/functions.php';
require_once 'config/auth.php';

// 🎯 ดักจับความปลอดภัยระดับไฟล์หลัก ตรวจสอบสิทธิ์ก่อนเข้าใช้งานหน้าแดชบอร์ด
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

mb_internal_encoding("UTF-8");

$user_id       = $_SESSION['user_id'];
$fullname      = $_SESSION['fullname'] ?? 'ไม่ระบุชื่อ';
$employee_code = $_SESSION['employee_code'] ?? '-';
$profile_image = $_SESSION['profile_image'] ?? '';
$user_role     = $_SESSION['role'] ?? 'employee';

// ดึงข้อมูลข่าวสาร
$news_list = [];
try {
    $stmt_news = $pdo->query("SELECT * FROM news ORDER BY id DESC LIMIT 5");
    $news_list = $stmt_news->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $news_list = [];
}

$avatar_url = !empty($profile_image) 
    ? 'uploads/profiles/' . htmlspecialchars($profile_image, ENT_QUOTES, 'UTF-8') 
    : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&h=150&q=80';

// 🎯 1. ตรรกะดึงข้อมูลบันทึกเวลาจริงประจำวันของพนักงานคนนี้
$today          = date('Y-m-d');
$tomorrow       = date('Y-m-d', strtotime('+1 day'));
$checkin_time   = '--:--';
$checkout_time  = '--:--';
$status_text    = 'ยังไม่เข้างาน';

try {
    $stmt = $pdo->prepare("
        SELECT log_type, DATE_FORMAT(scan_time, '%H:%i') as s_time 
        FROM attendance 
        WHERE user_id = :user_id 
          AND scan_time >= :today 
          AND scan_time < :tomorrow 
        ORDER BY scan_time ASC
    ");
    $stmt->execute([
        'user_id'  => $user_id, 
        'today'    => $today . ' 00:00:00',
        'tomorrow' => $tomorrow . ' 00:00:00'
    ]);
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($records as $rec) {
        if ($rec['log_type'] === 'check_in') {
            $checkin_time = $rec['s_time'];
            $status_text  = 'เข้างานแล้ว';
        } elseif ($rec['log_type'] === 'check_out') {
            $checkout_time = $rec['s_time'];
            $status_text   = 'เลิกงานแล้ว';
        }
    }
    if ($checkout_time !== '--:--') {
        $status_text = 'เลิกงานแล้ว';
    }
} catch (PDOException $e) {}

// 🎯 2. ดึงข้อมูลสลิปเงินเดือนเฉพาะพนักงานคนนี้
$employee_payslips = [];
try {
    $stmt_salary = $pdo->prepare("
        SELECT * FROM salaries 
        WHERE employee_id = :user_id AND is_published = 1
        ORDER BY year DESC, month DESC
    ");
    $stmt_salary->execute(['user_id' => $user_id]);
    $employee_payslips = $stmt_salary->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 🎯 3. ตั้งค่าโควตาวันลาเริ่มต้น & คำนวณวันลา
$leave_quota = ['sick' => ['used' => 0, 'max' => 30], 'business' => ['used' => 0, 'max' => 3], 'vacation' => ['used' => 0, 'max' => 6]];
try {
    $stmt_q = $pdo->prepare("SELECT sick_quota, business_quota, vacation_quota, start_date FROM users WHERE id = :user_id");
    $stmt_q->execute(['user_id' => $user_id]);
    $q_row = $stmt_q->fetch(PDO::FETCH_ASSOC);
    if ($q_row) {
        if (isset($q_row['sick_quota'])) $leave_quota['sick']['max'] = (int)$q_row['sick_quota'];
        if (isset($q_row['business_quota'])) $leave_quota['business']['max'] = (int)$q_row['business_quota'];
        $leave_quota['vacation']['max'] = calculateVacationQuota($q_row['start_date'] ?? '');
    }
} catch (PDOException $e) {}

$leave_type_map = [
    'sick' => 'ลาป่วย', 'business' => 'ลากิจ', 'personal' => 'ลากิจส่วนตัว',
    'vacation' => 'ลาพักร้อน', 'annual' => 'ลาพักร้อนประจำปี', 'maternity' => 'ลาคลอด',
    'ordination' => 'ลาอุปสมบท', 'other' => 'ลาอื่นๆ'
];

$leaves_list_json = [];
try {
    $stmt_l = $pdo->prepare("
        SELECT l.*, (DATEDIFF(l.end_date, l.start_date) + 1) AS total_days
        FROM leave_requests l
        WHERE l.user_id = :user_id AND YEAR(l.created_at) = YEAR(CURRENT_DATE)
        ORDER BY l.created_at DESC LIMIT 10
    ");
    $stmt_l->execute(['user_id' => $user_id]);
    $user_leaves = $stmt_l->fetchAll(PDO::FETCH_ASSOC);

    foreach ($user_leaves as $item) {
        $raw_type = strtolower($item['leave_type'] ?? '');
        $type_th  = $leave_type_map[$raw_type] ?? ($item['leave_type'] ?? 'ลาหยุด');
        $start_ts = strtotime($item['start_date']);
        $end_ts   = strtotime($item['end_date']);
        $start_th = date('d/m/', $start_ts) . (date('Y', $start_ts) + 543);
        $end_th   = date('d/m/', $end_ts) . (date('Y', $end_ts) + 543);
        $date_range = ($item['start_date'] === $item['end_date']) ? $start_th : ($start_th . ' - ' . $end_th);
        $created_ts = strtotime($item['created_at']);
        $sub_date = date('d/m/', $created_ts) . (date('Y', $created_ts) + 543) . ' ' . date('H:i', $created_ts) . ' น.';
        $days_str = (($item['leave_duration'] ?? 'full') === 'hourly') ? ($item['leave_hours'] ?? '0') . ' ชั่วโมง' : max(1, (int)($item['total_days'] ?? 1)) . ' วัน';
        
        $leaves_list_json[] = [
            'id' => $item['id'], 'leaveType' => $type_th, 'dateRange' => $date_range,
            'totalDays' => $days_str, 'subDate' => $sub_date, 'reason' => $item['reason'] ?? 'ไม่ได้ระบุเหตุผล',
            'attachment' => !empty($item['attachment']) ? 'uploads/leaves/' . $item['attachment'] : '',
            'status' => $item['status'] ?? 'pending_head', 'rejectReason' => $item['reject_reason'] ?? '',
            'headName' => $item['head_fullname'] ?? ''
        ];
    }
} catch (PDOException $e) {}

$status_dot_color = 'bg-amber-400';
$status_note      = 'อย่าลืมสแกนเวลางานเมื่อถึงพื้นที่ปฏิบัติงานครับ';
if ($status_text === "เข้างานแล้ว") {
    $status_dot_color = 'bg-emerald-400';
    $status_note      = 'ระบบบันทึกเวลาเข้างานเรียบร้อย ขอให้เป็นวันที่ดีในการทำงานครับ ✨';
} elseif ($status_text === "เลิกงานแล้ว") {
    $status_dot_color = 'bg-blue-400';
    $status_note      = 'ขอบคุณความทุ่มเทในวันนี้ พักผ่อนให้เต็มที่ เดินทางกลับปลอดภัยครับ 🌙';
}

$unread_notifications_count = 0;
try {
    $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmt_notif->execute(['user_id' => $user_id]);
    $unread_notifications_count = (int)$stmt_notif->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lanto Workspace</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1e3a8a">
    <link rel="apple-touch-icon" href="assets/images/Logo.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Lanto Web">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { display: none; }
    </style>
    <script src="assets/js/alerts.js"></script>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen text-slate-800 antialiased flex justify-center w-full">

    <!-- ========================================================================= -->
    <!-- 📱 MOBILE CONTAINER (แสดงเฉพาะจอมือถือ - จะซ่อนเมื่อเปิดบนคอม md:hidden) -->
    <!-- ========================================================================= -->
    <div class="w-full min-h-screen flex flex-col justify-between relative overflow-y-auto block md:hidden bg-slate-50/90 backdrop-blur-xl">
        <div>
            <!-- 🔝 ส่วนหัวดีไซน์เรียบหรูสไตล์แอปชั้นนำ -->
            <div class="bg-gradient-to-b from-blue-900 via-blue-800 to-indigo-700 pt-10 pb-24 px-5 rounded-b-[35px] shadow-lg relative">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <img src="<?php echo $avatar_url; ?>" alt="Profile" class="w-11 h-11 rounded-full object-cover border-2 border-white/40 shadow-sm">
                        <div>
                            <p class="text-white/60 text-[10px] font-light">รหัสพนักงาน: <?php echo htmlspecialchars($employee_code, ENT_QUOTES, 'UTF-8'); ?></p>
                            <h2 class="text-white text-sm font-medium tracking-wide"><?php echo htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                    </div>
                    
                    <!-- 🔔 ปุ่มแจ้งเตือน -->
                    <button type="button" 
                            onclick="LantoAlert.warning('การแจ้งเตือน', '<?php echo $unread_notifications_count > 0 ? "คุณมี ".$unread_notifications_count." รายการแจ้งเตือนใหม่" : "ขณะนี้ยังไม่มีรายการแจ้งเตือนใหม่ครับ"; ?>')" 
                            class="relative w-10 h-10 bg-white/10 backdrop-blur-md border border-white/20 text-white rounded-full flex items-center justify-center shadow-sm hover:bg-white/25 active:scale-90 transition-all cursor-pointer shrink-0">
                        <svg class="w-5 h-5 text-white opacity-95" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"></path></svg>
                        <?php if ($unread_notifications_count > 0): ?>
                        <span class="absolute top-2.5 right-2.5 flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500 border border-white"></span>
                        </span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- แสดงสถานะ -->
                <div class="text-center mt-4 space-y-1">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-bold tracking-widest text-white/90 uppercase bg-white/10 border border-white/15">
                            <span class="relative flex h-1.5 w-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full <?php echo $status_dot_color; ?> opacity-75"></span><span class="relative inline-flex rounded-full h-1.5 w-1.5 <?php echo $status_dot_color; ?>"></span></span>
                            Today Status
                        </span>
                    </div>
                    <h1 class="text-white text-3xl font-extrabold tracking-wide drop-shadow-xs"><?php echo htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-blue-100/70 text-xs pt-1 font-light max-w-[280px] mx-auto leading-relaxed"><?php echo htmlspecialchars($status_note, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <!-- โซนเนื้อหาลอยเด่นทับแถบสีน้ำเงิน -->
            <div class="px-4 -mt-14 space-y-5 relative z-10">
                
                <!-- 📊 แผงรายงานเวลาเช็คอิน-เช็คเอาท์จริงประจำวัน -->
                <div class="bg-white rounded-2xl p-4 shadow-xl shadow-slate-200/60 grid grid-cols-2 gap-3 border border-white">
                    <div class="bg-emerald-50/40 p-3 rounded-xl flex items-center gap-3 border border-emerald-100/40">
                        <div class="w-8 h-8 bg-emerald-600 text-white rounded-lg flex items-center justify-center font-bold text-xs shadow-xs">IN</div>
                        <div><p class="text-slate-400 text-[9px]">เวลาเข้างาน</p><p class="text-slate-800 text-xs font-bold tracking-wide"><?php echo htmlspecialchars($checkin_time, ENT_QUOTES, 'UTF-8'); ?></p></div>
                    </div>
                    <div class="bg-rose-50/40 p-3 rounded-xl flex items-center gap-3 border border-rose-100/40">
                        <div class="w-8 h-8 bg-rose-600 text-white rounded-lg flex items-center justify-center font-bold text-xs shadow-xs">OUT</div>
                        <div><p class="text-slate-400 text-[9px]">เวลาเลิกงาน</p><p class="text-slate-800 text-xs font-bold tracking-wide"><?php echo htmlspecialchars($checkout_time, ENT_QUOTES, 'UTF-8'); ?></p></div>
                    </div>
                </div>

                <!-- 🟡 เมนูระบบงาน (Workforce List) -->
                <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200/60 space-y-4">
                    <h3 class="text-xs font-semibold text-slate-700 tracking-wide px-1">เมนูระบบงาน (Workforce List)</h3>
                    <div class="grid grid-cols-4 gap-y-5 gap-x-1 text-center pt-1">
                        <a href="https://istshipping.co.th/" target="_blank" class="flex flex-col items-center group active:scale-95 transition-transform"><div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 overflow-hidden p-1"><img src="assets/images/LOGO-IST.jpg" class="w-full h-full object-contain rounded-md"></div><span class="text-slate-600 text-[10px] font-medium tracking-tight">Intershipping</span></a>
                        <a href="company_info.php" class="flex flex-col items-center group active:scale-95 transition-transform"><div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2"><img src="assets/images/building.png" class="w-full h-full object-contain"></div><span class="text-slate-600 text-[10px] font-medium tracking-tight">ข้อมูลบริษัท</span></a>
                        <button type="button" onclick='openLeaveStatusModal(<?php echo json_encode($leaves_list_json, JSON_UNESCAPED_UNICODE); ?>)' class="flex flex-col items-center group active:scale-95 transition-transform cursor-pointer"><div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2"><img src="assets/images/health-check.png" class="w-full h-full object-contain"></div><span class="text-slate-600 text-[10px] font-medium tracking-tight">สถานะการลา</span></button>
                        <button type="button" onclick="openPayslipModal()" class="flex flex-col items-center group active:scale-95 transition-transform cursor-pointer"><div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2"><img src="assets/images/payslip.png" class="w-full h-full object-contain"></div><span class="text-slate-600 text-[10px] font-medium tracking-tight">สลิปเงินเดือน</span></button>
                        <a href="messenger_request/index.php" class="flex flex-col items-center group active:scale-95 transition-transform"><div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2"><img src="assets/images/box.png" class="w-full h-full object-contain"></div><span class="text-slate-600 text-[10px] font-medium tracking-tight">Messenger Request</span></a>
                        <a href="#" class="flex flex-col items-center group active:scale-95 transition-transform"><div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2"><img src="assets/images/italy.png" class="w-full h-full object-contain"></div><span class="text-slate-600 text-[10px] font-medium tracking-tight">IT Service</span></a>
                        <a href="car_request/car_index.php" class="flex flex-col items-center group active:scale-95 transition-transform"><div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2"><img src="assets/images/car.png" onerror="this.src='assets/images/box.png'" class="w-full h-full object-contain"></div><span class="text-slate-600 text-[10px] font-medium tracking-tight leading-tight">Car Request</span></a>
                        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'it_support', 'hr'], true)): ?>
                        <a href="admin/dashboard.php" class="flex flex-col items-center group active:scale-95 transition-transform"><div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2"><img src="assets/images/key.png" class="w-full h-full object-contain"></div><span class="text-purple-700 text-[10px] font-bold tracking-tight">จัดการระบบ</span></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 📢 การ์ดแสดงข่าวสารองค์กร Mobile -->
                <div class="bg-white rounded-3xl p-4 shadow-2xs border border-slate-200/80 flex flex-col justify-between relative overflow-hidden">
                    <div>
                        <div class="flex justify-between items-center mb-2.5">
                            <div class="w-8 h-8 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center font-bold text-sm">📢</div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100">ข่าวสารองค์กร</span>
                        </div>
                        
                        <?php if (empty($news_list)): ?>
                            <div class="py-4 text-center text-slate-400 text-xs">
                                <p class="font-bold text-slate-700">ข่าวสารและประกาศองค์กร</p>
                                <p class="mt-0.5">ยังไม่มีประกาศข่าวสารในขณะนี้</p>
                            </div>
                        <?php else: ?>
                            <div class="relative overflow-hidden w-full">
                                <div id="newsSliderMobile" class="flex transition-transform duration-300 ease-out w-full">
                                    <?php 
                                        $news_json_all = htmlspecialchars(json_encode($news_list, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                        foreach ($news_list as $index => $news): 
                                            $news_json_single = htmlspecialchars(json_encode($news, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="w-full shrink-0 px-0.5 space-y-2 news-slide-mobile cursor-pointer group" onclick='openNewsDetailModal(<?php echo $news_json_single; ?>, <?php echo $news_json_all; ?>)'>
                                        <?php if (!empty($news['image'])): ?>
                                        <div class="w-full h-28 rounded-xl overflow-hidden bg-slate-900/5 border border-slate-200 shadow-xs relative flex items-center justify-center">
                                            <img src="uploads/news/<?php echo htmlspecialchars($news['image']); ?>" class="max-h-full max-w-full object-contain">
                                        </div>
                                        <?php endif; ?>
                                        <div class="space-y-0.5">
                                            <h3 class="font-extrabold text-slate-900 text-xs truncate"><?php echo htmlspecialchars($news['title']); ?></h3>
                                            <p class="text-[11px] text-slate-600 line-clamp-1 leading-relaxed"><?php echo htmlspecialchars($news['content']); ?></p>
                                            <p class="text-[9px] text-slate-400 font-medium">เผยแพร่: <?php echo date('d/m/Y H:i', strtotime($news['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="flex justify-center items-center gap-1.5 mt-2.5">
                                <?php foreach ($news_list as $index => $news): ?>
                                <button type="button" class="news-dot-mobile h-1.5 rounded-full transition-all bg-slate-300 <?php echo $index === 0 ? 'w-3.5 bg-blue-600' : 'w-1.5'; ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (in_array($user_role ?? 'employee', ['admin', 'it_support', 'hr'], true)): ?>
                    <div class="pt-2.5 border-t border-slate-100 flex justify-end mt-2.5">
                        <button type="button" onclick="openPostNewsModal()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer active:scale-95 flex items-center gap-1">
                            <span>✍️</span> ลงประกาศข่าวสาร
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 🛠️ กล่องอวกาศ (Spacer) ขนาดใหญ่ดันการ์ดข่าวให้เลื่อนพ้น Navbar และปุ่มสแกน -->
                <div class="w-full h-36 shrink-0"></div>

            </div>
        </div>

        <!-- แถบปุ่มเมนูส่วนกลางท้ายไฟล์ -->
        <?php include 'includes/navbar.php'; ?>
    </div>



    <!-- ========================================================================= -->
    <!-- 💻 PC CONTAINER (แสดงเฉพาะจอคอมพิวเตอร์ - จะซ่อนเมื่อเปิดบนมือถือ hidden md:flex) -->
    <!-- ========================================================================= -->
    <div class="hidden md:flex flex-col min-h-screen w-full bg-[#f4f6fa]">
        <?php 
        $page_title    = 'Lanto Workspace';
        $page_subtitle = 'Enterprise Workforce System';
        $show_back     = false;
        include_once 'includes/header.php'; 
        ?>

        <main class="p-6 lg:p-10 space-y-6 max-w-7xl mx-auto w-full">
            <!-- 🖥️ บล็อกรายงานเวลาและข่าวสาร (PC Grid ซ้าย 60% / ขวา 40%) -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-stretch">
                
                <!-- 📊 การ์ดสถานะฝั่งซ้าย (60%) -->
                <div class="lg:col-span-3 bg-gradient-to-r from-blue-900 via-blue-800 to-indigo-700 rounded-3xl p-6 lg:p-8 text-white shadow-lg flex flex-col justify-between relative overflow-hidden h-full">
                    <div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase bg-white/10 border border-white/20">
                            <span class="h-2 w-2 rounded-full <?php echo $status_dot_color; ?>"></span> Today Status
                        </span>
                        <h1 class="text-2xl lg:text-3xl font-extrabold mt-3 tracking-wide"><?php echo htmlspecialchars($status_text); ?></h1>
                        <p class="text-blue-100/80 text-xs lg:text-sm mt-1 font-light"><?php echo htmlspecialchars($status_note); ?></p>
                    </div>
                    
                    <div class="flex gap-4 mt-6">
                        <div class="bg-white/10 backdrop-blur-md px-4 py-3 rounded-2xl border border-white/15 flex items-center gap-3">
                            <div class="w-9 h-9 bg-emerald-500 text-white rounded-xl flex items-center justify-center font-bold text-xs shadow-xs">IN</div>
                            <div><p class="text-[10px] text-blue-200">เวลาเข้างาน</p><p class="text-sm lg:text-base font-bold"><?php echo htmlspecialchars($checkin_time); ?></p></div>
                        </div>
                        <div class="bg-white/10 backdrop-blur-md px-4 py-3 rounded-2xl border border-white/15 flex items-center gap-3">
                            <div class="w-9 h-9 bg-rose-500 text-white rounded-xl flex items-center justify-center font-bold text-xs shadow-xs">OUT</div>
                            <div><p class="text-[10px] text-blue-200">เวลาเลิกงาน</p><p class="text-sm lg:text-base font-bold"><?php echo htmlspecialchars($checkout_time); ?></p></div>
                        </div>
                    </div>
                </div>

                <!-- 📢 การ์ดข่าวสารฝั่งขวา PC (40%) -->
                <div class="lg:col-span-2 bg-white rounded-3xl p-5 shadow-2xs border border-slate-200/80 flex flex-col justify-between relative overflow-hidden h-full">
                    <div>
                        <div class="flex justify-between items-center mb-3">
                            <div class="w-9 h-9 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center font-bold text-base">📢</div>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-100">ข่าวสารองค์กร</span>
                        </div>
                        
                        <?php if (empty($news_list)): ?>
                            <div class="py-6 text-center text-slate-400 text-xs">
                                <p class="font-bold text-slate-700 text-sm">ข่าวสารและประกาศองค์กร</p>
                                <p class="mt-1">ยังไม่มีประกาศข่าวสารในขณะนี้</p>
                            </div>
                        <?php else: ?>
                            <div class="relative overflow-hidden w-full">
                                <div id="newsSliderPC" class="flex transition-transform duration-300 ease-out w-full">
                                    <?php 
                                        $news_json_all = htmlspecialchars(json_encode($news_list, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                        foreach ($news_list as $index => $news): 
                                            $news_json_single = htmlspecialchars(json_encode($news, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="w-full shrink-0 px-0.5 space-y-2.5 news-slide-pc cursor-pointer group" onclick='openNewsDetailModal(<?php echo $news_json_single; ?>, <?php echo $news_json_all; ?>)'>
                                        <?php if (!empty($news['image'])): ?>
                                        <div class="w-full h-36 rounded-2xl overflow-hidden bg-slate-900/5 border border-slate-200 shadow-xs relative group-hover:border-blue-400 transition-all flex items-center justify-center">
                                            <img src="uploads/news/<?php echo htmlspecialchars($news['image']); ?>" class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <?php endif; ?>
                                        <div class="space-y-0.5">
                                            <h3 class="font-extrabold text-slate-900 text-xs group-hover:text-blue-600 transition-colors truncate"><?php echo htmlspecialchars($news['title']); ?></h3>
                                            <p class="text-[11px] text-slate-600 line-clamp-1 leading-relaxed"><?php echo htmlspecialchars($news['content']); ?></p>
                                            <p class="text-[9.5px] text-slate-400 font-medium">เผยแพร่: <?php echo date('d/m/Y H:i', strtotime($news['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="flex justify-center items-center gap-1.5 mt-3">
                                <?php foreach ($news_list as $index => $news): ?>
                                <button type="button" class="news-dot-pc h-1.5 rounded-full transition-all bg-slate-300 <?php echo $index === 0 ? 'w-4 bg-blue-600' : 'w-1.5'; ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (in_array($user_role, ['admin', 'it_support', 'hr'], true)): ?>
                    <div class="pt-3 border-t border-slate-100 flex justify-end mt-3">
                        <button type="button" onclick="openPostNewsModal()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer active:scale-95 flex items-center gap-1.5">
                            <span>✍️</span> ลงประกาศข่าวสาร
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- เมนูระบบงาน PC -->
            <div class="bg-white rounded-3xl p-6 shadow-2xs border border-slate-200/80 space-y-4">
                <h3 class="text-sm font-bold text-slate-800">เมนูระบบงาน (Workforce List)</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="https://istshipping.co.th/" target="_blank" class="p-4 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-2xl flex items-center gap-4 transition-all"><div class="w-12 h-12 bg-white border rounded-xl flex items-center justify-center p-1.5"><img src="assets/images/LOGO-IST.jpg" class="w-full h-full object-contain rounded-md"></div><div><p class="text-xs font-bold">Intershipping</p><p class="text-[10px] text-slate-400">ระบบลงเวลาหลัก</p></div></a>
                    <a href="company_info.php" class="p-4 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-2xl flex items-center gap-4 transition-all"><div class="w-12 h-12 bg-white border rounded-xl flex items-center justify-center p-2.5"><img src="assets/images/building.png" class="w-full h-full object-contain"></div><div><p class="text-xs font-bold">ข้อมูลบริษัท</p><p class="text-[10px] text-slate-400">โครงสร้างและระเบียบ</p></div></a>
                    <button type="button" onclick='openLeaveStatusModal(<?php echo json_encode($leaves_list_json, JSON_UNESCAPED_UNICODE); ?>)' class="p-4 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-2xl flex items-center gap-4 transition-all text-left"><div class="w-12 h-12 bg-white border rounded-xl flex items-center justify-center p-2.5"><img src="assets/images/health-check.png" class="w-full h-full object-contain"></div><div><p class="text-xs font-bold">สถานะการลา</p><p class="text-[10px] text-slate-400">ยื่นและเช็กประวัติลา</p></div></button>
                    <button type="button" onclick="openPayslipModal()" class="p-4 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-2xl flex items-center gap-4 transition-all text-left"><div class="w-12 h-12 bg-white border rounded-xl flex items-center justify-center p-2.5"><img src="assets/images/payslip.png" class="w-full h-full object-contain"></div><div><p class="text-xs font-bold">สลิปเงินเดือน</p><p class="text-[10px] text-slate-400">ดาวน์โหลด PDF</p></div></button>
                    <a href="messenger_request/index.php" class="p-4 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-2xl flex items-center gap-4 transition-all"><div class="w-12 h-12 bg-white border rounded-xl flex items-center justify-center p-2.5"><img src="assets/images/box.png" class="w-full h-full object-contain"></div><div><p class="text-xs font-bold">Messenger Request</p><p class="text-[10px] text-slate-400">บริการรับส่งเอกสาร</p></div></a>
                    <a href="#" class="p-4 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-2xl flex items-center gap-4 transition-all"><div class="w-12 h-12 bg-white border rounded-xl flex items-center justify-center p-2.5"><img src="assets/images/italy.png" class="w-full h-full object-contain"></div><div><p class="text-xs font-bold">IT Service</p><p class="text-[10px] text-slate-400">แจ้งปัญหาไอที</p></div></a>
                    <a href="car_request/car_index.php" class="p-4 bg-slate-50 hover:bg-blue-50 border border-slate-200 rounded-2xl flex items-center gap-4 transition-all"><div class="w-12 h-12 bg-white border rounded-xl flex items-center justify-center p-2.5"><img src="assets/images/car.png" onerror="this.src='assets/images/box.png'" class="w-full h-full object-contain"></div><div><p class="text-xs font-bold">Car Request</p><p class="text-[10px] text-slate-400">จองรถองค์กร</p></div></a>
                    <?php if (in_array($user_role, ['admin', 'it_support', 'hr'], true)): ?>
                    <a href="admin/dashboard.php" class="p-4 bg-purple-50 border border-purple-200 rounded-2xl flex items-center gap-4 transition-all"><div class="w-12 h-12 bg-white border border-purple-200 rounded-xl flex items-center justify-center p-2.5"><img src="assets/images/key.png" class="w-full h-full object-contain"></div><div><p class="text-xs font-bold text-purple-900">จัดการระบบ</p><p class="text-[10px] text-purple-600">เมนูผู้ดูแลระบบ</p></div></a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- เมนูหลักพนักงาน PC -->
            <div class="bg-white rounded-3xl p-6 shadow-2xs border border-slate-200/80 space-y-4">
                <h3 class="text-sm font-bold text-slate-800">เมนูหลักพนักงาน (Main Employee Services)</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="employee/scan.php" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex items-center gap-4 hover:bg-blue-50 transition-all"><div class="w-12 h-12 bg-gradient-to-tr from-blue-700 to-indigo-600 text-white rounded-xl flex items-center justify-center"><img src="assets/images/face-id.png" class="w-9 h-9 object-contain brightness-0 invert"></div><div><p class="text-xs font-bold">สแกนเข้า-ออกงาน</p></div></a>
                    <a href="employee/history.php" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex items-center gap-4 hover:bg-blue-50 transition-all"><div class="w-12 h-12 bg-amber-500 text-white rounded-xl flex items-center justify-center"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v5h5M12 7v5l3.5 2"/></svg></div><div><p class="text-xs font-bold">ประวัติการลงเวลางาน</p></div></a>
                    <a href="employee/leave.php" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex items-center gap-4 hover:bg-blue-50 transition-all"><div class="w-12 h-12 bg-emerald-500 text-white rounded-xl flex items-center justify-center"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div><div><p class="text-xs font-bold">ยื่นใบแจ้งลา</p></div></a>
                    <a href="employee/profile.php" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex items-center gap-4 hover:bg-blue-50 transition-all"><div class="w-12 h-12 bg-indigo-500 text-white rounded-xl flex items-center justify-center"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div><div><p class="text-xs font-bold">ข้อมูลส่วนตัว / บัตร</p></div></a>
                </div>
            </div>
        </main>
    </div>

    <!-- 📌 MODAL: สลิปเงินเดือน (ใช้ร่วมกัน) -->
    <div id="payslipModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-sm md:max-w-xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-auto">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2"><span>💵</span> สลิปเงินเดือนของคุณ</h3>
                <button type="button" onclick="closePayslipModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
            </div>

            <div class="space-y-1 relative z-30">
                <label class="text-[11px] font-bold text-slate-500 px-1">เลือกปี พ.ศ.</label>
                <?php
                    $current_y = (int)date('Y');
                    $mobile_years_opts = [];
                    for ($y = $current_y; $y >= $current_y - 3; $y--) {
                        $mobile_years_opts[] = ['id' => (string)($y + 543), 'name' => (string)($y + 543)];
                    }
                    renderRoundedDropdown('mobile_slip_year', 'slip_year', (string)($current_y + 543), $mobile_years_opts, (string)($current_y + 543));
                ?>
            </div>

            <div class="space-y-2.5 max-h-[45vh] overflow-y-auto pr-1 text-xs pt-1" id="payslipListContainer">
                <?php if (empty($employee_payslips)): ?>
                    <div class="p-8 text-center text-slate-400 font-light bg-slate-50 rounded-2xl border border-slate-100">🚫 ยังไม่มีสลิปเงินเดือนในระบบ</div>
                <?php else: ?>
                    <?php foreach ($employee_payslips as $slip): 
                        $month_names = ['01'=>'มกราคม', '02'=>'กุมภาพันธ์', '03'=>'มีนาคม', '04'=>'เมษายน', '05'=>'พฤษภาคม', '06'=>'มิถุนายน', '07'=>'กรกฎาคม', '08'=>'สิงหาคม', '09'=>'กันยายน', '10'=>'ตุลาคม', '11'=>'พฤศจิกายน', '12'=>'ธันวาคม'];
                        $m_text = $month_names[$slip['month']] ?? $slip['month'];
                        $y_thai = (int)$slip['year'] + 543;
                        $pdf_url = !empty($slip['pdf_file']) ? 'uploads/payslips/' . $slip['pdf_file'] : '#';
                    ?>
                    <div class="slip-item bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 flex items-center justify-between gap-2" data-year="<?php echo $y_thai; ?>">
                        <div>
                            <p class="font-bold text-slate-800">งวดประจำเดือน <?php echo $m_text . ' ' . $y_thai; ?></p>
                            <p class="text-[10px] text-emerald-600 font-extrabold mt-0.5">สถานะ: พร้อมดาวน์โหลด</p>
                        </div>
                        <a href="<?php echo $pdf_url; ?>" target="_blank" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors cursor-pointer flex items-center gap-1 shrink-0 shadow-sm text-xs">
                            <span>📄</span> PDF
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ดึงไฟล์ Modal อื่นๆ (ใช้ร่วมกัน) -->
    <?php include_once 'includes/modal_leave_status.php'; ?>
    <?php include_once 'modal_news.php'; ?>

    <!-- สคริปต์สไลด์และฟังก์ชัน (ใช้ร่วมกัน) -->
    <script>
        // Setup slider แยกตาม id ของ mobile และ pc
        function setupSlider(sliderId, slideClass, dotClass, dotWidthActive, dotWidthInactive) {
            let index = 0;
            const slides = document.querySelectorAll('.' + slideClass);
            const slider = document.getElementById(sliderId);
            const dots = document.querySelectorAll('.' + dotClass);
            
            if (!slides.length || !slider) return;

            setInterval(() => {
                index = (index + 1) % slides.length;
                slider.style.transform = `translateX(-${index * 100}%)`;
                
                dots.forEach((dot, idx) => {
                    if (idx === index) {
                        dot.classList.remove('bg-slate-300', dotWidthInactive);
                        dot.classList.add('bg-blue-600', dotWidthActive);
                    } else {
                        dot.classList.remove('bg-blue-600', dotWidthActive);
                        dot.classList.add('bg-slate-300', dotWidthInactive);
                    }
                });
            }, 5000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            setupSlider('newsSliderMobile', 'news-slide-mobile', 'news-dot-mobile', 'w-3.5', 'w-1.5');
            setupSlider('newsSliderPC', 'news-slide-pc', 'news-dot-pc', 'w-4', 'w-1.5');
        });

        // สคริปต์ Payslip
        function openPayslipModal() {
            document.getElementById('payslipModal').classList.remove('hidden');
            const currentYearVal = document.getElementById('mobile_slip_year').value;
            filterSlipsByYear(currentYearVal);
        }
        function closePayslipModal() {
            document.getElementById('payslipModal').classList.add('hidden');
        }
        function filterSlipsByYear(selectedYear) {
            const items = document.querySelectorAll('.slip-item');
            items.forEach(item => {
                const itemYear = item.getAttribute('data-year');
                if (itemYear === selectedYear) { item.style.display = 'flex'; } 
                else { item.style.display = 'none'; }
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            const yearInput = document.getElementById('mobile_slip_year');
            if (yearInput) {
                const observer = new MutationObserver(function(mutations) {
                    filterSlipsByYear(yearInput.value);
                });
                observer.observe(yearInput, { attributes: true, attributeFilter: ['value'] });
            }
        });
    </script>
</body>
</html>