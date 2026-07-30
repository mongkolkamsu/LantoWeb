<?php
session_start();
require_once '../config/db.php'; // เชื่อมต่อฐานข้อมูลหลัก

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องล็อกอินก่อน)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

mb_internal_encoding("UTF-8");

$user_id = $_SESSION['user_id'];

// ประกาศตัวแปรเริ่มต้น
$fullname = "";
$employee_code = "";
$profile_image = "";
$dept_name = "ไม่ระบุแผนก";
$email = "-";
$phone = "-";

$employee_type = "-";
$work_shift = "-";
$birth_date = "-";
$start_date = "-";
$address_list = array();

try {
    // 🎯 ดึงข้อมูลพร้อม JOIN แปลง ID เป็นชื่อประเภทพนักงาน แผนก และกะงาน
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
    $stmt->execute(array('id' => $user_id));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $fullname = $user['fullname'];
        $employee_code = $user['employee_code'];
        $profile_image = $user['profile_image'];
        $email = !empty($user['email']) ? $user['email'] : '-';
        $phone = !empty($user['phone']) ? $user['phone'] : '-';
        
        // แปลงค่า ID ให้แสดงเป็นชื่อจริง
        $dept_name     = !empty($user['dept_name']) ? $user['dept_name'] : (!empty($user['department']) ? $user['department'] : 'ไม่ระบุแผนก');
        $employee_type = !empty($user['type_name']) ? $user['type_name'] : (!empty($user['employee_type']) ? $user['employee_type'] : '-');
        $work_shift    = !empty($user['shift_name']) ? $user['shift_name'] : (!empty($user['work_shift']) ? $user['work_shift'] : '-');
        
        $birth_date = ($user['birth_date'] && $user['birth_date'] != '0000-00-00') ? date('d/m/Y', strtotime($user['birth_date'])) : '-';
        $start_date = ($user['start_date'] && $user['start_date'] != '0000-00-00') ? date('d/m/Y', strtotime($user['start_date'])) : '-';

        $work_tenure = "-";
        if (!empty($user['start_date']) && $user['start_date'] != '0000-00-00') {
            $start_dt = new DateTime($user['start_date']);
            $current_dt = new DateTime(); 
            $diff = $start_dt->diff($current_dt);
            $work_tenure = $diff->y . " ปี " . $diff->m . " เดือน " . $diff->d . " วัน";
        }

        // ระบบจัดกลุ่มที่อยู่เป็น 3 บรรทัด
        $address_list = array();

        if (!empty($user['address_detail'])) {
            $line1 = str_replace('หมู่บ้าน/อาคาร', 'หมู่ที่', $user['address_detail']);
            $line1 = str_replace('|', ' ', $line1);
            $line1 = preg_replace('/\s+/', ' ', $line1);
            $address_list[] = trim($line1);
        }

        $line2 = "";
        if (!empty($user['subdistrict'])) $line2 .= "ตำบล " . $user['subdistrict'] . "  ";
        if (!empty($user['district'])) $line2 .= "อำเภอ " . $user['district'];
        if (trim($line2) !== '') $address_list[] = trim($line2);

        $line3 = "";
        if (!empty($user['province'])) $line3 .= "จังหวัด " . $user['province'] . " ";
        if (!empty($user['zipcode'])) $line3 .= $user['zipcode'];
        if (trim($line3) !== '') $address_list[] = trim($line3);
    }
} catch (PDOException $e) {
    // ซ่อนข้อผิดพลาด
}

$avatar_url = !empty($profile_image) ? '../uploads/profiles/' . $profile_image : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=300&h=300&q=80';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>บัตรพนักงานดิจิทัล - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { display: none; }
        
        .card-container { perspective: 1000px; }
        .card-inner { 
            transition: transform 0.6s ease-in-out; 
            -webkit-transform-style: preserve-3d; 
            transform-style: preserve-3d; 
        }
        .card-flipped { transform: rotateY(180deg); }
        
        /* 🎯 บังคับซ่อนหน้าหลังแบบ 100% สำหรับ iOS Safari */
        .card-front, .card-back { 
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden !important; 
            backface-visibility: hidden !important; 
            -webkit-transform-style: preserve-3d;
            transform-style: preserve-3d;
        }
        .card-front { transform: rotateY(0deg); }
        .card-back { transform: rotateY(180deg); }
    </style>
    <script src="../assets/js/alerts.js"></script>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex items-center justify-center p-0 md:p-4 text-slate-800 antialiased select-none">

    <div class="w-full min-h-screen bg-white/40 backdrop-blur-xl flex flex-col justify-between relative overflow-y-auto p-5 pb-28
            md:max-w-md md:mx-auto md:my-6 md:min-h-[812px] md:rounded-[40px] md:border md:border-white/60 md:shadow-2xl">
        
        <div>
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-200/60">
                <a href="../index.php?view=mobile" class="w-10 h-10 bg-white/80 border border-slate-200 text-slate-600 rounded-full flex items-center justify-center shadow-xs active:scale-90 transition-transform cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="text-[15px] font-bold tracking-wide text-slate-700">บัตรประจำตัวพนักงาน</h2>
                <div class="w-10"></div>
            </div>

            <p class="text-[12px] text-center text-slate-400 mt-4 font-semibold">💡 แตะที่ตัวบัตรเพื่อพลิกดูข้อมูลส่วนตัวของพนักงาน</p>

            <!-- 💳 3D FLIP ID CARD -->
            <div class="card-container w-80 h-[500px] mx-auto my-4 cursor-pointer" onclick="flipCard()">
                <div id="id-card" class="card-inner w-full h-full relative shadow-2xl rounded-[32px]">
                    
                    <!-- ด้านหน้าบัตร -->
                    <div class="card-front bg-white rounded-[32px] overflow-hidden border border-slate-200/40 flex flex-col justify-end">
                        <img src="<?php echo $avatar_url; ?>" class="absolute top-[45px] left-1/2 -translate-x-1/2 w-[220px] h-[270px] object-cover" alt="Employee Photo">
                        <img src="../assets/images/bg.png" class="absolute inset-0 w-full h-full object-cover pointer-events-none" alt="Card Background">
                        
                        <div class="relative text-center w-full px-5 pb-5 space-y-1">
                            <h3 class="text-lg font-bold text-slate-950 tracking-wide leading-tight">
                                <?php echo htmlspecialchars($fullname); ?>
                            </h3>
                            <p class="text-sm font-black text-slate-800">
                                แผนก : <?php echo htmlspecialchars($dept_name); ?>
                            </p>
                            <p class="text-sm font-black text-slate-800 tracking-wide">
                                ID NO : <?php echo htmlspecialchars($employee_code); ?>
                            </p>
                        </div>
                    </div>

                    <!-- ด้านหลังบัตร -->
                    <div class="card-back absolute inset-0 w-full h-full bg-white rounded-[32px] overflow-hidden border border-slate-200/60 flex flex-col p-6 text-slate-800 text-left justify-between">
                        
                        <div class="space-y-3 w-full">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-2.5">
                                <span class="text-base">👤</span>
                                <h4 class="text-sm font-bold tracking-wider text-back-600 uppercase">ข้อมูลส่วนตัวพนักงาน</h4>
                            </div>

                            <div class="space-y-2.5 text-xs pt-1">
                                <div class="border-b border-slate-100 pb-1.5">
                                    <span class="text-slate-700 block text-[12px] font-bold uppercase tracking-wide">ประเภทบุคลากร</span>
                                    <span class="text-slate-500 font-semibold mt-0.5 block"><?php echo htmlspecialchars($employee_type); ?></span>
                                </div>
                                <div class="border-b border-slate-100 pb-1.5">
                                    <span class="text-slate-700 block text-[12px] font-bold uppercase tracking-wide">กะเวลาปฏิบัติงานหลัก</span>
                                    <span class="text-slate-500 font-semibold mt-0.5 block"><?php echo htmlspecialchars($work_shift); ?></span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 border-b border-slate-100 pb-1.5">
                                    <div>
                                        <span class="text-slate-700 block text-[12px] font-bold uppercase tracking-wide">วันเกิด</span>
                                        <span class="text-slate-500 font-semibold mt-0.5 block"><?php echo htmlspecialchars($birth_date); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-slate-700 block text-[12px] font-bold uppercase tracking-wide">วันเริ่มบรรจุงาน</span>
                                        <span class="text-slate-500 font-semibold mt-0.5 block"><?php echo htmlspecialchars($start_date); ?></span>
                                    </div>
                                </div>
                                
                                <div class="border-b border-slate-100 pb-1.5">
                                    <span class="text-slate-700 block text-[12px] font-bold uppercase tracking-wide">อายุงานรวม</span>
                                    <span class="text-slate-500 font-semibold mt-0.5 block"><?php echo htmlspecialchars($work_tenure); ?></span>
                                </div>
                                
                                <div>
                                    <span class="text-slate-700 block text-[12px] font-bold uppercase tracking-wide mb-1">ที่อยู่อาศัยตามทะเบียน</span>
                                    <div class="space-y-1 text-[12px] font-semibold text-slate-500 max-h-[125px] overflow-y-auto pr-1">
                                        <?php if (empty($address_list)): ?>
                                            <p class="text-slate-600 italic font-semibold">- ไม่มีข้อมูลที่อยู่ -</p>
                                        <?php else: ?>
                                            <?php foreach ($address_list as $line): ?>
                                                <div class="flex items-start gap-1.5 leading-normal">
                                                    <span class="text-orange-500 text-[13px] mt-0.5 select-none shrink-0">•</span>
                                                    <span><?php echo htmlspecialchars($line); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-3 flex flex-col gap-1 text-[10px] text-slate-500 mt-2">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-slate-700 truncate max-w-[200px]">Email: <?php echo htmlspecialchars($email); ?></span>
                                <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px] font-bold shrink-0">ID : <?php echo htmlspecialchars($employee_code); ?></span>
                            </div>
                            <!-- 🎯 แสดงเบอร์โทรศัพท์ -->
                            <div class="font-bold text-slate-700">
                                เบอร์โทร: <span class="text-blue-600"><?php echo htmlspecialchars($phone); ?></span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ✏️ ปุ่มแก้ไขข้อมูลส่วนตัว -->
            <div class="w-full max-w-[320px] mx-auto mt-3">
                <a href="edit_profile.php" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold transition-all shadow-md shadow-blue-500/20 active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                    <span>✏️</span> แก้ไขข้อมูลส่วนตัว
                </a>
            </div>

            <!-- 🚪 ปุ่มออกจากระบบ -->
            <div class="w-full max-w-[320px] mx-auto mt-2.5 mb-2">
                <a href="../logout.php" 
                onclick="confirmLogout(event)" 
                class="w-full py-3 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200/80 rounded-2xl text-xs font-bold transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-3xs">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>ออกจากระบบ (Logout)</span>
                </a>
            </div>
        </div>
        
        <?php include '../includes/navbar.php'; ?>                                        
        
    </div>
    <script>
        function flipCard() {
            const card = document.getElementById('id-card');
            card.classList.toggle('card-flipped');
        }

        // 🎯 ใช้ LantoAlert ระบบหลักของเว็บ
        function confirmLogout(event) {
            event.preventDefault(); // ยับยั้งไม่ให้เปลี่ยนหน้าทันที

            LantoAlert.confirm(
                'ยืนยันการออกจากระบบ?',                            
                'คุณต้องการออกจากระบบ Lanto Workforce ใช่หรือไม่', 
                function() {                                       
                    window.location.href = '../logout.php';
                },
                null,                                             
                'danger'                                          
            );
        }
    </script>
</body>
</html>