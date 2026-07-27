<!-- 📌 Popup Modal เพิ่มพนักงานใหม่ -->
<div id="addEmployeeModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4 relative animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-visible">
        
        <!-- หัวข้อ Modal & ปุ่มปิด -->
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <span>➕</span> เพิ่มพนักงานใหม่เข้าสู่ระบบ
            </h3>
            <button type="button" onclick="closeAddEmployeeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <!-- ฟอร์มกรอกข้อมูลพนักงาน -->
        <form method="POST" action="manage_employees.php" class="space-y-3.5 text-xs">
            <input type="hidden" name="action" value="add_employee">

            <div class="grid grid-cols-2 gap-3">
                <!-- รหัสพนักงาน -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">รหัสพนักงาน <span class="text-rose-500">*</span></label>
                    <input type="text" name="employee_code" required placeholder="เช่น EMP69001" 
                        class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                </div>
                <!-- สิทธิ์การใช้งาน (Dropdown ขอบมน) -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">สิทธิ์ผู้ใช้งาน <span class="text-rose-500">*</span></label>
                    <?php 
                        $role_options = [
                            ['id' => 'employee', 'name' => 'พนักงานทั่วไป (Employee)'],
                            ['id' => 'hr', 'name' => 'ฝ่ายบุคคล (HR)'],
                            ['id' => 'it_support', 'name' => 'IT Support']
                        ];
                        renderRoundedDropdown('add_role_select', 'role', 'พนักงานทั่วไป (Employee)', $role_options, 'employee');
                    ?>
                </div>
            </div>

            <!-- ชื่อ - นามสกุล -->
            <div class="space-y-1">
                <label class="font-bold text-slate-700">ชื่อ - นามสกุล <span class="text-rose-500">*</span></label>
                <input type="text" name="fullname" required placeholder="กรอกชื่อและนามสกุลจริง" 
                    class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <!-- อีเมล -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">อีเมล</label>
                    <input type="email" name="email" placeholder="example@lanto.com" 
                        class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                </div>
                <!-- 🎯 เบอร์โทรศัพท์ -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">เบอร์โทรศัพท์</label>
                    <input type="tel" name="phone" placeholder="เช่น 0812345678" maxlength="10" 
                        class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                </div>
                <!-- รหัสผ่านเริ่มต้น -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">รหัสผ่านเริ่มต้น</label>
                    <input type="text" name="password" value="123456" placeholder="รหัสผ่านเข้าระบบ" 
                        class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <!-- วันเกิด -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">วัน/เดือน/ปี เกิด</label>
                    <input type="date" name="birth_date" value="2000-01-01" 
                        class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2 font-semibold text-slate-800 focus:outline-none shadow-sm">
                </div>
                <!-- วันเริ่มงาน -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">วันที่เริ่มงาน</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" 
                        class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2 font-semibold text-slate-800 focus:outline-none shadow-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <!-- สังกัดแผนก (Dropdown ขอบมน) -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">สังกัดแผนก</label>
                    <?php 
                        $dept_options = $departments ?? [];
                        renderRoundedDropdown('add_dept_select', 'department', '-- เลือกแผนก --', $dept_options, '');
                    ?>
                </div>
                <!-- สังกัดสาขา (Dropdown ขอบมน) -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">สังกัดสาขา</label>
                    <?php 
                        $branch_options = $branches ?? [];
                        renderRoundedDropdown('add_branch_select', 'branch_id', '-- เลือกสาขา --', $branch_options, '');
                    ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <!-- ประเภทพนักงาน (Dropdown ขอบมน) -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">ประเภทพนักงาน</label>
                    <?php 
                        $type_options = $employee_types ?? [];
                        renderRoundedDropdown('add_type_select', 'employee_type', '-- เลือกประเภทพนักงาน --', $type_options, '');
                    ?>
                </div>
                <!-- กะเวลาการทำงาน (Dropdown ขอบมน) -->
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">กะเวลาการทำงาน</label>
                    <?php 
                        $shift_options = $work_shifts ?? [];
                        renderRoundedDropdown('add_shift_select', 'work_shift', '-- เลือกกะการทำงาน --', $shift_options, '');
                    ?>
                </div>
            </div>

            <!-- ปุ่มกดยืนยัน -->
            <div class="flex gap-2 pt-3">
                <button type="button" onclick="closeAddEmployeeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-md shadow-blue-500/20 cursor-pointer">
                    บันทึกข้อมูลพนักงาน
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddEmployeeModal() {
        document.getElementById('addEmployeeModal').classList.remove('hidden');
    }

    function closeAddEmployeeModal() {
        document.getElementById('addEmployeeModal').classList.add('hidden');
    }
</script>