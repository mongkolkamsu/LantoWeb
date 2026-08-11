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
$user_id = $_SESSION['user_id'];

// ดึงข้อมูลวันเริ่มงานพนักงานมาคำนวณสิทธิ์พักร้อน
$start_date = "";
$fullname = "";
try {
    $stmt = $pdo->prepare("SELECT fullname, start_date FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(array('id' => $user_id));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $fullname = $user['fullname'];
        $start_date = $user['start_date'];
    }
} catch (PDOException $e) {}

// 🎯 1. ตั้งค่าโควตาการลาพื้นฐานเต็มจำนวนประจำปี
$personal_limit = 3;  // ลากิจ 3 วัน
$sick_limit     = 30; // ลาป่วย 30 วัน
$vacation_limit = 0;  // ตั้งต้นเป็น 0 วันก่อนปลดล็อก

// 🎯 2. คำนวณอายุงานเพื่อปลดล็อกสิทธิ์ลาพักร้อน (ต้องครบ 1 ปีขึ้นไป)
$is_unlocked_vacation = false;
if (!empty($start_date) && $start_date != '0000-00-00') {
    $start_dt = new DateTime($start_date);
    $current_dt = new DateTime(); 
    $diff = $start_dt->diff($current_dt);
    
    if ($diff->y >= 1) {
        $vacation_limit = 6; // ปลดล็อกลาพักร้อน 6 วัน
        $is_unlocked_vacation = true;
    }
}

// 🎯 3. ดึงจำนวนวันลาที่ได้รับอนุมัติแล้ว (approved) ในปีนี้มาหักลบออกจากโควตา
$personal_used = 0;
$sick_used     = 0;
$vacation_used = 0;

try {
    $stmt_used = $pdo->prepare("
        SELECT leave_type, SUM(DATEDIFF(end_date, start_date) + 1) AS days_used
        FROM leave_requests
        WHERE user_id = :user_id 
          AND status = 'approved'
          AND YEAR(created_at) = YEAR(CURRENT_DATE)
        GROUP BY leave_type
    ");
    $stmt_used->execute(['user_id' => $user_id]);
    $used_records = $stmt_used->fetchAll(PDO::FETCH_ASSOC);

    foreach ($used_records as $ur) {
        $type = strtolower($ur['leave_type']);
        $days = (int)$ur['days_used'];

        if ($type === 'personal' || $type === 'business') {
            $personal_used += $days;
        } elseif ($type === 'sick') {
            $sick_used += $days;
        } elseif ($type === 'vacation' || $type === 'annual') {
            $vacation_used += $days;
        }
    }
} catch (PDOException $e) {}

// คำนวณวันลาคงเหลือจริง
$personal_remaining = max(0, $personal_limit - $personal_used);
$sick_remaining     = max(0, $sick_limit - $sick_used);
$vacation_remaining = max(0, $vacation_limit - $vacation_used);

// 🎯 4. จัดเตรียมชุดข้อมูลตัวเลือกอาร์เรย์สำหรับส่งให้กล่องดรอปดาวน์
$leave_options = array(
    array('id' => 'personal', 'name' => 'ลากิจ (คงเหลือ ' . $personal_remaining . ' วัน)'),
    array('id' => 'sick', 'name' => 'ลาป่วย (คงเหลือ ' . $sick_remaining . ' วัน)'),
    array('id' => 'other', 'name' => 'ลาอื่นๆ (โปรดระบุเหตุผล)')
);
if ($is_unlocked_vacation) {
    $leave_options[] = array('id' => 'vacation', 'name' => 'ลาพักร้อน (คงเหลือ ' . $vacation_remaining . ' วัน)');
}

// 🎯 อาร์เรย์สำหรับรูปแบบเวลาการลา
$duration_options = array(
    array('id' => 'full', 'name' => 'ลาเต็มวัน (Full Day)'),
    array('id' => 'half', 'name' => 'ลาครึ่งวัน (Half Day)'),
    array('id' => 'hourly', 'name' => 'ลารายชั่วโมง (Hourly)')
);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ระบบแจ้งลาออนไลน์ - Lanto Workspace</title>
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

    <!-- 📁 ดึง Sidebar พนักงาน (แสดงเฉพาะ PC) -->
    <?php include_once 'sidebar.php'; ?>

    <!-- 🖥️ ส่วนเนื้อหาฝั่งขวา -->
    <div class="flex-1 flex flex-col min-w-0 justify-between md:ml-64">
        <div class="w-full flex flex-col">

            <!-- 🔝 ดึง Header ด้านบน -->
            <?php 
            $page_title    = 'ระบบแจ้งลาออนไลน์';
            $page_subtitle = 'ยื่นคำขออนุมัติการลาและตรวจสอบสิทธิ์โควตาคงเหลือประจำปี';
            $show_back     = true;
            $back_url      = '../index_pc.php';
            include_once '../includes/header.php'; 
            ?>

            <!-- 💻/📱 Main Container (มือถือ = max-w-xl, PC = ขยาย max-w-3xl และ max-w-4xl กว้างสบายตาพอดี) -->
            <main class="p-4 md:p-6 lg:p-8 max-w-xl md:max-w-3xl lg:max-w-4xl mx-auto md:-translate-x-16 w-full space-y-5 md:space-y-6 pb-28 md:pb-10">

                <!-- 📊 โซนกล่องโควตาสรุปสิทธิ์วันลา (ขนาดกลางกำลังดี อ่านง่ายสบายตา) -->
                <div class="space-y-2.5">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider pl-0.5">
                        📊 สิทธิ์โควตาการลาคงเหลือประจำปี
                    </h3>

                    <div class="grid grid-cols-3 gap-3 md:gap-4">
                        
                        <!-- การ์ดลากิจ -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl md:rounded-3xl p-3.5 md:p-4.5 shadow-2xs flex items-center justify-center gap-3 md:gap-4">
                            <span class="text-2xl md:text-3xl shrink-0">💼</span>
                            <div class="text-left">
                                <h4 class="text-xs md:text-sm font-bold text-slate-600 leading-tight">ลากิจ</h4>
                                <p class="text-sm md:text-base font-black text-slate-900 mt-0.5"><?php echo $personal_remaining; ?> <span class="text-xs font-normal text-slate-400">วัน</span></p>
                            </div>
                        </div>

                        <!-- การ์ดลาป่วย -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl md:rounded-3xl p-3.5 md:p-4.5 shadow-2xs flex items-center justify-center gap-3 md:gap-4">
                            <span class="text-2xl md:text-3xl shrink-0">🤒</span>
                            <div class="text-left">
                                <h4 class="text-xs md:text-sm font-bold text-slate-600 leading-tight">ลาป่วย</h4>
                                <p class="text-sm md:text-base font-black text-slate-900 mt-0.5"><?php echo $sick_remaining; ?> <span class="text-xs font-normal text-slate-400">วัน</span></p>
                            </div>
                        </div>

                        <!-- การ์ดพักร้อน -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl md:rounded-3xl p-3.5 md:p-4.5 shadow-2xs flex items-center justify-center gap-3 md:gap-4 relative overflow-hidden <?php echo !$is_unlocked_vacation ? 'opacity-75' : ''; ?>">
                            <?php if (!$is_unlocked_vacation): ?>
                                <div class="absolute top-1.5 right-2 text-[10px]" title="ต้องมีอายุงานครบ 1 ปี">🔒</div>
                            <?php endif; ?>
                            <span class="text-2xl md:text-3xl shrink-0">🌴</span>
                            <div class="text-left">
                                <h4 class="text-xs md:text-sm font-bold text-slate-600 leading-tight">พักร้อน</h4>
                                <p class="text-sm md:text-base font-black text-slate-900 mt-0.5"><?php echo $vacation_remaining; ?> <span class="text-xs font-normal text-slate-400">วัน</span></p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- 📝 ฟอร์มข้อมูลส่งใบลา (การ์ดขาวทรงมน สมส่วนทั้งมือถือและ PC) -->
                <div class="bg-white border border-slate-200/80 rounded-3xl p-4 md:p-6 lg:p-8 shadow-2xs space-y-3.5 md:space-y-4">
                    <div class="border-b border-slate-100 pb-2.5 md:pb-3">
                        <h3 class="text-xs md:text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <span>📝</span> กรอกข้อมูลยื่นคำขอแจ้งวันลา
                        </h3>
                    </div>

                    <form action="leave_process.php" method="POST" enctype="multipart/form-data" class="space-y-3.5 md:space-y-4">
                        
                        <!-- เลือกประเภทการลา -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 pl-0.5">เลือกประเภทการลา</label>
                            <?php 
                            include_once '../includes/rounded_dropdown.php';
                            renderRoundedDropdown('leave_select', 'leave_type', '-- โปรดเลือกประเภทการลา --', $leave_options);
                            ?>
                        </div>

                        <!-- รูปแบบเวลา + จำนวนชั่วโมง (คู่กัน 2 คอลัมน์) -->
                        <div class="grid grid-cols-2 gap-2.5 md:gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 pl-0.5">รูปแบบเวลา</label>
                                <?php renderRoundedDropdown('duration_select', 'leave_duration', 'เลือกรูปแบบเวลา', $duration_options, 'full'); ?>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 pl-0.5">จำนวน ชม. (ถ้ามี)</label>
                                <input type="number" name="leave_hours" min="1" max="8" class="w-full bg-slate-50 border border-slate-200/80 rounded-2xl px-3.5 md:px-4 py-2.5 md:py-3 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-2xs" placeholder="1-8 ชม.">
                            </div>
                        </div>

                        <!-- วันที่เริ่มลา + สิ้นสุดวันที่ (คู่กัน 2 คอลัมน์) -->
                        <div class="grid grid-cols-2 gap-2.5 md:gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 pl-0.5">วันที่เริ่มลา</label>
                                <div class="relative">
                                    <input type="text" name="start_leave" required autocomplete="off" placeholder="วว/ดด/ปปปป" class="calendar-trigger w-full bg-slate-50 border border-slate-200/80 rounded-2xl pl-3.5 md:pl-4 pr-8 md:pr-10 py-2.5 md:py-3 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-2xs cursor-pointer">
                                    <div class="absolute inset-y-0 right-0 pr-2.5 md:pr-3.5 flex items-center pointer-events-none text-slate-400">
                                        <svg class="w-3.5 h-3.5 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 pl-0.5">สิ้นสุดวันที่</label>
                                <div class="relative">
                                    <input type="text" name="end_leave" required autocomplete="off" placeholder="วว/ดด/ปปปป" class="calendar-trigger w-full bg-slate-50 border border-slate-200/80 rounded-2xl pl-3.5 md:pl-4 pr-8 md:pr-10 py-2.5 md:py-3 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-2xs cursor-pointer">
                                    <div class="absolute inset-y-0 right-0 pr-2.5 md:pr-3.5 flex items-center pointer-events-none text-slate-400">
                                        <svg class="w-3.5 h-3.5 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- แนบไฟล์รูปภาพเอกสาร -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 pl-0.5">📸 แนบหลักฐาน/เอกสาร</label>
                            <input type="file" id="leave_attachment" name="leave_attachment" accept="image/*" required onchange="previewLeaveImage(event)" class="w-full bg-slate-50 border border-slate-200/80 border-dashed rounded-2xl px-3.5 md:px-4 py-2.5 text-xs text-slate-500 font-medium file:mr-2.5 file:py-0.5 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer shadow-2xs">
                            
                            <!-- กล่องพรีวิวรูปตัวอย่าง -->
                            <div id="leave-preview-container" class="hidden mt-2 p-2 bg-slate-50 border border-slate-200/60 border-dashed rounded-2xl text-center transition-all">
                                <img id="leave-image-preview" src="#" alt="Preview" class="max-h-36 md:max-h-48 mx-auto rounded-xl border border-slate-200 shadow-2xs object-contain">
                            </div>
                        </div>

                        <!-- เหตุผลการลา -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 pl-0.5">เหตุผลการลา</label>
                            <textarea name="reason" rows="3" required placeholder="ระบุรายละเอียดเหตุผลการลา..." class="w-full bg-slate-50 border border-slate-200/80 rounded-2xl px-3.5 md:px-4 py-2.5 md:py-3 text-xs text-slate-800 focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-2xs resize-none leading-relaxed"></textarea>
                        </div>

                        <!-- ปุ่มส่งใบอนุมัติ -->
                        <div class="pt-1">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 md:py-3.5 rounded-2xl text-xs tracking-wide transition-all active:scale-[0.98] shadow-md shadow-blue-500/20 cursor-pointer">
                                ส่งใบอนุมัติแจ้งลา
                            </button>
                        </div>
                    </form>
                </div>

            </main>
        </div>
    </div>

    <!-- 📱 แถบเมนูด้านล่างแสดงเฉพาะบนมือถือ -->
    <div class="md:hidden">
        <?php include '../includes/navbar.php'; ?>
    </div>

    <!-- คอมโพเนนต์ปฏิทิน -->
    <?php include_once '../includes/calendar_component.php'; ?>

    <script>
    function previewLeaveImage(event) {
        const input = event.target;
        const container = document.getElementById('leave-preview-container');
        const preview = document.getElementById('leave-image-preview');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                container.classList.remove('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.src = '#';
            container.classList.add('hidden');
        }
    }
    </script>
    <script src="../assets/js/alerts.js"></script>
</body>
</html>