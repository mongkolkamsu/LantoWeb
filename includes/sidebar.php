<?php
// 🎯 ระบบดึงชื่อหน้าปัจจุบันมาเช็ก Active Menu อัตโนมัติ
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- 📱 แถบ Header ปุ่มเมนูเฉพาะบนมือถือ -->
<div class="md:hidden bg-slate-900 text-white p-4 flex justify-between items-center sticky top-0 z-40 shadow-md">
    <div class="flex items-center gap-2">
        <span class="text-base">⚙️</span>
        <span class="font-extrabold text-sm tracking-wide">Lanto Admin System</span>
    </div>
    <button type="button" onclick="document.getElementById('adminSidebar').classList.toggle('hidden')" class="px-3 py-1.5 bg-slate-800 rounded-xl text-xs font-bold text-slate-300 hover:text-white cursor-pointer active:scale-95">
        🍔 เมนู
    </button>
</div>
<!-- 👤 SIDEBAR NAVIGATION (Modern Clean with Standard Lucide Vector Icons) -->
<aside id="adminSidebar" class="hidden md:flex flex-col w-full md:w-64 bg-white border-b md:border-b-0 md:border-r border-slate-200/80 p-4 space-y-4 shrink-0 transition-all">
    <div>
        <!-- LOGO HEADER -->
        <div class="p-5 flex items-center gap-3 border-b border-slate-100">
            <img src="../assets/images/LOGO-Lanto.png" alt="Lanto Logo" class="w-10 h-10 object-contain rounded-xl shrink-0">
            <div>
                <h2 class="text-slate-900 font-extrabold text-sm tracking-wide leading-tight">Lanto Workforce</h2>
                <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wider mt-0.5">Admin Portal</p>
            </div>
        </div>

        <!-- NAV MENUS -->
        <nav class="p-3.5 space-y-1 text-xs font-semibold">
            
            <!-- ปุ่มกลับหน้าหลักพนักงาน (ไอคอน Smartphone) -->
            <a href="../index_mobile.php" class="flex items-center gap-3 px-3.5 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200/80 rounded-2xl transition-all font-bold mb-3 shadow-3xs active:scale-95 group">
                <div class="w-7 h-7 bg-emerald-500 text-white rounded-xl flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                        <line x1="12" y1="18" x2="12.01" y2="18" stroke-width="3"></line>
                    </svg>
                </div>
                <span>กลับหน้าหลักพนักงาน</span>
            </a>

            <!-- 1. แดชบอร์ดภาพรวม (ไอคอน Dashboard Grid) -->
            <?php $active = ($current_page == 'dashboard.php'); ?>
            <a href="dashboard.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl transition-all duration-200 <?php echo $active ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'; ?>">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 transition-all <?php echo $active ? 'bg-white/20 text-white' : 'bg-blue-100 text-blue-600'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="9" rx="1"></rect>
                        <rect x="14" y="3" width="7" height="5" rx="1"></rect>
                        <rect x="14" y="12" width="7" height="9" rx="1"></rect>
                        <rect x="3" y="16" width="7" height="5" rx="1"></rect>
                    </svg>
                </div>
                <span>แดชบอร์ดภาพรวม</span>
            </a>

            <!-- 2. พนักงานทั้งหมด (ไอคอน Users Group) -->
            <?php $active = ($current_page == 'manage_employees.php'); ?>
            <a href="manage_employees.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl transition-all duration-200 <?php echo $active ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'; ?>">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 transition-all <?php echo $active ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-600'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <span>พนักงานทั้งหมด</span>
            </a>

            <!-- 3. ประวัติการเข้าออกงาน (ไอคอน Clock) -->
            <?php $active = ($current_page == 'attendance_history.php'); ?>
            <a href="attendance_history.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl transition-all duration-200 <?php echo $active ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'; ?>">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 transition-all <?php echo $active ? 'bg-white/20 text-white' : 'bg-teal-100 text-teal-600'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <span>ประวัติการเข้าออกงาน</span>
            </a>

            <!-- 4. คำร้องอนุมัติใบลา (ไอคอน Document Text) -->
            <?php if (isset($role) && ($role === 'admin' || $role === 'hr')): ?>
            <?php $active = ($current_page == 'manage_leaves.php'); ?>
            <a href="manage_leaves.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl transition-all duration-200 <?php echo $active ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'; ?>">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 transition-all <?php echo $active ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-600'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <span>คำร้องอนุมัติใบลา</span>
            </a>

            <!-- 5. บริหารสลิปเงินเดือน (ไอคอน Banknote) -->
            <?php $active = ($current_page == 'manage_salaries.php'); ?>
            <a href="manage_salaries.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl transition-all duration-200 <?php echo $active ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'; ?>">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 transition-all <?php echo $active ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-600'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                        <circle cx="12" cy="12" r="2"></circle>
                        <path d="M6 12h.01M18 12h.01"></path>
                    </svg>
                </div>
                <span>บริหารสลิปเงินเดือน</span>
            </a>
            <?php endif; ?>

            <!-- 6. จัดการเคสแจ้งปัญหา IT (ไอคอน Monitor/Display) -->
            <?php if (isset($role) && ($role === 'admin' || $role === 'it_support')): ?>
            <?php $active = ($current_page == 'manage_it_tickets.php'); ?>
            <a href="manage_it_tickets.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl transition-all duration-200 <?php echo $active ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'; ?>">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 transition-all <?php echo $active ? 'bg-white/20 text-white' : 'bg-violet-100 text-violet-600'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <span>จัดการเคสแจ้งปัญหา IT</span>
            </a>
            <?php endif; ?>

            <!-- 7. เมนูตั้งค่าระบบ (ไอคอน Cog/Settings) -->
            <?php $active = ($current_page == 'system_settings.php'); ?>
            <a href="system_settings.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl transition-all duration-200 <?php echo $active ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'; ?>">
                <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 transition-all <?php echo $active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </div>
                <span>เมนูตั้งค่าระบบ</span>
            </a>

        </nav>
    </div>

    <!-- FOOTER BUTTONS -->
    <div class="p-4 border-t border-slate-100 bg-slate-50/50 space-y-1">
        <a href="#" class="flex items-center justify-center gap-2 w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-2xl transition-all shadow-md shadow-blue-500/20 active:scale-95">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
            </svg>
            <span>คู่มือการใช้งาน</span>
        </a>
        <a href="../logout.php" class="flex items-center justify-center gap-1.5 w-full py-2 text-slate-400 hover:text-rose-600 text-[11px] font-bold transition-colors rounded-xl">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>ออกจากระบบ</span>
        </a>
    </div>
</aside>