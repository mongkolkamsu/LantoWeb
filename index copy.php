<?php
session_start();

// 🎯 ถ้ายังไม่ได้ล็อกอิน ให้ปลดล็อก Session แล้วส่งกลับไปหน้า Login
if (!isset($_SESSION['user_id'])) {
    session_write_close();
    header("Location: login.php");
    exit();
}

// 🎯 ปลดล็อก Session ทันที ก่อนส่ง JavaScript เปลี่ยนหน้า
session_write_close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lanto Workspace - Loading</title>
    <script>
        // ตรวจสอบขนาดหน้าจออัตโนมัติ
        if (window.innerWidth >= 768) {
            window.location.href = 'index_pc.php';
        } else {
            window.location.href = 'index_mobile.php';
        }
    </script>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen">
    <div class="text-slate-500 font-medium text-sm animate-pulse">กำลังโหลดหน้าจอเข้าสู่ระบบ...</div>
</body>
</html>