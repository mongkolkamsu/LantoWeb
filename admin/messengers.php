<?php
require_once '../config/auth.php';

// 🔒 ตรวจสอบสิทธิ์เฉพาะ Admin, HR, และ IT
if (!in_array($user_role, ['admin', 'hr', 'it_support'], true)) {
    $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
    header("Location: ../index_pc.php");
    exit();
}

$messengers_list = [];
$all_employees   = [];

try {
    // 1. ดึงแมสเซนเจอร์ (role = 'messenger') พร้อมเช็กสถานะงานค้างเรียลไทม์
    $stmt_msg = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.employee_code, u.phone, u.role,
               d.name AS dept_name,
               (
                   SELECT COUNT(*) 
                   FROM messenger_requests m 
                   WHERE m.messenger_id = u.id 
                     AND m.status IN ('accepted', 'picking_up', 'delivering')
               ) AS active_job_count
        FROM users u
        LEFT JOIN departments d ON u.department = d.id
        WHERE u.role = 'messenger'
        ORDER BY u.id DESC
    ");
    $messengers_list = $stmt_msg->fetchAll(PDO::FETCH_ASSOC);

    // 2. ดึงรายชื่อพนักงานทั่วไปที่ยังไม่ได้เป็นแมสเซนเจอร์ (สำหรับเลือกมอบสิทธิ์)
    $stmt_emp = $pdo->query("
        SELECT id, first_name, last_name, employee_code, role 
        FROM users 
        WHERE role NOT IN ('messenger', 'admin')
        ORDER BY first_name ASC
    ");
    $all_employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {}

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสิทธิ์แมสเซนเจอร์ - Lanto Workspace</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Noto Sans Thai', sans-serif; }</style>
</head>
<body class="bg-[#f8fafc] min-h-screen text-slate-800 antialiased pb-12">

    <!-- 🔝 ดึง Header กลางระบบ -->
    <?php 
    $page_title    = '🛵 จัดการสิทธิ์แมสเซนเจอร์';
    $page_subtitle = 'กำหนด Role พนักงานปฏิบัติหน้าที่แมสเซนเจอร์ และตรวจสอบสถานะงานเรียลไทม์';
    $show_back     = true;
    $back_url      = '../index_pc.php';
    include_once '../includes/header.php'; 
    ?>

    <main class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto w-full space-y-6">

        <!-- ➕ ส่วนที่ 1: เลือกพนักงานเพื่อปรับ Role เป็นแมสเซนเจอร์ -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4">
            <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                <span>➕</span> แต่งตั้งพนักงานเป็นแมสเซนเจอร์
            </h2>

            <form method="POST" action="process_messenger.php" class="flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="action" value="add_messenger">
                
                <select name="user_id" required class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                    <option value="">-- เลือกพนักงานที่ต้องการเปลี่ยนเป็น Role แมสเซนเจอร์ --</option>
                    <?php foreach ($all_employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            [<?php echo htmlspecialchars($emp['employee_code']); ?>] <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-2xl shadow-md transition-all cursor-pointer active:scale-95 shrink-0">
                    + มอบสิทธิ์แมสเซนเจอร์
                </button>
            </form>
        </div>

        <!-- 📋 ส่วนที่ 2: รายชื่อแมสเซนเจอร์ และป้ายสถานะ ว่าง / กำลังวิ่งงาน -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-2xs space-y-4">
            <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                <span>🛵</span> รายชื่อแมสเซนเจอร์ในระบบ (<?php echo count($messengers_list); ?> คน)
            </h2>

            <?php if (empty($messengers_list)): ?>
                <div class="p-8 text-center text-slate-400 text-xs font-light bg-slate-50 rounded-2xl">
                    🚫 ขณะนี้ยังไม่มีพนักงานที่มี Role เป็นแมสเซนเจอร์
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($messengers_list as $msg): ?>
                    <?php 
                        $is_busy = ($msg['active_job_count'] > 0);
                        $status_bg = $is_busy ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700';
                        $status_text = $is_busy ? '🔴 กำลังวิ่งงาน (' . $msg['active_job_count'] . ' งาน)' : '🟢 พร้อมรับงาน (ว่างอยู่)';
                    ?>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-sm shrink-0">
                                🛵
                            </div>
                            <div>
                                <p class="font-extrabold text-xs text-slate-800">
                                    <?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?>
                                    <span class="text-[10px] text-slate-400 font-normal">(<?php echo htmlspecialchars($msg['employee_code']); ?>)</span>
                                </p>
                                <p class="text-[10px] text-slate-500">แผนก: <?php echo htmlspecialchars($msg['dept_name'] ?? 'ทั่วไป'); ?> | โทร: <?php echo htmlspecialchars($msg['phone'] ?? '-'); ?></p>
                                
                                <span class="inline-block mt-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border <?php echo $status_bg; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                        </div>

                        <!-- ปุ่มถอด Role แมสเซนเจอร์ -->
                        <button type="button" onclick="confirmRemoveMessenger(<?php echo $msg['id']; ?>, '<?php echo htmlspecialchars($msg['first_name']); ?>')" class="px-3 py-1.5 bg-rose-100 hover:bg-rose-200 text-rose-700 font-bold rounded-xl text-xs transition-colors cursor-pointer shrink-0">
                            ถอดสิทธิ์
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script src="../assets/js/alerts.js"></script>
    <script>
        function confirmRemoveMessenger(userId, name) {
            if (typeof LantoAlert !== 'undefined' && typeof LantoAlert.confirm === 'function') {
                LantoAlert.confirm('ยืนยันถอดสิทธิ์', `คุณต้องการปรับ Role ของ ${name} กลับเป็นพนักงานทั่วไปใช่หรือไม่?`, function() {
                    window.location.href = `process_messenger.php?action=remove_messenger&user_id=${userId}`;
                });
            } else if (confirm(`คุณต้องการปรับ Role ของ ${name} กลับเป็นพนักงานทั่วไปใช่หรือไม่?`)) {
                window.location.href = `process_messenger.php?action=remove_messenger&user_id=${userId}`;
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            <?php if (!empty($success_msg)): ?>
                if (typeof LantoAlert !== 'undefined') LantoAlert.success('สำเร็จ', '<?php echo htmlspecialchars($success_msg); ?>');
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                if (typeof LantoAlert !== 'undefined') LantoAlert.error('ผิดพลาด', '<?php echo htmlspecialchars($error_msg); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>