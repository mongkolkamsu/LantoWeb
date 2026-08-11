<?php
session_start();
require_once 'config/db.php';

// 🔑 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 🎯 ตั้งค่าส่วนหัวและปุ่มย้อนกลับ
$page_title    = 'ข้อมูลองค์กรและระเบียบปฏิบัติ';
$page_subtitle = 'โครงสร้างบริษัท แผนกงาน สาขา และข้อบังคับเกี่ยวกับการทำงาน';
$show_back     = true;
$back_url      = (isset($_GET['from']) && $_GET['from'] === 'mobile') ? 'index_mobile.php' : 'index_pc.php';

// 🎯 ฟังก์ชันดึงค่าจาก system_settings
function getSetting($pdo, $key, $default = '-') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :k");
        $stmt->execute(['k' => $key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && trim($val) !== '') ? $val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// 🏢 2. ดึงข้อมูลบริษัทจากฐานข้อมูล
$company_name    = getSetting($pdo, 'company_name', 'บริษัท แลนโต เทคโนโลยี จำกัด');
$company_tax_id  = getSetting($pdo, 'company_tax_id', '0105550000000');
$company_address = getSetting($pdo, 'company_address', '123/45 สำนักงานใหญ่ กรุงเทพมหานคร 10900');
$company_phone   = getSetting($pdo, 'company_phone', '02-123-4567');
$company_email   = getSetting($pdo, 'company_email', 'contact@lantoglobal.com');

$sick_quota      = getSetting($pdo, 'sick_quota', '30');
$business_quota  = getSetting($pdo, 'business_quota', '3');
$vacation_start  = getSetting($pdo, 'vacation_start_quota', '6');

// 📁 3. ดึงข้อมูลแผนกและหัวหน้าแผนก
$departments = [];
try {
    $stmt_dept = $pdo->query("
        SELECT d.*, 
               CONCAT(u.first_name, ' ', u.last_name) AS head_name,
               u.employee_code AS head_code,
               u.profile_image AS head_avatar,
               (SELECT COUNT(*) FROM users WHERE department = d.id AND is_active = 1) AS member_count
        FROM departments d
        LEFT JOIN users u ON d.head_user_id = u.id
        ORDER BY d.sort_order ASC, d.id ASC
    ");
    $departments = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// 📍 4. ดึงข้อมูลสาขา
$branches = [];
try {
    $stmt_branch = $pdo->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY id ASC");
    $branches = $stmt_branch->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $branches = [];
}

// 👔 5. ดึงข้อมูลตำแหน่งงาน
$positions = [];
try {
    $stmt_pos = $pdo->query("
        SELECT p.*, 
               (SELECT COUNT(*) FROM users WHERE position_id = p.id AND is_active = 1) AS member_count
        FROM positions p
        ORDER BY p.sort_order ASC, p.id ASC
    ");
    $positions = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $positions = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลบริษัท - Lanto Workspace</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen text-slate-800 antialiased pb-12">

    <!-- 🔝 Header ด้านบนส่วนกลาง -->
    <?php include_once 'includes/header.php'; ?>

    <!-- เนื้อหาหลัก -->
    <main class="p-4 sm:p-6 lg:p-10 space-y-6 max-w-7xl mx-auto w-full">

        <!-- 🏢 บล็อก 1: แบนเนอร์โปรไฟล์บริษัทหลัก (โทนสีฟ้าพรีเมียม) -->
        <div class="bg-gradient-to-r from-white via-slate-50 to-blue-50/50 rounded-3xl p-6 sm:p-8 text-slate-800 shadow-xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6 border border-slate-200/80">
            
            <div class="absolute -top-16 -left-16 w-56 h-56 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-16 -right-16 w-56 h-56 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col sm:flex-row items-center gap-5 z-10 text-center sm:text-left">
                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-white p-3 rounded-2xl shadow-md border border-blue-200/80 flex items-center justify-center shrink-0">
                    <img src="assets/images/LOGO-Lanto.png" alt="Lanto Logo" class="w-full h-full object-contain" onerror="this.src='assets/images/Logo.png'">
                </div>

                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <span class="px-3 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-100 text-blue-700 border border-blue-200 uppercase tracking-wider">
                            🏢 Lanto Group
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
                            🟢 สถานะสถานประกอบการ: ปกติ
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-wide text-slate-900"><?php echo htmlspecialchars($company_name); ?></h1>
                    <p class="text-xs text-slate-600 font-medium flex items-center justify-center sm:justify-start gap-1.5">
                        <span class="text-blue-500">📍</span> <?php echo htmlspecialchars($company_address); ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 w-full md:w-auto shrink-0 z-10 text-xs">
                <div class="bg-white/90 backdrop-blur-md px-4 py-3 rounded-2xl border border-slate-200/80 text-center sm:text-left shadow-2xs">
                    <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">เลขประจำตัวผู้เสียภาษี</p>
                    <p class="text-sm font-extrabold text-slate-800 mt-0.5"><?php echo htmlspecialchars($company_tax_id); ?></p>
                </div>
                <div class="bg-white/90 backdrop-blur-md px-4 py-3 rounded-2xl border border-slate-200/80 text-center sm:text-left shadow-2xs">
                    <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">เบอร์โทรศัพท์สายตรง</p>
                    <p class="text-sm font-extrabold text-slate-800 mt-0.5"><?php echo htmlspecialchars($company_phone); ?></p>
                </div>
            </div>
        </div>

        <!-- ℹ️ บล็อก 2: การ์ดรวมข้อมูลรายละเอียดองค์กรสด -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-bold shrink-0">✉️</div>
                <div class="truncate">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">อีเมลติดต่อฝ่าย HR</p>
                    <p class="text-xs font-extrabold text-slate-800 truncate mt-0.5"><?php echo htmlspecialchars($company_email); ?></p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold shrink-0">🌐</div>
                <div class="truncate">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">เว็บไซต์องค์กร</p>
                    <p class="text-xs font-extrabold text-blue-600 truncate mt-0.5">www.lantoglobal.com</p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold shrink-0">⏰</div>
                <div class="truncate">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">เวลาทำการสำนักงาน</p>
                    <p class="text-xs font-extrabold text-slate-800 truncate mt-0.5">จันทร์ - ศุกร์ (08:30 - 17:30 น.)</p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg font-bold shrink-0">🏬</div>
                <div class="truncate">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">จำนวนสาขาเปิดบริการ</p>
                    <p class="text-xs font-extrabold text-sky-600 truncate mt-0.5"><?php echo count($branches); ?> สาขาทั่วประเทศ</p>
                </div>
            </div>
        </div>

        <!-- 📊 บล็อก 3: เลย์เอาต์ตารางผังแผนก ตำแหน่ง สาขา และระเบียบวันลา -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- ฝั่งซ้าย 2 คอลัมน์: ผังแผนก และ ตำแหน่งงาน -->
            <div class="md:col-span-2 space-y-6">

                <!-- 📁 รายชื่อแผนก & หัวหน้างาน -->
                <div class="bg-white rounded-3xl p-6 shadow-2xs border border-slate-200/80 space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            <span>📁</span> โครงสร้างแผนกงาน (Departments)
                        </h3>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full border border-blue-100">
                            <?php echo count($departments); ?> แผนก
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[380px] overflow-y-auto pr-1">
                        <?php if (empty($departments)): ?>
                            <div class="col-span-2 text-center py-8 text-slate-400 text-xs font-light">ยังไม่มีการบันทึกข้อมูลแผนก</div>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): 
                                $head_avatar = !empty($dept['head_avatar']) ? 'uploads/profiles/' . $dept['head_avatar'] : '';
                            ?>
                            <div class="bg-slate-50/80 p-3.5 rounded-2xl border border-slate-200/60 space-y-2.5 hover:border-blue-300 transition-colors">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-extrabold text-slate-800 text-xs truncate max-w-[150px]"><?php echo htmlspecialchars($dept['name']); ?></h4>
                                    <span class="text-[10px] font-bold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-slate-200 shrink-0">
                                        👥 <?php echo $dept['member_count']; ?> คน
                                    </span>
                                </div>

                                <div class="pt-2 border-t border-slate-200/60 flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 font-bold flex items-center justify-center text-[10px] shrink-0 overflow-hidden">
                                        <?php if (!empty($head_avatar)): ?>
                                            <img src="<?php echo htmlspecialchars($head_avatar); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            👤
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[11px] truncate">
                                        <p class="text-[9.5px] text-slate-400 font-medium">หัวหน้าแผนก (Approver)</p>
                                        <p class="font-bold text-slate-700 truncate">
                                            <?php echo !empty($dept['head_name']) ? htmlspecialchars($dept['head_name']) : 'ยังไม่ได้กำหนด'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 👔 รายชื่อตำแหน่งงานในองค์กร -->
                <div class="bg-white rounded-3xl p-6 shadow-2xs border border-slate-200/80 space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            <span>👔</span> ตำแหน่งงาน (Positions)
                        </h3>
                        <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-100">
                            <?php echo count($positions); ?> ตำแหน่ง
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-52 overflow-y-auto pr-1">
                        <?php if (empty($positions)): ?>
                            <div class="col-span-2 text-center py-8 text-slate-400 text-xs font-light">ไม่มีข้อมูลตำแหน่ง</div>
                        <?php else: ?>
                            <?php foreach ($positions as $pos): ?>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-200/60 text-xs font-semibold">
                                <span class="text-slate-800 truncate"><?php echo htmlspecialchars($pos['name']); ?></span>
                                <span class="text-[10px] text-slate-500 font-bold bg-white px-2.5 py-1 rounded-xl border border-slate-200 shrink-0">
                                    <?php echo $pos['member_count']; ?> คน
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- ฝั่งขวา 1 คอลัมน์: ระเบียบปฏิบัติ / สิทธิ์วันลา และ สาขา (Branches) -->
            <div class="space-y-6">

                <!-- 🏖️ สรุปโควตาวันลาและสิทธิ์พนักงาน -->
                <div class="bg-white rounded-3xl p-6 shadow-2xs border border-slate-200/80 space-y-4">
                    <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b border-slate-100 pb-3">
                        <span>🏖️</span> โควตาสิทธิ์วันลาประจำปี
                    </h3>

                    <div class="space-y-2.5 text-xs font-medium">
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/60 flex items-center justify-between">
                            <span class="flex items-center gap-2 font-bold text-slate-700">🤒 สิทธิ์ลาป่วย</span>
                            <span class="font-extrabold text-blue-600 bg-white px-2.5 py-1 rounded-xl border border-slate-200"><?php echo htmlspecialchars($sick_quota); ?> วัน/ปี</span>
                        </div>

                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/60 flex items-center justify-between">
                            <span class="flex items-center gap-2 font-bold text-slate-700">💼 สิทธิ์ลากิจ</span>
                            <span class="font-extrabold text-blue-600 bg-white px-2.5 py-1 rounded-xl border border-slate-200"><?php echo htmlspecialchars($business_quota); ?> วัน/ปี</span>
                        </div>

                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/60 flex items-center justify-between">
                            <span class="flex items-center gap-2 font-bold text-slate-700">🏖️ ลาพักร้อนเริ่มต้น</span>
                            <span class="font-extrabold text-emerald-600 bg-white px-2.5 py-1 rounded-xl border border-slate-200"><?php echo htmlspecialchars($vacation_start); ?> วัน/ปี</span>
                        </div>
                    </div>

                    <div class="p-3.5 bg-blue-50/60 border border-blue-100 rounded-2xl text-[11px] text-blue-900 leading-relaxed font-medium">
                        💡 สิทธิ์วันลาพักร้อนจะคำนวณเพิ่มขึ้นโดยอัตโนมัติตามอายุงานนับจาก "วันที่เริ่มงาน" ของพนักงานแต่ละท่าน
                    </div>
                </div>

                <!-- 📍 สาขาและสถานที่ปฏิบัติงาน -->
                <div class="bg-white rounded-3xl p-6 shadow-2xs border border-slate-200/80 space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            <span>📍</span> สาขา (Branches)
                        </h3>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">
                            <?php echo count($branches); ?> สาขา
                        </span>
                    </div>

                    <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1">
                        <?php if (empty($branches)): ?>
                            <div class="text-center py-8 text-slate-400 text-xs font-light">ยังไม่มีการบันทึกข้อมูลสาขา</div>
                        <?php else: ?>
                            <?php foreach ($branches as $b): ?>
                            <div class="bg-slate-50/80 p-3.5 rounded-2xl border border-slate-200/60 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-extrabold text-slate-800 text-xs flex items-center gap-1.5 truncate">
                                        <span>🏢</span> <?php echo htmlspecialchars($b['name']); ?>
                                    </h4>
                                    <?php if (!empty($b['is_default'])): ?>
                                        <span class="text-[9px] bg-blue-100 text-blue-700 font-extrabold px-1.5 py-0.5 rounded shrink-0">สำนักงานใหญ่</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-[10.5px] text-slate-500 font-medium truncate">
                                    📍 พิกัด: <?php echo ($b['latitude'] && $b['longitude']) ? htmlspecialchars($b['latitude'] . ', ' . $b['longitude']) : 'ไม่ล็อกพิกัด'; ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

    </main>

</body>
</html>