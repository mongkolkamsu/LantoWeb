<?php
// ดึงไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config/db.php';

// ความปลอดภัยขั้นที่ 1: ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$fullname  = $_SESSION['fullname'];
$emp_code  = $_SESSION['employee_code'];
$profile_img = !empty($_SESSION['profile_image']) ? '../uploads/profiles/' . $_SESSION['profile_image'] : 'https://ui-avatars.com/api/?name=' . urlencode($fullname) . '&background=0D8ABC&color=fff&size=128';

// ค้นหาประวัติการสแกนในวันนี้ของพนักงานล่าสุดเพื่อสลับโหมดปุ่มวงกลมอัจฉริยะ
$today = date('Y-m-d');
try {
    $stmt = $pdo->prepare("SELECT log_type FROM attendance WHERE user_id = :user_id AND DATE(scan_time) = :today ORDER BY id DESC LIMIT 1");
    $stmt->execute(['user_id' => $user_id, 'today' => $today]);
    $last_log = $stmt->fetch();

    $current_action = 'check_in'; 
    if ($last_log) {
        if ($last_log['log_type'] === 'check_in') {
            $current_action = 'check_out'; 
        } else {
            $current_action = 'check_in';  
        }
    }
} catch (PDOException $e) {
    $current_action = 'check_in';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกเวลาทำงาน - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex flex-col pb-24"> <!-- เติม pb-24 ป้องกันเมนูล่างทับคอนเทนต์ -->

    <!-- 🌐 1. แถบเมนูด้านบน ดีไซน์มินิมอล (ลบโปรไฟล์ซ้าย และปุ่ม Log out ขวาออกตามสั่ง) -->
    <nav class="bg-white/50 backdrop-blur-md border-b border-white/60 px-4 py-3 flex justify-between items-center sticky top-0 z-40 h-16">
        <!-- ฝั่งซ้าย: ปล่อยว่างเพื่อความสมดุล Balance สมมาตรกับฝั่งขวา -->
        <div class="w-9 h-9"></div>
        
        <!-- 🎯 โลโก้และชื่อแบรนด์ Lanto Web ล็อกตำแหน่งกึ่งกลางหน้าจอถาวร -->
        <div class="absolute left-1/2 transform -translate-x-1/2 flex items-center gap-2">
            <div class="w-8 h-8 bg-white p-1 rounded-lg shadow-sm overflow-hidden flex items-center justify-center">
                <img src="../assets/images/LOGO-Lanto.png" alt="Lanto Logo" class="object-contain w-full h-full">
            </div>
            <div class="text-center">
                <span class="font-bold text-slate-800 text-sm tracking-wide block">Lanto Web</span>
                <p class="text-[9px] text-slate-400 -mt-1">Lanto Global Logistics</p>
            </div>
        </div>
        
        <!-- ฝั่งขวา: ปุ่มกระดิ่งแจ้งเตือนเรืองแสงสีเหลืองทอง (คงไว้ตามบรีฟภาพแรก) -->
        <div class="flex items-center">
            <button type="button" class="relative p-2 bg-white/60 hover:bg-white rounded-xl transition-all text-slate-500 shadow-sm cursor-pointer group" title="การแจ้งเตือน">
                <svg class="w-4.5 h-4.5 group-hover:animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                <span class="absolute top-1.5 right-1.5 flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
            </button>
        </div>
    </nav>

    <!-- 🎬 พื้นที่คอนเทนต์หลัก (นาฬิกา และปุ่มวงกลม AI Orb พลังงานเรดาร์) -->
    <main class="flex-grow p-4 flex items-center justify-center">
        <div class="w-full max-w-md bg-white/40 backdrop-blur-xl border border-white/60 p-8 rounded-3xl shadow-2xl shadow-slate-300/50 text-center flex flex-col items-center">
            
            <span class="text-xs font-semibold text-blue-700 bg-blue-50 px-3 py-1 rounded-xl uppercase tracking-wider mb-2">Lanto Time Clock</span>
            <div id="liveTime" class="text-5xl font-bold text-slate-800 tracking-tight my-2">00:00:00</div>
            <div id="liveDate" class="text-xs text-slate-500 font-medium mb-12">วันเวย์ที่ 00 เดือนปี พ.ศ. 0000</div>

            <input type="hidden" id="latitude" name="latitude">
            <input type="hidden" id="longitude" name="longitude">

            <!-- ปุ่มวงกลมเรืองแสงอนิเมชันคลื่นเรดาร์ -->
            <div class="relative flex items-center justify-center mb-6">
                <?php if ($current_action === 'check_in'): ?>
                    <div class="absolute w-40 h-40 bg-emerald-400/20 rounded-full animate-ping duration-1000"></div>
                    <div class="absolute w-36 h-36 bg-emerald-400/10 rounded-full animate-pulse"></div>
                    <button type="button" onclick="openScanPopup('check_in')"
                        class="relative w-32 h-32 rounded-full bg-gradient-to-tr from-emerald-600 via-emerald-500 to-teal-400 text-white flex flex-col items-center justify-center shadow-xl shadow-emerald-600/30 hover:shadow-emerald-600/50 hover:scale-105 active:scale-95 transition-all duration-300 border-4 border-white/80 cursor-pointer group">
                        <svg class="w-8 h-8 group-hover:-translate-y-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 13l-7 7-7-7m14-6l-7 7-7-7"></path></svg>
                        <span class="text-[11px] font-semibold mt-1.5 tracking-wide">สแกนเข้างาน</span>
                    </button>
                <?php else: ?>
                    <div class="absolute w-40 h-40 bg-rose-400/20 rounded-full animate-ping duration-1000"></div>
                    <div class="absolute w-36 h-36 bg-rose-400/10 rounded-full animate-pulse"></div>
                    <button type="button" onclick="openScanPopup('check_out')"
                        class="relative w-32 h-32 rounded-full bg-gradient-to-tr from-rose-600 via-rose-500 to-amber-500 text-white flex flex-col items-center justify-center shadow-xl shadow-rose-600/30 hover:shadow-rose-600/50 hover:scale-105 active:scale-95 transition-all duration-300 border-4 border-white/80 cursor-pointer group">
                        <svg class="w-8 h-8 group-hover:translate-y-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 11l7-7 7 7M5 19l7-7 7 7"></path></svg>
                        <span class="text-[11px] font-semibold mt-1.5 tracking-wide">สแกนออกงาน</span>
                    </button>
                <?php endif; ?>
            </div>

            <p class="text-[11px] font-medium text-slate-600 mt-2">แตะที่วงกลมเพื่อเปิดกล้องชีวมาตร</p>
            <p id="gpsStatus" class="text-[10px] text-slate-400 mt-6 flex items-center gap-1">
                <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-ping"></span> กำลังดึงพิกัดตำแหน่งดาวเทียมความปลอดภัย...
            </p>
        </div>
    </main>

    <!-- 🟢 2. [จุดประสงค์หลัก] แถบเมนูด้านล่างแปลงโฉมใหม่ (ลบปุ่มกลาง / ย้ายรูปโปรไฟล์ลงล่าง / เพิ่มปุ่ม Log out ขวาสด) -->
    <div class="fixed bottom-0 left-0 right-0 bg-white/60 backdrop-blur-xl border-t border-white/80 rounded-t-3xl shadow-[0_-10px_30px_rgba(15,23,42,0.06)] flex justify-around items-center z-40 pt-3 pb-5 px-2">
        
        <!-- เมนูที่ 1: ประวัติการมาทำงาน -->
        <a href="history.php" class="flex flex-col items-center gap-1 text-slate-400 hover:text-blue-600 transition-colors group w-1/4">
            <div class="p-1 group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <span class="text-[10px] font-medium">ประวัติงาน</span>
        </a>

        <!-- เมนูที่ 2: แจ้งลาพักผ่อน -->
        <a href="leave.php" class="flex flex-col items-center gap-1 text-slate-400 hover:text-blue-600 transition-colors group w-1/4">
            <div class="p-1 group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <span class="text-[10px] font-medium">แจ้งลา</span>
        </a>

        <!-- 🔴 เมนูที่ 3: ย้ายรูปถ่ายโปรไฟล์จริงลงมาสวมแทนที่ตรงนี้ (จากจุดสีแดงหัวมุมซ้ายบนเดิม) -->
        <a href="profile.php" class="flex flex-col items-center gap-1 text-slate-400 hover:text-blue-600 transition-colors group w-1/4">
            <div class="p-0.5 group-hover:scale-110 transition-transform flex justify-center items-center">
                <img src="<?php echo $profile_img; ?>" alt="Profile" class="w-5.5 h-5.5 rounded-full object-cover border border-slate-200/80 shadow-sm">
            </div>
            <span class="text-[10px] font-medium">โปรไฟล์</span>
        </a>

        <!-- 🟢 เมนูที่ 4: ย้ายปุ่ม "ออกจากระบบ" มาล็อกตำแหน่งขวาสุดถัดจากโปรไฟล์ (จากมุมขวาบนเดิม) -->
        <a href="../logout.php" class="flex flex-col items-center gap-1 text-slate-400 hover:text-rose-600 transition-colors group w-1/4">
            <div class="p-1 group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-rose-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </div>
            <span class="text-[10px] font-medium">ออกจากระบบ</span>
        </a>
    </div>

    <!-- หน้าต่างป๊อปอัปส่องกล้องสแกนใบหน้าลอยกลางจอ -->
    <div id="scanModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-center justify-center p-4 transition-all duration-300 opacity-0 pointer-events-none">
        <div class="bg-white/80 backdrop-blur-2xl border border-white p-6 rounded-3xl shadow-2xl max-w-md w-full text-center transform scale-90 transition-all duration-300">
            <div class="mb-4">
                <h3 id="modalTitle" class="text-base font-bold text-slate-800">ระบบตรวจสอบใบหน้า</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">กรุณามองตรงมาที่กล้องเพื่อทำการบันทึกเวลางาน</p>
            </div>
            <div class="relative w-full aspect-video bg-slate-950 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center border border-white/20">
                <video id="webcam" autoplay playsinline muted class="w-full h-full object-cover transform -scale-x-100"></video>
                <canvas id="captureCanvas" class="hidden"></canvas>
                <div class="absolute inset-0 border-2 border-dashed border-blue-400/20 rounded-2xl pointer-events-none flex items-center justify-center">
                    <div class="w-40 h-40 border-4 border-blue-500/40 rounded-full animate-pulse opacity-50"></div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3 mt-5">
                <button type="button" onclick="closeScanPopup()" class="col-span-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium py-3 rounded-2xl text-xs transition-colors cursor-pointer">ยกเลิก</button>
                <button type="button" id="btnShutter" class="col-span-2 bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white font-medium py-3 rounded-2xl text-xs shadow-md transition-all cursor-pointer transform active:scale-95">📸 ยืนยันสแกนใบหน้า</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/alerts.js"></script>

    <script>
    let streamObject = null; 
    let targetLogType = 'check_in'; 

    const modal = document.getElementById('scanModal');
    const video = document.getElementById('webcam');
    const modalTitle = document.getElementById('modalTitle');

    function openScanPopup(type) {
        targetLogType = type;
        modalTitle.textContent = type === 'check_in' ? 'สแกนใบหน้าเข้างาน (Check In)' : 'สแกนใบหน้าออกงาน (Check Out)';
        
        modal.classList.remove('opacity-0', 'pointer-events-none');
        modal.firstElementChild.classList.remove('scale-90');
        modal.firstElementChild.classList.add('scale-100');

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } })
                .then(function(stream) {
                    streamObject = stream;
                    video.srcObject = stream;
                })
                .catch(function(error) {
                    LantoAlert.error('ไม่พบอุปกรณ์กล้อง', 'เบราว์เซอร์ไม่สามารถเข้าถึงกล้องหน้าได้ โปรดเปิดรับสิทธิ์ระบบความปลอดภัย');
                    closeScanPopup();
                });
        }
    }

    function closeScanPopup() {
        modal.classList.add('opacity-0', 'pointer-events-none');
        modal.firstElementChild.classList.remove('scale-100');
        modal.firstElementChild.classList.add('scale-90');
        
        if (streamObject) {
            streamObject.getTracks().forEach(track => track.stop()); 
            video.srcObject = null;
        }
    }

    function getGPSLocation() {
        const statusText = document.getElementById('gpsStatus');
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(
                function(position) {
                    document.getElementById('latitude').value = position.coords.latitude;
                    document.getElementById('longitude').value = position.coords.longitude;
                    statusText.innerHTML = `<span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> ระบบล็อกพิกัดดาวเทียมสากลสำเร็จ`;
                },
                function(error) {
                    statusText.innerHTML = `<span class="w-1.5 h-1.5 bg-rose-500 rounded-full"></span> สัญญาณระบุตำแหน่งพิกัดบกพร่อง`;
                },
                { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
            );
        }
    }

    function updateClock() {
        const now = new Date();
        const thDayNames = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        const thMonthNames = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
        
        document.getElementById('liveTime').textContent = now.toLocaleTimeString('th-TH', { hour12: false });
        document.getElementById('liveDate').textContent = `วัน${thDayNames[now.getDay()]}ที่ ${now.getDate()} ${thMonthNames[now.getMonth()]} พ.ศ. ${now.getFullYear() + 543}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        getGPSLocation();
        updateClock();
        setInterval(updateClock, 1000);
    });

    document.getElementById('btnShutter').addEventListener('click', function() {
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        
        if (!lat || !lng) {
            LantoAlert.warning('พิกัดไม่สมบูรณ์', 'ระบบต้องการสัญญาณตำแหน่ง GPS เพื่อยืนยันพิกัดเข้างาน กรุณาเปิดสิทธิ์ที่อยู่บนอุปกรณ์');
            return;
        }

        closeScanPopup();
        LantoAlert.loading('กำลังตรวจจับโครงสร้างใบหน้า', 'ระบบความปลอดภัย Lanto Web กำลังบันทึกผลพิกัดและเวลาปัจจุบันเข้าสู่เซิร์ฟเวอร์...');

        const canvas = document.getElementById('captureCanvas');
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const base64Photo = canvas.toDataURL('image/jpeg');

        setTimeout(() => {
            const postData = new FormData();
            postData.append('photo', base64Photo);
            postData.append('log_type', targetLogType);
            postData.append('latitude', lat);
            postData.append('longitude', lng);

            fetch('../api/save-attendance.php', {
                method: 'POST',
                body: postData
            })
            .then(res => res.json())
            .then(data => {
                LantoAlert.close();
                setTimeout(() => {
                    if (data.status === 'success') {
                        LantoAlert.success('บันทึกเวลาสำเร็จ', data.message, function() {
                            window.location.reload(); 
                        });
                    } else {
                        LantoAlert.error('สแกนใบหน้าล้มเหลว', data.message);
                    }
                }, 300);
            })
            .catch(err => {
                LantoAlert.close();
                setTimeout(() => {
                    LantoAlert.error('เกิดข้อผิดพลาด', 'ไม่สามารถติดต่อหรืออัปโหลดข้อมูลเข้าสู่เซิร์ฟเวอร์ฐานข้อมูลหลักได้');
                }, 300);
            });
        }, 1400);
    });
    </script>
</body>
</html>