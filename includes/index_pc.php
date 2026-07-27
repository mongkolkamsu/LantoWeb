<?php
// ดักจับกรณีถ้าพนักงานพยายามแอบพิมพ์เข้าหน้านี้โดยตรงโดยไม่ผ่าน index.php หลัก
if (!isset($user_id)) {
    header("Location: ../index.php");
    exit();
}

// 🔍 ดึงข้อมูลพนักงานแบบละเอียดเพิ่มเติมจากฐานข้อมูลเพื่อนำมาแสดงผลให้ครบถ้วน
try {
    $stmt_user = $pdo->prepare("SELECT employee_code, email, employee_type, department, start_date, work_shift FROM users WHERE id = :id LIMIT 1");
    $stmt_user->execute(['id' => $user_id]);
    $user_detail = $stmt_user->fetch();

    $emp_code   = $user_detail['employee_code'] ?? '---';
    $emp_email  = $user_detail['email'] ?? '---';
    $emp_type   = $user_detail['employee_type'] ?? '---';
    $emp_dept   = $user_detail['department'] ?? '---';
    $work_shift = $user_detail['work_shift'] ?? '---';
    
    // แปลงฟอร์แมตวันที่เริ่มทำงานให้เป็นแบบไทย (ว/ด/ป)
    $start_date = ($user_detail['start_date'] !== '0000-00-00' && !empty($user_detail['start_date'])) 
                  ? date('d/m/Y', strtotime($user_detail['start_date'])) 
                  : '---';
} catch (PDOException $e) {
    // กำหนดค่า Default กรณีระบบฐานข้อมูลขัดข้อง
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แผงควบคุมระบบบันทึกเวลางาน - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex text-slate-800">

    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between shrink-0 border-r border-slate-800 hidden md:flex min-h-screen">
        <div>
            <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-800 bg-slate-950/40">
                <div class="h-8 w-8 bg-white p-1 rounded-lg flex items-center justify-center">
                    <img src="assets/images/LOGO-Lanto.png" alt="Lanto Logo" class="object-contain h-full w-full">
                </div>
                <span class="text-white font-bold tracking-wider text-sm uppercase">Lanto Logistics</span>
            </div>
            
            <nav class="p-4 space-y-1.5">
                <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider px-3 mb-2">Main Menu</p>
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-gradient-to-r from-blue-700 to-blue-600 text-white font-medium shadow-md shadow-blue-900/20 transition-all">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                    <span class="text-sm">หน้าแรก / แดชบอร์ด</span>
                </a>
                <a href="employee/scan.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800/60 hover:text-white transition-all text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 4v1m-11-5h2m13-11a7 7 0 11-14 0 7 7 0 0114 0zM12 9v3m0 0v3m0-3h3m-3 0H9"></path></svg>
                    <span>สแกนใบหน้าเข้า/ออกงาน</span>
                </a>
                <a href="employee/history.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800/60 hover:text-white transition-all text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z"></path></svg>
                    <span>รายงานประวัติลงเวลา</span>
                </a>
                <a href="employee/profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800/60 hover:text-white transition-all text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <span>ข้อมูลประวัติส่วนตัว</span>
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-slate-800/80 bg-slate-950/20">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 transition-all text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h3a3 3 0 013 3v1"></path></svg>
                <span>ออกจากระบบ</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        
        <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-xs">
            <div>
                <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Employee Portal</p>
                <h2 class="text-lg font-bold text-slate-800">ระบบสารสนเทศบันทึกเวลาปฏิบัติงาน</h2>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="text-right hidden lg:block border-r border-slate-200 pr-6">
                    <p class="text-[10px] text-slate-400">เข้างานวันนี้</p>
                    <p class="text-sm font-bold text-slate-700"><?php echo $checkin_time; ?></p>
                </div>
                
                <div class="flex items-center gap-3">
                    <img src="<?php echo $avatar_url; ?>" alt="Profile Avatar" class="w-10 h-10 rounded-xl object-cover ring-2 ring-slate-100 shadow-xs">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 leading-tight"><?php echo htmlspecialchars($fullname); ?></p>
                        <p class="text-[11px] text-slate-400"><?php echo htmlspecialchars($emp_code); ?></p>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 md:p-8 space-y-6 max-w-(screen-2xl) w-full mx-auto">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-medium text-slate-400 uppercase">เวลาเช็คอิน (IN)</p>
                        <p class="text-2xl font-bold text-slate-800 mt-1"><?php echo $checkin_time; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center font-bold text-sm shadow-xs">IN</div>
                </div>

                <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-medium text-slate-400 uppercase">เวลาเช็คเอาท์ (OUT)</p>
                        <p class="text-2xl font-bold text-slate-800 mt-1"><?php echo $checkout_time; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center font-bold text-sm shadow-xs">OUT</div>
                </div>

                <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-medium text-slate-400 uppercase">ตารางกะเวลาทำงาน</p>
                        <p class="text-base font-bold text-slate-700 mt-2 truncate max-w-[150px]"><?php echo htmlspecialchars($work_shift); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-sm shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-medium text-slate-400 uppercase">สถานะประจำวันนี้</p>
                        <span class="inline-block text-xs font-semibold px-3 py-1 rounded-xl mt-2 border <?php echo $status_badge_color; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                    <div class="w-10 h-10 bg-slate-50 text-slate-500 rounded-xl flex items-center justify-center text-sm shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-6 lg:col-span-2 space-y-5">
                    <div class="border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-bold text-slate-800 tracking-wide flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5t-2-2z"></path></svg>
                            ประวัติและข้อมูลสังกัดพนักงานคอมพิวเตอร์พอร์ทัล
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">รายละเอียดข้อมูลโครงสร้างสังกัดอย่างเป็นทางการจากฐานข้อมูลองค์กร</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div class="flex justify-between py-2 border-b border-slate-50">
                            <span class="text-slate-400 font-light">รหัสพนักงาน (Username):</span>
                            <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($emp_code); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-50">
                            <span class="text-slate-400 font-light">ชื่อ-นามสกุลพนักงาน:</span>
                            <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($fullname); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-50">
                            <span class="text-slate-400 font-light">แผนก / ฝ่ายปฏิบัติงาน:</span>
                            <span class="font-semibold text-blue-700"><?php echo htmlspecialchars($emp_dept); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-50">
                            <span class="text-slate-400 font-light">ประเภทการจ้างงาน:</span>
                            <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($emp_type); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-50">
                            <span class="text-slate-400 font-light">วันที่เริ่มปฏิบัติงานจริง:</span>
                            <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($start_date); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-50">
                            <span class="text-slate-400 font-light">อีเมลพนักงาน (Email):</span>
                            <span class="font-semibold text-slate-700 underline decoration-slate-200"><?php echo htmlspecialchars($emp_email); ?></span>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-5 space-y-3">
                        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-1">ระบบลงเวลาแบบด่วน</h3>
                        
                        <a href="employee/scan.php?type=check_in" class="flex items-center justify-center gap-2 p-3.5 bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white rounded-xl shadow-md shadow-blue-700/20 transition-all font-medium text-sm active:scale-[0.99]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 01-3-3h3a3 3 0 013 3v1"></path></svg>
                            เปิดกล้องสแกนเข้างาน (Check-In)
                        </a>

                        <a href="employee/scan.php?type=check_out" class="flex items-center justify-center gap-2 p-3.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl transition-all font-medium text-sm">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h3a3 3 0 013 3v1"></path></svg>
                            เปิดกล้องสแกนออกงาน (Check-Out)
                        </a>
                    </div>

                    <div class="bg-gradient-to-br from-blue-800 to-indigo-900 rounded-2xl p-6 text-white shadow-md relative overflow-hidden">
                        <span class="text-[10px] bg-white/20 text-blue-100 px-2 py-0.5 rounded-md font-medium tracking-wide uppercase">AI SECURITY</span>
                        <h4 class="text-sm font-semibold mt-3">ระบบยืนยันตัวตนระดับความปลอดภัยสูง</h4>
                        <p class="text-xs text-blue-200/90 mt-1.5 leading-relaxed font-light">โปรดตรวจสอบให้แน่ใจว่าอุปกรณ์คอมพิวเตอร์ของคุณเชื่อมต่อกล้องเว็บแคมเรียบร้อย เพื่อประมวลผลวิเคราะห์ค่า Face Vector 128 มิติ ผ่านไลบรารี face-api.js ได้อย่างถูกต้องแม่นยำ</p>
                    </div>

                </div>

            </div>

        </div>

        <footer class="mt-auto py-4 bg-white/60 border-t border-slate-200 text-center text-xs text-slate-400 font-light w-full">
            © Lanto Global Logistics. ระบบบริหารจัดการเวลาปฏิบัติงานพนักงานพอร์ทัลคอมพิวเตอร์แบบทางการครบรอบด้าน
        </footer>
    </main>

</div>
</body>
</html>