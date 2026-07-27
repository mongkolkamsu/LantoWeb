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
    array('id' => 'personal', 'name' => 'ลากิจ (โควตาคงเหลือ ' . $personal_limit . ' วัน)'),
    array('id' => 'sick', 'name' => 'ลาป่วย (โควตาคงเหลือ ' . $sick_limit . ' วัน)'),
    array('id' => 'other', 'name' => 'ลาอื่นๆ (โปรดระบุเหตุผลในช่องด้านล่าง)')
);
if ($is_unlocked_vacation) {
    $leave_options[] = array('id' => 'vacation', 'name' => 'ลาพักร้อน (โควตาคงเหลือ ' . $vacation_limit . ' วัน)');
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
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex items-center justify-center p-0 md:p-4 text-slate-800 antialiased">

    <!-- 📱 Main Mobile App Shell Layout -->
    <div class="w-full max-w-md bg-white/60 backdrop-blur-xl border border-white/80 min-h-screen md:min-h-[812px] md:rounded-[40px] shadow-2xl flex flex-col justify-between relative overflow-hidden pb-24">
        
        <!-- ส่วนเนื้อหาด้านใน -->
        <div class="p-5">
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-200/60">
                <a href="../index.php?view=mobile" class="w-10 h-10 bg-white border border-slate-200 text-slate-600 rounded-full flex items-center justify-center shadow-xs active:scale-90 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="text-[15px] font-bold tracking-wide text-slate-700">ยื่นคำขอแจ้งวันลา</h2>
                <div class="w-10"></div>
            </div>

            <!-- 📊 โซนกล่องโควตาสรุปสิทธิ์วันลาประจำปี -->
            <div class="mt-5">
                <h3 class="text-[12px] font-bold text-slate-600 uppercase tracking-wider mb-2.5">สิทธิ์โควตาการลาคงเหลือประจำปี</h3>
                <div class="grid grid-cols-3 gap-3">
                    
                    <!-- การ์ดสิทธิ์ลากิจ -->
                    <div class="bg-white border border-slate-200/60 rounded-2xl p-3 text-center shadow-xs">
                        <span class="text-xl">💼</span>
                        <h4 class="text-[12px] font-medium text-slate-600 mt-1">ลากิจ</h4>
                        <p class="text-base font-bold text-slate-900 mt-0.5"><?php echo $personal_limit; ?> วัน</p>
                        <span class="text-[11px] text-slate-500 block mt-0.5">ได้รับสิทธิ์เต็ม</span>
                    </div>

                    <!-- การ์ดสิทธิ์ลาป่วย -->
                    <div class="bg-white border border-slate-200/60 rounded-2xl p-3 text-center shadow-xs">
                        <span class="text-xl">🤒</span>
                        <h4 class="text-[12px] font-medium text-slate-600 mt-1">ลาป่วย</h4>
                        <p class="text-base font-bold text-slate-900 mt-0.5"><?php echo $sick_limit; ?> วัน</p>
                        <span class="text-[11px] text-slate-500 block mt-0.5">ตามกฎหมายกำหนด</span>
                    </div>

                    <!-- การ์ดสิทธิ์ลาพักร้อน -->
                    <div class="rounded-2xl p-3 text-center shadow-xs border relative overflow-hidden transition-all <?php echo $is_unlocked_vacation ? 'bg-white border-slate-200/60' : 'bg-slate-100/80 border-dashed border-slate-200'; ?>">
                        <?php if (!$is_unlocked_vacation): ?>
                            <div class="absolute top-1 right-1 text-[10px]" title="ต้องมีอายุงานครบ 1 ปี">🔒</div>
                            <span class="text-xl opacity-40">🌴</span>
                        <?php else: ?>
                            <span class="text-xl">🌴</span>
                        <?php endif; ?>
                        
                        <h4 class="text-[12px] font-medium <?php echo $is_unlocked_vacation ? 'text-slate-600' : 'text-slate-400'; ?> mt-1">ลาพักร้อน</h4>
                        <p class="text-base font-bold <?php echo $is_unlocked_vacation ? 'text-slate-900' : 'text-slate-400'; ?> mt-0.5"><?php echo $vacation_limit; ?> วัน</p>
                        <span class="text-[11px] <?php echo $is_unlocked_vacation ? 'text-slate-500' : 'text-red-500 font-medium'; ?> block mt-0.5">
                            <?php echo $is_unlocked_vacation ? 'ปลดล็อกสิทธิ์แล้ว' : 'อายุงานไม่ถึง 1 ปี'; ?>
                        </span>
                    </div>

                </div>
            </div>

            <!-- 📝 ฟอร์มข้อมูลส่งใบลา -->
            <form action="leave_process.php" method="POST" enctype="multipart/form-data" class="mt-6 bg-white border border-slate-200/60 rounded-3xl p-5 shadow-xs space-y-4">
                
                <!-- 🎯 จุดดึงข้อมูลที่ 1: ดึงคอมโพเนนต์ Custom Dropdown มาแทนที่ HTML Select เดิม -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">เลือกประเภทการลา</label>
                    <?php 
                    include_once '../includes/rounded_dropdown.php';
                    renderRoundedDropdown('leave_select', 'leave_type', '-- โปรดเลือกประเภทการลา --', $leave_options);
                    ?>
                </div>
                <!-- 🎯 เพิ่มระบบเลือกรูปแบบเวลาการลา (เต็มวัน/ครึ่งวัน/รายชั่วโมง) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">รูปแบบเวลาการลา</label>
                        <?php renderRoundedDropdown('duration_select', 'leave_duration', 'เลือกรูปแบบเวลา', $duration_options, 'full'); ?>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">จำนวนชั่วโมง (ถ้าลารายชั่วโมง)</label>
                        <input type="number" name="leave_hours" min="1" max="8" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-xs" placeholder="ระบุ 1-8 ชม.">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">📸 แนบรูปภาพหลักฐานใบรับรอง / เอกสาร (จำเป็นต้องใส่)</label>
                    <input type="file" id="leave_attachment" name="leave_attachment" accept="image/*" required onchange="previewLeaveImage(event)" class="w-full bg-slate-50 border border-slate-200 border-dashed rounded-2xl px-4 py-2.5 text-xs text-slate-500 font-medium file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer shadow-xs">
                    
                    <!-- กล่องแสดงรูปตัวอย่างพรีวิว (เริ่มต้นจะถูกซ่อนไว้) -->
                    <div id="leave-preview-container" class="hidden mt-3 p-2 bg-slate-50 border border-slate-200/60 border-dashed rounded-2xl text-center transition-all">
                        <p class="text-[10px] text-slate-400 mb-2 font-medium">✨ ตัวอย่างรูปภาพหลักฐานที่เลือก</p>
                        <div class="relative inline-block max-w-full">
                            <img id="leave-image-preview" src="#" alt="Preview" class="max-h-48 mx-auto rounded-xl border border-slate-200 shadow-sm object-contain">
                        </div>
                    </div>
                </div>

                <!-- 🎯 จุดดึงข้อมูลที่ 2: เปลี่ยนช่องกรอกวันที่ให้รองรับคลาสเปิดใช้งานปฏิทินอัจฉริยะ -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">วันที่เริ่มลา</label>
                        <input type="text" name="start_leave" required autocomplete="off" class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-xs" placeholder="วว/ดด/ปปปป">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">สิ้นสุดวันที่</label>
                        <input type="text" name="end_leave" required autocomplete="off" class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-800 font-medium focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-xs" placeholder="วว/ดด/ปปปป">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">เหตุผลความจำเป็นในการลา</label>
                    <textarea name="reason" rows="3" required placeholder="ระบุรายละเอียด เช่น ไปทำธุระครอบครัวที่ต่างจังหวัด หรือ มีนัดพบแพทย์..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-800 focus:outline-none focus:border-blue-500 focus:bg-white transition-all shadow-xs resize-none leading-relaxed"></textarea>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white font-bold py-3.5 rounded-2xl text-xs tracking-wide transition-all active:scale-[0.98] shadow-md shadow-blue-500/10 cursor-pointer">
                    ส่งใบอนุมัติแจ้งลา
                </button>
            </form>

        </div>
        
        <?php include '../includes/navbar.php'; ?>
    </div>

    <!-- 🎯 จุดดึงข้อมูลที่ 3: ดึงโครงสร้างป็อปอัป CSS/JS ปฏิทินขึ้นมาทำงานสแตนด์บายรอรับคำสั่งกดครอบจักรวาล -->
    <?php include_once '../includes/calendar_component.php'; ?>
<!-- สคริปต์ JavaScript สำหรับอ่านไฟล์และเสกรูปตัวอย่างขึ้นจอทันที -->
<script>
function previewLeaveImage(event) {
    const input = event.target;
    const container = document.getElementById('leave-preview-container');
    const preview = document.getElementById('leave-image-preview');
    
    // ตรวจสอบว่าพนักงานเลือกไฟล์จริงไหม
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        // เมื่อโหลดไฟล์เสร็จ ให้ยัดเข้าแท็ก img แล้วสั่งเปิดกล่องแสดงผล
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.classList.remove('hidden');
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        // ถ้ากดยกเลิกหรือไม่เลือกไฟล์ ให้เคลียร์ค่าแล้วซ่อนกล่องตามเดิม
        preview.src = '#';
        container.classList.add('hidden');
    }
}
</script>
</body>
</html>