<?php
session_start();
require_once 'config/db.php'; // เชื่อมต่อฐานข้อมูลหลัก
require_once 'includes/rounded_dropdown.php'; // 🎯 เพิ่มเรียกใช้งานไฟล์ดรอปดาวน์ขอบมน
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

// 🎯 ดึงรูปภาพล่าสุดจากฐานข้อมูลตรงๆ ป้องกันค่าใน Session ตกหล่น
if (isset($pdo) && $user_id) {
    try {
        $stmt_u = $pdo->prepare("SELECT profile_image, first_name, last_name FROM users WHERE id = :id LIMIT 1");
        $stmt_u->execute(['id' => $user_id]);
        $u_row = $stmt_u->fetch(PDO::FETCH_ASSOC);
        if ($u_row && !empty($u_row['profile_image'])) {
            $profile_image = $u_row['profile_image'];
        }
    } catch (PDOException $e) {}
}

$news_list = [];
try {
    $stmt_news = $pdo->query("SELECT * FROM news ORDER BY id DESC LIMIT 5");
    $news_list = $stmt_news->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $news_list = [];
}
$clean_img = !empty($profile_image) ? basename(trim($profile_image)) : '';
$mobile_file_path = 'uploads/profiles/' . $clean_img;
$mobile_v = (!empty($clean_img) && file_exists($mobile_file_path)) ? filemtime($mobile_file_path) : '1';
$avatar_url = !empty($clean_img) ? $mobile_file_path . '?v=' . $mobile_v : '';

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
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

// 🎯 2. ดึงข้อมูลสลิปเงินเดือนเฉพาะพนักงานคนนี้จากฐานข้อมูล
$employee_payslips = [];
try {
    $stmt_salary = $pdo->prepare("
        SELECT * FROM salaries 
        WHERE employee_id = :user_id AND is_published = 1
        ORDER BY year DESC, month DESC
    ");
    $stmt_salary->execute(['user_id' => $user_id]);
    $employee_payslips = $stmt_salary->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ป้องกัน Error กรณีตารางยังไม่พร้อม
}

// 🎯 3. ตั้งค่าโควตาวันลาเริ่มต้น & คำนวณวันลา
$leave_quota = [
    'sick'     => ['used' => 0, 'max' => 30],
    'business' => ['used' => 0, 'max' => 3],
    'vacation' => ['used' => 0, 'max' => 6]
];

try {
    $stmt_q = $pdo->prepare("SELECT sick_quota, business_quota, vacation_quota, start_date FROM users WHERE id = :user_id");
    $stmt_q->execute(['user_id' => $user_id]);
    $q_row = $stmt_q->fetch(PDO::FETCH_ASSOC);

    if ($q_row) {
        if (isset($q_row['sick_quota']))     $leave_quota['sick']['max']     = (int)$q_row['sick_quota'];
        if (isset($q_row['business_quota'])) $leave_quota['business']['max'] = (int)$q_row['business_quota'];
        $leave_quota['vacation']['max'] = calculateVacationQuota($q_row['start_date'] ?? '');
    }
} catch (PDOException $e) {}

try {
    $stmt_sum = $pdo->prepare("
        SELECT leave_type, SUM(DATEDIFF(end_date, start_date) + 1) as days_used
        FROM leave_requests
        WHERE user_id = :user_id 
          AND status = 'approved'
          AND YEAR(created_at) = YEAR(CURRENT_DATE)
        GROUP BY leave_type
    ");
    $stmt_sum->execute(['user_id' => $user_id]);
    $sum_records = $stmt_sum->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sum_records as $sr) {
        $type = strtolower($sr['leave_type']);
        $days = (int)$sr['days_used'];
        if ($type === 'personal') $type = 'business';
        if (isset($leave_quota[$type])) {
            $leave_quota[$type]['used'] += $days;
        }
    }
} catch (PDOException $e) {}

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

$leaves_list_json = [];
try {
    $stmt_l = $pdo->prepare("
        SELECT l.*, 
               (DATEDIFF(l.end_date, l.start_date) + 1) AS total_days
        FROM leave_requests l
        WHERE l.user_id = :user_id
          AND YEAR(l.created_at) = YEAR(CURRENT_DATE)
        ORDER BY l.created_at DESC 
        LIMIT 10
    ");
    $stmt_l->execute(['user_id' => $user_id]);
    $user_leaves = $stmt_l->fetchAll(PDO::FETCH_ASSOC);

    foreach ($user_leaves as $index => $item) {
        $raw_type = strtolower($item['leave_type'] ?? '');
        $type_th  = $leave_type_map[$raw_type] ?? ($item['leave_type'] ?? 'ลาหยุด');

        $start_ts = strtotime($item['start_date']);
        $end_ts   = strtotime($item['end_date']);
        $start_th = date('d/m/', $start_ts) . (date('Y', $start_ts) + 543);
        $end_th   = date('d/m/', $end_ts) . (date('Y', $end_ts) + 543);
        $date_range = ($item['start_date'] === $item['end_date']) ? $start_th : ($start_th . ' - ' . $end_th);

        $created_ts = strtotime($item['created_at']);
        $sub_date = date('d/m/', $created_ts) . (date('Y', $created_ts) + 543) . ' ' . date('H:i', $created_ts) . ' น.';

        if (($item['leave_duration'] ?? 'full') === 'hourly') {
            $days_str = ($item['leave_hours'] ?? '0') . ' ชั่วโมง';
        } else {
            $days_str = max(1, (int)($item['total_days'] ?? 1)) . ' วัน';
        }

        $attachment_url = !empty($item['attachment']) ? 'uploads/leaves/' . $item['attachment'] : '';

        $status_symbol = '⏳';
        if ($item['status'] === 'approved') $status_symbol = '✅';
        elseif ($item['status'] === 'rejected') $status_symbol = '❌';
        elseif ($item['status'] === 'pending_hr') $status_symbol = '🔵';

        $leaves_list_json[] = [
            'id'           => $item['id'],
            'leaveType'    => $type_th,
            'dateRange'    => $date_range,
            'totalDays'    => $days_str,
            'subDate'      => $sub_date,
            'reason'       => $item['reason'] ?? 'ไม่ได้ระบุเหตุผล',
            'attachment'   => $attachment_url,
            'status'       => $item['status'] ?? 'pending_head',
            'rejectReason' => $item['reject_reason'] ?? '',
            'headName'     => $item['head_fullname'] ?? ''
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

// 🎯 เช็กจำนวนการแจ้งเตือนที่ยังไม่ได้อ่าน
$unread_notifications_count = 0;

try {
    // สมมุติตารางชื่อ notifications (ถ้าไม่มีตาราง ระบบจะ catch แล้วตั้งค่าเป็น 0 ให้อัตโนมัติ)
    $stmt_notif = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = :user_id AND is_read = 0
    ");
    $stmt_notif->execute(['user_id' => $user_id]);
    $unread_notifications_count = (int)$stmt_notif->fetchColumn();
} catch (PDOException $e) {
    // หากยังไม่มีตาราง notifications ให้ตั้งค่าเป็น 0 ไว้ก่อน
    $unread_notifications_count = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ระบบลงเวลางานพนักงาน (Mobile) - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- 📱 ตั้งค่า PWA (รองรับทั้ง Android และ iOS iPhone) -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1e3a8a">

    <!-- 🍎 สำหรับ iPhone / Safari (แก้ชื่อแอปและรูปไอคอน) -->
    <link rel="apple-touch-icon" href="assets/images/Logo.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Lanto Web">
    <style>
        html { 
            font-size: 20px; /* ปรับเป็น 17px - 18px ตามความเหมาะสม */
        }
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { display: none; }

    </style>
    <script>
        // ป้องกันลูปดีดกลับเมื่อมีสกรอลล์บาร์ หรือเมื่อกดสลับโหมดดูบนมือถือ
        const urlParams = new URLSearchParams(window.location.search);
        if (window.matchMedia("(min-width: 768px)").matches && urlParams.get('view') !== 'mobile') {
            window.location.replace('index_pc.php');
        }
    </script>
    <script src="assets/js/alerts.js"></script>
    <script>
    // ลงทะเบียน Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('PWA Ready!'))
                .catch(err => console.log('PWA Error', err));
        });
    }
    </script>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex justify-center text-slate-800 antialiased p-0 md:py-6">

    <!-- 📱 Main Mobile Container (ขยายเต็มจอมือถืออัตโนมัติ / บนคอมเป็นกรอบสมาร์ตโฟน) -->
    <div class="w-full min-h-screen bg-slate-50 flex flex-col justify-between relative overflow-y-auto pb-28 border-0 shadow-none
        md:max-w-md md:min-h-[812px] md:h-auto md:rounded-[40px] md:shadow-2xl md:border md:border-white/60">
        
        <div>
            <!-- 🔝 ส่วนหัวดีไซน์เรียบหรูสไตล์แอปชั้นนำ -->
            <div class="bg-gradient-to-b from-blue-900 via-blue-800 to-indigo-700 pt-10 pb-24 px-5 rounded-b-[35px] shadow-lg relative">
                
                <!-- จัดการเรียงเป็น justify-end เพื่อดันกระดิ่งและโปรไฟล์ไปชิดขวาอย่างเดียว -->
                <div class="flex justify-end items-center mb-6">
                    
                    <!-- 👉 ฝั่งขวา: ปุ่มแจ้งเตือน + ปุ่มโปรไฟล์ Dropdown -->
                    <div class="flex items-center gap-2.5 shrink-0">
                        
                        <!-- 🔔 ปุ่มแจ้งเตือน -->
                        <button type="button" 
                                onclick="LantoAlert.warning('การแจ้งเตือน', '<?php echo $unread_notifications_count > 0 ? "คุณมี ".$unread_notifications_count." รายการแจ้งเตือนใหม่" : "ขณะนี้ยังไม่มีรายการแจ้งเตือนใหม่ครับ"; ?>')" 
                                class="relative w-9 h-9 bg-white/10 backdrop-blur-md border border-white/20 text-white rounded-full flex items-center justify-center shadow-sm hover:bg-white/25 active:scale-90 transition-all cursor-pointer">
                            
                            <svg class="w-4.5 h-4.5 text-white opacity-95" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"></path>
                            </svg>

                            <?php if ($unread_notifications_count > 0): ?>
                            <span class="absolute top-2 right-2 flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500 border border-white"></span>
                            </span>
                            <?php endif; ?>

                        </button>

                        <!-- 👤 Profile Dropdown Container -->
                        <div class="relative inline-block" id="mobile-profile-dropdown-container">
                            <button type="button" onclick="window.toggleMobileProfileDropdown(event)" class="relative group active:scale-90 transition-transform flex items-center cursor-pointer">
                                <div class="w-9 h-9 rounded-full bg-white/20 border-2 border-white/40 shadow-sm overflow-hidden flex items-center justify-center font-bold text-white text-sm shrink-0">
                                    <?php if (!empty($avatar_url)): ?>
                                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
                                            class="w-full h-full object-cover" 
                                            alt=""
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <span style="display:none;"><?php echo mb_substr($fullname, 0, 1, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span><?php echo mb_substr($fullname, 0, 1, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="mobile-profile-menu" class="hidden absolute right-0 top-full mt-3 w-52 bg-white border border-slate-200/80 rounded-2xl shadow-xl z-50 p-1.5 space-y-0.5 text-slate-800">
                                <div class="absolute -top-[7px] right-3.5 w-3.5 h-3.5 bg-white border-t border-l border-slate-200/80 rotate-45 z-0"></div>
                                
                                <a href="index_mobile.php" class="relative z-10 flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 hover:bg-purple-50 hover:text-purple-600 transition-colors">
                                    <span>🏢</span> หน้าหลักพนักงาน
                                </a>
                                
                                <a href="employee/profile.php" class="relative z-10 flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                                    <span>👤</span> ข้อมูลส่วนตัว / บัตร
                                </a>

                                <a href="employee/history.php" class="relative z-10 flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                                    <span>📋</span> ประวัติการลงเวลางาน
                                </a>

                                <div class="border-t border-slate-100 my-1 relative z-10"></div>
                                
                                <a href="logout.php" class="relative z-10 flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors">
                                    <span>🚪</span> ออกจากระบบ
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- แสดงสถานะ -->
                <div class="text-center mt-4 space-y-1">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-bold tracking-widest text-white/90 uppercase bg-white/10 border border-white/15">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full <?php echo $status_dot_color; ?> opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 <?php echo $status_dot_color; ?>"></span>
                            </span>
                            Today Status
                        </span>
                    </div>
                    <h1 class="text-white text-3xl font-extrabold tracking-wide drop-shadow-xs"><?php echo htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-blue-100/70 text-xs pt-1 font-light max-w-[280px] mx-auto leading-relaxed">
                        <?php echo htmlspecialchars($status_note, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
            </div>

            <!-- โซนเนื้อหาลอยเด่นทับแถบสีน้ำเงิน -->
            <div class="px-4 -mt-14 space-y-5 relative z-10">
                
                <!-- 📊 แผงรายงานเวลาเช็คอิน-เช็คเอาท์จริงประจำวัน -->
                <div class="bg-white rounded-2xl p-4 shadow-xl shadow-slate-200/60 grid grid-cols-2 gap-3 border border-white">
                    <div class="bg-emerald-50/40 p-3 rounded-xl flex items-center gap-3 border border-emerald-100/40">
                        <div class="w-8 h-8 bg-emerald-600 text-white rounded-lg flex items-center justify-center font-bold text-xs shadow-xs">IN</div>
                        <div>
                            <p class="text-slate-400 text-[9px]">เวลาเข้างาน</p>
                            <p class="text-slate-800 text-xs font-bold tracking-wide"><?php echo htmlspecialchars($checkin_time, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <div class="bg-rose-50/40 p-3 rounded-xl flex items-center gap-3 border border-rose-100/40">
                        <div class="w-8 h-8 bg-rose-600 text-white rounded-lg flex items-center justify-center font-bold text-xs shadow-xs">OUT</div>
                        <div>
                            <p class="text-slate-400 text-[9px]">เวลาเลิกงาน</p>
                            <p class="text-slate-800 text-xs font-bold tracking-wide"><?php echo htmlspecialchars($checkout_time, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- 🟡 เมนูระบบงาน (Workforce List) -->
                <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200/60 space-y-4">
                    <h3 class="text-xs font-semibold text-slate-700 tracking-wide px-1">เมนูระบบงาน (Workforce List)</h3>
                    
                    <div class="grid grid-cols-4 gap-y-5 gap-x-1 text-center pt-1">
                        
                        <!-- 1. ลงเวลางาน -->
                        <a href="https://istshipping.co.th/" target="_blank" class="flex flex-col items-center group active:scale-95 transition-transform">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 overflow-hidden p-1">
                                <img src="assets/images/LOGO-IST.jpg" alt="IST Logo" class="w-full h-full object-contain rounded-md">
                            </div>
                            <span class="text-slate-600 text-[10px] font-medium tracking-tight">Intershipping</span>
                        </a>

                        <!-- 2. ข้อมูลบริษัท -->
                        <a href="company_info.php" class="flex flex-col items-center group active:scale-95 transition-transform">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2">
                                <img src="assets/images/building.png" alt="Company" class="w-full h-full object-contain">
                            </div>
                            <span class="text-slate-600 text-[10px] font-medium tracking-tight">ข้อมูลบริษัท</span>
                        </a>

                        <!-- 3. สถานะการลา -->
                        <button type="button" onclick='openLeaveStatusModal(<?php echo json_encode($leaves_list_json, JSON_UNESCAPED_UNICODE); ?>)' class="flex flex-col items-center group active:scale-95 transition-transform cursor-pointer">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2">
                                <img src="assets/images/health-check.png" alt="Leave" class="w-full h-full object-contain">
                            </div>
                            <span class="text-slate-600 text-[10px] font-medium tracking-tight">สถานะการลา</span>
                        </button>

                        <!-- 4. สลิปเงินเดือน -->
                        <button type="button" onclick="openPayslipModal()" class="flex flex-col items-center group active:scale-95 transition-transform cursor-pointer">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2">
                                <img src="assets/images/payslip.png" alt="Payslip" class="w-full h-full object-contain">
                            </div>
                            <span class="text-slate-600 text-[10px] font-medium tracking-tight">สลิปเงินเดือน</span>
                        </button>

                        <!-- 5. จองแมส -->
                        <a href="messenger_request/msg_index.php?view=mobile" class="flex flex-col items-center group active:scale-95 transition-transform">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2">
                                <img src="assets/images/box.png" alt="Messenger" class="w-full h-full object-contain">
                            </div>
                            <span class="text-slate-600 text-[10px] font-medium tracking-tight">Messenger Request</span>
                        </a>

                        <!-- 6. จองรถองค์กร (แทนที่จองยืมของไอทีเดิม) -->
                        <a href="car_request/car_index.php" class="flex flex-col items-center group active:scale-95 transition-transform">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2">
                                <img src="assets/images/car.png" alt="Car Booking" class="w-full h-full object-contain" onerror="this.src='assets/images/box.png'">
                            </div>
                            <span class="text-slate-600 text-[10px] font-medium tracking-tight leading-tight">Car Request</span>
                        </a>

                        <!-- 7. แจ้งปัญหาไอที -->
                        <a href="#" class="flex flex-col items-center group active:scale-95 transition-transform">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2">
                                <img src="assets/images/italy.png" alt="IT Support" class="w-full h-full object-contain">
                            </div>
                            <span class="text-slate-600 text-[10px] font-medium tracking-tight">IT Service</span>
                        </a>

                        <!-- 8. จัดการระบบ -->
                        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'it_support', 'hr'], true)): ?>
                        <a href="admin/dashboard.php" class="flex flex-col items-center group active:scale-95 transition-transform">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-200/60 rounded-2xl flex items-center justify-center shadow-2xs mb-1.5 p-2">
                                <img src="assets/images/key.png" alt="Admin Key" class="w-full h-full object-contain">
                            </div>
                            <span class="text-purple-700 text-[10px] font-bold tracking-tight">จัดการระบบ</span>
                        </a>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- 📢 การ์ดแสดงข่าวสารองค์กร (ดีไซน์รองรับมือถือ พร้อมสไลด์และคลิกดูรายละเอียด) -->
                <div class="bg-white rounded-3xl p-5 shadow-2xs border border-slate-200/80 flex flex-col justify-between relative overflow-hidden">
                    <div>
                        <div class="flex items-center justify-center gap-2 mb-3.5 pb-2.5 border-b border-slate-100">
                            <div class="w-7 h-7 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shrink-0">
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                                </svg>
                            </div>
                            <h3 class="text-sm sm:text-base font-black text-slate-800 tracking-wide">
                                ข่าวสารองค์กร
                            </h3>
                        </div>
                        
                        <?php if (empty($news_list)): ?>
                            <div class="py-6 text-center text-slate-400 text-xs">
                                <p class="font-bold text-slate-700 text-sm">ข่าวสารและประกาศองค์กร</p>
                                <p class="mt-1">ยังไม่มีประกาศข่าวสารในขณะนี้</p>
                            </div>
                        <?php else: ?>
                            <!-- Slider Wrapper -->
                            <div class="relative overflow-hidden w-full">
                                <div id="newsSlider" class="flex transition-transform duration-300 ease-out w-full">
                                    <?php 
                                        $news_json_all = htmlspecialchars(json_encode($news_list, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                        foreach ($news_list as $index => $news): 
                                            $news_json_single = htmlspecialchars(json_encode($news, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="w-full shrink-0 px-0.5 space-y-2.5 news-slide cursor-pointer group" onclick='openNewsDetailModal(<?php echo $news_json_single; ?>, <?php echo $news_json_all; ?>)'>
                                        <?php 
                                            $cover_img = '';
                                            if (!empty($news['image'])) {
                                                $decoded = json_decode($news['image'], true);
                                                $cover_img = is_array($decoded) ? ($decoded[0] ?? '') : $news['image'];
                                            }
                                            if (!empty($cover_img)): 
                                        ?>
                                        <div class="w-full h-36 rounded-2xl overflow-hidden bg-slate-900/5 border border-slate-200 shadow-xs relative group-hover:border-blue-400 transition-all flex items-center justify-center">
                                            <img src="uploads/news/<?php echo htmlspecialchars($cover_img); ?>" alt="News Image" class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-300">
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

                            <!-- Dots Indicator (. . .) -->
                            <div class="flex justify-center items-center gap-1.5 mt-3">
                                <?php foreach ($news_list as $index => $news): ?>
                                <button type="button" onclick="currentSlide(<?php echo $index; ?>)" class="news-dot h-1.5 rounded-full transition-all bg-slate-300 cursor-pointer <?php echo $index === 0 ? 'w-4 bg-blue-600' : 'w-1.5'; ?>" data-index="<?php echo $index; ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ปุ่มลงประกาศ (แสดงเฉพาะสิทธิ์ Admin, HR, IT) -->
                    <?php if (in_array($user_role ?? 'employee', ['admin', 'it_support', 'hr'], true)): ?>
                    <div class="pt-3 border-t border-slate-100 flex justify-end mt-3">
                        <button type="button" onclick="openPostNewsModal()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer active:scale-95 flex items-center gap-1.5">
                            <span>✍️</span> ลงประกาศข่าวสาร
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <script>
                    let currentSlideIndex = 0;
                    const slides = document.querySelectorAll('.news-slide');
                    const dots = document.querySelectorAll('.news-dot');

                    function showSlide(index) {
                        if (!slides.length) return;
                        if (index >= slides.length) currentSlideIndex = 0;
                        else if (index < 0) currentSlideIndex = slides.length - 1;
                        else currentSlideIndex = index;

                        const slider = document.getElementById('newsSlider');
                        if (slider) {
                            slider.style.transform = `translateX(-${currentSlideIndex * 100}%)`;
                        }

                        dots.forEach((dot, idx) => {
                            if (idx === currentSlideIndex) {
                                dot.classList.remove('bg-slate-300', 'w-1.5');
                                dot.classList.add('bg-blue-600', 'w-4');
                            } else {
                                dot.classList.remove('bg-blue-600', 'w-4');
                                dot.classList.add('bg-slate-300', 'w-1.5');
                            }
                        });
                    }

                    function currentSlide(index) {
                        showSlide(index);
                    }

                    if (slides.length > 1) {
                        setInterval(() => {
                            showSlide(currentSlideIndex + 1);
                        }, 5000);
                    }
                </script>
            </div>
        </div>

        <!-- แถบปุ่มเมนูส่วนกลางท้ายไฟล์ -->
        <?php include 'includes/navbar.php'; ?>
    </div>

    <!-- 📌 MODAL: แสดงรายการสลิปเงินเดือน -->
    <div id="payslipModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 space-y-4 overflow-visible my-auto">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2"><span>💵</span> สลิปเงินเดือนของคุณ</h3>
                <button type="button" onclick="closePayslipModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
            </div>

            <!-- ดรอปดาวน์เลือกปี พ.ศ. -->
            <div class="space-y-1 relative z-30">
                <label class="text-[11px] font-bold text-slate-500 px-1">เลือกปี พ.ศ.</label>
                <?php
                    $current_y = (int)date('Y');
                    $mobile_years_opts = [];
                    for ($y = $current_y; $y >= $current_y - 3; $y--) {
                        $mobile_years_opts[] = [
                            'id'   => (string)($y + 543),
                            'name' => (string)($y + 543)
                        ];
                    }
                    $default_year_thai = (string)($current_y + 543);
                    
                    renderRoundedDropdown('mobile_slip_year', 'slip_year', $default_year_thai, $mobile_years_opts, $default_year_thai);
                ?>
            </div>

            <!-- รายการสลิปเงินเดือน -->
            <div class="space-y-2.5 max-h-[45vh] overflow-y-auto pr-1 text-xs pt-1" id="payslipListContainer">
                <?php if (empty($employee_payslips)): ?>
                    <div class="p-8 text-center text-slate-400 font-light bg-slate-50 rounded-2xl border border-slate-100">
                        🚫 ยังไม่มีสลิปเงินเดือนในระบบ
                    </div>
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

    <?php include_once 'includes/modal_leave_status.php'; ?>
    <?php include_once 'modal_news.php'; ?>

    <script>
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
                if (itemYear === selectedYear) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
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

        function toggleMobileProfileDropdown(e) {
            e.stopPropagation();
            const menu = document.getElementById('mobile-profile-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        document.addEventListener('click', function(e) {
            const container = document.getElementById('mobile-profile-dropdown-container');
            const menu = document.getElementById('mobile-profile-menu');
            if (container && menu && !container.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>