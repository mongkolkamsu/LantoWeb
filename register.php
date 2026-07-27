<?php
require_once 'config/db.php';

// 🎯 ดึงข้อมูลจาก phpMyAdmin มารอไว้สำหรับทำ Dropdown ด้านล่าง
try {
    $stmt_branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1");
    $branches = $stmt_branches->fetchAll();

    $stmt_depts = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1");
    $departments = $stmt_depts->fetchAll();

    $stmt_emp_types = $pdo->query("SELECT id, name FROM employee_types WHERE is_active = 1");
    $employee_types = $stmt_emp_types->fetchAll();

    $stmt_shifts = $pdo->query("SELECT id, name FROM work_shifts WHERE is_active = 1");
    $work_shifts = $stmt_shifts->fetchAll();
    
} catch (PDOException $e) {
    die("ดึงข้อมูลตัวเลือกจากระบบไม่สำเร็จ: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $employee_code = trim(htmlspecialchars($_POST['employee_code'] ?? ''));
    $password      = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $fullname      = trim(htmlspecialchars($_POST['fullname'] ?? ''));
    $birth_date    = $_POST['birth_date'] ?? '';
    $email         = trim(htmlspecialchars($_POST['email'] ?? ''));
    $phone         = trim(htmlspecialchars($_POST['phone'] ?? ''));

    $house_no      = trim(htmlspecialchars($_POST['house_no'] ?? ''));
    $village       = trim(htmlspecialchars($_POST['village'] ?? ''));
    $alley         = trim(htmlspecialchars($_POST['alley'] ?? ''));
    $street        = trim(htmlspecialchars($_POST['street'] ?? ''));
    
    $subdistrict    = $_POST['subdistrict'] ?? '';
    $district       = $_POST['district'] ?? '';
    $province       = $_POST['province'] ?? '';
    $zipcode        = $_POST['zipcode'] ?? '';
    
    $branch_id      = $_POST['branch_id'] ?? '';
    $employee_type  = $_POST['employee_type'] ?? '';
    $department     = $_POST['department'] ?? '';
    $start_date     = $_POST['start_date'] ?? '';
    $work_shift     = $_POST['work_shift'] ?? '';

    if (empty($employee_code) || empty($password) || empty($fullname) || empty($email) || empty($branch_id)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลสำคัญรวมถึงเลือกสาขาให้ครบถ้วน']);
        exit();
    }

    if ($password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน']);
        exit();
    }

    if (!empty($birth_date)) {
        $parts = explode('/', $birth_date);
        if (count($parts) === 3) {
            $birth_date = ((int)$parts[2] - 543) . '-' . $parts[1] . '-' . $parts[0];
        }
    }
    if (!empty($start_date)) {
        $parts = explode('/', $start_date);
        if (count($parts) === 3) {
            $start_date = ((int)$parts[2] - 543) . '-' . $parts[1] . '-' . $parts[0];
        }
    }

    try {
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE employee_code = :code OR email = :email LIMIT 1");
        $check_stmt->execute(['code' => $employee_code, 'email' => $email]);
        if ($check_stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'รหัสพนักงานหรืออีเมลนี้มีอยู่ในระบบแล้ว']);
            exit();
        }

        $profile_name = null;
        $id_card_name = null;

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $profile_name = "profile_" . $employee_code . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], "uploads/profiles/" . $profile_name);
        }

        if (isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['id_card_image']['name'], PATHINFO_EXTENSION);
            $id_card_name = "idcard_" . $employee_code . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['id_card_image']['tmp_name'], "uploads/id-cards/" . $id_card_name);
        }

        $full_address_detail = "บ้านเลขที่ $house_no | หมู่บ้าน/อาคาร $village | ซอย $alley | ถนน $street";
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO users (
                    employee_code, password, profile_image, id_card_image, fullname, 
                    birth_date, email, phone, address_detail, subdistrict, district, 
                    province, zipcode, branch_id, employee_type, department, start_date, work_shift
                ) VALUES (
                    :employee_code, :password, :profile_image, :id_card_image, :fullname, 
                    :birth_date, :email, :phone, :address_detail, :subdistrict, :district, 
                    :province, :zipcode, :branch_id, :employee_type, :department, :start_date, :work_shift
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'employee_code'  => $employee_code,
            'password'       => $hashed_password,
            'profile_image'  => $profile_name,
            'id_card_image'  => $id_card_name,
            'fullname'       => $fullname,
            'birth_date'     => $birth_date,
            'email'          => $email,
            'phone'          => $phone, 
            'address_detail' => $full_address_detail,
            'subdistrict'    => $subdistrict,
            'district'       => $district,
            'province'       => $province,
            'zipcode'        => $zipcode,
            'branch_id'      => $branch_id,
            'employee_type'  => $employee_type,
            'department'     => $department,
            'start_date'     => $start_date,
            'work_shift'     => $work_shift
        ]);

        echo json_encode(['status' => 'success', 'message' => 'ลงทะเบียนพนักงานพนักงานใหม่สำเร็จเรียบร้อยแล้ว!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนพนักงานใหม่ - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex items-center justify-center p-4 md:p-8">

    <div class="bg-white/40 backdrop-blur-xl border border-white/60 p-6 md:p-10 rounded-3xl shadow-2xl w-full max-w-5xl transition-all my-4">
        
        <div class="flex flex-col items-center md:items-start mb-8 border-b border-slate-200/60 pb-4">
            <h1 class="text-2xl font-bold text-slate-800 tracking-wide">ลงทะเบียนพนักงานใหม่</h1>
            <p class="text-slate-500 text-xs mt-1">เพิ่มข้อมูลเข้าสู่ระบบของ Lanto Global Logistics</p>
        </div>

        <form id="registerForm" enctype="multipart/form-data" autocomplete="off" class="space-y-8">
            
            <div>
                <h3 class="text-sm font-semibold text-blue-700 mb-4 flex items-center gap-2">📂 ส่วนที่ 1: รูปภาพหลักฐานตัวตน</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white/50 border border-slate-200/60 p-4 rounded-2xl flex flex-col items-center">
                        <label class="block text-xs font-medium text-slate-600 mb-3 w-full text-left">1. รูปถ่ายตัวเองพนักงาน</label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*" required onchange="previewImage(this, 'profile_view', 'profile_wrap')"
                            class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        
                        <div id="profile_wrap" class="hidden relative mt-4 w-32 h-32 rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                            <img id="profile_view" class="w-full h-full object-cover">
                            <button type="button" onclick="clearImage('profile_image', 'profile_wrap', 'profile_view')" class="absolute top-1 right-1 bg-rose-500 hover:bg-rose-600 text-white rounded-full p-1 shadow-md transition-colors cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>

                    <div class="bg-white/50 border border-slate-200/60 p-4 rounded-2xl flex flex-col items-center">
                        <label class="block text-xs font-medium text-slate-600 mb-3 w-full text-left">2. รูปถ่ายบัตรประชาชน</label>
                        <input type="file" id="id_card_image" name="id_card_image" accept="image/*" required onchange="previewImage(this, 'id_card_view', 'id_card_wrap')"
                            class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        
                        <div id="id_card_wrap" class="hidden relative mt-4 w-48 h-32 rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                            <img id="id_card_view" class="w-full h-full object-cover">
                            <button type="button" onclick="clearImage('id_card_image', 'id_card_wrap', 'id_card_view')" class="absolute top-1 right-1 bg-rose-500 hover:bg-rose-600 text-white rounded-full p-1 shadow-md transition-colors cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-blue-700 mb-4 flex items-center gap-2">🔐 ส่วนที่ 2: ข้อมูลบัญชีผู้ใช้</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">3. รหัสพนักงาน</label>
                        <input type="text" name="employee_code" placeholder="เช่น EMP002" required
                            class="w-full px-4 py-2.5 bg-white/60 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">4. รหัสผ่าน</label>
                        <input type="password" name="password" placeholder="••••••••" required autocomplete="new-password"
                            class="w-full px-4 py-2.5 bg-white/60 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">5. ยืนยันรหัสผ่าน</label>
                        <input type="password" name="confirm_password" placeholder="••••••••" required autocomplete="new-password"
                            class="w-full px-4 py-2.5 bg-white/60 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-blue-700 mb-4 flex items-center gap-2">👤 ส่วนที่ 3: ข้อมูลส่วนตัวและที่อยู่</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 mb-2">6. ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" placeholder="ชื่อ นามสกุลจริง" required
                            class="w-full px-4 py-2.5 bg-white/60 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">7. วัน/เดือน/ปี เกิด</label>
                        <div class="relative flex items-center">
                            <!-- 🎯 ไอคอนปฏิทิน SVG มินิมอล -->
                            <div class="absolute left-3.5 pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <input type="text" id="birth_date_input" name="birth_date" placeholder="คลิกเพื่อเลือกวันเกิด" readonly
                                class="calendar-trigger w-full pl-10 pr-4 py-2.5 bg-white/60 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-700 cursor-pointer">
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">8. อีเมล (Email)</label>
                        <input type="email" name="email" placeholder="example@lanto.com" required autocomplete="off"
                            class="w-full px-4 py-2.5 bg-white/60 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">9. เบอร์โทรศัพท์ติดต่อ</label>
                        <input type="tel" name="phone" placeholder="เช่น 0812345678" required autocomplete="off" maxlength="10"
                            class="w-full px-4 py-2.5 bg-white/60 border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="space-y-4 bg-slate-50/50 p-5 rounded-3xl border border-slate-200/50 shadow-inner">
                    <label class="block text-xs font-semibold text-slate-700">10. รายละเอียดพิกัดที่อยู่ติดต่อ</label>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">บ้านเลขที่</label>
                            <input type="text" name="house_no" placeholder="เช่น 123/45" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">หมู่ที่</label>
                            <input type="text" name="village" placeholder="เช่น มบ.แสนสุข" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">ซอย</label>
                            <input type="text" name="alley" placeholder="เช่น ซอย 5" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">ถนน</label>
                            <input type="text" name="street" placeholder="เช่น สุขุมวิท" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-1">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">จังหวัด</label>
                            <select id="province" name="province" required class="lanto-select w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:10px_10px] bg-[right:1rem_center] bg-no-repeat pr-8">
                                <option value="">เลือกจังหวัด</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">อำเภอ / เขต</label>
                            <select id="district" name="district" required class="lanto-select w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:10px_10px] bg-[right:1rem_center] bg-no-repeat pr-8">
                                <option value="">เลือกอำเภอ/เขต</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">ตำบล / แขวง</label>
                            <select id="subdistrict" name="subdistrict" required class="lanto-select w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:10px_10px] bg-[right:1rem_center] bg-no-repeat pr-8">
                                <option value="">เลือกตำบล/แขวง</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1 pl-1">รหัสไปรษณีย์</label>
                            <input type="text" id="zipcode" name="zipcode" placeholder="รหัสไปรษณีย์" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-blue-700 mb-4 flex items-center gap-2">💼 ส่วนที่ 4: ข้อมูลการทำงาน</h3>
                
                <?php include_once 'includes/rounded_dropdown.php'; ?>
                
                <!-- 🎯 ปรับเป็น Grid 2 คอลัมน์บนแท็บเล็ต และ 3 คอลัมน์บนจอคอม เพื่อให้แต่ละช่องกว้างขึ้น ไม่กระจุกแน่นล้นกรอบ -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    
                    <!-- 10. สาขาที่ปฏิบัติงาน -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">10. สาขาที่ปฏิบัติงาน</label>
                        <?php
                        $branch_opts = [];
                        foreach ($branches as $b) {
                            $branch_opts[] = ['id' => $b['id'], 'name' => $b['name']];
                        }
                        renderRoundedDropdown('branch_select', 'branch_id', 'เลือกสาขาที่ทำงาน', $branch_opts);
                        ?>
                    </div>

                    <!-- 11. ประเภทพนักงาน -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">11. ประเภทพนักงาน</label>
                        <?php
                        $type_opts = [];
                        foreach ($employee_types as $et) {
                            $type_opts[] = ['id' => $et['id'], 'name' => $et['name']];
                        }
                        renderRoundedDropdown('type_select', 'employee_type', 'เลือกประเภทพนักงาน', $type_opts);
                        ?>
                    </div>

                    <!-- 12. แผนก / ฝ่าย -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">12. แผนก / ฝ่าย</label>
                        <?php
                        $dept_opts = [];
                        foreach ($departments as $d) {
                            $dept_opts[] = ['id' => $d['id'], 'name' => $d['name']];
                        }
                        renderRoundedDropdown('department_select', 'department', 'เลือกแผนก/ฝ่าย', $dept_opts);
                        ?>
                    </div>

                    <!-- 13. วันเริ่มทำงาน -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">13. วันเริ่มทำงาน</label>
                        <div class="relative flex items-center">
                            <!-- 🎯 ไอคอนปฏิทิน SVG มินิมอล -->
                            <div class="absolute left-3.5 pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <input type="text" id="start_date_input" name="start_date" placeholder="คลิกเลือกวันเริ่มทำงาน" readonly
                                class="calendar-trigger w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-700 cursor-pointer h-[42px] shadow-sm font-medium">
                        </div>
                    </div>

                    <!-- 14. กะการทำงาน -->
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">14. กะการทำงาน</label>
                        <?php
                        $shift_opts = [];
                        foreach ($work_shifts as $ws) {
                            $shift_opts[] = ['id' => $ws['id'], 'name' => $ws['name']];
                        }
                        renderRoundedDropdown('shift_select', 'work_shift', 'เลือกกะการทำงาน', $shift_opts);
                        ?>
                    </div>

                </div>
            </div>

            <div class="flex gap-4 pt-4">
                <a href="login.php" class="w-1/3 text-center border border-slate-300 text-slate-600 font-medium py-3 rounded-2xl text-sm hover:bg-slate-50 transition-all">ย้อนกลับ</a>
                <button type="submit" class="w-2/3 bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white font-medium py-3 rounded-2xl shadow-lg shadow-blue-700/10 transition-all text-sm cursor-pointer">บันทึกข้อมูลพนักงานใหม่</button>
            </div>
        </form>
    </div>

    <?php include_once 'includes/calendar_component.php'; ?>

    <script src="assets/js/alerts.js"></script>
    <script src="assets/js/address_select.js"></script>
    <script src="assets/js/lanto_dropdown.js"></script>

    <script>
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

    function clearImage(inputId, wrapId, viewId) {
        document.getElementById(inputId).value = ''; 
        document.getElementById(viewId).src = '';     
        document.getElementById(wrapId).classList.add('hidden'); 
    }

    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        LantoAlert.loading('กำลังบันทึกข้อมูล', 'ระบบกำลังอัปโหลดเอกสารและเข้ารหัสข้อมูลความปลอดภัย...');
        const formData = new FormData(this);
        setTimeout(() => {
            fetch('register.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                LantoAlert.close();
                setTimeout(() => {
                    if (data.status === 'success') {
                        LantoAlert.success('บันทึกสำเร็จ', data.message, function() {
                            window.location.href = 'login.php';
                        });
                    } else {
                        LantoAlert.error('สมัครสมาชิกล้มเหลว', data.message);
                    }
                }, 300);
            })
            .catch(err => {
                LantoAlert.close();
                setTimeout(() => { LantoAlert.error('เกิดข้อผิดพลาด', 'ไม่สามารถติดต่อเซิร์ฟเวอร์หลักได้'); }, 300);
            });
        }, 1200);
    });
    </script>
</body>
</html>