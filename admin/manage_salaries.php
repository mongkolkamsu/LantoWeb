<?php
session_start();
require_once '../config/db.php';
require_once '../includes/rounded_dropdown.php';
require_once '../config/auth.php';
// 🔑 1. SECURITY LAYER
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$admin_fullname = $_SESSION['fullname'] ?? 'ผู้ดูแลระบบ';

// 🎯 ข้อความแจ้งเตือน
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// 📅 ค่าตัวกรอง
$selected_month = $_GET['month'] ?? date('m');
$selected_year  = $_GET['year'] ?? date('Y');
$search_emp     = trim($_GET['search'] ?? '');
$sort_order     = $_GET['sort'] ?? '';

// ตัวเลือกเดือนและปีสำหรับ Dropdown
$months_options = [
    ['id' => '01', 'name' => 'มกราคม'], ['id' => '02', 'name' => 'กุมภาพันธ์'],
    ['id' => '03', 'name' => 'มีนาคม'], ['id' => '04', 'name' => 'เมษายน'],
    ['id' => '05', 'name' => 'พฤษภาคม'], ['id' => '06', 'name' => 'มิถุนายน'],
    ['id' => '07', 'name' => 'กรกฎาคม'], ['id' => '08', 'name' => 'สิงหาคม'],
    ['id' => '09', 'name' => 'กันยายน'], ['id' => '10', 'name' => 'ตุลาคม'],
    ['id' => '11', 'name' => 'พฤศจิกายน'], ['id' => '12', 'name' => 'ธันวาคม']
];

// 🎯 ตัวเลือกปี แสดงเฉพาะ พ.ศ. (เช่น 2569)
$current_y = (int)date('Y');
$years_options = [];
for ($y = $current_y; $y >= $current_y - 3; $y--) {
    $years_options[] = [
        'id'   => (string)$y, 
        'name' => (string)($y + 543) // แสดงเฉพาะ พ.ศ.
    ];
}

$active_month_label = 'กรกฎาคม';
foreach ($months_options as $m) {
    if ($m['id'] === sprintf("%02d", $selected_month)) { $active_month_label = $m['name']; break; }
}
$active_year_label = (string)($selected_year + 543); // แสดงเฉพาะ พ.ศ.

// 💾 2. PROCESS POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    try {
        if ($act === 'save_payslip') {
            $emp_id        = $_POST['employee_id'] ?? '';
            $pay_month     = sprintf("%02d", intval($_POST['payslip_month'] ?? $selected_month));
            $pay_year      = (string)($_POST['payslip_year'] ?? $selected_year);
            $salary_amount = floatval($_POST['salary_amount'] ?? 0);
            $pdf_filename  = '';

            if (empty($emp_id)) {
                throw new Exception('กรุณาเลือกพนักงาน');
            }

            // จัดการอัปโหลดไฟล์ PDF สลิปเงินเดือน
            if (isset($_FILES['payslip_pdf']) && $_FILES['payslip_pdf']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['payslip_pdf']['name'], PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $pdf_filename = 'payslip_' . $emp_id . '_' . $pay_year . $pay_month . '_' . time() . '.pdf';
                    if (!is_dir('../uploads/payslips')) {
                        mkdir('../uploads/payslips', 0777, true);
                    }
                    move_uploaded_file($_FILES['payslip_pdf']['tmp_name'], '../uploads/payslips/' . $pdf_filename);
                } else {
                    throw new Exception('กรุณาอัปโหลดไฟล์นามสกุล .pdf เท่านั้น');
                }
            } else {
                throw new Exception('กรุณาแนบไฟล์สลิปเงินเดือน PDF');
            }

            // ตรวจสอบว่ามีสลิปของพนักงานคนนี้ในงวดเดือน/ปีดังกล่าวหรือยัง
            $chk_stmt = $pdo->prepare("SELECT id FROM salaries WHERE employee_id = :emp_id AND month = :m AND year = :y");
            $chk_stmt->execute(['emp_id' => $emp_id, 'm' => $pay_month, 'y' => $pay_year]);
            $existing_slip = $chk_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_slip) {
                // อัปเดตข้อมูลเดิม
                $stmt_up = $pdo->prepare("
                    UPDATE salaries SET net_pay = :net_pay, pdf_file = :pdf_file, is_published = 0 
                    WHERE id = :id
                ");
                $stmt_up->execute([
                    'net_pay'  => $salary_amount,
                    'pdf_file' => $pdf_filename,
                    'id'       => $existing_slip['id']
                ]);
            } else {
                // บันทึกใหม่
                $stmt_ins = $pdo->prepare("
                    INSERT INTO salaries (employee_id, month, year, net_pay, pdf_file, is_published)
                    VALUES (:emp_id, :month, :year, :net_pay, :pdf_file, 0)
                ");
                $stmt_ins->execute([
                    'emp_id'   => $emp_id,
                    'month'    => $pay_month,
                    'year'     => $pay_year,
                    'net_pay'  => $salary_amount,
                    'pdf_file' => $pdf_filename
                ]);
            }

            $_SESSION['success_msg'] = 'บันทึกสลิปเงินเดือนและอัปโหลดไฟล์เรียบร้อยแล้ว';
            
            // Redirect ไปที่งวดเดือน/ปีที่เพิ่งบันทึก
            header("Location: manage_salaries.php?month=$pay_month&year=$pay_year");
            exit();
        }
        elseif ($act === 'publish_payslips') {
            $ids = $_POST['employee_ids'] ?? [];
            if (!empty($ids)) {
                $in_clause = implode(',', array_map('intval', $ids));
                $stmt_pub = $pdo->prepare("UPDATE salaries SET is_published = 1 WHERE employee_id IN ($in_clause) AND month = :m AND year = :y");
                $stmt_pub->execute(['m' => sprintf("%02d", $selected_month), 'y' => (string)$selected_year]);
                $_SESSION['success_msg'] = 'ปล่อยสลิปเงินเดือนเข้าแอปพลิเคชันพนักงานสำเร็จ';
            } else {
                $_SESSION['error_msg'] = 'กรุณาเลือกพนักงานที่ต้องการปล่อยสลิป';
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

    header("Location: manage_salaries.php?month=$selected_month&year=$selected_year");
    exit();
}

// 🔍 3. FETCH USERS & SALARY LIST
$employees_list = [];
try {
    $sql = "
        SELECT u.id, u.employee_code, CONCAT(u.first_name, ' ', u.last_name) AS fullname, u.email, u.profile_image, 
               d.name AS dept_name, b.name AS branch_name,
               s.id AS salary_id, s.net_pay, s.pdf_file, s.is_published, s.created_at AS slip_uploaded_at
        FROM users u
        LEFT JOIN departments d ON u.department = d.id
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN salaries s ON u.id = s.employee_id AND s.month = :m_sql AND s.year = :y_sql
        WHERE u.role != 'admin'
    ";
    $params = [
        'm_sql' => sprintf("%02d", $selected_month),
        'y_sql' => (string)$selected_year
    ];

    if ($search_emp !== '') {
        $sql .= " AND (CONCAT(u.first_name, ' ', u.last_name) AS fullname LIKE :s1 OR u.employee_code LIKE :s2)";
        $params['s1'] = "%{$search_emp}%";
        $params['s2'] = "%{$search_emp}%";
    }

    if ($sort_order === 'asc') {
        $sql .= " ORDER BY u.employee_code ASC";
    } elseif ($sort_order === 'desc') {
        $sql .= " ORDER BY u.employee_code DESC";
    } else {
        $sql .= " ORDER BY u.id DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // fail-safe
}

$next_sort = ($sort_order === 'asc') ? 'desc' : 'asc';
$sort_link_params = $_GET;
$sort_link_params['sort'] = $next_sort;
$sort_url = 'manage_salaries.php?' . http_build_query($sort_link_params);

$page_title    = 'บริหารสลิปเงินเดือน (Payroll & Payslips)';
$page_subtitle = 'คำนวณ ออกสลิปเงินเดือนประจำงวด และปล่อยสลิปเข้าแอปพลิเคชันพนักงาน';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บริหารสลิปเงินเดือน - Lanto Web Management System</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#f4f6fa] text-slate-800 antialiased flex flex-col md:flex-row min-h-screen md:h-screen md:overflow-hidden">

    <?php
    // 🎯 ดึงชื่อไฟล์ปัจจุบันมาเช็ก Active Menu อัตโนมัติ
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>

    <!-- 👤 SIDEBAR NAVIGATION (Light & Clean Theme) -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- 💻 WORKSPACE WRAPPER ฝั่งขวา -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">

        <!-- 🔝 HEADER ADMIN -->
        <?php include_once '../includes/header_admin.php'; ?>

    <!-- 💻 2. MAIN WORKSPACE -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full min-h-screen md:h-screen overflow-y-auto space-y-4 sm:space-y-6 pb-20 md:pb-8">

        <!-- 📊 3. KPI SUMMARY CARDS (สไตล์ Clean Box แบบ system_settings.php) -->
        <div class="bg-white p-2.5 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                
                <!-- Card 1: ยอดทำจ่ายรวมสุทธิ -->
                <div class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border bg-slate-50 text-slate-700 border-slate-200/80 font-bold">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">💵</span>
                        <div>
                            <p class="text-[11px] text-slate-500 uppercase tracking-wider">ยอดทำจ่ายรวมสุทธิ</p>
                            <p class="text-[10px] text-emerald-600 font-medium">งวดประจำเดือน <?php echo $active_month_label; ?></p>
                        </div>
                    </div>
                    <span class="text-xl font-black tracking-tight text-emerald-700">
                        <?php 
                            $total_sum = 0;
                            foreach($employees_list as $e) { if(!empty($e['net_pay'])) $total_sum += $e['net_pay']; }
                            echo '฿ ' . number_format($total_sum, 2);
                        ?>
                    </span>
                </div>

                <!-- Card 2: สลิปที่ยังไม่สร้าง -->
                <div class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border bg-slate-50 text-slate-700 border-slate-200/80 font-bold">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🔻</span>
                        <div>
                            <p class="text-[11px] text-slate-500 uppercase tracking-wider">สลิปที่ยังไม่สร้าง</p>
                            <p class="text-[10px] text-rose-500 font-medium">ยังไม่ได้อัปโหลดสลิปงวดนี้</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight text-rose-600">
                        <?php 
                            $missing_count = 0;
                            foreach($employees_list as $e) { if(empty($e['salary_id'])) $missing_count++; }
                            echo $missing_count . ' คน';
                        ?>
                    </span>
                </div>

                <!-- Card 3: ปล่อยเข้าแอปมือถือแล้ว -->
                <div class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border bg-slate-50 text-slate-700 border-slate-200/80 font-bold">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📲</span>
                        <div>
                            <p class="text-[11px] text-slate-500 uppercase tracking-wider">ปล่อยเข้าแอปแล้ว</p>
                            <p class="text-[10px] text-blue-600 font-medium">สถานะเผยแพร่สลิป</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight text-blue-600">
                        <?php 
                            $published_count = 0;
                            foreach($employees_list as $e) { if(!empty($e['is_published']) && $e['is_published'] == 1) $published_count++; }
                            echo $published_count . ' / ' . count($employees_list);
                        ?>
                    </span>
                </div>

                <!-- Card 4: เงินเดือนเฉลี่ย/คน -->
                <div class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border bg-slate-50 text-slate-700 border-slate-200/80 font-bold">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📈</span>
                        <div>
                            <p class="text-[11px] text-slate-500 uppercase tracking-wider">เงินเดือนเฉลี่ย/คน</p>
                            <p class="text-[10px] text-indigo-500 font-medium">คิดจากพนักงานประจำงวด</p>
                        </div>
                    </div>
                    <span class="text-xl font-black tracking-tight text-slate-800">
                        <?php 
                            $cnt = count($employees_list);
                            $avg = $cnt > 0 ? $total_sum / $cnt : 0;
                            echo '฿ ' . number_format($avg, 2);
                        ?>
                    </span>
                </div>

            </div>
        </div>

        <!-- 🔎 4. FILTER & SEARCH BAR (ตัวกรองแบบ Rounded Dropdown และ พ.ศ.) -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col md:flex-row justify-between items-center gap-3">
            <form method="GET" action="manage_salaries.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto text-xs">
                
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_order); ?>">

                <div class="w-36">
                    <?php renderRoundedDropdown('month_select', 'month', $active_month_label, $months_options, sprintf("%02d", $selected_month)); ?>
                </div>

                <div class="w-32">
                    <?php renderRoundedDropdown('year_select', 'year', $active_year_label, $years_options, (string)$selected_year); ?>
                </div>

                <div class="w-full sm:w-100">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_emp); ?>" placeholder="ค้นหาชื่อ หรือ รหัสพนักงาน..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 font-medium focus:outline-none focus:border-blue-500 transition-colors h-10">
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-colors cursor-pointer active:scale-95 h-10 flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
                        </svg>
                        <span>ค้นหา</span>
                    </button>
                    <a href="?" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-3.5 py-2.5 rounded-xl transition-colors h-10 flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                        </svg>
                        <span>ล้างค่า</span>
                    </a>
                </div>
            </form>

            <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                <button type="button" onclick="publishSelectedPayslips()" class="bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 text-xs font-bold px-4 py-2.5 rounded-xl transition-colors cursor-pointer flex items-center gap-1.5 shadow-3xs">
                    <span>📲</span> ปล่อยสลิปเข้าแอป (Publish)
                </button>
            </div>
        </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="openCreatePayslipModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md shadow-blue-500/20 active:scale-95 cursor-pointer flex items-center gap-1.5">
                    <span>➕</span> สร้างสลิปประจำงวด
                </button>
            </div>

        <!-- 📑 5. PAYSLIP DATA TABLE -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 font-semibold border-b border-slate-100 uppercase tracking-wider">
                            <th class="p-3.5 w-10 text-center">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" class="w-4 h-4 text-blue-600 rounded border-slate-300 cursor-pointer">
                            </th>
                            <th class="p-3.5">วันที่อัพโหลด</th>

                            <th class="p-3.5 cursor-pointer select-none hover:text-slate-700 transition-colors">
                                <a href="<?php echo $sort_url; ?>" class="flex items-center gap-1.5 font-bold text-slate-400 hover:text-blue-600">
                                    <span>รหัสพนักงาน</span>
                                    <span class="text-[14px]">
                                        <?php 
                                            if ($sort_order === 'asc') echo '⬆';
                                            elseif ($sort_order === 'desc') echo '⬇';
                                            else echo '⭥';
                                        ?>
                                    </span>
                                </a>
                            </th>

                            <th class="p-3.5">ชื่อ-นามสกุล</th>
                            <th class="p-3.5">แผนก</th>
                            <th class="p-3.5 text-right">เงินเดือนที่ได้รับ</th>
                            <th class="p-3.5 text-center">สถานะ</th>
                            <th class="p-3.5 text-center">สลิป PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($employees_list)): ?>
                            <tr><td colspan="8" class="p-10 text-center text-slate-400 font-light">🚫 ไม่พบข้อมูลพนักงานในระบบ</td></tr>
                        <?php else: ?>
                            <?php foreach ($employees_list as $emp): 
                                $upload_date = !empty($emp['slip_uploaded_at']) ? date('d/m/Y H:i', strtotime($emp['slip_uploaded_at'])) : '-';
                                $avatar = !empty($emp['profile_image']) ? '../uploads/profiles/' . $emp['profile_image'] : '';
                                $net_salary = isset($emp['net_pay']) ? number_format($emp['net_pay'], 2) : '0.00';
                                $has_slip = !empty($emp['salary_id']);
                                $is_published = isset($emp['is_published']) && $emp['is_published'] == 1;
                            ?>
                            <tr onclick="openCreatePayslipModalForEmployee('<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars(addslashes($emp['fullname'] . ' (ID: ' . $emp['employee_code'] . ')')); ?>')"
                                class="hover:bg-blue-50/40 transition-colors cursor-pointer">
                                
                                <td class="p-3.5 text-center" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="payslip-checkbox w-4 h-4 text-blue-600 rounded border-slate-300 cursor-pointer" value="<?php echo $emp['id']; ?>">
                                </td>
                                <td class="p-3.5 font-extrabold text-slate-500"><?php echo $upload_date; ?></td>
                                <td class="p-3.5 font-extrabold text-blue-600"><?php echo htmlspecialchars($emp['employee_code']); ?></td>
                                <td class="p-3.5 font-bold text-slate-800 flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 font-bold flex items-center justify-center text-xs shrink-0 overflow-hidden">
                                        <?php if ($avatar): ?>
                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?php echo mb_substr($emp['fullname'], 0, 1); ?>
                                        <?php endif; ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($emp['fullname']); ?></span>
                                </td>
                                <td class="p-3.5 text-slate-600"><?php echo htmlspecialchars($emp['dept_name'] ?? 'ไม่ระบุแผนก'); ?></td>
                                <td class="p-3.5 text-right font-black text-emerald-700">฿ <?php echo $net_salary; ?></td>
                                <td class="p-3.5 text-center">
                                    <?php if (!$has_slip): ?>
                                        <span class="px-2.5 py-0.5 bg-slate-100 text-slate-500 border border-slate-200 rounded-full font-bold text-[10px]">ยังไม่สร้าง</span>
                                    <?php elseif ($is_published): ?>
                                        <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200/80 rounded-full font-bold text-[10px] inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> ปล่อยแล้ว
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200/80 rounded-full font-bold text-[10px]">ยังไม่ปล่อย</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-center" onclick="event.stopPropagation()">
                                    <?php if ($has_slip && !empty($emp['pdf_file'])): ?>
                                        <a href="../uploads/payslips/<?php echo htmlspecialchars($emp['pdf_file']); ?>" target="_blank" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-50 hover:text-blue-600 text-slate-700 font-bold rounded-lg transition-colors inline-block">📄 ดูสลิป PDF</a>
                                    <?php else: ?>
                                        <span class="text-slate-400 font-light">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- 📌 MODAL: อัปโหลดสลิปเงินเดือนประจำงวด (ปรับใช้ Rounded Dropdown สไตล์เดียวกัน) -->
    <div id="createPayslipModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 overflow-visible my-auto">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2"><span>📤</span> อัปโหลดสลิปเงินเดือนประจำงวด</h3>
                <button type="button" onclick="closeCreatePayslipModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
            </div>
            
            <form method="POST" action="manage_salaries.php" enctype="multipart/form-data" class="space-y-4 text-xs overflow-visible">
                <input type="hidden" name="action" value="save_payslip">
                
                <!-- เลือกพนักงาน (Searchable Dropdown) -->
                <div class="space-y-1 relative z-40" id="custom-dropdown-payslip_emp_select">
                    <label class="font-bold text-slate-700">เลือกพนักงาน <span class="text-rose-500">*</span></label>
                    <input type="hidden" id="payslip_emp_select" name="employee_id" value="" required>

                    <button type="button" onclick="toggleSearchableDropdown('payslip_emp_select')" id="trigger-payslip_emp_select"
                        class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-3 text-slate-700 flex justify-between items-center shadow-sm hover:border-slate-300 transition-all cursor-pointer">
                        <span id="label-payslip_emp_select" class="text-slate-500">-- เลือกพนักงาน --</span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" id="arrow-payslip_emp_select" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div id="list-payslip_emp_select" class="hidden absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl border border-slate-200 rounded-2xl shadow-xl z-50 p-2 transition-all">
                        <div class="p-1 pb-2 border-b border-slate-100">
                            <input type="text" id="search-input-payslip_emp_select" oninput="filterSearchableDropdown('payslip_emp_select', this.value)" placeholder="พิมพ์ชื่อหรือรหัสพนักงานเพื่อค้นหา..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="max-h-44 overflow-y-auto p-1 space-y-1 mt-1" id="options-list-payslip_emp_select">
                            <?php if (empty($employees_list)): ?>
                                <div class="px-3 py-2 text-slate-400 text-center">ไม่พบข้อมูลพนักงาน</div>
                            <?php else: ?>
                                <?php foreach ($employees_list as $emp): ?>
                                    <div onclick="selectSearchableOption('payslip_emp_select', '<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars($emp['fullname'] . ' (ID: ' . $emp['employee_code'] . ')'); ?>')"
                                         data-search-text="<?php echo strtolower($emp['fullname'] . ' ' . $emp['employee_code']); ?>"
                                         class="searchable-option px-3 py-2.5 rounded-xl text-slate-700 hover:bg-blue-50 hover:text-blue-700 transition-colors cursor-pointer text-xs font-medium flex items-center justify-between">
                                        <span><?php echo htmlspecialchars($emp['fullname']); ?></span>
                                        <span class="text-slate-400 text-[11px]">ID: <?php echo htmlspecialchars($emp['employee_code']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 🎯 ช่องเลือกงวดเดือน และ ปี (พ.ศ.) แบบ Rounded Dropdown -->
                <div class="grid grid-cols-2 gap-3 relative z-30">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">ประจำเดือน <span class="text-rose-500">*</span></label>
                        <?php renderRoundedDropdown('modal_month_select', 'payslip_month', $active_month_label, $months_options, sprintf("%02d", $selected_month)); ?>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">ประจำปี (พ.ศ.) <span class="text-rose-500">*</span></label>
                        <?php renderRoundedDropdown('modal_year_select', 'payslip_year', $active_year_label, $years_options, (string)$selected_year); ?>
                    </div>
                </div>

                <!-- ช่องกรอกจำนวนเงินเดือน -->
                <div class="space-y-1 relative z-10">
                    <label class="font-bold text-slate-700">เงินเดือนที่ได้รับ / ยอดสุทธิ (Net Pay) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" name="salary_amount" placeholder="0.00" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-bold text-emerald-700 focus:outline-none focus:border-blue-500">
                </div>

                <!-- ช่องอัปโหลดไฟล์ PDF -->
                <div class="space-y-1.5 pt-1 relative z-10">
                    <label class="font-bold text-slate-700">แนบไฟล์สลิปเงินเดือน (PDF) <span class="text-rose-500">*</span></label>
                    
                    <div id="dropzone-container" class="border-2 border-dashed border-slate-200 hover:border-blue-500 rounded-2xl p-4 text-center bg-slate-50 transition-all relative">
                        <input type="file" name="payslip_pdf" id="payslip_pdf_input" accept=".pdf" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="handlePayslipFileChange(this)">
                        
                        <div class="space-y-1 pointer-events-none relative z-0">
                            <span class="text-2xl transition-transform duration-200 inline-block" id="file-icon-display">📄</span>
                            <p class="font-bold text-slate-700 text-xs transition-colors" id="file-name-display">คลิกเพื่อเลือกไฟล์ PDF หรือลากมาวางที่นี่</p>
                            <p class="text-[10px] text-slate-400 transition-colors" id="file-sub-display">รองรับเฉพาะไฟล์ .pdf เท่านั้น</p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-3 relative z-10">
                    <button type="button" onclick="closeCreatePayslipModal()" class="flex-1 py-2.5 bg-slate-100 font-bold rounded-xl text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">ยกเลิก</button>
                    <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition-colors cursor-pointer">บันทึกและอัปโหลด</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 🔔 Alerts Script -->
    <script src="../assets/js/alerts.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($success_msg)): ?>
                if (window.LantoAlert) LantoAlert.success('สำเร็จ', '<?php echo addslashes($success_msg); ?>');
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                if (window.LantoAlert) LantoAlert.error('เกิดข้อผิดพลาด', '<?php echo addslashes($error_msg); ?>');
            <?php endif; ?>
        });

        function openCreatePayslipModal() {
            document.getElementById('createPayslipModal').classList.remove('hidden');
        }
        
        function openCreatePayslipModalForEmployee(empId, empLabel) {
            document.getElementById('payslip_emp_select').value = empId;
            const labelSpan = document.getElementById('label-payslip_emp_select');
            labelSpan.textContent = empLabel;
            labelSpan.className = "text-slate-800 font-medium";
            openCreatePayslipModal();
        }

        function closeCreatePayslipModal() {
            document.getElementById('createPayslipModal').classList.add('hidden');
        }

        function toggleSelectAll(source) {
            checkboxes = document.querySelectorAll('.payslip-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
        }

        function publishSelectedPayslips() {
            const checkedBoxes = document.querySelectorAll('.payslip-checkbox:checked');
            if (checkedBoxes.length === 0) {
                if (window.LantoAlert) {
                    LantoAlert.error('ยังไม่ได้เลือกพนักงาน', 'กรุณาติ๊กเลือกพนักงานที่ต้องการปล่อยสลิปอย่างน้อย 1 รายการ');
                } else {
                    alert('กรุณาติ๊กเลือกพนักงานที่ต้องการปล่อยสลิปอย่างน้อย 1 รายการ');
                }
                return;
            }

            if (confirm(`คุณต้องการปล่อยสลิปเงินเดือนให้พนักงานที่เลือกจำนวน ${checkedBoxes.length} คน เข้าสู่แอปมือถือใช่หรือไม่?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'manage_salaries.php';
                
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = 'publish_payslips';
                form.appendChild(inputAction);

                checkedBoxes.forEach(cb => {
                    const inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'employee_ids[]';
                    inputId.value = cb.value;
                    form.appendChild(inputId);
                });

                document.body.appendChild(form);
                form.submit();
            }
        }

        function toggleSearchableDropdown(id) {
            const list = document.getElementById('list-' + id);
            const arrow = document.getElementById('arrow-' + id);
            list.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
            
            if (!list.classList.contains('hidden')) {
                setTimeout(() => {
                    const searchInput = document.getElementById('search-input-' + id);
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.focus();
                        filterSearchableDropdown(id, '');
                    }
                }, 50);
            }
        }

        function filterSearchableDropdown(id, query) {
            const filter = query.toLowerCase().trim();
            const container = document.getElementById('options-list-' + id);
            const items = container.querySelectorAll('.searchable-option');
            
            items.forEach(item => {
                const text = item.getAttribute('data-search-text') || '';
                if (text.includes(filter)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function selectSearchableOption(id, value, label) {
            document.getElementById(id).value = value;
            const labelSpan = document.getElementById('label-' + id);
            labelSpan.textContent = label;
            labelSpan.className = "text-slate-800 font-medium";
            
            document.getElementById('list-' + id).classList.add('hidden');
            document.getElementById('arrow-' + id).classList.remove('rotate-180');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#custom-dropdown-payslip_emp_select')) {
                const list = document.getElementById('list-payslip_emp_select');
                const arrow = document.getElementById('arrow-payslip_emp_select');
                if (list && arrow) {
                    list.classList.add('hidden');
                    arrow.classList.remove('rotate-180');
                }
            }
        });
        // 🎯 ฟังก์ชันเปลี่ยนสไตล์กล่องเมื่อเลือก/ไม่ได้เลือกไฟล์ PDF
        function handlePayslipFileChange(input) {
            const container = document.getElementById('dropzone-container');
            const iconDisplay = document.getElementById('file-icon-display');
            const nameDisplay = document.getElementById('file-name-display');
            const subDisplay = document.getElementById('file-sub-display');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                
                // ✨ สถานะเมื่อเลือกไฟล์สำเร็จ (กรอบเขียวเส้นทึบ + BG เขียวอ่อน + ไอคอน ✅)
                container.className = "border-2 border-solid border-emerald-500 rounded-2xl p-4 text-center bg-emerald-50/80 transition-all relative shadow-xs";
                iconDisplay.textContent = "✅";
                iconDisplay.className = "text-2xl scale-110 inline-block";
                
                nameDisplay.textContent = file.name;
                nameDisplay.className = "font-black text-emerald-800 text-xs truncate px-2";
                
                subDisplay.textContent = `แนบไฟล์เรียบร้อย (${fileSizeMB} MB) • คลิกหากต้องการเปลี่ยนไฟล์`;
                subDisplay.className = "text-[10px] text-emerald-600 font-bold";
            } else {
                // 🔄 รีเซ็ตกลับเป็นสถานะยังไม่ได้เลือกไฟล์ (กรอบเทาเส้นประ)
                container.className = "border-2 border-dashed border-slate-200 hover:border-blue-500 rounded-2xl p-4 text-center bg-slate-50 transition-all relative";
                iconDisplay.textContent = "📄";
                iconDisplay.className = "text-2xl inline-block";
                
                nameDisplay.textContent = "คลิกเพื่อเลือกไฟล์ PDF หรือลากมาวางที่นี่";
                nameDisplay.className = "font-bold text-slate-700 text-xs";
                
                subDisplay.textContent = "รองรับเฉพาะไฟล์ .pdf เท่านั้น";
                subDisplay.className = "text-[10px] text-slate-400";
            }
        }

        // 🎯 ล้างค่าไฟล์เมื่อปิด Modal
        function closeCreatePayslipModal() {
            document.getElementById('createPayslipModal').classList.add('hidden');
            const fileInput = document.getElementById('payslip_pdf_input');
            if (fileInput) {
                fileInput.value = '';
                handlePayslipFileChange(fileInput);
            }
        }
    </script>
</body>
</html>