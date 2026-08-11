<?php
// 🎯 คำนวณ Base Path อัตโนมัติ
$base_path = './';
if (file_exists('../assets/images/face-id.png')) {
    $base_path = '../';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- 📌 เปลี่ยน absolute เป็น fixed เพื่อให้ Navbar ลอยติดขอบล่างหน้าจอเสมอไม่ว่าจะเลื่อนไปไหน -->
<div class="fixed bottom-4 left-4 right-4 max-w-md mx-auto h-16 bg-white/95 backdrop-blur-xl border border-slate-200/60 rounded-2xl shadow-xl z-50 flex items-center justify-between px-2">
    <div class="w-full grid grid-cols-5 text-center items-center relative h-full">
        
        <!-- เมนู: หน้าแรก -->
        <a href="<?php echo $base_path; ?>index_mobile.php" class="flex flex-col items-center active:scale-90 transition-transform <?php echo ($current_page == 'index.php' || $current_page == 'index_mobile.php') ? 'text-blue-700 font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'; ?>">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
            <span class="text-[9px] mt-0.5">หน้าแรก</span>
        </a>

        <!-- เมนู: ประวัติงาน -->
        <a href="<?php echo $base_path; ?>employee/history.php" class="flex flex-col items-center active:scale-90 transition-transform <?php echo ($current_page == 'history.php') ? 'text-blue-700 font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'; ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                <path d="M3 3v5h5" />
                <path d="M12 7v5l3.5 2" />
            </svg>
            <span class="text-[9px] mt-0.5">ประวัติงาน</span>
        </a>

        <!-- ปุ่มกลมสแกนตรงกลาง -->
        <div class="relative h-full flex items-center justify-center">
            <div class="absolute -top-7 left-1/2 transform -translate-x-1/2">
                <a href="<?php echo $base_path; ?>employee/scan.php" class="w-[70px] h-[70px] bg-gradient-to-tr from-blue-700 to-indigo-600 rounded-full flex items-center justify-center text-white shadow-xl shadow-blue-600/40 border-[5px] border-white transform active:scale-95 hover:scale-105 transition-all cursor-pointer">
                    <img src="<?php echo $base_path; ?>assets/images/face-id.png" class="w-10 h-10 shrink-0 object-contain brightness-0 invert" alt="Face ID" onerror="this.onerror=null; this.src='<?php echo $base_path; ?>assets/images/Logo.png';">
                </a>
            </div>
        </div>

        <!-- เมนู: แจ้งลา -->
        <a href="<?php echo $base_path; ?>employee/leave.php" class="flex flex-col items-center active:scale-90 transition-transform <?php echo ($current_page == 'leave.php') ? 'text-blue-700 font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <span class="text-[9px] mt-0.5">แจ้งลา</span>
        </a>

        <!-- เมนู: ส่วนตัว -->
        <a href="<?php echo $base_path; ?>employee/profile.php" class="flex flex-col items-center active:scale-90 transition-transform <?php echo ($current_page == 'profile.php') ? 'text-blue-700 font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            <span class="text-[9px] mt-0.5">ส่วนตัว</span>
        </a>

    </div>
</div>