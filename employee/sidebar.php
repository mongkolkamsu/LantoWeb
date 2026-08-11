<?php
// 🎯 ดึงชื่อไฟล์ปัจจุบันมาเช็ก Active Menu อัตโนมัติ
$current_page = basename($_SERVER['PHP_SELF']);

// 🛠️ ปรับ Path อัตโนมัติ (เผื่อเรียกใช้จาก Root หรือโฟลเดอร์ employee)
$prefix = file_exists('config/db.php') ? '' : '../';
?>

<!-- 📁 แถบด้านข้างพนักงาน (Sidebar) -->
<aside class="w-64 bg-white border-r border-slate-200/80 hidden md:flex flex-col justify-between fixed inset-y-0 left-0 z-40 shadow-2xs">
    <div>
        <!-- LOGO HEADER -->
        <div class="p-5 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 bg-slate-50 p-1.5 rounded-xl border border-slate-200/60 shadow-2xs flex items-center justify-center shrink-0">
                <img src="<?php echo $prefix; ?>assets/images/LOGO-Lanto.png" alt="Lanto Logo" class="w-full h-full object-contain" onerror="this.src='<?php echo $prefix; ?>assets/images/Logo.png'">
            </div>
            <div>
                <h1 class="font-extrabold text-xs tracking-wide text-slate-800 leading-tight">Lanto Workspace</h1>
                <p class="text-[9px] text-slate-400 font-medium">Enterprise Workforce</p>
            </div>
        </div>

        <!-- NAV MENUS (ใช้ไอคอนและสไตล์ชุดเดียวกับหน้าพนักงาน) -->
        <div class="p-3 space-y-1.5">
            <p class="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">เมนูนำทาง</p>
            
            <!-- 1. หน้าแรก (Dashboard) -->
            <?php $is_active = in_array($current_page, ['index_pc.php', 'index.php']); ?>
            <a href="<?php echo $prefix; ?>index_pc.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold <?php echo $is_active ? 'text-blue-600 bg-blue-50/80' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?> transition-all group">
                <div class="w-9 h-9 <?php echo $is_active ? 'bg-blue-600 text-white' : 'bg-slate-700 text-white'; ?> rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline stroke-linecap="round" stroke-linejoin="round" points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                </div>
                <span>หน้าแรก (Dashboard)</span>
            </a>

            <!-- 2. สแกนเข้า-ออกงาน -->
            <?php $is_active = ($current_page === 'scan.php'); ?>
            <a href="<?php echo $prefix; ?>employee/scan.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold <?php echo $is_active ? 'text-blue-600 bg-blue-50/80' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?> transition-all group">
                <div class="w-9 h-9 bg-gradient-to-tr from-blue-700 to-indigo-600 text-white rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                    <img src="<?php echo $prefix; ?>assets/images/face-id.png" class="w-6 h-6 shrink-0 object-contain brightness-0 invert" alt="Face ID" onerror="this.onerror=null; this.src='<?php echo $prefix; ?>assets/images/Logo.png';">
                </div>
                <span>สแกนเข้า-ออกงาน</span>
            </a>

            <!-- 3. ประวัติการทำงาน -->
            <?php $is_active = ($current_page === 'history.php'); ?>
            <a href="<?php echo $prefix; ?>employee/history.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold <?php echo $is_active ? 'text-amber-600 bg-amber-50/80' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?> transition-all group">
                <div class="w-9 h-9 bg-amber-500 text-white rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                        <path d="M3 3v5h5" />
                        <path d="M12 7v5l3.5 2" />
                    </svg>
                </div>
                <span>ประวัติการทำงาน</span>
            </a>

            <!-- 4. ยื่นใบแจ้งลา -->
            <?php $is_active = ($current_page === 'leave.php'); ?>
            <a href="<?php echo $prefix; ?>employee/leave.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold <?php echo $is_active ? 'text-emerald-600 bg-emerald-50/80' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?> transition-all group">
                <div class="w-9 h-9 bg-emerald-500 text-white rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <span>ยื่นใบแจ้งลา</span>
            </a>

            <!-- 5. ข้อมูลส่วนตัว / บัตร -->
            <?php $is_active = ($current_page === 'profile.php'); ?>
            <a href="<?php echo $prefix; ?>employee/profile.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold <?php echo $is_active ? 'text-indigo-600 bg-indigo-50/80' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?> transition-all group">
                <div class="w-9 h-9 bg-indigo-500 text-white rounded-xl flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <span>ข้อมูลส่วนตัว / บัตร</span>
            </a>
        </div>
    </div>
</aside>