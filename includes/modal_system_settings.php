<!-- 📁 1. MODAL: จัดการแผนก -->
<div id="deptModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="max-w-4xl w-full grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[90vh]">
        <!-- ฝั่งซ้าย: ฟอร์มแก้ไขข้อมูลแผนก -->
        <div class="bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 space-y-4 flex flex-col my-auto overflow-visible">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2" id="deptModalTitle"><span>📁</span> จัดการข้อมูลแผนก</h3>
                <button type="button" onclick="closeModal('deptModal')" class="md:hidden w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
            </div>

            <form method="POST" action="system_settings.php" class="space-y-4 text-xs overflow-visible">
                <input type="hidden" name="action" value="save_dept">
                <input type="hidden" id="dept_id_input" name="dept_id" value="">

                <div class="space-y-1">
                    <label class="font-bold text-slate-700">ชื่อแผนก <span class="text-rose-500">*</span></label>
                    <input type="text" id="dept_name_input" name="dept_name" required placeholder="เช่น ฝ่ายการตลาด, ฝ่าย IT" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                </div>

                <div class="space-y-1 relative z-30">
                    <label class="font-bold text-slate-700">หัวหน้าแผนก (Approver Step 2)</label>
                    <?php renderRoundedDropdown('head_user_id_select', 'head_user_id', '-- ยังไม่ได้กำหนด --', $dept_head_options, ''); ?>
                </div>

                <div class="flex gap-2 pt-3 relative z-10">
                    <button type="button" id="btn_delete_dept" onclick="deleteFromModal('dept')" class="hidden flex-1 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl transition-colors cursor-pointer text-xs flex items-center justify-center gap-1 border border-rose-200/60">
                        <span>🗑️</span> ลบแผนกนี้
                    </button>
                    <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition-colors cursor-pointer text-xs">
                        💾 บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>

        <!-- ฝั่งขวา: กล่องแสดงรายชื่อสมาชิกในแผนก -->
        <div class="bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 space-y-4 flex flex-col max-h-[85vh] relative">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <span>👥</span> รายชื่อพนักงานในแผนกนี้
                </h3>
                <button type="button" onclick="closeModal('deptModal')" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
            </div>
            <div class="overflow-y-auto flex-1 space-y-2 pr-1" id="dept_dual_members_container"></div>
        </div>
    </div>
</div>

<!-- 📍 2. MODAL: จัดการสาขา -->
<div id="branchModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2" id="branchModalTitle"><span>📍</span> เพิ่ม / แก้ไขข้อมูลสาขา</h3>
            <button type="button" onclick="closeModal('branchModal')" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <form method="POST" action="system_settings.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="save_branch">
            <input type="hidden" id="branch_id_input" name="branch_id" value="">

            <div class="space-y-1">
                <label class="font-bold text-slate-700">ชื่อสาขา <span class="text-rose-500">*</span></label>
                <input type="text" id="branch_name_input" name="branch_name" required placeholder="เช่น สำนักงานใหญ่, สาขาบางนา" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
            </div>

            <div class="space-y-1.5">
                <div class="flex justify-between items-center">
                    <label class="font-bold text-slate-700">คลิกบนแผนที่เพื่อเลือกตำแหน่งสาขา</label>
                    <button type="button" onclick="getCurrentLocationForBranch()" class="text-[10px] font-bold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-2.5 py-1 rounded-xl flex items-center gap-1 border border-blue-200/80 transition-colors cursor-pointer">
                        <span>🎯</span> ตำแหน่งปัจจุบันของฉัน
                    </button>
                </div>
                <div id="branchMap" style="height: 220px; width: 100%; min-height: 220px;" class="rounded-2xl border border-slate-200 relative overflow-hidden z-10 shadow-inner"></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">Latitude (ละติจูด)</label>
                    <input type="text" id="lat_input" name="latitude" placeholder="13.756331" oninput="updateMapFromInputs()" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-mono text-slate-800 focus:outline-none focus:border-blue-500">
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">Longitude (ลองจิจูด)</label>
                    <input type="text" id="lng_input" name="longitude" placeholder="100.501862" oninput="updateMapFromInputs()" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-mono text-slate-800 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div class="space-y-1">
                <label class="font-bold text-slate-700">รัศมีอนุญาตเช็กอิน (เมตร)</label>
                <input type="number" id="radius_input" name="radius" value="100" oninput="updateMapFromInputs()" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
            </div>

            <label class="flex items-center gap-2 cursor-pointer pt-1">
                <input type="checkbox" id="see_only_branch_checkbox" name="see_only_branch" value="1" class="w-4 h-4 text-blue-600 rounded border-slate-300">
                <span class="font-bold text-slate-700">จำกัดสิทธิ์การมองเห็นเฉพาะคนในสาขา (See Only Branch)</span>
            </label>

            <div class="flex gap-2 pt-3">
                <button type="button" id="btn_delete_branch" onclick="deleteFromModal('branch')" class="hidden flex-1 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl transition-colors cursor-pointer text-xs flex items-center justify-center gap-1 border border-rose-200/60">
                    <span>🗑️</span> ลบสาขานี้
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition-colors cursor-pointer text-xs">
                    💾 บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ⏰ 3. MODAL: จัดการกะงาน -->
<div id="shiftModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2" id="shiftModalTitle"><span>⏰</span> เพิ่ม / แก้ไขกะเวลาทำงาน</h3>
            <button type="button" onclick="closeModal('shiftModal')" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <form method="POST" action="system_settings.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="save_shift">
            <input type="hidden" id="shift_id_input" name="shift_id" value="">

            <div class="space-y-1">
                <label class="font-bold text-slate-700">ชื่อกะงาน <span class="text-rose-500">*</span></label>
                <input type="text" id="shift_name_input" name="shift_name" required placeholder="เช่น กะเช้าปกติ, กะดึก" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">เวลาเข้างาน</label>
                    <input type="time" id="start_time_input" name="start_time" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">เวลาเลิกงาน</label>
                    <input type="time" id="end_time_input" name="end_time" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div class="flex gap-2 pt-3">
                <button type="button" id="btn_delete_shift" onclick="deleteFromModal('shift')" class="hidden flex-1 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl transition-colors cursor-pointer text-xs flex items-center justify-center gap-1 border border-rose-200/60">
                    <span>🗑️</span> ลบกะงานนี้
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition-colors cursor-pointer text-xs">
                    💾 บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 👔 4. MODAL: จัดการตำแหน่ง -->
<div id="positionModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="max-w-4xl w-full grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[90vh]">
        <div class="bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 space-y-4 flex flex-col my-auto">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <span>👔</span> จัดการข้อมูลตำแหน่ง
                </h3>
                <button type="button" onclick="closeModal('positionModal')" class="md:hidden w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
            </div>

            <form method="POST" action="system_settings.php" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="save_position">
                <input type="hidden" name="position_id" id="position_id_input" value="">

                <div class="space-y-1">
                    <label class="font-bold text-slate-700">ชื่อตำแหน่ง <span class="text-rose-500">*</span></label>
                    <input type="text" name="position_name" id="position_name_input" required placeholder="เช่น ผู้จัดการฝ่าย, Senior Developer" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-3 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" id="btn_delete_position" onclick="deletePositionFromModal()" class="hidden py-2.5 px-4 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl transition-colors border border-rose-200 cursor-pointer">
                        🗑️ ลบตำแหน่งนี้
                    </button>
                    <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors shadow-md shadow-blue-500/20 cursor-pointer">
                        💾 บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 space-y-4 flex flex-col max-h-[85vh] relative">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <span>👥</span> รายชื่อพนักงานในตำแหน่งนี้
                </h3>
                <button type="button" onclick="closeModal('positionModal')" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
            </div>
            <div class="overflow-y-auto flex-1 space-y-2 pr-1" id="position_dual_members_container"></div>
        </div>
    </div>
</div>

<!-- 🛡️ 5. MODAL: มอบสิทธิ์การใช้งานระบบ -->
<div id="grantPermissionModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-5 overflow-visible">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                <span>➕</span> มอบสิทธิ์การใช้งานระบบ
            </h3>
            <button type="button" onclick="closeGrantPermissionModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <form method="POST" action="system_settings.php?tab=permissions" class="space-y-4 text-xs overflow-visible">
            <input type="hidden" name="action" value="grant_permission">

            <!-- 🎯 ช่องเลือกพนักงาน (มีช่องพิมพ์ค้นหาในตัว) -->
            <div class="space-y-1 relative z-20">
                <label class="font-bold text-slate-700">เลือกพนักงาน <span class="text-rose-500">*</span></label>
                <?php 
                    $grant_emp_opts = [];
                    if (!empty($all_employees_list)) {
                        foreach ($all_employees_list as $u) {
                            $grant_emp_opts[] = [
                                'id'   => (string)$u['id'],
                                'name' => htmlspecialchars($u['fullname']) . ' (' . htmlspecialchars($u['employee_code']) . ')'
                            ];
                        }
                    }
                    // พารามิเตอร์สุดท้าย true เปิดใช้งานช่องพิมพ์ค้นหา
                    renderRoundedDropdown('grant_user_id_select', 'user_id', '-- พิมพ์ชื่อหรือรหัสพนักงาน --', $grant_emp_opts, '', true);
                ?>
            </div>

            <!-- ช่องเลือกสิทธิ์ -->
            <div class="space-y-1 relative z-10">
                <label class="font-bold text-slate-700">เลือกสิทธิ์ที่ต้องการมอบให้ <span class="text-rose-500">*</span></label>
                <?php 
                    $grant_role_opts = [
                        ['id' => 'hr', 'name' => 'ฝ่ายบุคคล (HR)'],
                        ['id' => 'it_support', 'name' => 'IT Support'],
                        ['id' => 'messenger', 'name' => 'Messenger']
                    ];
                    // 🎯 เปลี่ยนตัวที่ 3 เป็น '-- เลือกสิทธิ์ --' และตัวที่ 5 เป็น '' (ค่าว่าง)
                    renderRoundedDropdown('grant_role_select', 'role', '-- เลือกสิทธิ์การใช้งาน --', $grant_role_opts, '', false);
                ?>
            </div>

            <div class="flex gap-2 pt-3">
                <button type="button" onclick="closeGrantPermissionModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors shadow-md shadow-blue-500/20 cursor-pointer">
                    บันทึกมอบสิทธิ์
                </button>
            </div>
        </form>
    </div>
</div>