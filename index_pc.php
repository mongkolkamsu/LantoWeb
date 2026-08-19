<?php
session_start();
require_once 'config/db.php';
require_once 'includes/rounded_dropdown.php';
require_once 'includes/functions.php';
require_once 'config/auth.php';
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
$employee_url  = 'index_pc.php'; // 👈 เพิ่มบรรทัดนี้


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

// 1. ตรรกะดึงข้อมูลบันทึกเวลาจริงประจำวัน
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

// 2. ดึงข้อมูลสลิปเงินเดือน
$employee_payslips = [];
try {
    $stmt_salary = $pdo->prepare("SELECT * FROM salaries WHERE employee_id = :user_id AND is_published = 1 ORDER BY year DESC, month DESC");
    $stmt_salary->execute(['user_id' => $user_id]);
    $employee_payslips = $stmt_salary->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 3. ข้อมูลสถานะการลา
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

        if (($item['leave_duration'] ?? 'full') === 'hourly') {
            $days_str = ($item['leave_hours'] ?? '0') . ' ชั่วโมง';
        } else {
            $days_str = max(1, (int)($item['total_days'] ?? 1)) . ' วัน';
        }

        $attachment_url = !empty($item['attachment']) ? 'uploads/leaves/' . $item['attachment'] : '';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard (PC) - Lanto Workspace</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { display: none; }
    </style>
    <script>
        // ป้องกันลูปดีดกลับเมื่อมีสกรอลล์บาร์ หรือเมื่อกดสลับโหมดดูบนคอม
        const urlParams = new URLSearchParams(window.location.search);
        if (window.matchMedia("(max-width: 767px)").matches && urlParams.get('view') !== 'pc') {
            window.location.replace('index_mobile.php');
        }
    </script>
    <script src="assets/js/alerts.js"></script>
</head>
<body class="bg-[#f4f6fa] min-h-screen text-slate-800 antialiased">
    
    <!-- 🔝 ดึง Header ด้านบน -->
    <?php 
    $page_title    = 'Lanto Workspace';
    $page_subtitle = 'Enterprise Workforce System';
    $show_back     = false; // 👈 ตั้งเป็น false เพื่อปิดปุ่มย้อนกลับและแสดงโลโก้แทน
    include_once 'includes/header.php'; 
    ?>

    <!-- Body เนื้อหาหลักแบบเต็มความกว้าง -->
    <main class="p-6 lg:p-10 space-y-6 max-w-7xl mx-auto w-full">
        <!-- 🖥️ บล็อกรายงานเวลาและข่าวสาร (ความสูงเท่ากันเป๊ะ และรูปภาพสมส่วนไม่กว้างแบน) -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-stretch">
            
            <!-- 📊 การ์ดสถานะฝั่งซ้าย (60% -> col-span-3) -->
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

            <!-- 📢 การ์ดข่าวสารฝั่งขวา (40% -> col-span-2) -->
            <div class="lg:col-span-2 bg-white rounded-3xl p-5 shadow-2xs border border-slate-200/80 flex flex-col justify-between relative overflow-hidden h-full">
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
                                    // แปลงข้อมูลข่าวทั้งหมดเป็น JSON สำหรับส่งเข้า Modal เพื่อให้กดเลื่อนดูได้
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
                <?php if (in_array($user_role, ['admin', 'it_support', 'hr'], true)): ?>
                <div class="pt-3 border-t border-slate-100 flex justify-end mt-3">
                    <button type="button" onclick="openPostNewsModal()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer active:scale-95 flex items-center gap-1.5">
                        <span>✍️</span> ลงประกาศข่าวสาร
                    </button>
                </div>
                <?php endif; ?>
            </div>

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
                }, 6000);
            }
        </script>

        <!-- เมนูระบบงาน (Workforce List) -->
        <div class="bg-white rounded-3xl p-6 shadow-2xs border border-slate-200/80 space-y-4">
            <h3 class="text-sm font-bold text-slate-800">เมนูระบบงาน (Workforce List)</h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                
                <!-- 1. Intershipping -->
                <a href="https://istshipping.co.th/" target="_blank" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-white border border-slate-200/60 rounded-xl flex items-center justify-center p-1.5 shadow-3xs group-hover:scale-105 transition-transform overflow-hidden shrink-0">
                        <img src="assets/images/LOGO-IST.jpg" class="w-full h-full object-contain rounded-md">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">Intershipping</p>
                        <p class="text-[10px] text-slate-400">ระบบลงเวลาหลัก</p>
                    </div>
                </a>

                <!-- 2. ข้อมูลบริษัท -->
                <a href="company_info.php" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-white border border-slate-200/60 rounded-xl flex items-center justify-center p-2.5 shadow-3xs group-hover:scale-105 transition-transform shrink-0">
                        <img src="assets/images/building.png" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">ข้อมูลบริษัท</p>
                        <p class="text-[10px] text-slate-400">โครงสร้างและระเบียบ</p>
                    </div>
                </a>

                <!-- 3. สถานะการลา -->
                <button type="button" onclick='openLeaveStatusModal(<?php echo json_encode($leaves_list_json, JSON_UNESCAPED_UNICODE); ?>)' class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group text-left cursor-pointer shadow-3xs">
                    <div class="w-12 h-12 bg-white border border-slate-200/60 rounded-xl flex items-center justify-center p-2.5 shadow-3xs group-hover:scale-105 transition-transform shrink-0">
                        <img src="assets/images/health-check.png" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">สถานะการลา</p>
                        <p class="text-[10px] text-slate-400">ยื่นและเช็กประวัติลา</p>
                    </div>
                </button>

                <!-- 4. สลิปเงินเดือน -->
                <button type="button" onclick="openPayslipModal()" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group text-left cursor-pointer shadow-3xs">
                    <div class="w-12 h-12 bg-white border border-slate-200/60 rounded-xl flex items-center justify-center p-2.5 shadow-3xs group-hover:scale-105 transition-transform shrink-0">
                        <img src="assets/images/payslip.png" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">สลิปเงินเดือน</p>
                        <p class="text-[10px] text-slate-400">ดาวน์โหลดไฟล์ PDF</p>
                    </div>
                </button>

                <!-- 5. จองแมส -->
                <a href="messenger_request/msg_index.php" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-white border border-slate-200/60 rounded-xl flex items-center justify-center p-2.5 shadow-3xs group-hover:scale-105 transition-transform shrink-0">
                        <img src="assets/images/box.png" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">Messenger Request</p>
                        <p class="text-[10px] text-slate-400">บริการรับส่งเอกสาร</p>
                    </div>
                </a>
                
                <!-- 6. Car Request -->
                <a href="car_request/car_index.php" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-white border border-slate-200/60 rounded-xl flex items-center justify-center p-2.5 shadow-3xs group-hover:scale-105 transition-transform shrink-0">
                        <img src="assets/images/car.png" class="w-full h-full object-contain" onerror="this.src='assets/images/box.png'">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">Car Request</p>
                        <p class="text-[10px] text-slate-400">จองใช้งานรถองค์กร</p>
                    </div>
                </a>

                <!-- 7. IT Service -->
                <a href="#" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-white border border-slate-200/60 rounded-xl flex items-center justify-center p-2.5 shadow-3xs group-hover:scale-105 transition-transform shrink-0">
                        <img src="assets/images/italy.png" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">IT Service</p>
                        <p class="text-[10px] text-slate-400">แจ้งปัญหาไอที/อุปกรณ์</p>
                    </div>
                </a>

                <!-- 8. จัดการระบบ (แสดงเฉพาะสิทธิ์ Admin / IT / HR) -->
                <?php if (in_array($user_role, ['admin', 'it_support', 'hr'], true)): ?>
                <a href="admin/dashboard.php" class="p-4 bg-purple-50/60 hover:bg-purple-100/80 border border-purple-200/80 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-white border border-purple-200/80 rounded-xl flex items-center justify-center p-2.5 shadow-3xs group-hover:scale-105 transition-transform shrink-0">
                        <img src="assets/images/key.png" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-purple-900">จัดการระบบ</p>
                        <p class="text-[10px] text-purple-600">เมนูผู้ดูแลระบบ</p>
                    </div>
                </a>
                <?php endif; ?>

            </div>
        </div>

        <!-- 🎯 เมนูหลักพนักงาน (Main Employee Services - ไอคอนตรงตาม navbar.php) -->
        <div class="bg-white rounded-3xl p-6 shadow-2xs border border-slate-200/80 space-y-4">
            <h3 class="text-sm font-bold text-slate-800">เมนูหลักพนักงาน (Main Employee Services)</h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                
                <!-- 1. สแกนเข้า-ออกงาน (ใช้ face-id.png จาก navbar.php) -->
                <a href="employee/scan.php" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-gradient-to-tr from-blue-700 to-indigo-600 text-white rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                        <img src="assets/images/face-id.png" class="w-9 h-9 shrink-0 object-contain brightness-0 invert" alt="Face ID" onerror="this.onerror=null; this.src='assets/images/Logo.png';">
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">สแกนเข้า-ออกงาน</p>
                        <p class="text-[10px] text-slate-400">ลงเวลาปฏิบัติงานประจำวัน</p>
                    </div>
                </a>

                <!-- 2. ประวัติการลงเวลางาน (ใช้ SVG ประวัติงาน จาก navbar.php) -->
                <a href="employee/history.php" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-amber-500 text-white rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                        <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                            <path d="M3 3v5h5" />
                            <path d="M12 7v5l3.5 2" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">ประวัติการลงเวลางาน</p>
                        <p class="text-[10px] text-slate-400">ตรวจสอบเวลามาทำงานย้อนหลัง</p>
                    </div>
                </a>

                <!-- 3. ยื่นใบแจ้งลา (ใช้ SVG แจ้งลา จาก navbar.php) -->
                <a href="employee/leave.php" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-emerald-500 text-white rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">ยื่นใบแจ้งลา</p>
                        <p class="text-[10px] text-slate-400">ยื่นขอลาหยุดงานออนไลน์</p>
                    </div>
                </a>

                <!-- 4. ข้อมูลส่วนตัว / บัตรพนักงาน (ใช้ SVG ส่วนตัว จาก navbar.php) -->
                <a href="employee/profile.php" class="p-4 bg-slate-50 hover:bg-blue-50/60 border border-slate-200/60 hover:border-blue-300 rounded-2xl flex items-center gap-4 transition-all group shadow-3xs">
                    <div class="w-12 h-12 bg-indigo-500 text-white rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">ข้อมูลส่วนตัว / บัตร</p>
                        <p class="text-[10px] text-slate-400">บัตรพนักงานดิจิทัล</p>
                    </div>
                </a>

            </div>
        </div>

    </main>

    <!-- 📌 MODAL: สลิปเงินเดือน -->
    <div id="payslipModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-sm md:max-w-xl w-full p-6 md:p-7 shadow-2xl border border-slate-100 space-y-4 my-auto">
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

        document.addEventListener('click', function(e) {
            const container = document.getElementById('profile-dropdown-container');
            const menu = document.getElementById('profile-menu');
            if (container && menu && !container.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>