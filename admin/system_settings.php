<?php
session_start();
require_once '../config/db.php';
require_once '../includes/rounded_dropdown.php';

// 🔑 1. SECURITY LAYER (เปิดให้ Admin, IT Support และ HR เข้าใช้งานได้)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'it_support', 'it', 'hr'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$admin_fullname = $_SESSION['fullname'] ?? 'ผู้ดูแลระบบ';

// 🎯 ฟังก์ชันช่วยเหลือในการดึง/บันทึก system_settings
function getSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :k");
        $stmt->execute(['k' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function saveSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
    $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
}

// 📝 ฟังก์ชันบันทึก Activity Log
function logSystemActivity($pdo, $userId, $action, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:u, :a, :d, :ip)");
        $stmt->execute(['u' => $userId, 'a' => $action, 'd' => $details, 'ip' => $ip]);
    } catch (PDOException $e) {
        // fail-safe
    }
}

// 💾 2. PROCESS POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];
    $active_tab = 'company';

    try {
        if ($act === 'save_company') {
            $active_tab = 'company';
            saveSetting($pdo, 'company_name', trim($_POST['company_name'] ?? ''));
            saveSetting($pdo, 'company_tax_id', trim($_POST['company_tax_id'] ?? ''));
            saveSetting($pdo, 'company_address', trim($_POST['company_address'] ?? ''));
            saveSetting($pdo, 'company_phone', trim($_POST['company_phone'] ?? ''));
            saveSetting($pdo, 'company_email', trim($_POST['company_email'] ?? ''));
            
            logSystemActivity($pdo, $user_id, 'อัปเดตข้อมูลบริษัท', 'แก้ไขข้อมูลทั่วไปของบริษัท');
            $_SESSION['success_msg'] = 'บันทึกข้อมูลบริษัทเรียบร้อยแล้ว';
        }
        elseif ($act === 'save_dept') {
            $active_tab = 'dept';
            $dept_id = $_POST['dept_id'] ?? '';
            $dept_name = trim($_POST['dept_name'] ?? '');
            $head_id = (!empty($_POST['head_user_id']) && $_POST['head_user_id'] !== '0') ? $_POST['head_user_id'] : null;

            if ($dept_id !== '') {
                $stmt = $pdo->prepare("UPDATE departments SET name = :n, head_user_id = :h WHERE id = :id");
                $stmt->execute(['n' => $dept_name, 'h' => $head_id, 'id' => $dept_id]);
                logSystemActivity($pdo, $user_id, 'แก้ไขแผนก', "อัปเดตแผนก: $dept_name (ID: $dept_id)");
                $_SESSION['success_msg'] = 'อัปเดตข้อมูลแผนกเรียบร้อยแล้ว';
            } else {
                $stmt = $pdo->prepare("INSERT INTO departments (name, head_user_id) VALUES (:n, :h)");
                $stmt->execute(['n' => $dept_name, 'h' => $head_id]);
                logSystemActivity($pdo, $user_id, 'เพิ่มแผนกใหม่', "สร้างแผนก: $dept_name");
                $_SESSION['success_msg'] = 'เพิ่มแผนกใหม่เรียบร้อยแล้ว';
            }
        }
        elseif ($act === 'delete_dept') {
            $active_tab = 'dept';
            $dept_id = $_POST['dept_id'] ?? '';
            if (!empty($dept_id)) {
                $pdo->prepare("UPDATE users SET department = NULL WHERE department = :id")->execute(['id' => $dept_id]);
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
                $stmt->execute(['id' => $dept_id]);
                logSystemActivity($pdo, $user_id, 'ลบแผนก', "ลบแผนก ID: $dept_id");
                $_SESSION['success_msg'] = 'ลบแผนกเรียบร้อยแล้ว';
            }
        }
        elseif ($act === 'save_branch') {
            $active_tab = 'branch';
            $b_id = $_POST['branch_id'] ?? '';
            $b_name = trim($_POST['branch_name'] ?? '');
            $lat = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
            $lng = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
            $radius = (int)($_POST['radius'] ?? 100);
            $see_only = isset($_POST['see_only_branch']) ? 1 : 0;

            if ($b_id !== '') {
                $stmt = $pdo->prepare("UPDATE branches SET name = :n, latitude = :lat, longitude = :lng, radius = :r, see_only_branch = :s WHERE id = :id");
                $stmt->execute(['n' => $b_name, 'lat' => $lat, 'lng' => $lng, 'r' => $radius, 's' => $see_only, 'id' => $b_id]);
                logSystemActivity($pdo, $user_id, 'แก้ไขสาขา', "อัปเดตสาขา: $b_name (ID: $b_id)");
                $_SESSION['success_msg'] = 'อัปเดตข้อมูลสาขาเรียบร้อยแล้ว';
            } else {
                $stmt = $pdo->prepare("INSERT INTO branches (name, latitude, longitude, radius, see_only_branch) VALUES (:n, :lat, :lng, :r, :s)");
                $stmt->execute(['n' => $b_name, 'lat' => $lat, 'lng' => $lng, 'r' => $radius, 's' => $see_only]);
                logSystemActivity($pdo, $user_id, 'เพิ่มสาขาใหม่', "สร้างสาขา: $b_name");
                $_SESSION['success_msg'] = 'เพิ่มสาขาใหม่เรียบร้อยแล้ว';
            }
        }
        elseif ($act === 'delete_branch') {
            $active_tab = 'branch';
            $b_id = $_POST['branch_id'] ?? '';
            if (!empty($b_id)) {
                $pdo->prepare("UPDATE users SET branch_id = NULL WHERE branch_id = :id")->execute(['id' => $b_id]);
                $stmt = $pdo->prepare("DELETE FROM branches WHERE id = :id");
                $stmt->execute(['id' => $b_id]);
                logSystemActivity($pdo, $user_id, 'ลบสาขา', "ลบสาขา ID: $b_id");
                $_SESSION['success_msg'] = 'ลบสาขาเรียบร้อยแล้ว';
            }
        }
        elseif ($act === 'save_shift') {
            $active_tab = 'shift';
            $s_id = $_POST['shift_id'] ?? '';
            $s_name = trim($_POST['shift_name'] ?? '');
            $s_start = $_POST['start_time'] ?? '08:30';
            $s_end = $_POST['end_time'] ?? '17:30';

            if ($s_id !== '') {
                $stmt = $pdo->prepare("UPDATE work_shifts SET name = :n, start_time = :st, end_time = :et WHERE id = :id");
                $stmt->execute(['n' => $s_name, 'st' => $s_start, 'et' => $s_end, 'id' => $s_id]);
                logSystemActivity($pdo, $user_id, 'แก้ไขกะงาน', "อัปเดตกะงาน: $s_name");
                $_SESSION['success_msg'] = 'อัปเดตข้อมูลกะงานเรียบร้อยแล้ว';
            } else {
                $stmt = $pdo->prepare("INSERT INTO work_shifts (name, start_time, end_time) VALUES (:n, :st, :et)");
                $stmt->execute(['n' => $s_name, 'st' => $s_start, 'et' => $s_end]);
                logSystemActivity($pdo, $user_id, 'เพิ่มกะงานใหม่', "สร้างกะงาน: $s_name");
                $_SESSION['success_msg'] = 'เพิ่มกะงานใหม่เรียบร้อยแล้ว';
            }
        }
        elseif ($act === 'delete_shift') {
            $active_tab = 'shift';
            $s_id = $_POST['shift_id'] ?? '';
            if (!empty($s_id)) {
                $pdo->prepare("UPDATE users SET work_shift = NULL WHERE work_shift = :id")->execute(['id' => $s_id]);
                $stmt = $pdo->prepare("DELETE FROM work_shifts WHERE id = :id");
                $stmt->execute(['id' => $s_id]);
                logSystemActivity($pdo, $user_id, 'ลบกะงาน', "ลบกะงาน ID: $s_id");
                $_SESSION['success_msg'] = 'ลบกะงานเรียบร้อยแล้ว';
            }
        }
        elseif ($act === 'save_leave') {
            $active_tab = 'leave';
            saveSetting($pdo, 'sick_quota', (int)($_POST['sick_quota'] ?? 30));
            saveSetting($pdo, 'business_quota', (int)($_POST['business_quota'] ?? 6));
            saveSetting($pdo, 'vacation_start_quota', (int)($_POST['vacation_start_quota'] ?? 6));
            saveSetting($pdo, 'vacation_inc_quota', (int)($_POST['vacation_inc_quota'] ?? 1));
            saveSetting($pdo, 'vacation_max_quota', (int)($_POST['vacation_max_quota'] ?? 10));
            saveSetting($pdo, 'vacation_advance_days', (int)($_POST['vacation_advance_days'] ?? 3));
            saveSetting($pdo, 'sick_cert_days', (int)($_POST['sick_cert_days'] ?? 2));
            logSystemActivity($pdo, $user_id, 'แก้ไขโควตาวันลา', 'อัปเดตเงื่อนไขวันลาประจำปี');
            $_SESSION['success_msg'] = 'บันทึกโควตาและเงื่อนไขวันลาเรียบร้อยแล้ว';
        }
        elseif ($act === 'save_notify') {
            $active_tab = 'notify';
            saveSetting($pdo, 'line_notify_enabled', isset($_POST['line_notify_enabled']) ? '1' : '0');
            saveSetting($pdo, 'line_notify_token', trim($_POST['line_notify_token'] ?? ''));
            saveSetting($pdo, 'email_leave_notify', isset($_POST['email_leave_notify']) ? '1' : '0');
            saveSetting($pdo, 'email_payslip_notify', isset($_POST['email_payslip_notify']) ? '1' : '0');
            logSystemActivity($pdo, $user_id, 'แก้ไขการแจ้งเตือน', 'อัปเดต LINE/Email Alerts');
            $_SESSION['success_msg'] = 'บันทึกการตั้งค่าการแจ้งเตือนเรียบร้อยแล้ว';
        }

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

    header("Location: system_settings.php?tab=" . $active_tab);
    exit();
}

$current_tab = $_GET['tab'] ?? 'company';

// 🔍 3. FETCH DATA FROM DB
$dept_list = [];
$branch_list = [];
$shift_list = [];
$users_list = [];
$logs_list = [];

$dept_members_all = $pdo->query("
    SELECT u.id, u.fullname, u.employee_code, u.profile_image, u.department, u.role, b.name AS branch_name
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.is_active = 1
    ORDER BY u.fullname ASC
")->fetchAll(PDO::FETCH_ASSOC);

try {
    $dept_list = $pdo->query("
        SELECT d.*, u.fullname AS head_name, u.employee_code AS head_code,
               (SELECT COUNT(*) FROM users WHERE department = d.id) AS member_count
        FROM departments d
        LEFT JOIN users u ON d.head_user_id = u.id
        ORDER BY d.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $branch_list = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $shift_list = $pdo->query("SELECT * FROM work_shifts ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $users_list = $pdo->query("SELECT id, fullname, employee_code FROM users WHERE is_active = 1 ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($role === 'admin') {
        $logs_list = $pdo->query("
            SELECT l.*, u.fullname, u.employee_code, u.role
            FROM system_logs l
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY l.id DESC LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

$dept_head_options = [
    ['id' => '', 'name' => '-- ยังไม่กำหนดหัวหน้าแผนก --']
];
foreach ($users_list as $u) {
    $dept_head_options[] = [
        'id'   => (string)$u['id'],
        'name' => htmlspecialchars($u['fullname']) . ' (ID: ' . htmlspecialchars($u['employee_code']) . ')'
    ];
}

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - Lanto Web Management System</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- 🗺️ Leaflet.js Maps CDN -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        /* ป้องกัน Tailwind CSS บีบรูปภาพแผนที่ Leaflet จนแสดงผลเป็นสีขาว */
        .leaflet-container img {
            max-width: none !important;
            max-height: none !important;
        }
        #branchMap {
            min-height: 220px !important;
        }
    </style>
</head>
<body class="bg-[#f4f6fa] text-slate-800 antialiased flex flex-col md:flex-row min-h-screen md:h-screen md:overflow-hidden">

    <?php
    // 🎯 ดึงชื่อไฟล์ปัจจุบันมาเช็ก Active Menu อัตโนมัติ
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>

    <!-- 👤 SIDEBAR NAVIGATION (Light & Clean Theme) -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- 💻 2. MAIN WORKSPACE -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full min-h-screen md:h-screen overflow-y-auto space-y-4 sm:space-y-6 pb-20 md:pb-8">
        
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">ตั้งค่าระบบองค์กร</h1>
                <p class="text-slate-400 text-xs mt-0.5 font-medium">จัดการโครงสร้างบริษัท แผนก สาขา เวลาทำงาน เงื่อนไข และการแจ้งเตือน</p>
            </div>
            <div class="bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-2xs text-xs font-bold text-slate-600 self-start sm:self-auto">
                สวัสดีคุณ, <span class="text-blue-600 font-extrabold"><?php echo htmlspecialchars($admin_fullname); ?></span> 👋
            </div>
        </div>

        <!-- 📑 3. MAIN UI TABS HEADER -->
        <div class="bg-white p-2 rounded-2xl border border-slate-200/80 shadow-2xs overflow-x-auto">
            <div class="flex items-center gap-2 min-w-max text-xs font-bold">
                <button type="button" onclick="switchSettingTab('company')" id="tab_btn_company" class="tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-blue-600 text-white border border-blue-600 shadow-md shadow-blue-500/20 font-extrabold">
                    <span>🏢</span> ข้อมูลบริษัท
                </button>
                <button type="button" onclick="switchSettingTab('dept')" id="tab_btn_dept" class="tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/80 font-bold">
                    <span>📁</span> ข้อมูลแผนก & หัวหน้า
                </button>
                <button type="button" onclick="switchSettingTab('branch')" id="tab_btn_branch" class="tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/80 font-bold">
                    <span>📍</span> สาขา & สิทธิ์การมองเห็น
                </button>
                <button type="button" onclick="switchSettingTab('shift')" id="tab_btn_shift" class="tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/80 font-bold">
                    <span>⏰</span> เวลาทำงาน (กะงาน)
                </button>
                <button type="button" onclick="switchSettingTab('leave')" id="tab_btn_leave" class="tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/80 font-bold">
                    <span>🏖️</span> เงื่อนไข & โควตาวันลา
                </button>
                <button type="button" onclick="switchSettingTab('notify')" id="tab_btn_notify" class="tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/80 font-bold">
                    <span>🔔</span> ตั้งค่าการแจ้งเตือน
                </button>

                <?php if ($role === 'admin'): ?>
                <button type="button" onclick="switchSettingTab('logs')" id="tab_btn_logs" class="tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/80 font-bold">
                    <span>📜</span> ประวัติกิจกรรมระบบ (System Logs)
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- 📦 4. TABS CONTENT CONTAINERS -->
        <div class="space-y-6">

            <!-- 🏢 TAB 1: ข้อมูลบริษัท -->
            <div id="tab_content_company" class="tab-content space-y-4">
                <form method="POST" action="system_settings.php" class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4 max-w-4xl">
                    <input type="hidden" name="action" value="save_company">
                    <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-800 flex items-center gap-2"><span>🏢</span> ข้อมูลทั่วไปของบริษัท / องค์กร</h3>
                            <p class="text-[11px] text-slate-400">ใช้แสดงบนเอกสารใบลา สลิปเงินเดือน และรายงานต่าง ๆ</p>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md shadow-blue-500/20 active:scale-95 cursor-pointer">
                            💾 บันทึกข้อมูลบริษัท
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">ชื่อบริษัท / องค์กร <span class="text-rose-500">*</span></label>
                            <input type="text" name="company_name" value="<?php echo htmlspecialchars(getSetting($pdo, 'company_name')); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500">
                        </div>

                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">เลขประจำตัวผู้เสียภาษี</label>
                            <input type="text" name="company_tax_id" value="<?php echo htmlspecialchars(getSetting($pdo, 'company_tax_id')); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500">
                        </div>

                        <div class="space-y-1 md:col-span-2">
                            <label class="font-bold text-slate-700">ที่อยู่สำนักงานใหญ่</label>
                            <textarea name="company_address" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-3 font-semibold text-slate-800 focus:outline-none focus:border-blue-500"><?php echo htmlspecialchars(getSetting($pdo, 'company_address')); ?></textarea>
                        </div>

                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">เบอร์โทรศัพท์ติดต่อ</label>
                            <input type="text" name="company_phone" value="<?php echo htmlspecialchars(getSetting($pdo, 'company_phone')); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500">
                        </div>

                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">อีเมลกลาง / ติดต่อ HR</label>
                            <input type="email" name="company_email" value="<?php echo htmlspecialchars(getSetting($pdo, 'company_email')); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                </form>
            </div>

            <!-- 📁 TAB 2: ข้อมูลแผนก & หัวหน้าแผนก -->
            <div id="tab_content_dept" class="tab-content hidden space-y-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-800 flex items-center gap-2"><span>📁</span> รายชื่อแผนก และการกำหนดหัวหน้าแผนก</h3>
                            <p class="text-[11px] text-slate-400">หัวหน้าแผนกจะมีสิทธิ์พิจารณาอนุมัติใบลาใน Step 2 ของสมาชิกในแผนก (คลิกที่แถบเพื่อแก้ไข/ลบ)</p>
                        </div>
                        <button type="button" onclick="openDeptModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md shadow-blue-500/20 active:scale-95 cursor-pointer flex items-center gap-1.5">
                            <span>➕</span> เพิ่มแผนกใหม่
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 font-semibold border-b border-slate-100 uppercase tracking-wider">
                                    <th class="p-3.5 w-16">ID</th>
                                    <th class="p-3.5">ชื่อแผนก</th>
                                    <th class="p-3.5">หัวหน้าแผนก (Approver Step 2)</th>
                                    <th class="p-3.5 text-center w-28">จำนวนสมาชิก</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <?php if (empty($dept_list)): ?>
                                    <tr><td colspan="4" class="p-4 text-center text-slate-400">ยังไม่มีข้อมูลแผนก</td></tr>
                                <?php else: ?>
                                    <?php foreach ($dept_list as $d): ?>
                                    <tr onclick="openDeptModal(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['name'])); ?>', '<?php echo $d['head_user_id'] ?? ''; ?>')" 
                                        class="hover:bg-blue-50/50 transition-colors cursor-pointer active:scale-[0.998]">
                                        <td class="p-3.5 font-extrabold text-slate-500"><?php echo $d['id']; ?></td>
                                        <td class="p-3.5 font-bold text-slate-800"><?php echo htmlspecialchars($d['name']); ?></td>
                                        <td class="p-3.5 font-bold text-blue-600">
                                            <?php echo !empty($d['head_name']) ? htmlspecialchars($d['head_name']) . ' (ID: ' . htmlspecialchars($d['head_code']) . ')' : '<span class="text-slate-400 font-normal">ยังไม่ได้กำหนด</span>'; ?>
                                        </td>
                                        <td class="p-3.5 text-center font-bold text-slate-600"><?php echo $d['member_count']; ?> คน</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 📍 TAB 3: ตั้งค่าสาขา & ขอบเขตข้อมูล -->
            <div id="tab_content_branch" class="tab-content hidden space-y-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-800 flex items-center gap-2"><span>📍</span> ตั้งค่าสาขา & การจำกัดการมองเห็นข้อมูล (Branch Scope)</h3>
                            <p class="text-[11px] text-slate-400">กำหนดพิกัด GPS สำหรับลงเวลาเข้างาน และสิทธิ์การเห็นข้อมูลเฉพาะคนในสาขาตัวเอง (คลิกที่แถบเพื่อแก้ไข/ลบ)</p>
                        </div>
                        <button type="button" onclick="openBranchModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md shadow-blue-500/20 active:scale-95 cursor-pointer flex items-center gap-1.5">
                            <span>➕</span> เพิ่มสาขาใหม่
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 font-semibold border-b border-slate-100 uppercase tracking-wider">
                                    <th class="p-3.5">ชื่อสาขา</th>
                                    <th class="p-3.5">พิกัด GPS (Lat, Long)</th>
                                    <th class="p-3.5">รัศมีเช็กอิน (เมตร)</th>
                                    <th class="p-3.5 text-center">การจำกัดสิทธิ์สาขา</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <?php if (empty($branch_list)): ?>
                                    <tr><td colspan="4" class="p-4 text-center text-slate-400">ยังไม่มีข้อมูลสาขา</td></tr>
                                <?php else: ?>
                                    <?php foreach ($branch_list as $b): ?>
                                    <tr onclick="openBranchModal(<?php echo $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['name'])); ?>', '<?php echo $b['latitude'] ?? ''; ?>', '<?php echo $b['longitude'] ?? ''; ?>', <?php echo $b['radius'] ?? 100; ?>, <?php echo $b['see_only_branch'] ?? 1; ?>)" 
                                        class="hover:bg-blue-50/50 transition-colors cursor-pointer active:scale-[0.998]">
                                        <td class="p-3.5 font-bold text-slate-800">
                                            <?php echo htmlspecialchars($b['name']); ?>
                                            <?php if (!empty($b['is_default'])): ?><span class="ml-1 text-[9px] bg-blue-100 text-blue-700 font-extrabold px-1.5 py-0.5 rounded">Default</span><?php endif; ?>
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-600">
                                            <?php echo ($b['latitude'] && $b['longitude']) ? "{$b['latitude']}, {$b['longitude']}" : '<span class="text-slate-400 font-normal">ไม่ระบุพิกัด (WFH/นอกสถานที่)</span>'; ?>
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-700"><?php echo $b['radius']; ?> เมตร</td>
                                        <td class="p-3.5 text-center">
                                            <?php if (!empty($b['see_only_branch'])): ?>
                                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-bold border border-emerald-200/80 rounded-lg">🔒 เห็นเฉพาะคนในสาขา</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 font-bold border border-slate-200 rounded-lg">🌐 เห็นข้อมูลทั้งหมด</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ⏰ TAB 4: ตั้งค่ากะเวลาการทำงาน -->
            <div id="tab_content_shift" class="tab-content hidden space-y-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4 max-w-4xl">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-800 flex items-center gap-2"><span>⏰</span> ตั้งค่ากะเวลาการทำงาน (Work Shifts)</h3>
                            <p class="text-[11px] text-slate-400">กำหนดเวลาเข้างาน-เลิกงาน สำหรับคำนวณสถานะการสแกนเข้าออกงาน (คลิกที่แถบเพื่อแก้ไข/ลบ)</p>
                        </div>
                        <button type="button" onclick="openShiftModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md shadow-blue-500/20 active:scale-95 cursor-pointer flex items-center gap-1.5">
                            <span>➕</span> เพิ่มกะงานใหม่
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 font-semibold border-b border-slate-100 uppercase tracking-wider">
                                    <th class="p-3.5">ชื่อกะงาน</th>
                                    <th class="p-3.5">เวลาเข้า - เลิกงาน</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <?php if (empty($shift_list)): ?>
                                    <tr><td colspan="2" class="p-4 text-center text-slate-400">ยังไม่มีข้อมูลกะงาน</td></tr>
                                <?php else: ?>
                                    <?php foreach ($shift_list as $s): ?>
                                    <tr onclick="openShiftModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>', '<?php echo $s['start_time']; ?>', '<?php echo $s['end_time']; ?>')" 
                                        class="hover:bg-blue-50/50 transition-colors cursor-pointer active:scale-[0.998]">
                                        <td class="p-3.5 font-bold text-slate-800"><?php echo htmlspecialchars($s['name']); ?></td>
                                        <td class="p-3.5 font-extrabold text-blue-600">
                                            <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?> น.
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 🏖️ TAB 5: เงื่อนไข & โควตาวันลา -->
            <div id="tab_content_leave" class="tab-content hidden space-y-4">
                <form method="POST" action="system_settings.php" class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4 max-w-4xl">
                    <input type="hidden" name="action" value="save_leave">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-800 flex items-center gap-2"><span>🏖️</span> ตั้งค่าสิทธิ์โควตาวันลาประจำปี และเงื่อนไขยื่นใบลา</h3>
                            <p class="text-[11px] text-slate-400">กำหนดจำนวนวันลาเริ่มต้นประจำปี และกฎเกณฑ์การส่งใบลา</p>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md shadow-blue-500/20 active:scale-95 cursor-pointer">
                            💾 บันทึกเงื่อนไข
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/60 space-y-2">
                            <span class="font-bold text-slate-700 flex items-center gap-1.5">🤒 โควตาลาป่วย (วัน/ปี)</span>
                            <input type="number" name="sick_quota" value="<?php echo htmlspecialchars(getSetting($pdo, 'sick_quota', '30')); ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 font-bold text-slate-800">
                        </div>

                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/60 space-y-2">
                            <span class="font-bold text-slate-700 flex items-center gap-1.5">💼 โควตาลากิจ (วัน/ปี)</span>
                            <input type="number" name="business_quota" value="<?php echo htmlspecialchars(getSetting($pdo, 'business_quota', '6')); ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2 font-bold text-slate-800">
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/60 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-800 flex items-center gap-1.5 text-xs">
                                <span>🏖️</span> เงื่อนไขการคำนวณโควตาลาพักร้อน (ตามอายุงาน)
                            </span>
                            <span class="px-2.5 py-0.5 bg-blue-50 text-blue-700 font-bold text-[10px] rounded-full border border-blue-200">✨ กำหนดเกณฑ์ได้</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">เริ่มต้นเมื่อครบ 1 ปี (วัน)</label>
                                <input type="number" name="vacation_start_quota" value="<?php echo htmlspecialchars(getSetting($pdo, 'vacation_start_quota', '6')); ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">เพิ่มขึ้นต่อปี (วัน)</label>
                                <input type="number" name="vacation_inc_quota" value="<?php echo htmlspecialchars(getSetting($pdo, 'vacation_inc_quota', '1')); ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 font-bold text-blue-600 focus:outline-none focus:border-blue-500">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">สิทธิ์สูงสุดไม่เกิน (วัน)</label>
                                <input type="number" name="vacation_max_quota" value="<?php echo htmlspecialchars(getSetting($pdo, 'vacation_max_quota', '10')); ?>" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 font-bold text-emerald-600 focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 italic">* ตัวเลข 3 ช่องนี้จะถูกนำไปคำนวณสิทธิ์พักร้อนของพนักงานแต่ละคนโดยอัตโนมัติตาม "วันที่เริ่มงาน" (start_date)</p>
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-3 text-xs">
                        <h4 class="font-bold text-slate-800">📌 กฎเกณฑ์เพิ่มเติม</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="font-semibold text-slate-600">ต้องยื่นลาพักร้อนล่วงหน้าอย่างน้อย (วัน)</label>
                                <input type="number" name="vacation_advance_days" value="<?php echo htmlspecialchars(getSetting($pdo, 'vacation_advance_days', '3')); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 font-semibold">
                            </div>
                            <div class="space-y-1">
                                <label class="font-semibold text-slate-600">บังคับแนบใบรับรองแพทย์เมื่อลาป่วยติดต่อกันเกิน (วัน)</label>
                                <input type="number" name="sick_cert_days" value="<?php echo htmlspecialchars(getSetting($pdo, 'sick_cert_days', '2')); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 font-semibold">
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 🔔 TAB 6: ตั้งค่าการแจ้งเตือน -->
            <div id="tab_content_notify" class="tab-content hidden space-y-4">
                <form method="POST" action="system_settings.php" class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-5 max-w-4xl">
                    <input type="hidden" name="action" value="save_notify">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-800 flex items-center gap-2"><span>🔔</span> ตั้งค่าช่องทางการแจ้งเตือนระบบ (Notification Channels)</h3>
                            <p class="text-[11px] text-slate-400">กำหนดการส่งการแจ้งเตือนเรื่องการยื่นใบลา และการอนุมัติผ่าน LINE & Email</p>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md shadow-blue-500/20 active:scale-95 cursor-pointer">
                            💾 บันทึกการตั้งค่า
                        </button>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 bg-emerald-500 text-white rounded-xl flex items-center justify-center font-bold text-sm">💬</span>
                                <div>
                                    <h4 class="font-bold text-slate-800">การแจ้งเตือนผ่าน LINE Notify</h4>
                                    <p class="text-[10px] text-slate-400">ส่งข้อความเตือนไปยังกลุ่มไลน์เมื่อมีใบลาใหม่</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="line_notify_enabled" value="1" <?php echo getSetting($pdo, 'line_notify_enabled', '1') === '1' ? 'checked' : ''; ?> class="sr-only peer">
                                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">LINE Notify Token (กลุ่มหลัก)</label>
                                <input type="text" name="line_notify_token" value="<?php echo htmlspecialchars(getSetting($pdo, 'line_notify_token', '')); ?>" placeholder="วาง Token ที่นี่..." class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 font-bold text-[11px]">
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 bg-blue-600 text-white rounded-xl flex items-center justify-center font-bold text-sm">✉️</span>
                                <div>
                                    <h4 class="font-bold text-slate-800">การแจ้งเตือนผ่านอีเมล (Email Notifications)</h4>
                                    <p class="text-[10px] text-slate-400">ส่งอีเมลผลการอนุมัติใบลา และสลิปเงินเดือนให้พนักงาน</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 pt-1 border-t border-slate-200/60">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="email_leave_notify" value="1" <?php echo getSetting($pdo, 'email_leave_notify', '1') === '1' ? 'checked' : ''; ?> class="w-4 h-4 text-blue-600 rounded border-slate-300">
                                <span class="font-semibold text-slate-700">ส่งอีเมลถึงพนักงานเมื่อใบลาได้รับการอนุมัติ / ปฏิเสธ</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="email_payslip_notify" value="1" <?php echo getSetting($pdo, 'email_payslip_notify', '1') === '1' ? 'checked' : ''; ?> class="w-4 h-4 text-blue-600 rounded border-slate-300">
                                <span class="font-semibold text-slate-700">ส่งอีเมลแจ้งเตือนเมื่อมีสลิปเงินเดือนประจำเดือนออกใหม่</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 📜 TAB 7: ประวัติกิจกรรมระบบ (System Logs - แสดงเฉพาะ Admin เท่านั้น) -->
            <?php if ($role === 'admin'): ?>
            <div id="tab_content_logs" class="tab-content hidden space-y-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-800 flex items-center gap-2"><span>📜</span> บันทึกประวัติกิจกรรมทั้งหมดในระบบ (System Logs)</h3>
                            <p class="text-[11px] text-slate-400">แสดงเฉพาะสิทธิ์แอดมิน เพื่อตรวจสอบว่าผู้ใดเข้ามาดำเนินการสิ่งใดบ้างในระบบ</p>
                        </div>
                        <span class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-xl text-[11px] font-bold">🔒 สิทธิ์เฉพาะ Admin เท่านั้น</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 font-semibold border-b border-slate-100 uppercase tracking-wider">
                                    <th class="p-3.5 w-16">ID</th>
                                    <th class="p-3.5">ผู้ดำเนินการ</th>
                                    <th class="p-3.5">กิจกรรม / การกระทำ</th>
                                    <th class="p-3.5">รายละเอียดเพิ่มเติม</th>
                                    <th class="p-3.5">IP Address</th>
                                    <th class="p-3.5 text-right">วันและเวลา</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <?php if (empty($logs_list)): ?>
                                    <tr><td colspan="6" class="p-4 text-center text-slate-400">ยังไม่มีประวัติกิจกรรมในระบบ</td></tr>
                                <?php else: ?>
                                    <?php foreach ($logs_list as $l): ?>
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="p-3.5 font-extrabold text-slate-400">#<?php echo $l['id']; ?></td>
                                        <td class="p-3.5 font-bold text-slate-800">
                                            <?php echo htmlspecialchars($l['fullname'] ?? 'ผู้ดูแลระบบ'); ?>
                                            <span class="text-[10px] text-slate-400 font-normal block">ID: <?php echo htmlspecialchars($l['employee_code'] ?? '-'); ?></span>
                                        </td>
                                        <td class="p-3.5 font-bold text-blue-600"><?php echo htmlspecialchars($l['action']); ?></td>
                                        <td class="p-3.5 text-slate-600"><?php echo htmlspecialchars($l['details'] ?? '-'); ?></td>
                                        <td class="p-3.5 font-bold text-[11px] text-slate-500"><?php echo htmlspecialchars($l['ip_address'] ?? '127.0.0.1'); ?></td>
                                        <td class="p-3.5 text-right font-semibold text-slate-500"><?php echo date('d/m/Y H:i:s', strtotime($l['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </main>

    <!-- 📌 ดึงไฟล์ Modals ทั้งหมดของ System Settings มาใช้งาน -->
    <?php include '../includes/modal_system_settings.php'; ?>

    <!-- 🔔 Alerts Script -->
    <script src="../assets/js/alerts.js"></script>

    <!-- 🎯 JavaScript สลับ Tab, เปิด Modal, ปักหมุดแผนที่ Leaflet และลบข้อมูล -->
    <script>
        let activeItemData = { type: '', id: '', name: '' };
        
        // ตัวแปรควบคุมแผนที่ Leaflet.js
        let leafletBranchMap = null;
        let branchMarker = null;
        let branchCircle = null;

        // แก้ไขปัญหารูปหมุด Leaflet หายเมื่อดึงผ่าน CDN
        if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = '<?php echo htmlspecialchars($current_tab); ?>';
            switchSettingTab(activeTab);

            <?php if (!empty($success_msg)): ?>
                if (window.LantoAlert) LantoAlert.success('ทำรายการสำเร็จ', '<?php echo addslashes($success_msg); ?>');
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                if (window.LantoAlert) LantoAlert.error('เกิดข้อผิดพลาด', '<?php echo addslashes($error_msg); ?>');
            <?php endif; ?>
        });

        function switchSettingTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.className = "tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/80 font-bold";
            });

            const targetContent = document.getElementById('tab_content_' + tabName);
            if (targetContent) targetContent.classList.remove('hidden');

            const targetBtn = document.getElementById('tab_btn_' + tabName);
            if (targetBtn) targetBtn.className = "tab-btn px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer bg-blue-600 text-white border border-blue-600 shadow-md shadow-blue-500/20 font-extrabold";
        }

        // 🎯 ฟังก์ชันเปิด Modal แผนก
        function openDeptModal(id = '', name = '', headId = '') {
            document.getElementById('dept_id_input').value = id;
            document.getElementById('dept_name_input').value = name;
            document.getElementById('head_user_id_select').value = headId || '';

            const deleteBtn = document.getElementById('btn_delete_dept');
            if (id) {
                activeItemData = { type: 'dept', id: id, name: name };
                if (deleteBtn) deleteBtn.classList.remove('hidden');
            } else {
                activeItemData = { type: '', id: '', name: '' };
                if (deleteBtn) deleteBtn.classList.add('hidden');
            }

            document.getElementById('deptModal').classList.remove('hidden');
        }

        // 🎯 ฟังก์ชันเปิด Modal สาขา
        function openBranchModal(id = '', name = '', lat = '', lng = '', radius = 100, seeOnly = 1) {
            document.getElementById('branch_id_input').value = id;
            document.getElementById('branch_name_input').value = name;
            document.getElementById('lat_input').value = lat;
            document.getElementById('lng_input').value = lng;
            document.getElementById('radius_input').value = radius;
            document.getElementById('see_only_branch_checkbox').checked = (seeOnly == 1);

            const deleteBtn = document.getElementById('btn_delete_branch');
            if (id) {
                activeItemData = { type: 'branch', id: id, name: name };
                if (deleteBtn) deleteBtn.classList.remove('hidden');
            } else {
                activeItemData = { type: '', id: '', name: '' };
                if (deleteBtn) deleteBtn.classList.add('hidden');
            }

            // 1. แสดง Modal ขึ้นมาก่อน
            document.getElementById('branchModal').classList.remove('hidden');

            // 2. หน่วงเวลาเพื่อให้ Modal ขยายตัวเต็มที่ แล้วค่อยสร้างแผนที่
            setTimeout(() => {
                initBranchMap(lat, lng, radius);
            }, 200);
        }

        // 🗺️ ฟังก์ชันสร้างแผนที่ Leaflet (ล้างของเก่าแล้วสร้างใหม่ ป้องกัน crash)
        function initBranchMap(latVal, lngVal, radiusVal) {
            let lat = parseFloat(latVal) || 13.756331; // Default: กทม.
            let lng = parseFloat(lngVal) || 100.501862;
            let radius = parseInt(radiusVal) || 100;

            const mapContainer = document.getElementById('branchMap');
            if (!mapContainer) return;

            // ล้าง Object แผนที่เดิม ป้องกัน Error "Map container is already initialized"
            if (leafletBranchMap !== null) {
                leafletBranchMap.remove();
                leafletBranchMap = null;
            }

            // สร้างแผนที่ใหม่
            leafletBranchMap = L.map('branchMap', {
                center: [lat, lng],
                zoom: 15
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(leafletBranchMap);

            // คำนวณขนาดภาพให้เต็มกล่อง
            setTimeout(() => {
                if (leafletBranchMap) leafletBranchMap.invalidateSize();
            }, 100);

            // วาดหมุดและวงกลม
            drawMarkerAndCircle(lat, lng, radius);

            // ดักจับการคลิกเพื่อย้ายหมุด
            leafletBranchMap.on('click', function(e) {
                setBranchLocationOnMap(e.latlng.lat, e.latlng.lng);
            });
        }

        // 🗺️ ฟังก์ชันวาดหมุด + วงกลมรัศมี
        function drawMarkerAndCircle(lat, lng, radius) {
            if (!leafletBranchMap) return;

            if (branchMarker) leafletBranchMap.removeLayer(branchMarker);
            if (branchCircle) leafletBranchMap.removeLayer(branchCircle);

            branchMarker = L.marker([lat, lng], { draggable: true }).addTo(leafletBranchMap);
            branchCircle = L.circle([lat, lng], {
                color: '#2563eb',
                fillColor: '#3b82f6',
                fillOpacity: 0.2,
                radius: radius
            }).addTo(leafletBranchMap);

            branchMarker.on('dragend', function(e) {
                const position = branchMarker.getLatLng();
                setBranchLocationOnMap(position.lat, position.lng);
            });
        }

        // 🗺️ อัปเดตพิกัดลงในช่อง Input
        function setBranchLocationOnMap(lat, lng) {
            const latFixed = lat.toFixed(6);
            const lngFixed = lng.toFixed(6);

            document.getElementById('lat_input').value = latFixed;
            document.getElementById('lng_input').value = lngFixed;

            const radius = parseInt(document.getElementById('radius_input').value) || 100;
            drawMarkerAndCircle(lat, lng, radius);
        }

        // 🗺️ อัปเดตตำแหน่งแผนที่เมื่อพิมพ์แก้ไขพิกัดใน Input เอง
        function updateMapFromInputs() {
            const lat = parseFloat(document.getElementById('lat_input').value);
            const lng = parseFloat(document.getElementById('lng_input').value);
            const radius = parseInt(document.getElementById('radius_input').value) || 100;

            if (!isNaN(lat) && !isNaN(lng) && leafletBranchMap) {
                leafletBranchMap.setView([lat, lng], 15);
                drawMarkerAndCircle(lat, lng, radius);
            }
        }

        // 🎯 ดึงตำแหน่ง GPS ปัจจุบันของผู้ใช้
        function getCurrentLocationForBranch() {
            if (navigator.geolocation) {
                if (window.LantoAlert) LantoAlert.loading('กำลังค้นหาพิกัด...', 'โปรดอนุญาตสิทธิ์ระบุตำแหน่งบนมือถือ/คอมพิวเตอร์');
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        if (window.LantoAlert) LantoAlert.close();
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        setBranchLocationOnMap(lat, lng);
                        if (leafletBranchMap) leafletBranchMap.setView([lat, lng], 16);
                        
                        if (window.LantoAlert) LantoAlert.success('ปักหมุดสำเร็จ', 'ดึงตำแหน่งปัจจุบันของคุณเรียบร้อยแล้ว');
                    },
                    function(error) {
                        if (window.LantoAlert) LantoAlert.close();
                        if (window.LantoAlert) LantoAlert.error('ไม่สามารถดึงพิกัดได้', 'กรุณาเปิด GPS หรืออนุญาตการเข้าถึงตำแหน่งพิกัดในเบราว์เซอร์');
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            } else {
                if (window.LantoAlert) LantoAlert.error('ไม่รองรับ GPS', 'อุปกรณ์ของคุณไม่รองรับการระบุตำแหน่ง GPS');
            }
        }

        function openShiftModal(id = '', name = '', start = '08:30', end = '17:30') {
            document.getElementById('shift_id_input').value = id;
            document.getElementById('shift_name_input').value = name;
            document.getElementById('start_time_input').value = start;
            document.getElementById('end_time_input').value = end;

            const deleteBtn = document.getElementById('btn_delete_shift');
            if (id) {
                activeItemData = { type: 'shift', id: id, name: name };
                if (deleteBtn) deleteBtn.classList.remove('hidden');
            } else {
                activeItemData = { type: '', id: '', name: '' };
                if (deleteBtn) deleteBtn.classList.add('hidden');
            }

            document.getElementById('shiftModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            const m = document.getElementById(modalId);
            if (m) m.classList.add('hidden');
        }

        function deleteFromModal(type) {
            if (!activeItemData || !activeItemData.id) return;
            deleteItem(type, activeItemData.id, activeItemData.name);
        }

        function deleteItem(type, id, name) {
            let label = '';
            let actionName = '';
            let idFieldName = '';

            if (type === 'dept') {
                label = 'แผนก';
                actionName = 'delete_dept';
                idFieldName = 'dept_id';
            } else if (type === 'branch') {
                label = 'สาขา';
                actionName = 'delete_branch';
                idFieldName = 'branch_id';
            } else if (type === 'shift') {
                label = 'กะงาน';
                actionName = 'delete_shift';
                idFieldName = 'shift_id';
            }

            const titleText = `ยืนยันการลบ${label}?`;
            const messageText = `คุณต้องการลบ${label} "${name}" ใช่หรือไม่?\n(พนักงานที่สังกัดกลุ่มนี้จะถูกปรับสถานะเป็นไม่ระบุ)`;

            if (window.LantoAlert && typeof LantoAlert.confirm === 'function') {
                LantoAlert.confirm(
                    titleText,
                    messageText,
                    function() {
                        submitDeleteForm(actionName, idFieldName, id);
                    },
                    null,
                    'danger'
                );
            } else {
                if (confirm(`${titleText}\n\n${messageText}`)) {
                    submitDeleteForm(actionName, idFieldName, id);
                }
            }
        }

        function submitDeleteForm(actionName, idFieldName, id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'system_settings.php';

            const inputAct = document.createElement('input');
            inputAct.type = 'hidden';
            inputAct.name = 'action';
            inputAct.value = actionName;
            form.appendChild(inputAct);

            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = idFieldName;
            inputId.value = id;
            form.appendChild(inputId);

            document.body.appendChild(form);
            form.submit();
        }
        
        const allDeptMembers = <?php echo json_encode($dept_members_all ?? []); ?>;
    </script>
</body>
</html>