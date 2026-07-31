<?php
ob_start(); // เปิดการจัดการ Output Buffer บนสุด ป้องกันข้อความ HTML/Script แทรก JSON
session_start();
require_once '../config/db.php';

// 🔑 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 👤 ดึงข้อมูลพนักงานปัจจุบันเพื่อตรวจสอบสิทธิ์ Role
try {
    $stmt_u = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt_u->execute(['id' => $user_id]);
    $user_data = $stmt_u->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        die("ไม่พบข้อมูลผู้ใช้งานในระบบ");
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

// 🛡️ ตรวจสอบสิทธิ์การแก้ไข (เฉพาะ admin, hr, it_support เท่านั้นที่แก้ ชื่อ/วันเกิด/อีเมล/ข้อมูลการทำงาน ได้)
$user_role = $_SESSION['role'] ?? $user_data['role'] ?? 'employee';
$can_edit_restricted = in_array(strtolower($user_role), ['admin', 'hr', 'it_support']);

// 💾 2. ประมวลผลเมื่อกดบันทึกการแก้ไข (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean(); // ล้าง Output Buffer ก่อนพ่น JSON
    header('Content-Type: application/json');

    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!empty($password) && $password !== $confirm_pass) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน']);
        exit();
    }

    // 🎯 1. ส่วนข้อมูลประจำตัวและข้อมูลการทำงาน (แก้ได้เฉพาะ Admin / HR / IT)
    if ($can_edit_restricted) {
        $first_name = trim(htmlspecialchars($_POST['first_name'] ?? $user_data['first_name']));
        $last_name  = trim(htmlspecialchars($_POST['last_name'] ?? $user_data['last_name']));
        $birth_date   = $_POST['birth_date'] ?? $user_data['birth_date'];
        $start_date   = $_POST['start_date'] ?? $user_data['start_date'];
        $email        = trim(htmlspecialchars($_POST['email'] ?? $user_data['email']));
        $phone        = trim(htmlspecialchars($_POST['phone'] ?? $user_data['phone']));
        
        $branch_id     = $_POST['branch_id'] ?? $user_data['branch_id'];
        $employee_type = $_POST['employee_type'] ?? $user_data['employee_type'];
        $department    = $_POST['department'] ?? $user_data['department'];
        $work_shift    = $_POST['work_shift'] ?? $user_data['work_shift'];

        // แปลงวันเกิด (พ.ศ. ➔ ค.ศ.)
        if (!empty($birth_date) && strpos($birth_date, '/') !== false) {
            $parts = explode('/', $birth_date);
            if (count($parts) === 3) {
                $birth_date = ((int)$parts[2] - 543) . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            }
        }

        // แปลงวันเริ่มบรรจุงาน (พ.ศ. ➔ ค.ศ.)
        if (!empty($start_date) && strpos($start_date, '/') !== false) {
            $parts = explode('/', $start_date);
            if (count($parts) === 3) {
                $start_date = ((int)$parts[2] - 543) . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            }
        }
    } else {
        // พนักงานทั่วไป: ล็อกข้อมูลประจำตัวและข้อมูลการทำงาน ให้ใช้ค่าเดิมใน DB
        $fullname      = $user_data['fullname'];
        $birth_date    = $user_data['birth_date'];
        $start_date    = $user_data['start_date'];
        $email         = $user_data['email'];
        $phone         = $user_data['phone'];
        $branch_id     = $user_data['branch_id'];
        $employee_type = $user_data['employee_type'];
        $department    = $user_data['department'];
        $work_shift    = $user_data['work_shift'];
    }

    // 🎯 2. ส่วนรายละเอียดที่อยู่อาศัย (ข้อ 9) -> ปลดล็อก! พนักงานทุกคนสามารถแก้ไขได้เองเสมอ
    $house_no     = trim(htmlspecialchars($_POST['house_no'] ?? ''));
    $village      = trim(htmlspecialchars($_POST['village'] ?? ''));
    $alley        = trim(htmlspecialchars($_POST['alley'] ?? ''));
    $street       = trim(htmlspecialchars($_POST['street'] ?? ''));
    
    $subdistrict  = trim(htmlspecialchars($_POST['subdistrict'] ?? $user_data['subdistrict']));
    $district     = trim(htmlspecialchars($_POST['district'] ?? $user_data['district']));
    $province     = trim(htmlspecialchars($_POST['province'] ?? $user_data['province']));
    $zipcode      = trim(htmlspecialchars($_POST['zipcode'] ?? $user_data['zipcode']));

    $full_address_detail = "บ้านเลขที่ $house_no | หมู่บ้าน/อาคาร $village | ซอย $alley | ถนน $street";

    try {
        $profile_name = $user_data['profile_image'];
        $id_card_name = $user_data['id_card_image'];

        // อัปโหลดรูปโปรไฟล์
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $profile_name = "profile_" . $user_data['employee_code'] . "_" . time() . "." . $ext;
            if (!is_dir('../uploads/profiles')) mkdir('../uploads/profiles', 0777, true);
            move_uploaded_file($_FILES['profile_image']['tmp_name'], "../uploads/profiles/" . $profile_name);
        }

        // อัปโหลดรูปบัตรประชาชน
        if ($can_edit_restricted && isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['id_card_image']['name'], PATHINFO_EXTENSION);
            $id_card_name = "idcard_" . $user_data['employee_code'] . "_" . time() . "." . $ext;
            if (!is_dir('../uploads/id-cards')) mkdir('../uploads/id-cards', 0777, true);
            move_uploaded_file($_FILES['id_card_image']['tmp_name'], "../uploads/id-cards/" . $id_card_name);
        }

        $update_sql = "UPDATE users SET 
                        first_name = :first_name, 
                        last_name = :last_name,
                        birth_date = :birth_date, 
                        start_date = :start_date,
                        email = :email, 
                        phone = :phone,
                        profile_image = :profile_image, 
                        id_card_image = :id_card_image, 
                        address_detail = :address_detail, 
                        subdistrict = :subdistrict, 
                        district = :district, 
                        province = :province, 
                        zipcode = :zipcode,
                        branch_id = :branch_id,
                        employee_type = :employee_type,
                        department = :department,
                        work_shift = :work_shift
                        WHERE id = :id";
        
        $params = [
            'first_name'     => $first_name, 
            'last_name'      => $last_name,  
            'birth_date'     => $birth_date,
            'start_date'     => $start_date,
            'email'          => $email,
            'phone'          => $phone,
            'profile_image'  => $profile_name,
            'id_card_image'  => $id_card_name,
            'address_detail' => $full_address_detail,
            'subdistrict'    => $subdistrict,
            'district'       => $district,
            'province'       => $province,
            'zipcode'        => $zipcode,
            'branch_id'      => $branch_id,
            'employee_type'  => $employee_type,
            'department'     => $department,
            'work_shift'     => $work_shift,
            'id'             => $user_id
        ];

        if (!empty($password)) {
            $update_sql = str_replace("WHERE id = :id", ", password = :password WHERE id = :id", $update_sql);
            $params['password'] = password_hash($password, PASSWORD_BCRYPT);
        }
        
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute($params);

        echo json_encode(['status' => 'success', 'message' => 'อัปเดตข้อมูลส่วนตัวสำเร็จเรียบร้อยแล้ว!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
    }
    exit();
}

// 📌 โหลดไฟล์ UI Rounded Dropdown
require_once '../includes/rounded_dropdown.php';

// 📥 3. ดึงตัวเลือกสำหรับ Dropdown จาก DB
try {
    $branches       = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $departments    = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $employee_types = $pdo->query("SELECT id, name FROM employee_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $work_shifts    = $pdo->query("SELECT id, name FROM work_shifts ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $branches = []; $departments = []; $employee_types = []; $work_shifts = [];
}

// 👤 4. ดึงข้อมูลพนักงานปัจจุบันพร้อม LEFT JOIN
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               d.name AS dept_name, 
               w.name AS shift_name, 
               b.name AS branch_name,
               et.name AS type_name
        FROM users u
        LEFT JOIN departments d ON (u.department = d.id OR u.department = d.name)
        LEFT JOIN branches b ON (u.branch_id = b.id OR u.branch_id = b.name)
        LEFT JOIN work_shifts w ON (u.work_shift = w.id OR u.work_shift = w.name)
        LEFT JOIN employee_types et ON (u.employee_type = et.id OR u.employee_type = et.name)
        WHERE u.id = :id LIMIT 1
    ");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

// 🏠 5. แยกข้อมูลที่อยู่เดิม
$parsed_house = ""; $parsed_village = ""; $parsed_alley = ""; $parsed_street = "";
if (!empty($user['address_detail'])) {
    $parts = explode('|', $user['address_detail']);
    foreach ($parts as $p) {
        $p = trim($p);
        if (strpos($p, 'บ้านเลขที่') !== false) $parsed_house = trim(str_replace('บ้านเลขที่', '', $p));
        elseif (strpos($p, 'หมู่บ้าน/อาคาร') !== false) $parsed_village = trim(str_replace('หมู่บ้าน/อาคาร', '', $p));
        elseif (strpos($p, 'ซอย') !== false) $parsed_alley = trim(str_replace('ซอย', '', $p));
        elseif (strpos($p, 'ถนน') !== false) $parsed_street = trim(str_replace('ถนน', '', $p));
    }
}

// 📅 แปลงวันที่ ค.ศ. ➔ พ.ศ.
$birth_display = "";
if (!empty($user['birth_date']) && $user['birth_date'] !== '0000-00-00') {
    $parts = explode('-', $user['birth_date']);
    if (count($parts) === 3) {
        $birth_display = str_pad($parts[2], 2, '0', STR_PAD_LEFT) . '/' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '/' . ((int)$parts[0] + 543);
    }
}

$start_work_display = "";
if (!empty($user['start_date']) && $user['start_date'] !== '0000-00-00') {
    $parts = explode('-', $user['start_date']);
    if (count($parts) === 3) {
        $start_work_display = str_pad($parts[2], 2, '0', STR_PAD_LEFT) . '/' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '/' . ((int)$parts[0] + 543);
    }
}

$profile_url = !empty($user['profile_image']) ? '../uploads/profiles/' . $user['profile_image'] : '';
$id_card_url = !empty($user['id_card_image']) ? '../uploads/id-cards/' . $user['id_card_image'] : '';

function getDropdownLabel($val, $options, $joined_name, $placeholder) {
    if (!empty($joined_name)) return $joined_name;
    foreach ($options as $opt) {
        if ((string)$opt['id'] === (string)$val) return $opt['name'];
    }
    return !empty($val) ? $val : $placeholder;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลส่วนตัว - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen py-8 px-4 md:px-8">

    <div class="max-w-4xl mx-auto bg-white/60 backdrop-blur-xl border border-white/80 p-6 md:p-10 rounded-3xl shadow-2xl transition-all">
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-8 border-b border-slate-200/60 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-wide">แก้ไขข้อมูลส่วนตัว</h1>
                <p class="text-slate-500 text-xs mt-1">อัปเดตข้อมูลรูปภาพ ที่อยู่ และรหัสผ่านของคุณ</p>
            </div>
            <a href="profile.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors cursor-pointer">กลับหน้าบัตรพนักงาน</a>
        </div>

        <form id="editProfileForm" enctype="multipart/form-data" class="space-y-8">
            
            <!-- ส่วนที่ 1: รูปภาพหลักฐานตัวตน (คลิกขยายดูรูปภาพใหญ่ได้ 🔍) -->
            <div>
                <h3 class="text-sm font-semibold text-blue-700 mb-4 flex items-center gap-2">📂 ส่วนที่ 1: รูปภาพหลักฐานตัวตน <span class="text-xs text-slate-400 font-normal">(คลิกที่รูปภาพเพื่อขยายดูรูปขนาดใหญ่)</span></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white/70 border border-slate-200/60 p-4 rounded-2xl flex flex-col items-center">
                        <label class="block text-xs font-medium text-slate-600 mb-3 w-full text-left">1. รูปถ่ายตัวเองพนักงาน</label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*" onchange="previewImage(this, 'profile_view', 'profile_wrap')"
                            class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        
                        <div id="profile_wrap" class="<?php echo empty($profile_url) ? 'hidden' : ''; ?> relative mt-4 w-32 h-32 rounded-2xl border border-slate-200 overflow-hidden shadow-xs bg-white cursor-pointer group" title="คลิกเพื่อดูรูปขนาดใหญ่">
                            <img id="profile_view" src="<?php echo htmlspecialchars($profile_url); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" onclick="openImagePreviewModal(this.src)">
                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition-opacity pointer-events-none">🔍 ขยาย</div>
                        </div>
                    </div>

                    <div class="bg-white/70 border border-slate-200/60 p-4 rounded-2xl flex flex-col items-center">
                        <div class="flex justify-between items-center w-full mb-3">
                            <label class="block text-xs font-medium text-slate-600">2. รูปถ่ายบัตรประชาชน</label>
                            <?php if (!$can_edit_restricted): ?>
                                <span class="text-[10px] font-normal text-amber-600 bg-amber-50 border border-amber-200/80 px-2 py-0.5 rounded-lg flex items-center gap-1">🔒 ติดต่อ HR เพื่อเปลี่ยน</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($can_edit_restricted): ?>
                            <!-- อนุญาตให้ Admin/HR/IT เลือกไฟล์เปลี่ยนรูปได้ -->
                            <input type="file" id="id_card_image" name="id_card_image" accept="image/*" onchange="previewImage(this, 'id_card_view', 'id_card_wrap')"
                                class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        <?php else: ?>
                            <!-- พนักงานทั่วไป: ซ่อนปุ่มเลือกไฟล์และขึ้นกล่องล็อก -->
                            <div class="w-full p-2.5 bg-slate-100/80 border border-slate-200/80 rounded-2xl text-center text-xs text-slate-400 font-medium select-none">
                                🚫 ไม่อนุญาตให้แก้ไขรูปบัตรประชาชน
                            </div>
                        <?php endif; ?>
                        
                        <div id="id_card_wrap" class="<?php echo empty($id_card_url) ? 'hidden' : ''; ?> relative mt-4 w-48 h-32 rounded-2xl border border-slate-200 overflow-hidden shadow-xs bg-white cursor-pointer group" title="คลิกเพื่อดูรูปขนาดใหญ่">
                            <img id="id_card_view" src="<?php echo htmlspecialchars($id_card_url); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" onclick="openImagePreviewModal(this.src)">
                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition-opacity pointer-events-none">🔍 ขยาย</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ส่วนที่ 2: ข้อมูลบัญชีผู้ใช้ -->
            <div>
                <h3 class="text-sm font-semibold text-blue-700 mb-4 flex items-center gap-2">🔐 ส่วนที่ 2: ข้อมูลบัญชีผู้ใช้</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">3. รหัสพนักงาน</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['employee_code'] ?? ''); ?>" readonly
                            class="w-full px-4 py-2.5 bg-slate-100/80 border border-slate-200 rounded-2xl text-sm text-slate-500 focus:outline-none cursor-not-allowed font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">4. รหัสผ่านใหม่ <span class="text-[10px] text-slate-400">(เว้นว่างถ้าไม่เปลี่ยน)</span></label>
                        <input type="password" name="password" placeholder="••••••••"
                            class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">5. ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" name="confirm_password" placeholder="••••••••"
                            class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs">
                    </div>
                </div>
            </div>

            <!-- ส่วนที่ 3: ข้อมูลส่วนตัวและที่อยู่ -->
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold text-blue-700 flex items-center gap-2">👤 ส่วนที่ 3: ข้อมูลส่วนตัวและที่อยู่</h3>
                    <?php if (!$can_edit_restricted): ?>
                        <span class="text-[11px] font-normal text-amber-600 bg-amber-50 border border-amber-200/80 px-2.5 py-0.5 rounded-lg flex items-center gap-1">🔒 ต้องการแก้ไขข้อมูลติดต่อ HR</span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">6.1 ชื่อจริง</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required <?php echo $can_edit_restricted ? '' : 'readonly'; ?>
                            class="w-full px-4 py-2.5 rounded-2xl text-sm focus:outline-none font-medium shadow-2xs <?php echo $can_edit_restricted ? 'bg-white border border-slate-200 focus:ring-2 focus:ring-blue-500' : 'bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed'; ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">6.2 นามสกุล</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required <?php echo $can_edit_restricted ? '' : 'readonly'; ?>
                            class="w-full px-4 py-2.5 rounded-2xl text-sm focus:outline-none font-medium shadow-2xs <?php echo $can_edit_restricted ? 'bg-white border border-slate-200 focus:ring-2 focus:ring-blue-500' : 'bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed'; ?>">
                    </div>
                    <!-- ข้อ 7: วันเกิด -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">7. วัน/เดือน/ปี เกิด</label>
                        <div class="relative flex items-center">
                            <div class="absolute left-3.5 pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <input type="text" id="birth_date_input" name="birth_date" value="<?php echo htmlspecialchars($birth_display); ?>" placeholder="วว/ดด/ปปปป" <?php echo $can_edit_restricted ? '' : 'readonly'; ?>
                                class="<?php echo $can_edit_restricted ? 'calendar-trigger cursor-pointer bg-white border border-slate-200 focus:ring-2 focus:ring-blue-500' : 'bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed'; ?> w-full pl-10 pr-4 py-2.5 rounded-2xl text-sm focus:outline-none font-medium shadow-2xs">
                        </div>
                    </div>
                </div>
                
                <!-- ข้อ 8: อีเมล -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">8. อีเมล (Email)</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required <?php echo $can_edit_restricted ? '' : 'readonly'; ?>
                            class="w-full px-4 py-2.5 rounded-2xl text-sm focus:outline-none font-medium shadow-2xs <?php echo $can_edit_restricted ? 'bg-white border border-slate-200 focus:ring-2 focus:ring-blue-500' : 'bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed'; ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">เบอร์โทรศัพท์ติดต่อ</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="เช่น 0812345678" maxlength="10" <?php echo $can_edit_restricted ? '' : 'readonly'; ?>
                            class="w-full px-4 py-2.5 rounded-2xl text-sm focus:outline-none font-medium shadow-2xs <?php echo $can_edit_restricted ? 'bg-white border border-slate-200 focus:ring-2 focus:ring-blue-500' : 'bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed'; ?>">
                    </div>
                </div>

                <!-- 🔓 ข้อ 9: รายละเอียดพิกัดที่อยู่ติดต่อ (ปลดล็อกให้พนักงานทุกคนแก้ไขได้เอง) -->
                <div class="space-y-4 bg-slate-50/70 p-5 rounded-3xl border border-slate-200/60 shadow-inner">
                    <div class="flex justify-between items-center">
                        <label class="block text-xs font-semibold text-slate-700">9. รายละเอียดพิกัดที่อยู่ติดต่อ</label>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">บ้านเลขที่</label>
                            <input type="text" name="house_no" value="<?php echo htmlspecialchars($parsed_house); ?>" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none shadow-2xs font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">หมู่ที่</label>
                            <input type="text" name="village" value="<?php echo htmlspecialchars($parsed_village); ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none shadow-2xs font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">ซอย</label>
                            <input type="text" name="alley" value="<?php echo htmlspecialchars($parsed_alley); ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none shadow-2xs font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">ถนน</label>
                            <input type="text" name="street" value="<?php echo htmlspecialchars($parsed_street); ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none shadow-2xs font-medium">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-1">
                        <!-- จังหวัด (Custom Dropdown) -->
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">จังหวัด</label>
                            <div class="relative w-full text-left text-xs font-medium" id="custom-dropdown-province">
                                <input type="hidden" id="province" name="province" value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>" data-selected="<?php echo htmlspecialchars($user['province'] ?? ''); ?>">
                                <button type="button" onclick="toggleDropdown('province')" id="trigger-province"
                                    class="w-full rounded-2xl px-4 py-2.5 text-slate-700 flex justify-between items-center shadow-2xs transition-all bg-white border border-slate-200 hover:border-slate-300 cursor-pointer">
                                    <span id="label-province" class="<?php echo !empty($user['province']) ? 'text-slate-800 font-medium' : 'text-slate-500'; ?>"><?php echo !empty($user['province']) ? htmlspecialchars($user['province']) : 'เลือกจังหวัด'; ?></span>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" id="arrow-province" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div id="list-province" class="hidden absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl border border-slate-200 rounded-2xl shadow-xl max-h-48 overflow-y-auto z-50 p-1.5 transition-all space-y-0.5"></div>
                            </div>
                        </div>

                        <!-- อำเภอ / เขต (Custom Dropdown) -->
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">อำเภอ / เขต</label>
                            <div class="relative w-full text-left text-xs font-medium" id="custom-dropdown-district">
                                <input type="hidden" id="district" name="district" value="<?php echo htmlspecialchars($user['district'] ?? ''); ?>" data-selected="<?php echo htmlspecialchars($user['district'] ?? ''); ?>">
                                <button type="button" onclick="toggleDropdown('district')" id="trigger-district"
                                    class="w-full rounded-2xl px-4 py-2.5 text-slate-700 flex justify-between items-center shadow-2xs transition-all bg-white border border-slate-200 hover:border-slate-300 cursor-pointer">
                                    <span id="label-district" class="<?php echo !empty($user['district']) ? 'text-slate-800 font-medium' : 'text-slate-500'; ?>"><?php echo !empty($user['district']) ? htmlspecialchars($user['district']) : 'เลือกอำเภอ/เขต'; ?></span>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" id="arrow-district" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div id="list-district" class="hidden absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl border border-slate-200 rounded-2xl shadow-xl max-h-48 overflow-y-auto z-50 p-1.5 transition-all space-y-0.5"></div>
                            </div>
                        </div>

                        <!-- ตำบล / แขวง (Custom Dropdown) -->
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">ตำบล / แขวง</label>
                            <div class="relative w-full text-left text-xs font-medium" id="custom-dropdown-subdistrict">
                                <input type="hidden" id="subdistrict" name="subdistrict" value="<?php echo htmlspecialchars($user['subdistrict'] ?? ''); ?>" data-selected="<?php echo htmlspecialchars($user['subdistrict'] ?? ''); ?>">
                                <button type="button" onclick="toggleDropdown('subdistrict')" id="trigger-subdistrict"
                                    class="w-full rounded-2xl px-4 py-2.5 text-slate-700 flex justify-between items-center shadow-2xs transition-all bg-white border border-slate-200 hover:border-slate-300 cursor-pointer">
                                    <span id="label-subdistrict" class="<?php echo !empty($user['subdistrict']) ? 'text-slate-800 font-medium' : 'text-slate-500'; ?>"><?php echo !empty($user['subdistrict']) ? htmlspecialchars($user['subdistrict']) : 'เลือกตำบล/แขวง'; ?></span>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" id="arrow-subdistrict" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div id="list-subdistrict" class="hidden absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl border border-slate-200 rounded-2xl shadow-xl max-h-48 overflow-y-auto z-50 p-1.5 transition-all space-y-0.5"></div>
                            </div>
                        </div>

                        <!-- รหัสไปรษณีย์ -->
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">รหัสไปรษณีย์</label>
                            <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($user['zipcode'] ?? ''); ?>" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none shadow-2xs font-medium">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ส่วนที่ 4: ข้อมูลการทำงาน (ปรับแก้ให้แสดงข้อความยาวได้ครบ 100%) -->
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold text-blue-700 flex items-center gap-2">💼 ส่วนที่ 4: ข้อมูลการทำงาน</h3>
                    <?php if (!$can_edit_restricted): ?>
                        <span class="text-[11px] font-normal text-amber-600 bg-amber-50 border border-amber-200/80 px-2.5 py-0.5 rounded-lg flex items-center gap-1">🔒 ต้องการแก้ไขข้อมูลติดต่อ HR</span>
                    <?php endif; ?>
                </div>

                <!-- 🎯 ปรับ Grid เป็น 2 คอลัมน์บนแท็บเล็ต / 3 คอลัมน์บนจอใหญ่ เพื่อเพิ่มความกว้างให้แต่ละช่อง -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    
                    <!-- 10. สาขาที่ปฏิบัติงาน -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">10. สาขาที่ปฏิบัติงาน</label>
                        <?php 
                            $branch_opts = array_map(fn($b) => ['id' => $b['id'], 'name' => $b['name']], $branches);
                            $branch_label = getDropdownLabel($user['branch_id'] ?? '', $branches, $user['branch_name'] ?? '', 'ไม่ระบุสาขา');
                            if ($can_edit_restricted) {
                                renderRoundedDropdown('branch_select', 'branch_id', $branch_label, $branch_opts, $user['branch_id'] ?? '');
                            } else {
                                echo '<div class="w-full px-4 py-2.5 bg-slate-100/80 border border-slate-200 rounded-2xl text-xs text-slate-700 font-medium shadow-2xs min-h-[42px] flex items-center whitespace-normal break-words leading-relaxed cursor-not-allowed">' . htmlspecialchars($branch_label) . '</div>';
                            }
                        ?>
                    </div>

                    <!-- 11. ประเภทพนักงาน -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">11. ประเภทพนักงาน</label>
                        <?php 
                            $type_opts = array_map(fn($et) => ['id' => $et['id'], 'name' => $et['name']], $employee_types);
                            $type_label = getDropdownLabel($user['employee_type'] ?? '', $employee_types, $user['type_name'] ?? '', 'ไม่ระบุประเภท');
                            if ($can_edit_restricted) {
                                renderRoundedDropdown('type_select', 'employee_type', $type_label, $type_opts, $user['employee_type'] ?? '');
                            } else {
                                echo '<div class="w-full px-4 py-2.5 bg-slate-100/80 border border-slate-200 rounded-2xl text-xs text-slate-700 font-medium shadow-2xs min-h-[42px] flex items-center whitespace-normal break-words leading-relaxed cursor-not-allowed">' . htmlspecialchars($type_label) . '</div>';
                            }
                        ?>
                    </div>

                    <!-- 12. แผนก / ฝ่าย (ให้สิทธิ์กว้างขวางเป็นพิเศษหากชื่อยาวมาก) -->
                    <div class="md:col-span-2 lg:col-span-1">
                        <label class="block text-xs font-medium text-slate-600 mb-2">12. แผนก / ฝ่าย</label>
                        <?php 
                            $dept_opts = array_map(fn($d) => ['id' => $d['id'], 'name' => $d['name']], $departments);
                            $dept_label = getDropdownLabel($user['department'] ?? '', $departments, $user['dept_name'] ?? '', 'ไม่ระบุแผนก');
                            if ($can_edit_restricted) {
                                renderRoundedDropdown('department_select', 'department', $dept_label, $dept_opts, $user['department'] ?? '');
                            } else {
                                echo '<div class="w-full px-4 py-2.5 bg-slate-100/80 border border-slate-200 rounded-2xl text-xs text-slate-700 font-medium shadow-2xs min-h-[42px] flex items-center whitespace-normal break-words leading-relaxed cursor-not-allowed">' . htmlspecialchars($dept_label) . '</div>';
                            }
                        ?>
                    </div>

                    <!-- 13. วันที่เริ่มบรรจุงาน -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">13. วันที่เริ่มบรรจุงาน</label>
                        <div class="relative flex items-center">
                            <div class="absolute left-3.5 pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <input type="text" id="start_date_input" name="start_date" value="<?php echo htmlspecialchars($start_work_display); ?>" placeholder="วว/ดด/ปปปป" <?php echo $can_edit_restricted ? '' : 'readonly'; ?>
                                class="<?php echo $can_edit_restricted ? 'calendar-trigger cursor-pointer bg-white border border-slate-200 focus:ring-2 focus:ring-blue-500' : 'bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed'; ?> w-full pl-10 pr-4 py-2.5 rounded-2xl text-xs focus:outline-none font-medium shadow-2xs h-[42px]">
                        </div>
                    </div>
                    <!-- 14. กะการทำงาน -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">14. กะการทำงาน</label>
                        <?php 
                            $shift_opts = array_map(fn($ws) => ['id' => $ws['id'], 'name' => $ws['name']], $work_shifts);
                            $shift_label = getDropdownLabel($user['work_shift'] ?? '', $work_shifts, $user['shift_name'] ?? '', 'ไม่ระบุกะงาน');
                            if ($can_edit_restricted) {
                                renderRoundedDropdown('shift_select', 'work_shift', $shift_label, $shift_opts, $user['work_shift'] ?? '');
                            } else {
                                echo '<div class="w-full px-4 py-2.5 bg-slate-100/80 border border-slate-200 rounded-2xl text-xs text-slate-700 font-medium shadow-2xs min-h-[42px] flex items-center whitespace-normal break-words leading-relaxed cursor-not-allowed">' . htmlspecialchars($shift_label) . '</div>';
                            }
                        ?>
                    </div>

                </div>
            </div>

            <!-- ปุ่มกดด้านล่าง -->
            <div class="flex gap-4 pt-4">
                <a href="profile.php" class="w-1/3 text-center border border-slate-300 text-slate-600 font-medium py-3 rounded-2xl text-sm hover:bg-slate-50 transition-all flex items-center justify-center">ย้อนกลับ</a>
                <button type="submit" class="w-2/3 bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white font-medium py-3 rounded-2xl shadow-lg shadow-blue-700/10 transition-all text-sm cursor-pointer">บันทึกข้อมูลส่วนตัว</button>
            </div>
        </form>
    </div>

    <!-- 🔍 Lightbox Modal ขยายดูรูปใหญ่ -->
    <div id="imagePreviewModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[70] flex items-center justify-center p-4 cursor-zoom-out" onclick="closeImagePreviewModal()">
        <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
            <button type="button" onclick="closeImagePreviewModal()" class="absolute -top-11 right-0 text-white/80 hover:text-white text-lg font-bold bg-white/10 hover:bg-white/20 w-9 h-9 rounded-full flex items-center justify-center transition-all cursor-pointer">✕</button>
            <img id="global_preview_img" src="" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain border border-white/10">
        </div>
    </div>

    <!-- 📅 ดึงคอมโพเนนต์ปฏิทิน -->
    <?php include_once '../includes/calendar_component.php'; ?>

    <script src="../assets/js/alerts.js"></script>

    <script>
    function openImagePreviewModal(src) {
        if (!src) return;
        const img = document.getElementById('global_preview_img');
        const modal = document.getElementById('imagePreviewModal');
        if (img && modal) {
            img.src = src;
            modal.classList.remove('hidden');
        }
    }

    function closeImagePreviewModal() {
        const modal = document.getElementById('imagePreviewModal');
        if (modal) modal.classList.add('hidden');
    }

    function previewImage(input, viewId, wrapId) {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(viewId).src = e.target.result;
                document.getElementById(wrapId).classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    }

    // 🎯 สคริปต์ควบคุม Custom Dropdowns ที่อยู่อาศัย (จังหวัด / อำเภอ / ตำบล)
    document.addEventListener("DOMContentLoaded", async function() {
        const provinceInput = document.getElementById("province");
        const districtInput = document.getElementById("district");
        const subdistrictInput = document.getElementById("subdistrict");
        const zipcodeOptions = document.getElementById("zipcode");

        const listProvince = document.getElementById("list-province");
        const listDistrict = document.getElementById("list-district");
        const listSubdistrict = document.getElementById("list-subdistrict");

        if (!provinceInput || !districtInput || !subdistrictInput) return;

        const selProv = provinceInput.getAttribute("data-selected") || "";
        const selDist = districtInput.getAttribute("data-selected") || "";
        const selSub = subdistrictInput.getAttribute("data-selected") || "";

        window.selectAddressOption = function(id, val, labelText) {
            const hiddenInput = document.getElementById(id);
            const labelSpan = document.getElementById("label-" + id);
            if (hiddenInput) hiddenInput.value = val;
            if (labelSpan) {
                labelSpan.textContent = labelText || val;
                labelSpan.className = val ? "text-slate-800 font-medium" : "text-slate-500";
            }
            const list = document.getElementById("list-" + id);
            const arrow = document.getElementById("arrow-" + id);
            if (list) list.classList.add("hidden");
            if (arrow) arrow.classList.remove("rotate-180");
        };

        try {
            const [provinces, districts, subdistricts] = await Promise.all([
                fetch("../assets/data/provinces.json").then(res => res.json()),
                fetch("../assets/data/districts.json").then(res => res.json()),
                fetch("../assets/data/subdistricts.json").then(res => res.json())
            ]);

            // 1. เติมรายชื่อจังหวัด
            listProvince.innerHTML = '';
            provinces.forEach(p => {
                const item = document.createElement("div");
                item.className = "px-3 py-2.5 rounded-xl text-slate-700 hover:bg-blue-50 hover:text-blue-700 transition-colors cursor-pointer flex items-center justify-between";
                item.innerHTML = `<span>${p.name_th}</span>`;
                item.onclick = function() {
                    selectAddressOption('province', p.name_th, p.name_th);
                    loadDistricts(p.name_th);
                };
                listProvince.appendChild(item);
            });

            // โหลดอำเภอ
            function loadDistricts(provName, chosenDist = "") {
                const foundProv = provinces.find(p => p.name_th === provName);
                listDistrict.innerHTML = '';
                listSubdistrict.innerHTML = '';
                
                if (!chosenDist) {
                    selectAddressOption('district', '', 'เลือกอำเภอ/เขต');
                    selectAddressOption('subdistrict', '', 'เลือกตำบล/แขวง');
                    if (zipcodeOptions) zipcodeOptions.value = "";
                }

                if (foundProv) {
                    const filtered = districts.filter(d => d.province_id === foundProv.id);
                    filtered.forEach(d => {
                        const item = document.createElement("div");
                        item.className = "px-3 py-2.5 rounded-xl text-slate-700 hover:bg-blue-50 hover:text-blue-700 transition-colors cursor-pointer flex items-center justify-between";
                        item.innerHTML = `<span>${d.name_th}</span>`;
                        item.onclick = function() {
                            selectAddressOption('district', d.name_th, d.name_th);
                            loadSubdistricts(provName, d.name_th);
                        };
                        listDistrict.appendChild(item);
                    });
                } else {
                    listDistrict.innerHTML = '<div class="px-3 py-2 text-slate-400">โปรดเลือกจังหวัดก่อน</div>';
                }
            }

            // โหลดตำบล
            function loadSubdistricts(provName, distName, chosenSub = "") {
                const foundProv = provinces.find(p => p.name_th === provName);
                const provId = foundProv ? foundProv.id : null;
                const foundDist = districts.find(d => d.name_th === distName && (provId === null || d.province_id === provId));
                
                listSubdistrict.innerHTML = '';
                if (!chosenSub) {
                    selectAddressOption('subdistrict', '', 'เลือกตำบล/แขวง');
                    if (zipcodeOptions) zipcodeOptions.value = "";
                }

                if (foundDist) {
                    const filtered = subdistricts.filter(s => s.district_id === foundDist.id);
                    filtered.forEach(s => {
                        const item = document.createElement("div");
                        item.className = "px-3 py-2.5 rounded-xl text-slate-700 hover:bg-blue-50 hover:text-blue-700 transition-colors cursor-pointer flex items-center justify-between";
                        item.innerHTML = `<span>${s.name_th}</span>`;
                        item.onclick = function() {
                            selectAddressOption('subdistrict', s.name_th, s.name_th);
                            if (zipcodeOptions) zipcodeOptions.value = s.zip_code || "";
                        };
                        listSubdistrict.appendChild(item);

                        if (s.name_th === chosenSub && zipcodeOptions && !zipcodeOptions.value) {
                            zipcodeOptions.value = s.zip_code || "";
                        }
                    });
                } else {
                    listSubdistrict.innerHTML = '<div class="px-3 py-2 text-slate-400">โปรดเลือกอำเภอก่อน</div>';
                }
            }

            // 2. เลือกค่าเดิมจาก DB ให้อัตโนมัติ
            if (selProv) {
                selectAddressOption('province', selProv, selProv);
                loadDistricts(selProv, selDist);
                if (selDist) {
                    selectAddressOption('district', selDist, selDist);
                    loadSubdistricts(selProv, selDist, selSub);
                    if (selSub) {
                        selectAddressOption('subdistrict', selSub, selSub);
                    }
                }
            } else {
                listDistrict.innerHTML = '<div class="px-3 py-2 text-slate-400">โปรดเลือกจังหวัดก่อน</div>';
                listSubdistrict.innerHTML = '<div class="px-3 py-2 text-slate-400">โปรดเลือกอำเภอก่อน</div>';
            }

        } catch (e) {
            console.error("ไม่สามารถโหลดไฟล์ JSON ที่อยู่ได้:", e);
        }
    });

    // 🚀 ส่งฟอร์มบันทึกข้อมูลด้วย AJAX
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (typeof LantoAlert !== 'undefined') {
            LantoAlert.loading('กำลังบันทึกข้อมูล', 'ระบบกำลังอัปเดตข้อมูลส่วนตัวของคุณ...');
        }
        const formData = new FormData(this);
        setTimeout(() => {
            fetch('edit_profile.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (typeof LantoAlert !== 'undefined') LantoAlert.close();
                setTimeout(() => {
                    if (data.status === 'success') {
                        if (typeof LantoAlert !== 'undefined') {
                            LantoAlert.success('บันทึกสำเร็จ', data.message, function() {
                                window.location.href = 'profile.php';
                            });
                        } else {
                            alert(data.message);
                            window.location.href = 'profile.php';
                        }
                    } else {
                        if (typeof LantoAlert !== 'undefined') {
                            LantoAlert.error('บันทึกล้มเหลว', data.message);
                        } else {
                            alert(data.message);
                        }
                    }
                }, 300);
            })
            .catch(err => {
                if (typeof LantoAlert !== 'undefined') {
                    LantoAlert.close();
                    setTimeout(() => { LantoAlert.error('เกิดข้อผิดพลาด', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้'); }, 300);
                } else {
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
                }
            });
        }, 800);
    });
    </script>
</body>
</html>