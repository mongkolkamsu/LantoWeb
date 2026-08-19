<?php
ob_start();
session_start();
require_once '../config/db.php';
require_once '../includes/rounded_dropdown.php';
require_once '../config/auth.php';

// 🔑 1. SECURITY LAYER
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'it_support', 'hr'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'];
$admin_fullname = $_SESSION['fullname'];
$today = date('Y-m-d');

// 🎯 ดึงข้อความแจ้งเตือนจาก SESSION
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// 🛠️ ฟังก์ชันแปลงวันที่ พ.ศ. (วว/ดด/ปปปป) หรือ ค.ศ. ให้เป็น YYYY-MM-DD สำหรับบันทึกลง Database
function parseThaiDateToAD($dateStr) {
    if (empty($dateStr)) return null;
    $dateStr = trim($dateStr);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $matches)) {
        $d = sprintf('%02d', $matches[1]);
        $m = sprintf('%02d', $matches[2]);
        $y = (int)$matches[3];
        if ($y > 2400) { $y -= 543; }
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
    return null;
}

// ✏️ 2. บันทึกการแก้ไขข้อมูลพนักงาน (update_employee)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employee') {
    $edit_mode = $_POST['edit_mode'] ?? 'single';
    $target_ids_raw = $_POST['target_ids'] ?? $_POST['employee_id'] ?? $_POST['id'] ?? '';
    $ids = array_filter(array_map('trim', explode(',', (string)$target_ids_raw)));

    if (!empty($ids)) {
        try {
            if ($edit_mode === 'single') {
                $id         = (int)$ids[0];
                $emp_code   = trim($_POST['single_code'] ?? $_POST['employee_code'] ?? '');
                $first_name = trim($_POST['single_first_name'] ?? $_POST['first_name'] ?? '');
                $last_name  = trim($_POST['single_last_name'] ?? $_POST['last_name'] ?? ''); 
                $email      = trim($_POST['single_email'] ?? $_POST['email'] ?? '');
                $phone      = trim($_POST['single_phone'] ?? $_POST['phone'] ?? '');
                $emp_role   = $_POST['role'] ?? $_POST['single_role'] ?? 'employee';
                
                // 🎯 แปลงวันที่ พ.ศ. เป็น ค.ศ. ก่อนบันทึก
                $birth_date = parseThaiDateToAD($_POST['birth_date'] ?? $_POST['single_birth_date'] ?? '');
                $start_date = parseThaiDateToAD($_POST['start_date'] ?? $_POST['single_start_date'] ?? '');
                
                $dept_val   = $_POST['department'] ?? $_POST['single_department'] ?? '';
                $dept_id    = (!empty($dept_val) && $dept_val !== '0') ? $dept_val : null;

                $branch_val = $_POST['branch_id'] ?? $_POST['single_branch_id'] ?? '';
                $branch_id  = (!empty($branch_val) && $branch_val !== '0') ? $branch_val : null;

                $shift_val  = $_POST['work_shift'] ?? $_POST['single_work_shift'] ?? '';
                $work_shift = (!empty($shift_val) && $shift_val !== '0') ? $shift_val : null;

                $type_val   = $_POST['employee_type'] ?? $_POST['single_employee_type'] ?? '';
                $emp_type   = (!empty($type_val) && $type_val !== '0') ? $type_val : null;
                
                $status_raw = $_POST['status'] ?? $_POST['single_status'] ?? 'active';
                $is_active_val = ($status_raw === 'active' || $status_raw === '1' || $status_raw === 1) ? 1 : 0;

                $params = [
                    'code'      => $emp_code, 
                    'fname'     => $first_name, 
                    'lname'     => $last_name, 
                    'email'     => $email, 
                    'phone'     => $phone,
                    'role'      => $emp_role,
                    'birth'     => $birth_date, 
                    'start'     => $start_date, 
                    'dept'      => $dept_id,
                    'branch'    => $branch_id, 
                    'emp_type'  => $emp_type, 
                    'shift'     => $work_shift,
                    'is_active' => $is_active_val, 
                    'id'        => $id
                ];

                $img_sql = "";

                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($ext, $allowed)) {
                        $new_profile = 'profile_' . $emp_code . '_' . time() . '.' . $ext;
                        if (!is_dir('../uploads/profiles')) mkdir('../uploads/profiles', 0777, true);
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], '../uploads/profiles/' . $new_profile)) {
                            $img_sql .= ", profile_image = :profile_img";
                            $params['profile_img'] = $new_profile;
                        }
                    }
                }

                if (isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['id_card_image']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($ext, $allowed)) {
                        $new_idcard = 'idcard_' . $emp_code . '_' . time() . '.' . $ext;
                        if (!is_dir('../uploads/id-cards')) mkdir('../uploads/id-cards', 0777, true);
                        if (move_uploaded_file($_FILES['id_card_image']['tmp_name'], '../uploads/id-cards/' . $new_idcard)) {
                            $img_sql .= ", id_card_image = :idcard_img";
                            $params['idcard_img'] = $new_idcard;
                        }
                    }
                }

                $pass_sql = "";
                if (!empty($_POST['password'])) {
                    $pass_sql = ", password = :pass";
                    $params['pass'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                // 🎯 บันทึกเฉพาะ first_name และ last_name ไม่มี fullname ในฐานข้อมูล
                $stmt_update = $pdo->prepare("
                    UPDATE users SET 
                        employee_code = :code, 
                        first_name = :fname, 
                        last_name = :lname, 
                        email = :email, 
                        phone = :phone, 
                        role = :role,
                        birth_date = :birth, 
                        start_date = :start, 
                        department = :dept,
                        branch_id = :branch, 
                        employee_type = :emp_type, 
                        work_shift = :shift,
                        is_active = :is_active 
                        $img_sql 
                        $pass_sql
                    WHERE id = :id
                ");
                $stmt_update->execute($params);
                $_SESSION['success_msg'] = 'อัปเดตข้อมูลพนักงานเรียบร้อยแล้ว';

            } else {
                // โหมดแก้ไขพร้อมกันหลายคน (Bulk Edit)
                $update_fields = [];
                $params = [];

                if (!empty($_POST['department']) && $_POST['department'] !== '0') { $update_fields[] = "department = :dept"; $params['dept'] = $_POST['department']; }
                if (!empty($_POST['branch_id']) && $_POST['branch_id'] !== '0') { $update_fields[] = "branch_id = :branch"; $params['branch'] = $_POST['branch_id']; }
                if (!empty($_POST['employee_type']) && $_POST['employee_type'] !== '0') { $update_fields[] = "employee_type = :emp_type"; $params['emp_type'] = $_POST['employee_type']; }
                if (!empty($_POST['work_shift']) && $_POST['work_shift'] !== '0') { $update_fields[] = "work_shift = :shift"; $params['shift'] = $_POST['work_shift']; }
                if (!empty($_POST['status'])) { 
                    $update_fields[] = "is_active = :is_active"; 
                    $params['is_active'] = ($_POST['status'] === 'active' || $_POST['status'] === '1') ? 1 : 0; 
                }

                if (!empty($update_fields)) {
                    $in_clause = implode(',', array_map('intval', $ids));
                    $sql_bulk = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id IN ($in_clause)";
                    $stmt_bulk = $pdo->prepare($sql_bulk);
                    $stmt_bulk->execute($params);
                    $_SESSION['success_msg'] = 'อัปเดตข้อมูลกลุ่มพนักงานจำนวน ' . count($ids) . ' รายการเรียบร้อยแล้ว';
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = 'ไม่พบรหัสพนักงานที่ต้องการแก้ไข';
    }
    header("Location: manage_employees.php");
    exit();
}

// 🗑️ 3. บันทึกการลบพนักงาน (delete_employees)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_employees') {
    $delete_ids_raw = $_POST['delete_ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $delete_ids_raw)));
    if (!empty($ids)) {
        try {
            $in_clause = implode(',', $ids);
            $stmt_del = $pdo->query("DELETE FROM users WHERE id IN ($in_clause)");
            $_SESSION['success_msg'] = 'ลบข้อมูลพนักงานเรียบร้อยแล้ว';
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage();
        }
    }
    header("Location: manage_employees.php");
    exit();
}

// 📥 4. เพิ่มพนักงานใหม่ (add_employee)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_employee') {
    $emp_code   = trim($_POST['employee_code'] ?? '');
    $fullname   = trim($_POST['fullname'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '123456';
    $emp_role   = $_POST['role'] ?? 'employee';
    $dept_id    = !empty($_POST['department']) ? $_POST['department'] : null;
    $branch_id  = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
    $work_shift = !empty($_POST['work_shift']) ? $_POST['work_shift'] : null;
    $emp_type   = !empty($_POST['employee_type']) ? $_POST['employee_type'] : null;
    $start_date = parseThaiDateToAD($_POST['start_date'] ?? '') ?? date('Y-m-d');
    $birth_date = parseThaiDateToAD($_POST['birth_date'] ?? '') ?? '2000-01-01';

    $parts = explode(' ', $fullname, 2);
    $first_name = $parts[0] ?? '';
    $last_name  = $parts[1] ?? '';

    if (empty($emp_code) || empty($fullname)) {
        $_SESSION['error_msg'] = 'กรุณากรอกรหัสพนักงาน และชื่อ-นามสกุลให้ครบถ้วน';
    } else {
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE employee_code = :code");
            $chk->execute(['code' => $emp_code]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['error_msg'] = 'รหัสพนักงานนี้มีอยู่ในระบบแล้ว';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_add = $pdo->prepare("
                    INSERT INTO users (
                        employee_code, first_name, last_name, password, role, email, phone,
                        birth_date, address_detail, subdistrict, district, province, zipcode,
                        branch_id, employee_type, department, start_date, work_shift, is_active
                    ) VALUES (
                        :code, :fname, :lname, :pass, :role, :email, :phone,
                        :birth, '', '', '', '', '',
                        :branch, :emp_type, :dept, :start_date, :shift, 1
                    )
                ");
                $stmt_add->execute([
                    'code'       => $emp_code,
                    'fname'      => $first_name,
                    'lname'      => $last_name,
                    'pass'       => $hashed_password,
                    'role'       => $emp_role,
                    'email'      => $email,
                    'phone'      => $phone,
                    'birth'      => $birth_date,
                    'branch'     => $branch_id,
                    'emp_type'   => $emp_type,
                    'dept'       => $dept_id,
                    'start_date' => $start_date,
                    'shift'      => $work_shift
                ]);
                $_SESSION['success_msg'] = 'เพิ่มข้อมูลพนักงานเรียบร้อยแล้ว';
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage();
        }
    }
    header("Location: manage_employees.php");
    exit();
}

// 🔍 5. ดึงข้อมูลพนักงาน & ตัวกรองการ์ด
$card_filter   = $_GET['filter'] ?? 'all';
$search        = trim($_GET['search'] ?? '');
$dept_filter   = $_GET['dept'] ?? '';
$branch_filter = $_GET['branch'] ?? '';
$type_filter   = $_GET['emp_type'] ?? '';
$sort_order    = $_GET['sort'] ?? '';

try {
    $current_month = date('m');
    $current_year  = date('Y');

    $stmt_emp = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'");
    $total_employees = $stmt_emp->fetchColumn() ?: 0;

    $stmt_new_hires = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role != 'admin' AND MONTH(start_date) = :m AND YEAR(start_date) = :y");
    $stmt_new_hires->execute(['m' => $current_month, 'y' => $current_year]);
    $new_hires_count = $stmt_new_hires->fetchColumn() ?: 0;

    $stmt_birthdays = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role != 'admin' AND MONTH(birth_date) = :m");
    $stmt_birthdays->execute(['m' => $current_month]);
    $birthdays_count = $stmt_birthdays->fetchColumn() ?: 0;

    $stmt_inactive = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin' AND is_active = 0");
    $inactive_count = $stmt_inactive->fetchColumn() ?: 0;

    $departments    = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $branches       = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $work_shifts    = $pdo->query("SELECT * FROM work_shifts ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $employee_types = $pdo->query("SELECT * FROM employee_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 🎯 รวม first_name และ last_name มาเป็นชื่อเรียก fullname สำหรับหน้า UI
    $sql = "
        SELECT u.*, 
               TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) AS fullname,
               d.name AS dept_name, 
               w.name AS shift_name, 
               b.name AS branch_name,
               et.name AS type_name
        FROM users u
        LEFT JOIN departments d ON u.department = d.id
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN work_shifts w ON u.work_shift = w.id
        LEFT JOIN employee_types et ON u.employee_type = et.id
        WHERE u.role != 'admin'
    ";
    $params = [];

    if ($card_filter === 'new_hires') {
        $sql .= " AND MONTH(u.start_date) = :m_hire AND YEAR(u.start_date) = :y_hire";
        $params['m_hire'] = $current_month;
        $params['y_hire'] = $current_year;
    } elseif ($card_filter === 'birthdays') {
        $sql .= " AND MONTH(u.birth_date) = :m_birth";
        $params['m_birth'] = $current_month;
    } elseif ($card_filter === 'inactive') {
        $sql .= " AND u.is_active = 0";
    }

    if ($search !== '') {
        $sql .= " AND (CONCAT_WS(' ', u.first_name, u.last_name) LIKE :search1 OR u.employee_code LIKE :search2 OR u.email LIKE :search3)";
        $params['search1'] = "%{$search}%";
        $params['search2'] = "%{$search}%";
        $params['search3'] = "%{$search}%";
    }

    if ($dept_filter !== '') {
        $sql .= " AND u.department = :dept";
        $params['dept'] = $dept_filter;
    }

    if ($branch_filter !== '') {
        $sql .= " AND u.branch_id = :branch";
        $params['branch'] = $branch_filter;
    }

    if ($type_filter !== '') {
        $sql .= " AND u.employee_type = :emp_type";
        $params['emp_type'] = $type_filter;
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
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดของระบบ: " . $e->getMessage());
}

$dept_opts = array_merge([['id' => '', 'name' => 'ทุกแผนก']], $departments);
$active_dept_label = 'ทุกแผนก';
foreach ($departments as $d) {
    if ((string)$d['id'] === (string)$dept_filter) {
        $active_dept_label = $d['name'];
        break;
    }
}

$branch_opts = array_merge([['id' => '', 'name' => 'ทุกสาขา']], $branches);
$active_branch_label = 'ทุกสาขา';
foreach ($branches as $b) {
    if ((string)$b['id'] === (string)$branch_filter) {
        $active_branch_label = $b['name'];
        break;
    }
}

$type_opts = array_merge([['id' => '', 'name' => 'ทุกประเภทพนักงาน']], $employee_types);
$active_type_label = 'ทุกประเภทพนักงาน';
foreach ($employee_types as $t) {
    if ((string)$t['id'] === (string)$type_filter) {
        $active_type_label = $t['name'];
        break;
    }
}

function buildCardUrl($filter_val, $search, $dept, $branch, $sort) {
    $p = ['filter' => $filter_val];
    if (!empty($search)) $p['search'] = $search;
    if (!empty($dept)) $p['dept'] = $dept;
    if (!empty($branch)) $p['branch'] = $branch;
    if (!empty($sort)) $p['sort'] = $sort;
    return 'manage_employees.php?' . http_build_query($p);
}

$next_sort = ($sort_order === 'asc') ? 'desc' : 'asc';
$sort_link_params = $_GET;
$sort_link_params['sort'] = $next_sort;
$sort_url = 'manage_employees.php?' . http_build_query($sort_link_params);

$page_title    = 'จัดการข้อมูลพนักงาน';
$page_subtitle = 'เพิ่ม แก้ไข และบริหารจัดการรายชื่อบุคลากรภายในองค์กร';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลพนักงาน - Lanto Web Management System</title>
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

    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>

    <!-- 👤 SIDEBAR NAVIGATION -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- 💻 WORKSPACE WRAPPER ฝั่งขวา -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">

        <!-- 🔝 HEADER ADMIN -->
        <?php include_once '../includes/header_admin.php'; ?>

    <!-- 💻 2. MAIN WORKSPACE -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full min-h-screen md:h-screen overflow-y-auto space-y-4 sm:space-y-6 pb-20 md:pb-8">

        <!-- 📊 3. KPI CARDS -->
        <div class="bg-white p-2.5 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                
                <!-- การ์ด 1: พนักงานทั้งหมด -->
                <?php $is_all = ($card_filter === 'all'); ?>
                <a href="<?php echo buildCardUrl('all', $search, $dept_filter, $branch_filter, $sort_order); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_all ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">👥</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">พนักงานทั้งหมด</p>
                            <p class="text-[10px] opacity-70 font-medium">รายชื่อในระบบพนักงาน</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $total_employees; ?></span>
                </a>

                <!-- การ์ด 2: พนักงานใหม่เดือนนี้ -->
                <?php $is_new = ($card_filter === 'new_hires'); ?>
                <a href="<?php echo buildCardUrl('new_hires', $search, $dept_filter, $branch_filter, $sort_order); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_new ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">✨</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">พนักงานใหม่เดือนนี้</p>
                            <p class="text-[10px] opacity-70 font-medium">เริ่มงานในเดือนปัจจุบัน</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $new_hires_count; ?></span>
                </a>

                <!-- การ์ด 3: วันเกิดเดือนนี้ -->
                <?php $is_birth = ($card_filter === 'birthdays'); ?>
                <a href="<?php echo buildCardUrl('birthdays', $search, $dept_filter, $branch_filter, $sort_order); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_birth ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🎂</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">วันเกิดเดือนนี้</p>
                            <p class="text-[10px] opacity-70 font-medium">มีวันเกิดในเดือนปัจจุบัน</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $birthdays_count; ?></span>
                </a>

                <!-- การ์ด 4: บัญชีปิดใช้งาน -->
                <?php $is_inactive = ($card_filter === 'inactive'); ?>
                <a href="<?php echo buildCardUrl('inactive', $search, $dept_filter, $branch_filter, $sort_order); ?>" 
                   class="p-3.5 rounded-xl transition-all duration-200 flex items-center justify-between border cursor-pointer active:scale-95 <?php echo $is_inactive ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20 font-extrabold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/80 font-bold'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🛑</span>
                        <div>
                            <p class="text-[11px] opacity-90 uppercase tracking-wider">ปิดใช้งาน</p>
                            <p class="text-[10px] opacity-70 font-medium">บัญชีพนักงานที่ระงับสิทธิ์</p>
                        </div>
                    </div>
                    <span class="text-2xl font-black tracking-tight"><?php echo $inactive_count; ?></span>
                </a>

            </div>
        </div>

        <!-- 🔎 แถบตัวกรองและค้นหา + ปุ่มเพิ่มพนักงาน -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex flex-col md:flex-row justify-between items-center gap-3">
            <form method="GET" action="manage_employees.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($card_filter); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_order); ?>">

                <div class="w-full sm:w-80">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหาชื่อ, รหัสพนักงาน, อีเมล..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-xs font-medium focus:outline-none focus:border-blue-500 transition-colors h-10">
                </div>

                <div class="w-48 sm:w-60">
                    <?php renderRoundedDropdown('dept_select', 'dept', $active_dept_label, $dept_opts, $dept_filter); ?>
                </div>

                <div class="w-48 sm:w-50">
                    <?php renderRoundedDropdown('branch_select', 'branch', $active_branch_label, $branch_opts, $branch_filter); ?>
                </div>
                
                <div class="w-full sm:w-56">
                    <?php renderRoundedDropdown('type_select', 'emp_type', $active_type_label, $type_opts, $type_filter); ?>
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
            
            <button type="button" onclick="openAddEmployeeModal()" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md shadow-blue-500/20 transition-all flex items-center justify-center gap-2 cursor-pointer active:scale-95 shrink-0">
                <span>➕</span> เพิ่มพนักงานใหม่
            </button>
        </div>

        <!-- 📑 ตารางข้อมูลพนักงาน -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 font-semibold border-b border-slate-100 uppercase tracking-wider">
                            <th class="p-4 w-10 text-center">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" 
                                    class="w-4 h-4 text-blue-600 rounded-md border-slate-300 focus:ring-blue-500 cursor-pointer">
                            </th>

                            <th class="p-4 cursor-pointer select-none hover:text-slate-700 transition-colors">
                                <a href="<?php echo $sort_url; ?>" class="flex items-center gap-1.5 font-bold">
                                    <span>รหัสพนักงาน</span>
                                    <span class="text-[15px]">
                                        <?php 
                                            if ($sort_order === 'asc') echo '⬆';
                                            elseif ($sort_order === 'desc') echo '⬇';
                                            else echo '⭥';
                                        ?>
                                    </span>
                                </a>
                            </th>

                            <th class="p-4">พนักงาน</th>
                            <th class="p-4">แผนก</th>
                            <th class="p-4">สาขา</th>
                            <th class="p-4">ประเภทพนักงาน</th>
                            <th class="p-4">สถานะใช้งาน</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10 text-slate-400 font-light">🚫 ไม่พบข้อมูลพนักงานตรงตามเงื่อนไข</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): 
                                $avatar_src = !empty($emp['profile_image']) ? '../uploads/profiles/' . $emp['profile_image'] : '';
                                $is_active = isset($emp['is_active']) ? ($emp['is_active'] == 1) : true;
                                $status_attr_val = $is_active ? 'active' : 'inactive';
                                $fullname_text = $emp['fullname'];
                            ?>
                            <tr onclick="openSingleRowEdit(this, event)" class="hover:bg-blue-50/50 transition-colors cursor-pointer active:scale-[0.995]">
                                <td class="p-4 text-center">
                                    <input type="checkbox" 
                                        class="emp-checkbox w-4 h-4 text-blue-600 rounded-md border-slate-300 focus:ring-blue-500 cursor-pointer" 
                                        value="<?php echo $emp['id']; ?>"
                                        data-code="<?php echo htmlspecialchars($emp['employee_code'] ?? ''); ?>"
                                        data-firstname="<?php echo htmlspecialchars($emp['first_name'] ?? ''); ?>"  
                                        data-lastname="<?php echo htmlspecialchars($emp['last_name'] ?? ''); ?>"
                                        data-fullname="<?php echo htmlspecialchars($fullname_text); ?>"
                                        data-email="<?php echo htmlspecialchars($emp['email'] ?? ''); ?>"
                                        data-phone="<?php echo htmlspecialchars($emp['phone'] ?? ''); ?>"
                                        data-role="<?php echo htmlspecialchars($emp['role'] ?? 'employee'); ?>"
                                        data-birth="<?php echo htmlspecialchars($emp['birth_date'] ?? ''); ?>"
                                        data-startdate="<?php echo htmlspecialchars($emp['start_date'] ?? ''); ?>"
                                        data-avatar="<?php echo htmlspecialchars($emp['profile_image'] ?? ''); ?>"
                                        data-idcard="<?php echo htmlspecialchars($emp['id_card_image'] ?? ''); ?>"
                                        data-dept="<?php echo htmlspecialchars(($emp['department'] && $emp['department'] != '0') ? $emp['department'] : ''); ?>"
                                        data-branch="<?php echo htmlspecialchars(($emp['branch_id'] && $emp['branch_id'] != '0') ? $emp['branch_id'] : ''); ?>"
                                        data-type="<?php echo htmlspecialchars(($emp['employee_type'] && $emp['employee_type'] != '0') ? $emp['employee_type'] : ''); ?>"
                                        data-shift="<?php echo htmlspecialchars(($emp['work_shift'] && $emp['work_shift'] != '0') ? $emp['work_shift'] : ''); ?>"
                                        data-status="<?php echo $status_attr_val; ?>">
                                </td>

                                <td class="p-4 font-extrabold text-slate-700"><?php echo htmlspecialchars($emp['employee_code']); ?></td>

                                <td class="p-4 flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-blue-100 border border-blue-200 overflow-hidden flex items-center justify-center font-bold text-blue-600 shrink-0">
                                        <?php if (!empty($avatar_src)): ?>
                                            <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="w-full h-full object-cover" onerror="this.remove();">
                                        <?php else: ?>
                                            <?php echo mb_substr($fullname_text, 0, 1); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($fullname_text); ?></p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">
                                            <?php echo htmlspecialchars($emp['email'] ?? '-'); ?>
                                        </p>
                                    </div>
                                </td>

                                <td class="p-4 font-bold text-slate-700"><?php echo htmlspecialchars($emp['dept_name'] ?? 'ไม่ระบุ'); ?></td>
                                <td class="p-4 text-slate-600 font-semibold">📍 <?php echo htmlspecialchars($emp['branch_name'] ?? 'สำนักงานใหญ่'); ?></td>
                                <td class="p-4">
                                    <span class="inline-block px-2.5 py-0.5 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        <?php echo htmlspecialchars($emp['type_name'] ?? 'พนักงานประจำ'); ?>
                                    </span>
                                </td>

                                <td class="p-4">
                                    <?php if ($is_active): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80 shadow-3xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> เปิดใช้งาน
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/80 shadow-3xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> ปิดใช้งาน
                                        </span>
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

    <!-- 📌 ดึงชุด Modal และ Floating Action Bar มาใช้งาน -->
    <?php include '../includes/modal_add_employee.php'; ?>
    <?php include '../includes/modal_edit_employee.php'; ?>
    <?php include '../includes/modal_delete_employee.php'; ?>
    <?php include '../includes/floating_bulk_bar.php'; ?>

    <script src="../assets/js/alerts.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($success_msg)): ?>
                LantoAlert.success('ทำรายการสำเร็จ', '<?php echo addslashes($success_msg); ?>');
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                LantoAlert.error('เกิดข้อผิดพลาด', '<?php echo addslashes($error_msg); ?>');
            <?php endif; ?>
        });

        function openSingleRowEdit(trElement, event) {
            if (event.target && event.target.tagName === 'INPUT' && event.target.type === 'checkbox') {
                return;
            }

            const checkbox = trElement.querySelector('.emp-checkbox');
            if (!checkbox) return;

            let fname = checkbox.getAttribute('data-firstname') || '';
            let lname = checkbox.getAttribute('data-lastname') || '';
            const fullname = checkbox.getAttribute('data-fullname') || '';

            if (!fname && fullname) {
                const parts = fullname.trim().split(' ');
                fname = parts[0] || '';
                lname = parts.slice(1).join(' ') || '';
            }

            const empData = {
                id: checkbox.value,
                code: checkbox.getAttribute('data-code') || '',
                firstname: fname,
                lastname: lname,
                fullname: fullname || (fname + ' ' + lname).trim(),
                email: checkbox.getAttribute('data-email') || '',
                phone: checkbox.getAttribute('data-phone') || '',
                role: checkbox.getAttribute('data-role') || 'employee',
                birth: checkbox.getAttribute('data-birth') || '',
                startdate: checkbox.getAttribute('data-startdate') || '',
                avatar: checkbox.getAttribute('data-avatar') || '',
                idcard: checkbox.getAttribute('data-idcard') || '',
                dept: checkbox.getAttribute('data-dept') || '',
                branch: checkbox.getAttribute('data-branch') || '',
                type: checkbox.getAttribute('data-type') || '',
                shift: checkbox.getAttribute('data-shift') || '',
                status: checkbox.getAttribute('data-status') || 'active'
            };

            if (typeof selectedEmployees !== 'undefined') {
                selectedEmployees = [empData];
                currentIndex = 0;

                if (typeof renderAvatarStack === 'function') renderAvatarStack();

                const modeToggle = document.getElementById('modeToggleContainer');
                if (modeToggle) modeToggle.classList.add('hidden');

                if (typeof switchEditMode === 'function') switchEditMode('single');

                const editModal = document.getElementById('editEmployeeModal');
                if (editModal) editModal.classList.remove('hidden');
            }
        }
    </script>

</body>
</html>