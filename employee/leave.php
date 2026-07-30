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
} catch (PDOException $e) {
    // ซ่อนข้อผิดพลาด
}

// 🎯 ตั้งค่าโควตาการลาพื้นฐานตามเงื่อนไขที่กำหนด
$personal_limit = 3;  // ลากิจ 3 วัน
$sick_limit = 30;    // ลาป่วย 30 วัน
$vacation_limit = 0; // ตั้งต้นเป็น 0 วันก่อนปลดล็อก

// 🎯 คำนวณอายุงานเพื่อปลดล็อกสิทธิ์ลาพักร้อน (ต้องครบ 1 ปีขึ้นไป)
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

// 🎯 จัดเตรียมชุดข้อมูลตัวเลือกอาร์เรย์สำหรับส่งให้กล่องดรอปดาวน์
$leave_options = array(
    array('id' => 'personal', 'name' => 'ลากิจ (คงเหลือ ' . $personal_limit . ' วัน)'),
    array('id' => 'sick', 'name' => 'ลาป่วย (คงเหลือ ' . $sick_limit . ' วัน)'),
    array('id' => 'other', 'name' => 'ลาอื่นๆ (โปรดระบุเหตุผล)')
);
if ($is_unlocked_vacation) {
    $leave_options[] = array('id' => 'vacation', 'name' => 'ลาพักร้อน (คงเหลือ ' . $vacation_limit . ' วัน)');
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
    <title>ระบบแจ้งลาออนไลน์ - Lanto Global</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex items-center justify-center p-0 md:p-4 text-slate-800 antialiased select-none">

    <!-- 📱 Main Mobile App Shell Layout -->
    <div class="w-full min-h-screen bg-white/40 backdrop-blur-xl flex flex-col justify-between relative overflow-y-auto p-5 pb-24
            md:max-w-md md:mx-auto md:my-6 md:min-h-[812px] md:rounded-[40px] md:border md:border-white/60 md:shadow-2xl">
        
        <!-- ส่วนเนื้อหาด้านใน -->
        <div>
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-200/60">
                <a href="../index.php?view=mobile" class="w-9 h-9 bg-white border border-slate-200 text-slate-600 rounded-full flex items-center justify-center shadow-xs active:scale-90 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="text-sm font-bold tracking-wide text-slate-700">ยื่นคำขอแจ้งวันลา</h2>
                <div class="w-9"></div>
            </div>

            <!-- 📊 โซนกล่องโควตาสรุปสิทธิ์วันลา -->
            <div class="mt-3.5">
                <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">สิทธิ์โควตาการลาคงเหลือ</h3>
                <div class="grid grid-cols-3 gap-2.5">
                    
                    <!-- การ์ดสิทธิ์ลากิจ -->
                    <div class="bg-white/80 border border-slate-200/60 rounded-xl p-2.5 text-center shadow-2xs">
                        <span class="text-lg">💼</span>
                        <h4 class="text-[11px] font-medium text-slate-500 mt-0.5">ลากิจ</h4>
                        <p class="text-sm font-bold text-slate-900 mt-0.5"><?php echo $personal_limit; ?> วัน</p>
                    </div>

                    <!-- การ์ดสิทธิ์ลาป่วย -->
                    <div class="bg-white/80 border border-slate-200/60 rounded-xl p-2.5 text-center shadow-2xs">
                        <span class="text-lg">🤒</span>
                        <h4 class="text-[11px] font-medium text-slate-500 mt-0.5">ลาป่วย</h4>
                        <p class="text-sm font-bold text-slate-900 mt-0.5"><?php echo $sick_limit; ?> วัน</p>
                    </div>

                    <!-- การ์ดสิทธิ์ลาพักร้อน -->
                    <div class="rounded-xl p-2.5 text-center shadow-2xs border relative overflow-hidden transition-all <?php echo $is_unlocked_vacation ? 'bg-white/80 border-slate-200/60' : 'bg-slate-100/80 border-dashed border-slate-200'; ?>">
                        <?php if (!$is_unlocked_vacation): ?>
                            <div class="absolute top-1 right-1 text-[9px]" title="ต้องมีอายุงานครบ 1 ปี">🔒</div>
                            <span class="text-lg opacity-40">🌴</span>
                        <?php else: ?>
                            <span class="text-lg">🌴</span>
                        <?php endif; ?>
                        
                        <h4 class="text-[11px] font-medium <?php echo $is_unlocked_vacation ? 'text-slate-500' : 'text-slate-400'; ?> mt-0.5">พักร้อน</h4>
                        <p class="text-sm font-bold <?php echo $is_unlocked_vacation ? 'text-slate-900' : 'text-slate-400'; ?> mt-0.5"><?php echo $vacation_limit; ?> วัน</p>
                    </div>

                </div>
            </div>

            <!-- 📝 ฟอร์มข้อมูลส่งใบลา (ปรับความสูงและระยะห่างให้อยู่ทรงพอดีเป๊ะ) -->
            <form action="leave_process.php" method="POST" enctype="multipart/form-data" class="mt-3.5 bg-white border border-slate-200/60 rounded-2xl p-4 shadow-xs space-y-3.5">
                
                <!-- เลือกประเภทการลา -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">เลือกประเภทการลา</label>
                    <?php 
                    include_once '../includes/rounded_dropdown.php';
                    renderRoundedDropdown('leave_select', 'leave_type', '-- โปรดเลือกประเภทการลา --', $leave_options);
                    ?>
                </div>

                <!-- รูปแบบเวลาการลา + จำนวนชั่วโมง -->
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">รูปแบบเวลา</label>
                        <?php renderRoundedDropdown('duration_select', 'leave_duration', 'เลือกรูปแบบเวลา', $duration_options, 'full'); ?>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">จำนวน ชม. (ถ้ามี)</label>
                        <input type="number" name="leave_hours" min="1" max="8" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-2xs" placeholder="1-8 ชม.">
                    </div>
                </div>

                <!-- แนบไฟล์รูปภาพเอกสาร -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">📸 แนบหลักฐาน/เอกสาร</label>
                    <input type="file" id="leave_attachment" name="leave_attachment" accept="image/*" required onchange="previewLeaveImage(event)" class="w-full bg-slate-50 border border-slate-200 border-dashed rounded-xl px-3 py-2 text-xs text-slate-500 font-medium file:mr-2 file:py-0.5 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer shadow-2xs">
                    
                    <!-- กล่องพรีวิวรูปตัวอย่าง -->
                    <div id="leave-preview-container" class="hidden mt-2 p-2 bg-slate-50 border border-slate-200/60 border-dashed rounded-xl text-center transition-all">
                        <img id="leave-image-preview" src="#" alt="Preview" class="max-h-32 mx-auto rounded-lg border border-slate-200 shadow-2xs object-contain">
                    </div>
                </div>

                <!-- เลือกช่วงวันที่ -->
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">วันที่เริ่มลา</label>
                        <div class="relative">
                            <input type="text" name="start_leave" required autocomplete="off" class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-xl pl-3 pr-9 py-2.5 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-2xs cursor-pointer" >
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">สิ้นสุดวันที่</label>
                        <div class="relative">
                            <input type="text" name="end_leave" required autocomplete="off" class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-xl pl-3 pr-9 py-2.5 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-2xs cursor-pointer" >
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ช่องกรอกเหตุผล -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">เหตุผลการลา</label>
                    <textarea name="reason" rows="3" required placeholder="ระบุรายละเอียดเหตุผลการลา..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-2xs resize-none leading-relaxed"></textarea>
                </div>

                <!-- ปุ่มส่งใบอนุมัติ -->
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl text-xs tracking-wide transition-all active:scale-[0.98] shadow-md shadow-blue-500/20 cursor-pointer">
                    ส่งใบอนุมัติแจ้งลา
                </button>
            </form>

        </div>
        
        <?php include '../includes/navbar.php'; ?>
    </div>

    <!-- ส่วนคอมโพเนนต์ปฏิทิน -->
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
</body>
</html>