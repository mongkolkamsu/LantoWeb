<?php
session_start();
require_once '../config/db.php'; // เชื่อมต่อฐานข้อมูลหลัก

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องล็อกอินก่อน)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];
$employee_code = $_SESSION['employee_code'];
$profile_image = $_SESSION['profile_image'];

$avatar_url = !empty($profile_image) ? '../uploads/profiles/' . $profile_image : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&h=150&q=80';

// ระบบคำนวณสถานะอัตโนมัติ (Smart Auto-Detect)
$today = date('Y-m-d');
$auto_type = 'check_in'; 

try {
    $stmt_check = $pdo->prepare("SELECT log_type FROM attendance WHERE user_id = :user_id AND DATE(scan_time) = :today ORDER BY scan_time DESC LIMIT 1");
    $stmt_check->execute(['user_id' => $user_id, 'today' => $today]);
    $last_log = $stmt_check->fetchColumn();

    if ($last_log === 'check_in') {
        $auto_type = 'check_out';
    }
} catch (PDOException $e) {
    $auto_type = 'check_in';
}

$type = $_GET['type'] ?? $auto_type;
$type_text = ($type === 'check_out') ? 'สแกนออกงาน (Check-Out)' : 'สแกนเข้างาน (Check-In)';
$type_color = ($type === 'check_out') ? 'from-rose-600 to-orange-500 shadow-rose-500/20' : 'from-blue-700 to-blue-600 shadow-blue-500/20';

// 🔍 ดึงข้อมูลกะเวลาทำงาน
$shift_start = "08:30:00"; 
$shift_end = "17:30:00";
$shift_display_name = "กะปกติ (Normal Shift)";

try {
    $stmt_u = $pdo->prepare("SELECT work_shift FROM users WHERE id = :id LIMIT 1");
    $stmt_u->execute(['id' => $user_id]);
    $user_shift_string = $stmt_u->fetchColumn();

    $stmt_s = $pdo->query("SELECT name, start_time, end_time FROM work_shifts WHERE is_active = 1");
    $all_shifts = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_shifts as $s) {
        $clean_name = explode(' ', $s['name'])[0]; 
        if (strpos($user_shift_string, $clean_name) !== false) {
            $shift_start = $s['start_time'];
            $shift_end = $s['end_time'];
            $shift_display_name = $s['name'];
            break;
        }
    }
} catch (PDOException $e) {
    // ซ่อนข้อผิดพลาด
}

// ดึงรายชื่อสาขา
try {
    $stmt_branches = $pdo->query("SELECT id, name, latitude, longitude, radius FROM branches WHERE is_active = 1");
    $branches = $stmt_branches->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $branches = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $type_text; ?> - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- 🤖 MediaPipe Face Mesh AI Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js" crossorigin="anonymous"></script>

    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        @keyframes scan { 0% { top: 15%; } 50% { top: 85%; } 100% { top: 15%; } }
        .scanner-line { animation: scan 3s linear infinite; }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex items-center justify-center p-0 md:p-4 text-slate-800 antialiased select-none">

    <div class="w-full min-h-screen bg-white/40 backdrop-blur-xl flex flex-col justify-between relative overflow-y-auto p-5 pb-28
            md:max-w-md md:mx-auto md:my-6 md:min-h-[812px] md:rounded-[40px] md:border md:border-white/60 md:shadow-2xl">
        
        <div>
            <!-- ส่วนหัวหน้าต่างระบบ -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-200/60">
                <a href="../index.php?view=mobile" class="w-10 h-10 bg-white/80 border border-slate-200 text-slate-600 rounded-full flex items-center justify-center shadow-xs active:scale-90 transition-transform cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="text-xs font-semibold tracking-wide text-slate-700"><?php echo $type_text; ?></h2>
                <div class="w-10"></div>
            </div>

            <!-- วันเวลาดิจิทัลพร้อมแถบสัญลักษณ์บอกความตรงเวลา -->
            <div class="text-center my-4 space-y-1.5">
                <div id="live-time" class="text-4xl font-extrabold tracking-wide text-transparent bg-clip-text bg-gradient-to-r from-blue-950 via-blue-900 to-indigo-900 tabular-nums">00:00:00</div>
                <div id="live-date" class="text-xs font-medium text-blue-600/80">กำลังดึงข้อมูลระบบ...</div>
                
                <div id="shift-status-container" class="pt-1 animate-pulse">
                    <span class="px-3 py-1 rounded-full text-[11px] bg-slate-100 text-slate-400 font-medium">กำลังคำนวณสถานะเวลา...</span>
                </div>
            </div>

            <!-- 🎯 กรอบรูปกล้องสแกนเนอร์ (ปรับขยายขนาดเป็น w-72 h-72) -->
            <div class="relative w-72 h-72 mx-auto bg-slate-950 rounded-full overflow-hidden border-4 border-white shadow-2xl flex items-center justify-center my-3">
                
                <!-- ป้ายคำสั่ง Liveness -->
                <div id="action-badge" class="absolute top-3 left-1/2 -translate-x-1/2 z-30 bg-amber-500 text-slate-950 text-[11px] font-extrabold px-3.5 py-1 rounded-full backdrop-blur-md border border-amber-300 transition-all flex items-center gap-1.5 shadow-lg animate-bounce">
                    <span id="action-icon">🤖</span>
                    <span id="action-text">กำลังโหลดระบบตรวจจับ...</span>
                </div>

                <video id="webcam" autoplay playsinline class="w-full h-full object-cover scale-x-[-1] rounded-full"></video>
                <canvas id="photo-preview" class="w-full h-full object-cover scale-x-[-1] hidden absolute inset-0 z-10 rounded-full"></canvas>
                <div id="laser-line" class="scanner-line absolute left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-blue-500 to-transparent shadow-[0_0_12px_#3b82f6] z-20 mx-6"></div>
                
                <!-- 🎯 เส้นประด้านในขยายเป็น w-56 h-56 -->
                <div id="target-ui" class="absolute inset-0 pointer-events-none z-10 flex items-center justify-center">
                    <div id="target-border" class="w-56 h-56 rounded-full border-2 border-dashed border-white/40 flex items-center justify-center relative transition-colors duration-300">
                        <div class="absolute top-0 left-0 w-3.5 h-3.5 border-t-2 border-l-2 border-blue-400 rounded-tl-sm"></div>
                        <div class="absolute top-0 right-0 w-3.5 h-3.5 border-t-2 border-r-2 border-blue-400 rounded-tr-sm"></div>
                        <div class="absolute bottom-0 left-0 w-3.5 h-3.5 border-b-2 border-l-2 border-blue-400 rounded-bl-sm"></div>
                        <div class="absolute bottom-0 right-0 w-3.5 h-3.5 border-b-2 border-r-2 border-blue-400 rounded-br-sm"></div>
                    </div>
                </div>

                <div id="camera-error" class="absolute inset-0 bg-white/95 hidden flex flex-col items-center justify-center p-6 text-center z-30 rounded-full">
                    <svg class="w-10 h-10 text-rose-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <p class="text-xs font-bold text-slate-800 tracking-wide">ไม่มีสัญญาณกล้อง</p>
                </div>
            </div>

            <!-- การ์ดแสดงประวัติย่อของพนักงาน -->
            <div class="bg-white/80 border border-slate-200/60 p-3.5 rounded-2xl flex items-center justify-between my-2 shadow-xs">
                <div class="flex items-center gap-3">
                    <img src="<?php echo $avatar_url; ?>" alt="User Avatar" class="w-9 h-9 rounded-xl object-cover border border-slate-200">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 leading-tight"><?php echo htmlspecialchars($fullname); ?></p>
                        <p class="text-[10px] text-slate-400 mt-0.5">กะของคุณ: <?php echo htmlspecialchars($shift_display_name); ?></p>
                    </div>
                </div>
                <span class="text-[10px] bg-slate-100 border border-slate-200 text-slate-500 px-2 py-1 rounded-lg font-mono font-medium">รหัสพนักงาน: <?php echo htmlspecialchars($employee_code); ?></span>
            </div>

            <!-- กล่องเลือกสถานที่/สาขา -->
            <div id="branch-section" class="hidden bg-white/80 border border-slate-200/60 p-4 rounded-2xl shadow-xs space-y-3 mt-3 transition-all duration-300">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1.5 pl-0.5">📍 เลือกสถานที่ / สาขาปฏิบัติงาน</label>
                    <?php 
                    include_once '../includes/rounded_dropdown.php';
                    $branch_opts = [];
                    foreach ($branches as $b) {
                        $branch_opts[] = [
                            'id' => $b['id'],
                            'name' => $b['name'],
                            'data_attributes' => "data-lat='{$b['latitude']}' data-lng='{$b['longitude']}' data-radius='{$b['radius']}'"
                        ];
                    }
                    renderRoundedDropdown('branch_select', 'branch_id', 'โปรดคลิกเลือกสาขาที่ทำงาน', $branch_opts);
                    ?>
                </div>
                
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 flex flex-col gap-1 text-[11px]">
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-light">ระยะห่างของคุณจากสาขา:</span>
                        <span id="distance-text" class="font-bold text-slate-700">กำลังตรวจสอบพิกัด...</span>
                    </div>
                    <div class="flex justify-between items-center mt-1 pt-1 border-t border-slate-200/40">
                        <span class="text-slate-400 font-light">ตรวจสอบสถานะพื้นที่:</span>
                        <span id="gps-status-badge" class="px-2 py-0.5 rounded-md font-semibold bg-slate-200 text-slate-500">Waiting...</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ส่วนปุ่มควบคุมการบันทึกข้อมูลเวลาทำงาน -->
        <div class="pt-4 space-y-2">
            <button id="btnRetake" class="w-full hidden bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-medium py-2.5 rounded-2xl text-xs tracking-wide transition-all active:scale-[0.98] cursor-pointer items-center justify-center gap-2 shadow-xs">
                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                </svg>
                ยกเลิกและกดถ่ายรูปใหม่ (Retake Photo)
            </button>

            <button id="btnCapture" class="w-full bg-gradient-to-r <?php echo $type_color; ?> text-white font-semibold py-3.5 rounded-2xl shadow-md text-sm tracking-wide transition-all transform active:scale-[0.98] cursor-pointer flex items-center justify-center gap-2 opacity-50 cursor-not-allowed" disabled>
                <svg id="btn-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span id="btn-text">ทำตามคำสั่งด้านบนเพื่อถ่ายรูป...</span>
            </button>
            
            <p class="text-[10px] text-center text-slate-400 mt-2 font-light">Lanto Web AI Liveness Engine v5.0.0</p>
        </div>
    <?php include '../includes/navbar.php'; ?>
    </div>

    <script src="../assets/js/alerts.js"></script>

    <script>
        let userLat = null;
        let userLng = null;
        let isCaptured = false;
        
        // 🎯 ระบบสุ่มคำสั่ง Liveness Challenge (0: กระพริบตา, 1: ยิ้ม)
        const challenges = [
            { id: 'blink', icon: '😉', text: 'กรุณากระพริบตาเพื่อยืนยัน' },
            { id: 'smile', icon: '😊', text: 'กรุณายิ้มเพื่อยืนยัน' }
        ];
        const currentChallenge = challenges[Math.floor(Math.random() * challenges.length)];
        let isLivenessPassed = false;
        let wasEyeClosed = false; // ตัวแปรเก็บสถานะการปิดตาชั่วคราว

        const shiftType = '<?php echo $type; ?>'; 
        const shiftStartStr = '<?php echo $shift_start; ?>'; 
        const shiftEndStr = '<?php echo $shift_end; ?>'; 

        function startLiveClock() {
            const timeElement = document.getElementById('live-time');
            const dateElement = document.getElementById('live-date');
            const statusContainer = document.getElementById('shift-status-container');

            function update() {
                const now = new Date();
                timeElement.innerText = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                dateElement.innerText = now.toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

                const curHours = now.getHours();
                const curMinutes = now.getMinutes();
                const curSeconds = now.getSeconds();
                const curTotalSeconds = (curHours * 3600) + (curMinutes * 60) + curSeconds;

                if (shiftType === 'check_in') {
                    const startParts = shiftStartStr.split(':');
                    const startTotalSeconds = (parseInt(startParts[0]) * 3600) + (parseInt(startParts[1]) * 60) + parseInt(startParts[2]);

                    if (curTotalSeconds <= startTotalSeconds) {
                        if (startTotalSeconds - curTotalSeconds <= 900) {
                            statusContainer.innerHTML = '<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">⏱️ ตรงเวลา (On Time)</span>';
                        } else {
                            statusContainer.innerHTML = '<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700 border border-blue-200">☀️ เข้างานก่อนเวลา (Early)</span>';
                        }
                    } else {
                        statusContainer.innerHTML = '<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700 border border-rose-200">⚠️ เข้างานสาย (Late)</span>';
                    }
                } else {
                    const endParts = shiftEndStr.split(':');
                    const endTotalSeconds = (parseInt(endParts[0]) * 3600) + (parseInt(endParts[1]) * 60) + parseInt(endParts[2]);

                    if (curTotalSeconds < endTotalSeconds) {
                        statusContainer.innerHTML = '<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 border border-amber-200">⚠️ ออกก่อนเวลา (Early Out)</span>';
                    } else {
                        statusContainer.innerHTML = '<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">✅ เลิกงานตามเวลา (On Time)</span>';
                    }
                }
            }
            update();
            setInterval(update, 1000);
        }

        function trackUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(
                    (position) => {
                        userLat = position.coords.latitude;
                        userLng = position.coords.longitude;
                        checkBranchDistance();
                    },
                    (error) => {
                        document.getElementById('distance-text').innerText = "โปรดเปิดสิทธิ์เข้าถึง GPS";
                    },
                    { enableHighAccuracy: true }
                );
            }
        }

        function calculateHaversine(lat1, lon1, lat2, lon2) {
            const R = 6371000;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon/2) * Math.sin(dLon/2);
            return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
        }

        function checkBranchDistance() {
            const branchId = document.getElementById('branch_select').value;
            if (!branchId) {
                document.getElementById('distance-text').innerText = "กรุณาเลือกสาขาก่อน";
                return;
            }
            if (userLat === null || userLng === null) return;

            const selectedItem = document.querySelector(`#list-branch_select [data-value="${branchId}"]`);
            if (!selectedItem) return;

            const branchLat = parseFloat(selectedItem.getAttribute('data-lat'));
            const branchLng = parseFloat(selectedItem.getAttribute('data-lng'));
            const branchRadius = parseInt(selectedItem.getAttribute('data-radius'));

            const distanceText = document.getElementById('distance-text');
            const badge = document.getElementById('gps-status-badge');

            if (isNaN(branchLat) || isNaN(branchLng)) {
                distanceText.innerText = "ได้รับข้อยกเว้นพื้นที่";
                badge.innerText = "นอกสถานที่อนุมัติ (ผ่าน)";
                badge.className = "px-2 py-0.5 rounded-md font-semibold bg-blue-100 text-blue-700";
                return;
            }

            const distance = calculateHaversine(userLat, userLng, branchLat, branchLng);
            distanceText.innerText = distance >= 1000 ? (distance / 1000).toFixed(2) + " กิโลเมตร" : distance.toFixed(0) + " เมตร";

            if (distance <= branchRadius) {
                badge.innerText = `อยู่ในพิกัดเข้างาน (รัศมี ${branchRadius}ม.)`;
                badge.className = "px-2 py-0.5 rounded-md font-semibold bg-emerald-100 text-emerald-700";
            } else {
                badge.innerText = `อยู่นอกรัศมีควบคุม (รัศมี ${branchRadius}ม.)`;
                badge.className = "px-2 py-0.5 rounded-md font-semibold bg-rose-100 text-rose-700";
            }
        }

        // 🤖 ฟังก์ชันคำนวณระยะห่างระหว่างจุด 2D บนใบหน้า
        function getDistance(p1, p2) {
            return Math.sqrt(Math.pow(p1.x - p2.x, 2) + Math.pow(p1.y - p2.y, 2));
        }

        const video = document.getElementById('webcam');
        const canvas = document.getElementById('photo-preview');
        const btnCapture = document.getElementById('btnCapture');
        const btnRetake = document.getElementById('btnRetake');
        const btnText = document.getElementById('btn-text');
        const laserLine = document.getElementById('laser-line');
        const targetUi = document.getElementById('target-ui');
        
        const actionBadge = document.getElementById('action-badge');
        const actionIcon = document.getElementById('action-icon');
        const actionText = document.getElementById('action-text');
        const targetBorder = document.getElementById('target-border');

        let cameraUtils = null;

        async function initFaceMeshLiveness() {
            // แสดงคำสั่งที่สุ่มได้ขึ้นหน้าจอทันที
            actionIcon.innerText = currentChallenge.icon;
            actionText.innerText = currentChallenge.text;

            try {
                const faceMesh = new FaceMesh({
                    locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`
                });

                faceMesh.setOptions({
                    maxNumFaces: 1,
                    refineLandmarks: true,
                    minDetectionConfidence: 0.65,
                    minTrackingConfidence: 0.65
                });

                faceMesh.onResults((results) => {
                    if (isCaptured || isLivenessPassed) return;

                    if (results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0) {
                        const landmarks = results.multiFaceLandmarks[0];

                        if (currentChallenge.id === 'blink') {
                            // 👁️ คำนวณ Eye Aspect Ratio (EAR) สำหรับตรวจการกระพริบตา
                            const leftEyeTop = landmarks[159];
                            const leftEyeBottom = landmarks[145];
                            const leftEyeLeft = landmarks[33];
                            const leftEyeRight = landmarks[133];

                            const eyeHeight = getDistance(leftEyeTop, leftEyeBottom);
                            const eyeWidth = getDistance(leftEyeLeft, leftEyeRight);
                            const ear = eyeHeight / eyeWidth;

                            // เมื่อหลับตา ค่า EAR จะต่ำกว่า 0.18
                            if (ear < 0.18) {
                                wasEyeClosed = true;
                            } 
                            // เมื่อเปิดตาขึ้นอีกครั้งหลังจากหลับตา = กระพริบตาสมบูรณ์
                            else if (wasEyeClosed && ear > 0.23) {
                                triggerLivenessPassed();
                            }

                        } else if (currentChallenge.id === 'smile') {
                            // 😊 คำนวณความกว้างมุมปากเทียบกับความกว้างใบหน้าสำหรับตรวจยิ้ม
                            const mouthLeft = landmarks[61];
                            const mouthRight = landmarks[291];
                            const cheekLeft = landmarks[234];
                            const cheekRight = landmarks[454];

                            const mouthWidth = getDistance(mouthLeft, mouthRight);
                            const faceWidth = getDistance(cheekLeft, cheekRight);
                            const smileRatio = mouthWidth / faceWidth;

                            // เมื่อยิ้ม อัตราส่วนความกว้างปากจะมากกว่า 0.42
                            if (smileRatio > 0.42) {
                                triggerLivenessPassed();
                            }
                        }
                    }
                });

                cameraUtils = new Camera(video, {
                    onFrame: async () => {
                        if (!isCaptured && !isLivenessPassed) {
                            await faceMesh.send({ image: video });
                        }
                    },
                    width: 640,
                    height: 640
                });

                await cameraUtils.start();

            } catch (error) {
                console.error("FaceMesh Error:", error);
                document.getElementById('camera-error').classList.remove('hidden');
            }
        }

        // 🎉 เมื่อทำคำสั่งผ่านสำเร็จ
        function triggerLivenessPassed() {
            isLivenessPassed = true;
            
            actionIcon.innerText = "🟢";
            actionText.innerText = "ผ่านการยืนยันตัวตนแล้ว!";
            actionBadge.className = "absolute top-3 left-1/2 -translate-x-1/2 z-30 bg-emerald-600 text-white text-[11px] font-extrabold px-3.5 py-1 rounded-full backdrop-blur-md border border-emerald-300 transition-all flex items-center gap-1.5 shadow-lg";
            targetBorder.className = "w-56 h-56 rounded-full border-2 border-solid border-emerald-400 flex items-center justify-center relative transition-colors duration-300 shadow-[0_0_20px_rgba(52,211,153,0.5)]";

            btnCapture.disabled = false;
            btnCapture.classList.remove('opacity-50', 'cursor-not-allowed');
            btnText.innerText = "กดถ่ายรูปเพื่อ" + (shiftType === 'check_out' ? 'ออกงาน' : 'เข้างาน');
        }

        function freezeCapturePhoto() {
            if (!isLivenessPassed) {
                if (typeof LantoAlert !== 'undefined') {
                    LantoAlert.warning('สแกนไม่สำเร็จ', 'กรุณาทำตามคำสั่งด้านบนกล้องให้สำเร็จก่อนกดถ่ายรูปครับ');
                }
                return;
            }

            const ctx = canvas.getContext('2d');
            const size = Math.min(video.videoWidth, video.videoHeight);
            canvas.width = size;
            canvas.height = size;
            
            const sx = (video.videoWidth - size) / 2;
            const sy = (video.videoHeight - size) / 2;
            
            ctx.drawImage(video, sx, sy, size, size, 0, 0, size, size);
            
            canvas.classList.remove('hidden');
            video.classList.add('hidden');
            laserLine.classList.add('hidden');
            targetUi.classList.add('hidden');
            actionBadge.classList.add('hidden');
            
            isCaptured = true;
            btnText.innerText = "ยืนยันและส่งข้อมูลบันทึกเวลา";
            btnRetake.classList.remove('hidden');
            btnRetake.classList.add('flex');

            if (shiftType === 'check_in') {
                document.getElementById('branch-section').classList.remove('hidden');
            }
        }

        function unfreezeAndReset() {
            canvas.classList.add('hidden');
            video.classList.remove('hidden');
            laserLine.classList.remove('hidden');
            targetUi.classList.remove('hidden');
            actionBadge.classList.remove('hidden');
            
            isCaptured = false;
            isLivenessPassed = false;
            wasEyeClosed = false;

            actionIcon.innerText = currentChallenge.icon;
            actionText.innerText = currentChallenge.text;
            actionBadge.className = "absolute top-3 left-1/2 -translate-x-1/2 z-30 bg-amber-500 text-slate-950 text-[11px] font-extrabold px-3.5 py-1 rounded-full backdrop-blur-md border border-amber-300 transition-all flex items-center gap-1.5 shadow-lg animate-bounce";
            targetBorder.className = "w-56 h-56 rounded-full border-2 border-dashed border-white/40 flex items-center justify-center relative transition-colors duration-300";

            btnCapture.disabled = true;
            btnCapture.classList.add('opacity-50', 'cursor-not-allowed');
            btnText.innerText = "ทำตามคำสั่งด้านบนเพื่อถ่ายรูป...";
            btnRetake.classList.remove('flex');
            btnRetake.classList.add('hidden');

            document.getElementById('branch-section').classList.add('hidden');
        }

        btnCapture.addEventListener('click', () => {
            if (!isCaptured) {
                freezeCapturePhoto();
            } else {
                const branchSelectEl = document.getElementById('branch_select');
                const branchId = branchSelectEl ? branchSelectEl.value : '';

                if (shiftType === 'check_in') {
                    if (!branchId) {
                        if (typeof LantoAlert !== 'undefined') {
                            LantoAlert.warning('ข้อมูลไม่ครบถ้วน', 'โปรดคลิกเลือกสถานที่/สาขาปฏิบัติงานก่อนกดยืนยันครับ');
                        } else {
                            alert('โปรดเลือกสาขาก่อนกดยืนยันครับ');
                        }
                        return;
                    }
                }

                if (typeof LantoAlert !== 'undefined') {
                    LantoAlert.loading('กำลังบันทึกข้อมูล', 'ระบบกำลังอัปโหลดรูปภาพและตรวจสอบพิกัดลงฐานข้อมูล...');
                }

                const imageData = canvas.toDataURL('image/jpeg');
                const formData = new FormData();
                formData.append('log_type', shiftType);
                formData.append('latitude', userLat);
                formData.append('longitude', userLng);
                formData.append('image', imageData);
                formData.append('branch_id', branchId);

                fetch('../api/save-attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (typeof LantoAlert !== 'undefined') LantoAlert.close();

                    setTimeout(() => {
                        if (data.status === 'success') {
                            if (typeof LantoAlert !== 'undefined') {
                                LantoAlert.success('บันทึกเวลางานสำเร็จ', data.message, function() {
                                    window.location.href = '../index.php?view=mobile';
                                });
                            } else {
                                alert(data.message);
                                window.location.href = '../index.php?view=mobile';
                            }
                        } else {
                            if (typeof LantoAlert !== 'undefined') {
                                LantoAlert.error('บันทึกเวลาล้มเหลว', data.message);
                            } else {
                                alert(data.message);
                            }
                        }
                    }, 300);
                })
                .catch(error => {
                    if (typeof LantoAlert !== 'undefined') {
                        LantoAlert.close();
                        setTimeout(() => { LantoAlert.error('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อฐานข้อมูล Lanto Web ได้'); }, 300);
                    } else {
                        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
                    }
                });
            }
        });

        btnRetake.addEventListener('click', unfreezeAndReset);

        window.addEventListener('DOMContentLoaded', () => {
            startLiveClock();
            trackUserLocation();
            initFaceMeshLiveness();
        });
    </script>
</body>
</html>