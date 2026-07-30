<?php
session_start();
require_once 'config/db.php';

// 🎯 หากผู้ใช้ล็อกอินอยู่แล้ว ให้ดีดไปหน้าแดชบอร์ดหลักทันที
if (isset($_SESSION['user_id'])) {
    header("Location: index_mobile.php");
    exit();
}

// ตัวแปรสำหรับเก็บข้อความเตือน
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $employee_code = trim(htmlspecialchars($_POST['employee_code'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (empty($employee_code) || empty($password)) {
        $msg = 'กรุณากรอกรหัสพนักงานและรหัสผ่านให้ครบถ้วน';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
            exit();
        } else {
            $error_message = $msg;
        }
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE employee_code = :employee_code LIMIT 1");
            $stmt->execute(['employee_code' => $employee_code]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // บันทึก Session ข้อมูลพนักงาน
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['employee_code'] = $user['employee_code'];
                $_SESSION['fullname']      = $user['fullname'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['profile_image'] = $user['profile_image'];

                // 🎯 แก้ไขเสร็จสิ้น: ปรับให้วิ่งไปหาหน้า index_mobile.php ในระดับเดียวกันตรง ๆ
                $redirect_url = 'index_mobile.php';
                
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'เข้าสู่ระบบสำเร็จ ยินดีต้อนรับกลับครับ', 'redirect' => $redirect_url], JSON_UNESCAPED_UNICODE);
                    exit();
                } else {
                    header("Location: " . $redirect_url);
                    exit();
                }
            } else {
                $msg = 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง';
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
                    exit();
                } else {
                    $error_message = $msg;
                }
            }
        } catch (PDOException $e) {
            $msg = 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
                exit();
            } else {
                $error_message = $msg;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome Back - Lanto Web</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- 📱 ตั้งค่า PWA (รองรับทั้ง Android และ iOS iPhone) -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1e3a8a">

    <!-- 🍎 สำหรับ iPhone / Safari (แก้ชื่อแอปและรูปไอคอน) -->
    <link rel="apple-touch-icon" href="assets/images/LOGO-IST.jpg">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Lanto Web">
    <style>
        body { font-family: 'Prompt', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-tr from-[#e2e8f0] via-[#f1f5f9] to-[#dbeafe] min-h-screen flex items-center justify-center p-4">

    <div class="bg-white/40 backdrop-blur-xl border border-white/60 p-8 rounded-3xl shadow-2xl shadow-slate-300/50 w-full max-w-md transition-all">
        
        <div class="text-center mb-6 flex flex-col items-center">
            <div class="w-32 h-32 bg-white/80 p-2 rounded-2xl shadow-sm mb-4 flex items-center justify-center overflow-hidden">
                <img src="assets/images/LOGO-Lanto.png" alt="Lanto Logo" class="object-contain w-full h-full">
            </div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-wide">Welcome Back</h1>
            <p class="text-slate-500 text-xs mt-1">Sign in to Lanto Global Logistics</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-700 p-3 mb-5 rounded-2xl text-xs text-center" role="alert">
                <p class="font-medium">⚠️ <?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php" class="space-y-5">
            <div>
                <label for="employee_code" class="block text-xs font-medium text-slate-600 mb-2 px-1">รหัสพนักงาน (Employee ID)</label>
                <input type="text" id="employee_code" name="employee_code" required
                    class="w-full px-4 py-3 bg-white/60 border border-slate-200/80 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-slate-800 placeholder-slate-400 text-sm"
                    placeholder="กรอกรหัสพนักงานของคุณ">
            </div>

            <div>
                <label for="password" class="block text-xs font-medium text-slate-600 mb-2 px-1">รหัสผ่าน (Password)</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-4 py-3 bg-white/60 border border-slate-200/80 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-slate-800 placeholder-slate-400 text-sm"
                    placeholder="••••••••">
            </div>

            <button type="submit" 
                class="w-full bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white font-medium py-3 rounded-2xl shadow-lg shadow-blue-700/20 transition-all duration-200 transform active:scale-[0.99] mt-2 text-sm tracking-wide cursor-pointer">
                Sign In
            </button>
        </form>

        <div class="border-t border-slate-200/60 my-6"></div>

        <div class="text-center text-xs text-slate-500">
            Don't have an account? 
            <a href="register.php" class="text-blue-600 hover:text-blue-700 font-medium transition-colors ml-1 underline">
                Sign up
            </a>
        </div>
    </div>

    <script src="assets/js/alerts.js"></script>

    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        if (typeof LantoAlert === 'undefined') return; 

        e.preventDefault();
        // 1. แสดงหน้าต่างกำลังโหลด
        LantoAlert.loading('กำลังเข้าสู่ระบบ', 'กำลังพาคุณเข้าสู่แดชบอร์ด Lanto Web...');

        const formData = new FormData(this);
        formData.append('ajax', '1');

        const minimumDelay = new Promise(resolve => setTimeout(resolve, 1000));
        const fetchServer = fetch('login.php', { method: 'POST', body: formData }).then(response => response.json());

        Promise.all([fetchServer, minimumDelay])
            .then(([data]) => {
                if (data.status === 'success') {
                    // 2. ถ้าสำเร็จ ไม่ต้องเรียกปุ่มกดตกลง ให้เปลี่ยนข้อความ Loading เป็นสำเร็จชั่วพริบตา แล้วพุ่งไปหน้าแดชบอร์ดทันที
                    if (typeof LantoAlert.update === 'function') {
                        LantoAlert.update('เข้าสู่ระบบสำเร็จ', 'กำลังเปลี่ยนหน้า...');
                    }
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 500); // หน่วงเวลาสั้นๆ 0.5 วินาทีให้ผู้ใช้เห็นสถานะ แล้วพาเข้าหน้าเว็บทันที
                } else {
                    LantoAlert.close();
                    setTimeout(() => {
                        LantoAlert.error('เข้าสู่ระบบล้มเหลว', data.message);
                    }, 300);
                }
            })
            .catch(error => {
                LantoAlert.close();
                setTimeout(() => {
                    LantoAlert.error('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ Lanto Web ได้ในขณะนี้');
                }, 300);
            });
    });
    </script>
</body>
</html>