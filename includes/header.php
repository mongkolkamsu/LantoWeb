<?php
// 🛠️ Auto Detect Path Prefix
$prefix = $prefix ?? (file_exists('config/db.php') ? '' : '../');

// ดึงข้อมูลผู้ใช้จาก Session
$user_id       = $_SESSION['user_id'] ?? null;
$fullname      = $_SESSION['fullname'] ?? 'ไม่ระบุชื่อ';
$employee_code = $_SESSION['employee_code'] ?? '-';
$profile_image = $_SESSION['profile_image'] ?? '';
$avatar_url    = !empty($profile_image) 
    ? $prefix . 'uploads/profiles/' . htmlspecialchars($profile_image, ENT_QUOTES, 'UTF-8') 
    : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&h=150&q=80';

// ดึงจำนวนการแจ้งเตือนยังไม่ได้อ่าน
$unread_notifications_count = 0;
if (isset($pdo) && $user_id) {
    try {
        $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt_notif->execute(['user_id' => $user_id]);
        $unread_notifications_count = (int)$stmt_notif->fetchColumn();
    } catch (PDOException $e) {}
}

// กำหนดหัวข้อหลัก, หัวข้อย่อย และปุ่มย้อนกลับ
$page_title    = $page_title ?? 'Lanto Workspace';
$page_subtitle = $page_subtitle ?? 'Enterprise Workforce System';
$show_back     = $show_back ?? true;
$is_mobile = (isset($_GET['view']) && $_GET['view'] === 'mobile') || 
             (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(android|iphone|ipod|mobile)/i', $_SERVER['HTTP_USER_AGENT']));

$default_index = $prefix . ($is_mobile ? 'index_mobile.php' : 'index_pc.php');

$back_url     = $back_url ?? $default_index;
$employee_url = $default_index;
?>

<!-- 🔝 Header ด้านบน -->
<header class="bg-white border-b border-slate-200/80 px-4 sm:px-6 lg:px-8 py-3.5 shadow-2xs sticky top-0 z-30 w-full">
    <div class="max-w-7xl mx-auto flex justify-between items-center w-full">
        <!-- ฝั่งซ้าย: ปุ่มย้อนกลับ + ชื่อหน้า -->
        <div class="flex items-center gap-3">
            <?php if ($show_back): ?>
                <a href="<?php echo $back_url; ?>" class="p-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-slate-600 transition-colors cursor-pointer shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
            <?php else: ?>
                <div class="w-10 h-10 bg-slate-50 p-1.5 rounded-xl border border-slate-200/60 shadow-2xs flex items-center justify-center shrink-0">
                    <img src="<?php echo $prefix; ?>assets/images/LOGO-Lanto.png" alt="Lanto Logo" class="w-full h-full object-contain" onerror="this.src='<?php echo $prefix; ?>assets/images/Logo.png'">
                </div>
            <?php endif; ?>
            <div>
                <h1 class="font-extrabold text-sm tracking-wide text-slate-800"><?php echo htmlspecialchars($page_title); ?></h1>
                <p class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($page_subtitle); ?></p>
            </div>
        </div>

        <!-- ฝั่งขวา: ปุ่มแจ้งเตือน + ปุ่มโปรไฟล์ Dropdown -->
        <div class="flex items-center gap-3">
            
            <!-- 🔔 ปุ่มแจ้งเตือน -->
            <button type="button" onclick="LantoAlert.warning('การแจ้งเตือน', '<?php echo $unread_notifications_count > 0 ? "คุณมี ".$unread_notifications_count." รายการแจ้งเตือนใหม่" : "ขณะนี้ยังไม่มีรายการแจ้งเตือนใหม่ครับ"; ?>')" class="relative p-2.5 bg-slate-100 hover:bg-slate-200 rounded-full text-slate-600 transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"></path></svg>
                <?php if ($unread_notifications_count > 0): ?>
                <span class="absolute top-1 right-1 h-2.5 w-2.5 bg-rose-500 rounded-full border-2 border-white"></span>
                <?php endif; ?>
            </button>

            <!-- 👤 Profile Dropdown Container -->
            <div class="relative inline-block pl-3 border-l border-slate-200" id="profile-dropdown-container">
                <button type="button" onclick="toggleProfileDropdown(event)" class="flex items-center gap-2.5 hover:bg-slate-50 p-1.5 rounded-2xl transition-all cursor-pointer">
                    <img src="<?php echo $avatar_url; ?>" class="w-9 h-9 rounded-full object-cover border border-slate-200 shadow-2xs">
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold text-slate-800 leading-tight flex items-center gap-1">
                            <?php echo htmlspecialchars($fullname); ?>
                            <svg class="w-3 h-3 text-slate-400 transition-transform duration-200" id="profile-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </p>
                        <p class="text-[10px] text-slate-400 font-medium">รหัส: <?php echo htmlspecialchars($employee_code); ?></p>
                    </div>
                </button>

                <!-- Dropdown Menu -->
                <div id="profile-menu" class="hidden absolute right-0 top-full mt-3 w-52 bg-white border border-slate-200/80 rounded-2xl shadow-xl z-50 p-1.5 space-y-0.5">
                    <div class="absolute -top-[7px] right-6 w-3.5 h-3.5 bg-white border-t border-l border-slate-200/80 rotate-45 z-0"></div>
                    
                    <a href="<?php echo $employee_url; ?>" class="relative z-10 flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 hover:bg-purple-50 hover:text-purple-600 transition-colors">
                        <span>🏢</span> หน้าหลักพนักงาน
                    </a>
                    
                    <a href="<?php echo $prefix; ?>employee/profile.php" class="relative z-10 flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                        <span>👤</span> ข้อมูลส่วนตัว / บัตร
                    </a>

                    <!-- 🎯 เมนูประวัติการใช้งาน (แยกตามระบบรถยนต์ และ แมสเซนเจอร์) -->
                    <?php 
                        $current_page = basename($_SERVER['PHP_SELF']);
                        $is_car_system = in_array($current_page, ['car_index.php', 'car_history.php']);
                        $is_msg_system = in_array($current_page, ['jobs.php', 'index.php', 'history.php']);

                        if ($is_car_system || $is_msg_system):
                            $history_link = $is_car_system ? 'car_history.php' : 'history.php';
                            $history_label = $is_car_system ? 'ประวัติและตารางใช้รถ' : 'ประวัติงาน';
                    ?>
                        <a href="<?php echo $history_link; ?>" class="relative z-10 flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                            <span>📋</span> <?php echo $history_label; ?>
                        </a>
                    <?php endif; ?>

                    <div class="border-t border-slate-100 my-1 relative z-10"></div>
                    
                    <a href="<?php echo $prefix; ?>logout.php" class="relative z-10 flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors">
                        <span>🚪</span> ออกจากระบบ
                    </a>
                </div>
            </div>

        </div>
    </div>
</header>

<script>
function toggleProfileDropdown(e) {
    e.stopPropagation();
    const menu = document.getElementById('profile-menu');
    const arrow = document.getElementById('profile-arrow');
    if (menu) {
        menu.classList.toggle('hidden');
        if (arrow) arrow.classList.toggle('rotate-180');
    }
}

document.addEventListener('click', function(e) {
    const container = document.getElementById('profile-dropdown-container');
    const menu = document.getElementById('profile-menu');
    const arrow = document.getElementById('profile-arrow');
    if (container && menu && !container.contains(e.target)) {
        menu.classList.add('hidden');
        if (arrow) arrow.classList.remove('rotate-180');
    }
});
</script>