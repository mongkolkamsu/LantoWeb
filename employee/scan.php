<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id       = $_SESSION['user_id'];
$fullname      = $_SESSION['fullname'] ?? '';
$employee_code = $_SESSION['employee_code'] ?? '';
$profile_image = $_SESSION['profile_image'] ?? '';

$avatar_url = !empty($profile_image) ? '../uploads/profiles/' . $profile_image : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&h=150&q=80';

// ระบบคำนวณสถานะอัตโนมัติ (Smart Auto-Detect)
$today = date('Y-m-d');
$has_check_in  = false;
$has_check_out = false;

try {
    $stmt_check = $pdo->prepare("SELECT log_type FROM attendance WHERE user_id = :user_id AND DATE(scan_time) = :today ORDER BY scan_time ASC");
    $stmt_check->execute(['user_id' => $user_id, 'today' => $today]);
    $today_logs = $stmt_check->fetchAll(PDO::FETCH_ASSOC);

    foreach ($today_logs as $l) {
        if ($l['log_type'] === 'check_in')  $has_check_in = true;
        if ($l['log_type'] === 'check_out') $has_check_out = true;
    }
} catch (PDOException $e) {}

$is_completed_today = ($has_check_in && $has_check_out);
$auto_type = (!$has_check_in) ? 'check_in' : 'check_out';

$type = $_GET['type'] ?? $auto_type;
$type_text = ($type === 'check_out') ? 'สแกนออกงาน (Check-Out)' : 'สแกนเข้างาน (Check-In)';
$type_color = ($type === 'check_out') ? 'from-rose-600 to-orange-500 shadow-rose-500/20' : 'from-blue-700 to-blue-600 shadow-blue-500/20';

$shift_start = "08:30:00"; 
$shift_end   = "17:30:00";
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
            $shift_end   = $s['end_time'];
            $shift_display_name = $s['name'];
            break;
        }
    }
} catch (PDOException $e) {}

// 🏢 ดึงเฉพาะสาขาที่พนักงานคนนี้ได้รับมอบหมายเท่านั้น
$branches = [];
$assigned_branch_id = null;

try {
    $stmt_u_branch = $pdo->prepare("SELECT branch_id, role FROM users WHERE id = :user_id LIMIT 1");
    $stmt_u_branch->execute(['user_id' => $user_id]);
    $u_row = $stmt_u_branch->fetch(PDO::FETCH_ASSOC);
    $assigned_branch_id = $u_row['branch_id'] ?? null;

    // 1. ดึงเฉพาะสาขาที่ถูกผูกกับพนักงาน (users.branch_id หรือตาราง user_branches)
    try {
        $stmt_b = $pdo->prepare("
            SELECT DISTINCT b.id, b.name, b.latitude, b.longitude, b.radius 
            FROM branches b
            LEFT JOIN user_branches ub ON b.id = ub.branch_id
            WHERE (b.is_active = 1 OR b.is_active IS NULL)
              AND (b.id = :branch_id OR ub.user_id = :user_id)
            ORDER BY b.id ASC
        ");
        $stmt_b->execute([
            'branch_id' => $assigned_branch_id,
            'user_id'   => $user_id
        ]);
        $branches = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // หากไม่มีตาราง user_branches ให้ดึงเฉพาะ users.branch_id
        if (!empty($assigned_branch_id)) {
            $stmt_b = $pdo->prepare("
                SELECT id, name, latitude, longitude, radius 
                FROM branches 
                WHERE id = :branch_id AND (is_active = 1 OR is_active IS NULL)
            ");
            $stmt_b->execute(['branch_id' => $assigned_branch_id]);
            $branches = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 2. ถ้าพนักงานยังไม่เคยถูกกำหนดสาขาเลย ให้ดึงสาขาหลักที่เปิดใช้งานอยู่
    if (empty($branches)) {
        $stmt_fallback = $pdo->query("SELECT id, name, latitude, longitude, radius FROM branches WHERE is_active = 1 OR is_active IS NULL ORDER BY id ASC");
        $branches = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $branches = [];
}

// กำหนดค่าเริ่มต้นของสาขา
$default_branch = '';
$default_label  = 'โปรดคลิกเลือกสาขาที่ทำงาน';

if (!empty($assigned_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $assigned_branch_id) {
            $default_branch = (string)$b['id'];
            $default_label  = $b['name'];
            break;
        }
    }
}

if (empty($default_branch) && count($branches) > 0) {
    $default_branch = (string)$branches[0]['id'];
    $default_label  = $branches[0]['name'];
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
    
    <!-- 🗺️ Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js" crossorigin="anonymous"></script>

    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; -webkit-tap-highlight-color: transparent; }
        @keyframes scan { 0% { top: 15%; } 50% { top: 85%; } 100% { top: 15%; } }
        .scanner-line { animation: scan 3s linear infinite; }
        ::-webkit-scrollbar { display: none; }
        .leaflet-container img { max-width: none !important; max-height: none !important; }
        #scanMap { min-height: 200px !important; z-index: 10; }

        /* 📍 ลบกรอบสี่เหลี่ยมพื้นหลังสีขาวของหมุด Leaflet */
        .leaflet-div-icon {
            background: transparent !important;
            border: none !important;
        }

        /* 📡 แอนิเมชันคลื่นเรดาร์สีน้ำเงินกระเพื่อม (สำหรับหมุดตำแหน่งผู้ใช้) */
        @keyframes pulse-ring {
            0% { transform: scale(0.6); opacity: 0.9; }
            80%, 100% { transform: scale(2.2); opacity: 0; }
        }
        .user-radar-ring {
            animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
    </style>
</head>
<body class="bg-[#f4f6fa] min-h-screen text-slate-800 antialiased flex">

    <!-- 📁 ดึง Sidebar พนักงาน -->
    <?php include_once 'sidebar.php'; ?>

    <!-- 🖥️ ส่วนเนื้อหาฝั่งขวา -->
    <div class="flex-1 flex flex-col min-w-0 justify-between md:ml-64">
        <div class="w-full flex flex-col">
            
            <!-- 🔝 Header ด้านบน -->
            <?php 
            $page_title = $type_text;
            $page_subtitle = 'ระบบยืนยันตัวตนบันทึกเวลาสแกนเข้า-ออกงาน';
            include_once '../includes/header.php'; 
            ?>

            <!-- 💻/📱 Main Centered Container -->
            <main class="p-4 sm:p-6 lg:p-8 max-w-xl mx-auto w-full pb-28 md:pb-10">
                <div class="bg-white rounded-3xl p-6 md:p-8 border border-slate-200/80 shadow-xl space-y-6">
                    
                    <?php if ($is_completed_today): ?>

                        <div class="bg-emerald-50 border border-emerald-200/80 p-8 rounded-3xl text-center space-y-4 shadow-xs">
                            <div class="w-16 h-16 bg-emerald-500 text-white rounded-2xl flex items-center justify-center text-3xl mx-auto shadow-md shadow-emerald-500/20">
                                ✅
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-emerald-900">ลงเวลาประจำวันนี้เรียบร้อยแล้ว</h3>
                                <p class="text-xs text-emerald-700/80 mt-1 font-medium leading-relaxed">
                                    คุณได้บันทึกเวลาสแกนเข้าและออกงานประจำวันที่ <br>
                                    <span class="font-bold text-emerald-900"><?php echo date('d/m/') . (date('Y') + 543); ?></span> ครบถ้วนแล้วครับ
                                </p>
                            </div>
                            <div class="pt-2">
                                <a href="../index_pc.php" class="inline-flex items-center justify-center px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                                    กลับหน้าแรก
                                </a>
                            </div>
                        </div>

                    <?php else: ?>

                        <!-- วันเวลาดิจิทัลตรงกลาง -->
                        <div class="text-center space-y-1">
                            <div id="live-time" class="text-4xl md:text-5xl font-extrabold tracking-wide text-slate-900 tabular-nums">00:00:00</div>
                            <div id="live-date" class="text-xs md:text-sm font-medium text-blue-600">กำลังดึงข้อมูลระบบ...</div>
                            <div id="shift-status-container" class="pt-1">
                                <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-400 font-medium">กำลังคำนวณสถานะเวลา...</span>
                            </div>
                        </div>

                        <!-- 🎯 กล้องสแกนเนอร์ -->
                        <div class="relative w-72 h-72 mx-auto bg-slate-950 rounded-full overflow-hidden border-4 border-white shadow-2xl flex items-center justify-center">
                            <div id="action-badge" class="absolute top-8 z-30 bg-amber-500/95 backdrop-blur-md text-slate-950 text-[10px] font-bold px-3 py-1 rounded-full border border-amber-300 flex items-center gap-1.5 shadow-md">
                                <span id="action-icon">🤖</span>
                                <span id="action-text">กำลังโหลดระบบตรวจจับ...</span>
                            </div>
                            <video id="webcam" autoplay playsinline class="w-full h-full object-cover scale-x-[-1] rounded-full"></video>
                            <canvas id="photo-preview" class="w-full h-full object-cover scale-x-[-1] hidden absolute inset-0 z-10 rounded-full"></canvas>
                            <div id="laser-line" class="scanner-line absolute left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-blue-500 to-transparent shadow-[0_0_12px_#3b82f6] z-20 mx-6"></div>
                            
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
                                <p class="text-xs font-bold text-slate-800">ไม่มีสัญญาณกล้อง</p>
                            </div>
                        </div>

                        <!-- 📍 กล่องเลือกสถานที่ / สาขา พร้อมแผนที่แสดงรัศมี -->
                        <div id="branch-section" class="<?php echo ($type === 'check_in') ? '' : 'hidden'; ?> bg-slate-50 border border-slate-200/80 p-4 rounded-2xl shadow-2xs space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5 pl-0.5">📍 เลือกสถานที่ / สาขาปฏิบัติงาน</label>
                                <?php 
                                include_once '../includes/rounded_dropdown.php';
                                $branch_opts = [];
                                foreach ($branches as $b) {
                                    $branch_opts[] = [
                                        'id' => (string)$b['id'],
                                        'name' => $b['name'],
                                        'data_attributes' => "data-lat='{$b['latitude']}' data-lng='{$b['longitude']}' data-radius='{$b['radius']}'"
                                    ];
                                }

                                renderRoundedDropdown('branch_select', 'branch_id', $default_label, $branch_opts, $default_branch);
                                ?>
                            </div>
                            
                            <!-- แผงแสดงระยะห่างและสถานะ -->
                            <div class="bg-white p-3 rounded-xl border border-slate-200/60 flex flex-col gap-1.5 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400 font-medium">ระยะห่างของคุณจากสาขา:</span>
                                    <span id="distance-text" class="font-bold text-slate-800">กำลังคำนวณพิกัด GPS...</span>
                                </div>
                                <div class="flex justify-between items-center pt-1.5 border-t border-slate-100">
                                    <span class="text-slate-400 font-medium">ตรวจสอบสถานะพื้นที่:</span>
                                    <span id="gps-status-badge" class="px-2.5 py-0.5 rounded-md font-bold bg-slate-200 text-slate-500 text-[11px]">Waiting...</span>
                                </div>
                            </div>

                            <!-- 🗺️ แผนที่ Leaflet ตรวจสอบตำแหน่งและรัศมีสาขา -->
                            <div class="space-y-1.5 pt-1">
                                <div class="flex justify-between items-center px-0.5">
                                    <span class="text-[11px] font-bold text-slate-700">แผนผังพิกัดและรัศมีลงเวลา (Check-in Area)</span>
                                    <span class="text-[10px] text-slate-400 font-medium">🏢 สาขา | 📍 ตัวคุณ</span>
                                </div>
                                <div class="w-full h-52 rounded-2xl overflow-hidden border border-slate-200 shadow-inner relative bg-slate-100">
                                    <div id="scanMap" class="w-full h-full"></div>
                                    <div id="map-loading" class="absolute inset-0 bg-slate-100/90 flex items-center justify-center text-slate-400 text-xs font-bold z-20">
                                        กำลังโหลดแผนที่...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ปุ่มควบคุมการบันทึก -->
                        <div class="space-y-2 pt-2">
                            <button id="btnRetake" class="w-full hidden bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold py-3 rounded-2xl text-xs tracking-wide transition-all active:scale-[0.98] cursor-pointer items-center justify-center gap-2 shadow-xs">
                                🔄 ยกเลิกและสแกนใหม่อีกครั้ง
                            </button>

                            <button id="btnCapture" class="hidden w-full bg-gradient-to-r <?php echo $type_color; ?> text-white font-bold py-3.5 rounded-2xl shadow-md text-xs tracking-wide transition-all transform active:scale-[0.98] cursor-pointer flex items-center justify-center gap-2">
                                <span id="btn-text">ยืนยันและส่งข้อมูลบันทึกเวลา</span>
                            </button>
                        </div>

                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- 📱 แถบเมนูด้านล่างแสดงเฉพาะบนมือถือ -->
    <div class="md:hidden">
        <?php include '../includes/navbar.php'; ?>
    </div>

    <script src="../assets/js/alerts.js"></script>

    <script>
        let userLat = null;
        let userLng = null;
        let isCaptured = false;
        
        const challenges = [
            { id: 'blink', icon: '😉', text: 'กรุณากระพริบตาเพื่อยืนยัน' },
            { id: 'smile', icon: '😊', text: 'กรุณายิ้มเพื่อยืนยัน' }
        ];
        const currentChallenge = challenges[Math.floor(Math.random() * challenges.length)];
        let isLivenessPassed = false;
        let wasEyeClosed = false;

        const shiftType = '<?php echo $type; ?>'; 
        const shiftStartStr = '<?php echo $shift_start; ?>'; 
        const shiftEndStr   = '<?php echo $shift_end; ?>'; 

        let qualityCanvas = null;
        let qualityCtx = null;

        // 🗺️ ตัวแปรสำหรับ Leaflet Map
        let scanMapInstance = null;
        let branchMarker = null;
        let branchCircle = null;
        let userMarker = null;

        function initScanMap(lat = 13.7563, lng = 100.5018) {
            const mapContainer = document.getElementById('scanMap');
            if (!mapContainer || typeof L === 'undefined') return;

            if (scanMapInstance !== null) {
                scanMapInstance.remove();
                scanMapInstance = null;
            }

            scanMapInstance = L.map('scanMap', { zoomControl: true }).setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(scanMapInstance);

            const mapLoading = document.getElementById('map-loading');
            if (mapLoading) mapLoading.classList.add('hidden');

            setTimeout(() => {
                if (scanMapInstance) scanMapInstance.invalidateSize();
            }, 300);
        }

        function updateScanMapVisuals(bLat, bLng, bRadius, isInside) {
            if (!scanMapInstance) {
                const initLat = (bLat !== null && !isNaN(bLat)) ? bLat : (userLat || 13.7563);
                const initLng = (bLng !== null && !isNaN(bLng)) ? bLng : (userLng || 100.5018);
                initScanMap(initLat, initLng);
            }
            if (!scanMapInstance) return;

            const group = [];

            // 🏢 1. ปักหมุดสาขา (ดีไซน์เข็มหมุดชี้พิกัดตรงจุด 100%)
            if (bLat !== null && bLng !== null && !isNaN(bLat) && !isNaN(bLng)) {
                if (branchMarker) scanMapInstance.removeLayer(branchMarker);
                if (branchCircle) scanMapInstance.removeLayer(branchCircle);

                const circleColor = isInside ? '#10b981' : '#f43f5e';
                const fillColor   = isInside ? '#34d399' : '#fb7185';

                // ดึงชื่อสาขาที่กำลังเลือก
                const branchInput = document.getElementById('branch_select');
                const branchText = branchInput ? (document.querySelector(`#list-branch_select [data-value="${branchInput.value}"]`)?.textContent.trim() || 'จุดเช็คอินสาขา') : 'จุดเช็คอินสาขา';

                const branchIcon = L.divIcon({
                    className: 'custom-branch-pin',
                    html: `
                        <div style="position: relative; display: flex; flex-direction: column; align-items: center; transform: translate(-50%, -100%); pointer-events: auto;">
                            <!-- ป้ายชื่อสาขา -->
                            <div style="background: #0f172a; color: #ffffff; font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 1.5px solid #ffffff; white-space: nowrap; display: flex; align-items: center; gap: 3px;">
                                <span>🏢</span> <span>${branchText}</span>
                            </div>
                            <!-- ติ่งชี้ลง -->
                            <div style="width: 8px; height: 8px; background: #0f172a; transform: rotate(45deg); margin-top: -4px; border-right: 1.5px solid #ffffff; border-bottom: 1.5px solid #ffffff;"></div>
                            <!-- จุดปลายเข็มหมุดสัมผัสพิกัดจริง -->
                            <div style="width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.4); margin-top: -2px;"></div>
                        </div>
                    `,
                    iconSize: [0, 0],
                    iconAnchor: [0, 0]
                });

                branchMarker = L.marker([bLat, bLng], { icon: branchIcon }).addTo(scanMapInstance);
                branchCircle = L.circle([bLat, bLng], {
                    color: circleColor,
                    fillColor: fillColor,
                    fillOpacity: 0.22,
                    weight: 2.5,
                    radius: bRadius || 100
                }).addTo(scanMapInstance);

                group.push(branchMarker);
                group.push(branchCircle);
            }

            // 📍 2. ปักหมุดตำแหน่งคุณ (ดีไซน์ Blue Radar Dot สไตล์ Google Maps ปักกลางพิกัด GPS แม่นยำ)
            if (userLat !== null && userLng !== null) {
                if (userMarker) scanMapInstance.removeLayer(userMarker);

                const userIcon = L.divIcon({
                    className: 'custom-user-radar',
                    html: `
                        <div style="position: relative; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; transform: translate(-50%, -50%);">
                            <!-- คลื่นเรดาร์กระเพื่อม -->
                            <div class="user-radar-ring" style="position: absolute; width: 100%; height: 100%; border-radius: 50%; background: #3b82f6;"></div>
                            <!-- จุดตำแหน่งสีน้ำเงินตรงกลาง -->
                            <div style="position: relative; width: 14px; height: 14px; background: #2563eb; border-radius: 50%; border: 2.5px solid #ffffff; box-shadow: 0 2px 8px rgba(37,99,235,0.6);"></div>
                        </div>
                    `,
                    iconSize: [0, 0],
                    iconAnchor: [0, 0]
                });

                userMarker = L.marker([userLat, userLng], { icon: userIcon }).addTo(scanMapInstance)
                    .bindPopup('<b>📍 ตำแหน่งปัจจุบันของคุณ</b>');

                group.push(userMarker);
            }

            // 📐 ปรับระยะมุมมองแผนที่ (มี Padding ป้องกันหมุดหลุด/ตกขอบล่าง)
            if (group.length > 1) {
                const featureGroup = L.featureGroup(group);
                scanMapInstance.fitBounds(featureGroup.getBounds(), {
                    paddingTopLeft: [30, 30],
                    paddingBottomRight: [30, 45],
                    maxZoom: 16
                });
            } else if (bLat && bLng) {
                scanMapInstance.setView([bLat, bLng], 15);
            }
        }

        function startLiveClock() {
            const timeElement = document.getElementById('live-time');
            const dateElement = document.getElementById('live-date');
            const statusContainer = document.getElementById('shift-status-container');

            function update() {
                const now = new Date();
                if (timeElement) timeElement.innerText = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                if (dateElement) dateElement.innerText = now.toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

                const curHours = now.getHours();
                const curMinutes = now.getMinutes();
                const curSeconds = now.getSeconds();
                const curTotalSeconds = (curHours * 3600) + (curMinutes * 60) + curSeconds;

                if (shiftType === 'check_in') {
                    const startParts = shiftStartStr.split(':');
                    const startTotalSeconds = (parseInt(startParts[0]) * 3600) + (parseInt(startParts[1]) * 60) + parseInt(startParts[2]);

                    if (curTotalSeconds <= startTotalSeconds) {
                        if (startTotalSeconds - curTotalSeconds <= 900) {
                            statusContainer.innerHTML = '<span class="px-3.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">⏱️ ตรงเวลา (On Time)</span>';
                        } else {
                            statusContainer.innerHTML = '<span class="px-3.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700">☀️ เข้างานก่อนเวลา (Early)</span>';
                        }
                    } else {
                        statusContainer.innerHTML = '<span class="px-3.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-700">⚠️ เข้างานสาย (Late)</span>';
                    }
                } else {
                    const endParts = shiftEndStr.split(':');
                    const endTotalSeconds = (parseInt(endParts[0]) * 3600) + (parseInt(endParts[1]) * 60) + parseInt(endParts[2]);

                    if (curTotalSeconds < endTotalSeconds) {
                        statusContainer.innerHTML = '<span class="px-3.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">⚠️ ออกก่อนเวลา (Early Out)</span>';
                    } else {
                        statusContainer.innerHTML = '<span class="px-3.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">✅ เลิกงานตามเวลา (On Time)</span>';
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
                        const distText = document.getElementById('distance-text');
                        if (distText) distText.innerText = "โปรดเปิดสิทธิ์เข้าถึง GPS";
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
            const branchInput = document.getElementById('branch_select');
            const branchId = branchInput ? branchInput.value : '';
            
            const distanceText = document.getElementById('distance-text');
            const badge = document.getElementById('gps-status-badge');

            if (!branchId) {
                if (distanceText) distanceText.innerText = "กรุณาเลือกสาขาก่อน";
                if (badge) {
                    badge.innerText = "Waiting...";
                    badge.className = "px-2.5 py-0.5 rounded-md font-bold bg-slate-200 text-slate-500 text-[11px]";
                }
                return;
            }

            const selectedItem = document.querySelector(`#list-branch_select [data-value="${branchId}"]`);
            if (!selectedItem) return;

            const branchLat = parseFloat(selectedItem.getAttribute('data-lat'));
            const branchLng = parseFloat(selectedItem.getAttribute('data-lng'));
            const branchRadius = parseInt(selectedItem.getAttribute('data-radius')) || 100;

            if (isNaN(branchLat) || isNaN(branchLng)) {
                if (distanceText) distanceText.innerText = "ได้รับข้อยกเว้นพื้นที่ (WFH/นอกสถานที่)";
                if (badge) {
                    badge.innerText = "นอกสถานที่อนุมัติ (ผ่าน)";
                    badge.className = "px-2.5 py-0.5 rounded-md font-bold bg-blue-100 text-blue-700 text-[11px]";
                }
                updateScanMapVisuals(null, null, branchRadius, true);
                return;
            }

            // ถ้าพิกัด GPS ยังค้นหาไม่เสร็จ ให้ปักหมุดสาขารอไว้ก่อน
            if (userLat === null || userLng === null) {
                if (distanceText) distanceText.innerText = "กำลังค้นหาพิกัด GPS ของคุณ...";
                if (badge) {
                    badge.innerText = "กำลังหาพิกัด GPS...";
                    badge.className = "px-2.5 py-0.5 rounded-md font-bold bg-amber-100 text-amber-700 text-[11px]";
                }
                updateScanMapVisuals(branchLat, branchLng, branchRadius, false);
                return;
            }

            // คำนวณระยะทางเมื่อได้ครบทั้ง 2 ฝั่ง
            const distance = calculateHaversine(userLat, userLng, branchLat, branchLng);
            if (distanceText) {
                distanceText.innerText = distance >= 1000 ? (distance / 1000).toFixed(2) + " กิโลเมตร" : distance.toFixed(0) + " เมตร";
            }

            const isInside = (distance <= branchRadius);

            if (badge) {
                if (isInside) {
                    badge.innerText = `อยู่ในพิกัดเข้างาน (รัศมี ${branchRadius}ม.)`;
                    badge.className = "px-2.5 py-0.5 rounded-md font-bold bg-emerald-100 text-emerald-700 text-[11px]";
                } else {
                    badge.innerText = `อยู่นอกรัศมีควบคุม (รัศมี ${branchRadius}ม.)`;
                    badge.className = "px-2.5 py-0.5 rounded-md font-bold bg-rose-100 text-rose-700 text-[11px]";
                }
            }

            updateScanMapVisuals(branchLat, branchLng, branchRadius, isInside);
        }

        function getDistance(p1, p2) {
            return Math.sqrt(Math.pow(p1.x - p2.x, 2) + Math.pow(p1.y - p2.y, 2));
        }

        function checkImageQuality(videoElem) {
            const w = 100;
            const h = 100;

            if (!qualityCanvas) {
                qualityCanvas = document.createElement('canvas');
                qualityCanvas.width = w;
                qualityCanvas.height = h;
                qualityCtx = qualityCanvas.getContext('2d', { willReadFrequently: true });
            }

            qualityCtx.drawImage(videoElem, 0, 0, w, h);
            const imgData = qualityCtx.getImageData(0, 0, w, h);
            const data = imgData.data;

            let totalLum = 0;
            const gray = new Float32Array(w * h);

            for (let i = 0; i < data.length; i += 4) {
                const lum = 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
                gray[i / 4] = lum;
                totalLum += lum;
            }

            const avgBrightness = totalLum / (w * h);

            let sumLap = 0;
            let sumLapSq = 0;
            let count = 0;

            for (let y = 1; y < h - 1; y++) {
                for (let x = 1; x < w - 1; x++) {
                    const idx = y * w + x;
                    const lap = gray[idx - w] + gray[idx - 1] - 4 * gray[idx] + gray[idx + 1] + gray[idx + w];
                    sumLap += lap;
                    sumLapSq += lap * lap;
                    count++;
                }
            }

            const meanLap = sumLap / count;
            const lapVariance = (sumLapSq / count) - (meanLap * meanLap);

            return { brightness: avgBrightness, sharpness: lapVariance };
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
            if (!actionIcon || !actionText) return;
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

                        const topHead = landmarks[10];
                        const chin = landmarks[152];
                        const leftCheek = landmarks[234];
                        const rightCheek = landmarks[454];

                        const isFaceCentered = 
                            topHead.y > 0.05 && topHead.y < 0.40 &&
                            chin.y > 0.58 && chin.y < 0.95 &&
                            leftCheek.x > 0.05 && leftCheek.x < 0.50 &&
                            rightCheek.x > 0.50 && rightCheek.x < 0.95;

                        if (!isFaceCentered) {
                            actionIcon.innerText = "👤";
                            actionText.innerText = "กรุณาจัดใบหน้าให้อยู่ตรงกลางกรอบ";
                            actionBadge.className = "absolute top-8 z-30 bg-amber-500/90 text-slate-950 text-[10px] font-bold px-3 py-0.5 rounded-full border border-amber-300 flex items-center gap-1 shadow-xs max-w-[85%] truncate";
                            targetBorder.className = "w-56 h-56 rounded-full border-2 border-dashed border-amber-400 flex items-center justify-center relative transition-colors duration-300";
                            return; 
                        }

                        const quality = checkImageQuality(video);

                        if (quality.brightness < 45) {
                            actionIcon.innerText = "💡";
                            actionText.innerText = "แสงน้อยเกินไป กรุณาอยู่ในที่สว่าง";
                            return;
                        }

                        if (quality.sharpness < 50) {
                            actionIcon.innerText = "🔍";
                            actionText.innerText = "กล้องไม่ชัด/ภาพเบลอ โปรดอยู่นิ่งๆ";
                            return;
                        }

                        actionIcon.innerText = currentChallenge.icon;
                        actionText.innerText = currentChallenge.text;
                        targetBorder.className = "w-56 h-56 rounded-full border-2 border-dashed border-white/40 flex items-center justify-center relative transition-colors duration-300";

                        if (currentChallenge.id === 'blink') {
                            const leftEyeTop = landmarks[159];
                            const leftEyeBottom = landmarks[145];
                            const leftEyeLeft = landmarks[33];
                            const leftEyeRight = landmarks[133];

                            const eyeHeight = getDistance(leftEyeTop, leftEyeBottom);
                            const eyeWidth = getDistance(leftEyeLeft, leftEyeRight);
                            const ear = eyeHeight / eyeWidth;

                            if (ear < 0.18) wasEyeClosed = true;
                            else if (wasEyeClosed && ear > 0.23) triggerLivenessPassed();

                        } else if (currentChallenge.id === 'smile') {
                            const mouthLeft = landmarks[61];
                            const mouthRight = landmarks[291];
                            const cheekLeft = landmarks[234];
                            const cheekRight = landmarks[454];

                            const mouthWidth = getDistance(mouthLeft, mouthRight);
                            const faceWidth = getDistance(cheekLeft, cheekRight);
                            const smileRatio = mouthWidth / faceWidth;

                            if (smileRatio > 0.42) triggerLivenessPassed();
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

        function triggerLivenessPassed() {
            if (isLivenessPassed) return;
            isLivenessPassed = true;
            
            actionIcon.innerText = "📸";
            actionText.innerText = "ผ่านการยืนยันแล้ว กำลังถ่ายรูปอัตโนมัติ...";
            actionBadge.className = "absolute top-8 z-30 bg-emerald-600 text-white text-[10px] font-bold px-3 py-0.5 rounded-full border border-emerald-300 flex items-center gap-1 shadow-xs";
            targetBorder.className = "w-56 h-56 rounded-full border-2 border-solid border-emerald-400 flex items-center justify-center relative transition-colors duration-300 shadow-[0_0_20px_rgba(52,211,153,0.5)]";

            btnCapture.disabled = false;
            btnText.innerText = "ระบบจับภาพสำเร็จแล้ว!";

            setTimeout(() => { freezeCapturePhoto(); }, 400);
        }

        function freezeCapturePhoto() {
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
            btnCapture.classList.remove('hidden');
            btnRetake.classList.remove('hidden');
            btnRetake.classList.add('flex');
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

            btnCapture.disabled = true;
            btnText.innerText = "มองกล้องและทำตามคำสั่งเพื่อสแกนอัตโนมัติ...";
            btnCapture.classList.add('hidden');
            btnRetake.classList.remove('flex');
            btnRetake.classList.add('hidden');
        }

        btnCapture.addEventListener('click', () => {
            if (!isCaptured) {
                freezeCapturePhoto();
            } else {
                const branchSelectEl = document.getElementById('branch_select');
                const branchId = branchSelectEl ? branchSelectEl.value : '';

                if (shiftType === 'check_in' && !branchId) {
                    if (typeof LantoAlert !== 'undefined') {
                        LantoAlert.warning('ข้อมูลไม่ครบถ้วน', 'โปรดคลิกเลือกสถานที่/สาขาปฏิบัติงานก่อนกดยืนยันครับ');
                    } else {
                        alert('โปรดเลือกสาขาก่อนกดยืนยันครับ');
                    }
                    return;
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

                fetch('../api/save-attendance.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (typeof LantoAlert !== 'undefined') LantoAlert.close();

                    setTimeout(() => {
                        if (data.status === 'success') {
                            if (typeof LantoAlert !== 'undefined') {
                                LantoAlert.success('บันทึกเวลางานสำเร็จ', data.message, function() {
                                    window.location.href = '../index_pc.php';
                                });
                            } else {
                                alert(data.message);
                                window.location.href = '../index_pc.php';
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
            initScanMap();
            trackUserLocation();
            initFaceMeshLiveness();

            // ตรวจสอบและแสดงผลแผนที่ของสาขาเริ่มต้นทันทีเมื่อเปิดหน้า
            setTimeout(() => {
                checkBranchDistance();
            }, 400);
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('#list-branch_select .dropdown-item') || e.target.closest('[id^="custom-dropdown-branch_select"]')) {
                setTimeout(() => { checkBranchDistance(); }, 150);
            }
        });
    </script>
</body>
</html>