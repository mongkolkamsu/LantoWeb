<!-- 📌 Modal แก้ไขข้อมูลพนักงาน (ถอดส่วนซ้อนทับออก ปรับเป็นแถบควบคุมสไลด์บรรทัดเดียวเรียบหรู) -->
<style>
    /* สไตล์ สกอร์บอร์ด (Scrollbar) มินิมอลสำหรับ Modal */
    .edit-modal-scroll::-webkit-scrollbar { width: 5px; }
    .edit-modal-scroll::-webkit-scrollbar-track { background: transparent; }
    .edit-modal-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .edit-modal-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* 🎯 CSS ย่อขนาด Calendar Popup ให้เล็กลง กระทัดรัด */
    #calendarPopup {
        position: fixed !important;
        z-index: 9999 !important;
        padding: 0.75rem !important;
        border-radius: 1.25rem !important;
        box-shadow: 0 15px 30px -5px rgba(15, 23, 42, 0.2) !important;
    }
    #calendarPopup .calendar {
        width: 250px !important;
    }
    #calendarPopup .calendar-header {
        padding: 0.2rem 0 0.5rem 0 !important;
    }
    #calendarPopup .calendar-header button {
        padding: 0.2rem 0.5rem !important;
        font-size: 0.8rem !important;
        border-radius: 8px !important;
    }
    #calendarPopup .day-name {
        padding: 0.25rem 0 !important;
        font-size: 0.7rem !important;
    }
    #calendarPopup .day {
        padding: 0.35rem 0 !important;
        font-size: 0.8rem !important;
        border-radius: 8px !important;
    }
</style>

<div id="editEmployeeModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    
    <!-- ตัว POPUP MODAL หลัก -->
    <div id="mainModalCard" class="relative bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 max-h-[85vh] overflow-y-auto edit-modal-scroll pb-10">
        
        <!-- Header + ปุ่มปิด -->
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <span>✏️</span> แก้ไขข้อมูลพนักงาน
            </h3>
            <button type="button" onclick="closeEditEmployeeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <!-- 🖼️ 1. แถบแสดง Avatar Overlap Stack + สวิตช์สลับโหมด (ทีละคน / พร้อมกัน) -->
        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-2xl flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div id="avatarStackContainer" class="flex items-center -space-x-2.5 overflow-hidden p-0.5"></div>
                <div class="text-xs">
                    <p id="stackTitle" class="font-extrabold text-slate-800 leading-tight">พนักงานที่เลือก</p>
                    <p id="stackSub" class="text-[10px] text-slate-400 font-medium">1 รายการ</p>
                </div>
            </div>

            <div id="modeToggleContainer" class="hidden bg-slate-200/70 p-1 rounded-xl flex items-center gap-1 text-[10px] font-bold">
                <button type="button" id="btnSingleMode" onclick="switchEditMode('single')" class="px-2.5 py-1 rounded-lg bg-white text-blue-600 shadow-2xs transition-all cursor-pointer">
                    👤 ทีละคน
                </button>
                <button type="button" id="btnBulkMode" onclick="switchEditMode('bulk')" class="px-2.5 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition-all cursor-pointer">
                    👥 พร้อมกัน
                </button>
            </div>
        </div>

        <!-- ฟอร์มส่งข้อมูล -->
        <form method="POST" action="manage_employees.php" enctype="multipart/form-data" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="update_employee">
            <input type="hidden" name="edit_mode" id="edit_mode_input" value="single">
            <input type="hidden" name="target_ids" id="edit_target_ids" value="">

            <!-- 🔄 2. ส่วนข้อมูลส่วนบุคคล (แสดงเฉพาะเมื่อเลือกโหมด '👤 ทีละคน') -->
            <div id="singleEditSection" class="space-y-3.5 border-b border-slate-100 pb-4">
                
                <!-- ◀️ ▶️ แถบสไลด์เปลี่ยนตัวพนักงาน (เรียบหรู บรรทัดเดียว ไม่มีส่วนซ้อนทับ) -->
                <div id="carouselNav" class="hidden flex items-center justify-between bg-blue-50/70 border border-blue-200/80 px-3.5 py-2 rounded-2xl text-blue-700 text-xs font-bold shadow-2xs">
                    <button type="button" onclick="prevSlide()" title="คนก่อนหน้า" class="w-7 h-7 bg-white hover:bg-blue-100 rounded-xl shadow-3xs border border-blue-200/60 flex items-center justify-center font-black text-blue-600 cursor-pointer transition-all active:scale-90 shrink-0">‹</button>
                    
                    <div id="carouselCounter" class="text-[11px] tracking-wide text-center px-2 min-w-0 flex-1 truncate">
                        <!-- JS จะใส่ข้อมูล: พนักงานคนที่ 1 / 3 : คุณสมชาย (ID: TestIT) -->
                    </div>

                    <button type="button" onclick="nextSlide()" title="คนถัดไป" class="w-7 h-7 bg-white hover:bg-blue-100 rounded-xl shadow-3xs border border-blue-200/60 flex items-center justify-center font-black text-blue-600 cursor-pointer transition-all active:scale-90 shrink-0">›</button>
                </div>

                <!-- 📂 ส่วนรูปภาพหลักฐานตัวตน -->
                <div id="formFieldsContainer" class="space-y-3.5">
                    <div>
                        <h4 class="text-xs font-bold text-blue-700 mb-2.5 flex items-center gap-1.5">📂 รูปภาพหลักฐานตัวตน <span class="text-[10px] text-slate-400 font-normal">(คลิกที่รูปเพื่อขยายใหญ่)</span></h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            
                            <!-- 1. รูปถ่ายโปรไฟล์ -->
                            <div class="bg-slate-50/80 border border-slate-200/60 p-3 rounded-2xl flex flex-col items-center">
                                <label class="block text-[11px] font-bold text-slate-700 mb-2 w-full text-left">1. รูปถ่ายโปรไฟล์พนักงาน</label>
                                <input type="file" id="edit_profile_image" name="profile_image" accept="image/*" onchange="previewEditImage(this, 'edit_profile_view', 'edit_profile_wrap')"
                                    class="w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                                
                                <div id="edit_profile_wrap" class="relative mt-2.5 w-24 h-24 rounded-2xl border border-slate-200 overflow-hidden shadow-xs bg-white flex items-center justify-center group">
                                    <img id="edit_profile_view" class="w-full h-full object-cover hidden cursor-pointer hover:scale-105 transition-transform duration-200" onclick="openImagePreviewModal(this.src)" title="คลิกเพื่อขยายรูปภาพ">
                                    <span id="edit_profile_no_img" class="text-[10px] text-slate-400 font-semibold">ไม่มีรูปภาพ</span>
                                </div>
                            </div>

                            <!-- 2. รูปถ่ายบัตรประชาชน -->
                            <div class="bg-slate-50/80 border border-slate-200/60 p-3 rounded-2xl flex flex-col items-center">
                                <label class="block text-[11px] font-bold text-slate-700 mb-2 w-full text-left">2. รูปถ่ายบัตรประชาชน</label>
                                <input type="file" id="edit_id_card_image" name="id_card_image" accept="image/*" onchange="previewEditImage(this, 'edit_idcard_view', 'edit_idcard_wrap')"
                                    class="w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                                
                                <div id="edit_idcard_wrap" class="relative mt-2.5 w-32 h-24 rounded-2xl border border-slate-200 overflow-hidden shadow-xs bg-white flex items-center justify-center group">
                                    <img id="edit_idcard_view" class="w-full h-full object-cover hidden cursor-pointer hover:scale-105 transition-transform duration-200" onclick="openImagePreviewModal(this.src)" title="คลิกเพื่อขยายรูปภาพ">
                                    <span id="edit_idcard_no_img" class="text-[10px] text-slate-400 font-semibold">ไม่มีรูปภาพ</span>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">รหัสพนักงาน <span class="text-rose-500">*</span></label>
                            <input type="text" id="single_code" name="single_code" required class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">สิทธิ์ผู้ใช้งาน <span class="text-rose-500">*</span></label>
                            <?php 
                                $role_options = [
                                    ['id' => 'employee', 'name' => 'พนักงานทั่วไป (Employee)'],
                                    ['id' => 'hr', 'name' => 'ฝ่ายบุคคล (HR)'],
                                    ['id' => 'it_support', 'name' => 'IT Support']
                                ];
                                renderRoundedDropdown('edit_role_select', 'role', 'พนักงานทั่วไป (Employee)', $role_options, 'employee');
                            ?>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">ชื่อ - นามสกุล <span class="text-rose-500">*</span></label>
                        <input type="text" id="single_fullname" name="single_fullname" required class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">อีเมล</label>
                            <input type="email" id="single_email" name="single_email" class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">เบอร์โทรศัพท์</label>
                            <input type="tel" id="single_phone" name="single_phone" placeholder="เช่น 0812345678" maxlength="10" class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">เปลี่ยนรหัสผ่าน <span class="text-slate-400 font-normal">(เว้นว่างถ้าไม่เปลี่ยน)</span></label>
                            <input type="password" name="password" placeholder="••••••••" autocomplete="new-password" class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm">
                        </div>
                    </div>

                    <!-- 📅 ช่องเลือกวันที่ + ไอคอนปฏิทิน 📅 -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">วัน/เดือน/ปี เกิด</label>
                            <div class="relative">
                                <input type="text" id="single_birth_date" name="birth_date" placeholder="วว/ดด/ปปปป"
                                    class="calendar-trigger w-full bg-white border border-slate-200 rounded-2xl pl-4 pr-10 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer">
                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 text-sm">📅</span>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">วันที่เริ่มงาน</label>
                            <div class="relative">
                                <input type="text" id="single_start_date" name="start_date" placeholder="วว/ดด/ปปปป"
                                    class="calendar-trigger w-full bg-white border border-slate-200 rounded-2xl pl-4 pr-10 py-2.5 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer">
                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 text-sm">📅</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🏢 3. ส่วนข้อมูลกลุ่ม (แสดงข้อความเตือนเมื่อเลือกโหมด '👥 พร้อมกัน') -->
            <div id="bulkNoticeText" class="hidden p-3 bg-amber-50 border border-amber-200/80 rounded-2xl text-[11px] font-bold text-amber-800 mb-2">
                ⚠️ คุณกำลังแก้ไขข้อมูลกลุ่มจำนวน <span id="editBulkCount" class="text-amber-950 font-black">0</span> คนพร้อมกัน (เลือกเฉพาะช่องที่ต้องการเปลี่ยน)
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">แผนก</label>
                    <?php 
                        $dept_options = $departments ?? [];
                        renderRoundedDropdown('edit_dept_select', 'department', '-- เลือกแผนก --', $dept_options, '');
                    ?>
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">สาขา</label>
                    <?php 
                        $branch_options = $branches ?? [];
                        renderRoundedDropdown('edit_branch_select', 'branch_id', '-- เลือกสาขา --', $branch_options, '');
                    ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">ประเภทพนักงาน</label>
                    <?php 
                        $type_options = $employee_types ?? [];
                        renderRoundedDropdown('edit_type_select', 'employee_type', '-- เลือกประเภท --', $type_options, '');
                    ?>
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-700">กะเวลาการทำงาน</label>
                    <?php 
                        $shift_options = $work_shifts ?? [];
                        renderRoundedDropdown('edit_shift_select', 'work_shift', '-- เลือกกะงาน --', $shift_options, '');
                    ?>
                </div>
            </div>

            <div class="space-y-1 max-w-[255px]">
                <label class="font-bold text-slate-700">สถานะใช้งานบัญชี</label>
                <?php 
                    $status_options = [
                        ['id' => 'active', 'name' => '🟢 เปิดใช้งาน (Active)'],
                        ['id' => 'inactive', 'name' => '🔴 ปิดใช้งาน (Inactive)']
                    ];
                    renderRoundedDropdown('edit_status_select', 'status', '-- เลือกสถานะ --', $status_options, 'active');
                ?>
            </div>

            <!-- ปุ่มบันทึก -->
            <div class="flex gap-2 pt-3">
                <button type="button" onclick="closeEditEmployeeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-md shadow-blue-500/20 cursor-pointer">
                    บันทึกการแก้ไข
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 🔍 Lightbox Modal ขยายดูรูปใหญ่ -->
<div id="imagePreviewModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[70] flex items-center justify-center p-4 cursor-zoom-out" onclick="closeImagePreviewModal()">
    <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeImagePreviewModal()" class="absolute -top-11 right-0 text-white/80 hover:text-white text-lg font-bold bg-white/10 hover:bg-white/20 w-9 h-9 rounded-full flex items-center justify-center transition-all cursor-pointer">✕</button>
        <img id="global_preview_img" src="" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain border border-white/10">
    </div>
</div>

<!-- 📅 ดึงปฏิทิน calendar_component.php -->
<?php include_once '../includes/calendar_component.php'; ?>

<script>
    let selectedEmployees = [];
    let currentIndex = 0;

    // 🎯 แปลง YYYY-MM-DD จาก DB เป็น วว/ดด/ปปปป (พ.ศ.)
    function formatDateToThaiDisplay(dateStr) {
        if (!dateStr || dateStr === '0000-00-00' || dateStr.trim() === '') return '';
        if (dateStr.includes('/')) return dateStr;
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            const y = parseInt(parts[0]) + 543;
            const m = parts[1].padStart(2, '0');
            const d = parts[2].padStart(2, '0');
            return `${d}/${m}/${y}`;
        }
        return dateStr;
    }

    // 🎯 Override ฟังก์ชันเปิดปฏิทิน
    const originalOpenCalendarFunc = window.openCalendar;
    window.openCalendar = function(e) {
        if (typeof originalOpenCalendarFunc === 'function') {
            originalOpenCalendarFunc(e);
        }
        const popup = document.getElementById("calendarPopup");
        if (popup && e.target) {
            const rect = e.target.getBoundingClientRect();
            popup.style.position = "fixed";
            popup.style.zIndex = "9999";
            
            const popupHeight = popup.offsetHeight || 280;
            const popupWidth = popup.offsetWidth || 250;
            
            if (window.innerHeight - rect.bottom < popupHeight && rect.top > popupHeight) {
                popup.style.top = (rect.top - popupHeight - 6) + "px";
            } else {
                popup.style.top = (rect.bottom + 6) + "px";
            }
            
            let left = rect.left;
            if (left + popupWidth > window.innerWidth - 15) {
                left = window.innerWidth - popupWidth - 15;
            }
            if (left < 15) left = 15;
            popup.style.left = left + "px";
        }
    };

    function openEditModalFromBulk() {
        selectedEmployees = getSelectedEmployeesData();
        if (selectedEmployees.length === 0) return;

        currentIndex = 0;
        renderAvatarStack();

        const modeToggle = document.getElementById('modeToggleContainer');

        if (selectedEmployees.length > 1) {
            if (modeToggle) modeToggle.classList.remove('hidden');
        } else {
            if (modeToggle) modeToggle.classList.add('hidden');
        }

        switchEditMode('single');
        document.getElementById('editEmployeeModal').classList.remove('hidden');
    }

    function renderAvatarStack() {
        const container = document.getElementById('avatarStackContainer');
        const title = document.getElementById('stackTitle');
        const sub = document.getElementById('stackSub');
        if (!container || !title || !sub) return;
        
        container.innerHTML = '';
        sub.textContent = selectedEmployees.length + ' รายการที่เลือก';

        const maxDisplay = 4;
        const displayEmps = selectedEmployees.slice(0, maxDisplay);

        displayEmps.forEach(emp => {
            const avatarDiv = document.createElement('div');
            avatarDiv.className = 'w-8 h-8 rounded-full bg-blue-600 border-2 border-white flex items-center justify-center font-bold text-white text-xs shrink-0 shadow-xs overflow-hidden';
            
            if (emp.avatar && emp.avatar.trim() !== '') {
                avatarDiv.innerHTML = `<img src="../uploads/profiles/${emp.avatar}" class="w-full h-full object-cover">`;
            } else {
                avatarDiv.textContent = emp.fullname ? emp.fullname.substring(0, 1) : '?';
            }
            container.appendChild(avatarDiv);
        });

        if (selectedEmployees.length > maxDisplay) {
            const extraDiv = document.createElement('div');
            extraDiv.className = 'w-8 h-8 rounded-full bg-slate-800 border-2 border-white flex items-center justify-center font-bold text-white text-[10px] shrink-0 shadow-xs';
            extraDiv.textContent = '+' + (selectedEmployees.length - maxDisplay);
            container.appendChild(extraDiv);
        }

        if (selectedEmployees.length === 1) {
            title.textContent = selectedEmployees[0].fullname;
            sub.textContent = 'รหัส: ' + selectedEmployees[0].code;
        } else {
            title.textContent = 'แก้ไขข้อมูลพนักงาน';
        }
    }

    function switchEditMode(mode) {
        document.getElementById('edit_mode_input').value = mode;
        const btnSingle = document.getElementById('btnSingleMode');
        const btnBulk = document.getElementById('btnBulkMode');
        const singleSection = document.getElementById('singleEditSection');
        const bulkNotice = document.getElementById('bulkNoticeText');

        if (mode === 'single') {
            if (btnSingle) btnSingle.className = "px-2.5 py-1 rounded-lg bg-white text-blue-600 shadow-2xs transition-all cursor-pointer font-bold";
            if (btnBulk) btnBulk.className = "px-2.5 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition-all cursor-pointer";
            if (singleSection) singleSection.classList.remove('hidden');
            if (bulkNotice) bulkNotice.classList.add('hidden');
            loadCurrentEmployeeToForm();
        } else {
            if (btnBulk) btnBulk.className = "px-2.5 py-1 rounded-lg bg-white text-blue-600 shadow-2xs transition-all cursor-pointer font-bold";
            if (btnSingle) btnSingle.className = "px-2.5 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition-all cursor-pointer";
            if (singleSection) singleSection.classList.add('hidden');
            if (bulkNotice) {
                bulkNotice.classList.remove('hidden');
                document.getElementById('editBulkCount').textContent = selectedEmployees.length;
            }
            document.getElementById('edit_target_ids').value = selectedEmployees.map(e => e.id).join(',');
            
            setDropdownValue('edit_dept_select', '', '-- เลือกแผนก --');
            setDropdownValue('edit_branch_select', '', '-- เลือกสาขา --');
            setDropdownValue('edit_type_select', '', '-- เลือกประเภท --');
            setDropdownValue('edit_shift_select', '', '-- เลือกกะงาน --');
            setDropdownValue('edit_status_select', '', '-- เลือกสถานะ --');
        }
    }

    function loadCurrentEmployeeToForm() {
        if (selectedEmployees.length === 0) return;
        const emp = selectedEmployees[currentIndex];

        // ◀️ ▶️ ควบคุมการแสดงผลแถบสไลด์
        const carouselNav = document.getElementById('carouselNav');
        const counter     = document.getElementById('carouselCounter');

        if (selectedEmployees.length > 1) {
            if (carouselNav) carouselNav.classList.remove('hidden');
            if (counter) {
                counter.innerHTML = `พนักงานคนที่ <span class="font-black text-blue-700">${currentIndex + 1}</span> / ${selectedEmployees.length} : <span class="font-extrabold text-slate-800">${emp.fullname}</span> <span class="text-[10px] font-bold text-slate-500">(ID: ${emp.code})</span>`;
            }
        } else {
            if (carouselNav) carouselNav.classList.add('hidden');
        }

        document.getElementById('edit_target_ids').value = emp.id;
        document.getElementById('single_code').value = emp.code;
        document.getElementById('single_fullname').value = emp.fullname;
        document.getElementById('single_email').value = emp.email;
        // 🎯 เพิ่มบรรทัดนี้เพื่อดึงเบอร์โทรมาหยอดใส่ช่อง
        if (document.getElementById('single_phone')) {
            document.getElementById('single_phone').value = emp.phone || '';
        }
        // 📅 โหลดและแปลงวันที่
        document.getElementById('single_birth_date').value = formatDateToThaiDisplay(emp.birth);
        document.getElementById('single_start_date').value = formatDateToThaiDisplay(emp.startdate);

        if (typeof bindCalendarEvents === 'function') {
            bindCalendarEvents();
        }

        // Reset Input File
        document.getElementById('edit_profile_image').value = '';
        document.getElementById('edit_id_card_image').value = '';

        // 🖼️ 1. รูปโปรไฟล์
        const pImg = document.getElementById('edit_profile_view');
        const pNoImg = document.getElementById('edit_profile_no_img');
        if (emp.avatar && emp.avatar.trim() !== '') {
            pImg.src = '../uploads/profiles/' + emp.avatar;
            pImg.classList.remove('hidden');
            pNoImg.classList.add('hidden');
        } else {
            pImg.src = '';
            pImg.classList.add('hidden');
            pNoImg.classList.remove('hidden');
        }

        // 🖼️ 2. รูปบัตรประชาชน
        const idImg = document.getElementById('edit_idcard_view');
        const idNoImg = document.getElementById('edit_idcard_no_img');
        if (emp.idcard && emp.idcard.trim() !== '') {
            idImg.src = '../uploads/id-cards/' + emp.idcard;
            idImg.classList.remove('hidden');
            idNoImg.classList.add('hidden');
        } else {
            idImg.src = '';
            idImg.classList.add('hidden');
            idNoImg.classList.remove('hidden');
        }

        const roleName = emp.role === 'hr' ? 'ฝ่ายบุคคล (HR)' : (emp.role === 'it_support' ? 'IT Support' : 'พนักงานทั่วไป (Employee)');
        setDropdownValue('edit_role_select', emp.role || 'employee', roleName);
        
        setDropdownValue('edit_dept_select', (emp.dept && emp.dept !== '0') ? emp.dept : '', '-- เลือกแผนก --');
        setDropdownValue('edit_branch_select', (emp.branch && emp.branch !== '0') ? emp.branch : '', '-- เลือกสาขา --');
        setDropdownValue('edit_type_select', (emp.type && emp.type !== '0') ? emp.type : '', '-- เลือกประเภท --');
        setDropdownValue('edit_shift_select', (emp.shift && emp.shift !== '0') ? emp.shift : '', '-- เลือกกะงาน --');
        
        const isInactive = (emp.status === 'inactive' || emp.status === '0');
        setDropdownValue('edit_status_select', isInactive ? 'inactive' : 'active', isInactive ? '🔴 ปิดใช้งาน (Inactive)' : '🟢 เปิดใช้งาน (Active)');
    }

    function setDropdownValue(dropdownId, val, defaultPlaceholder) {
        const hiddenInput = document.getElementById(dropdownId);
        const labelSpan = document.getElementById('label-' + dropdownId);
        if (!hiddenInput || !labelSpan) return;

        if (val !== null && val !== undefined && val !== '' && val !== '0') {
            hiddenInput.value = val;
            const optionItem = document.querySelector(`#list-${dropdownId} [data-value="${val}"]`);
            if (optionItem) {
                labelSpan.textContent = optionItem.textContent.trim();
                labelSpan.className = "text-slate-800 font-medium";
                return;
            }
        }

        hiddenInput.value = '';
        labelSpan.textContent = defaultPlaceholder;
        labelSpan.className = "text-slate-500";
    }

    function previewEditImage(input, viewId, wrapId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById(viewId);
                img.src = e.target.result;
                img.classList.remove('hidden');
                
                const parent = img.parentElement;
                const noImgSpan = parent.querySelector('span');
                if (noImgSpan) noImgSpan.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function openImagePreviewModal(src) {
        if (!src) return;
        const img = document.getElementById('global_preview_img');
        const modal = document.getElementById('imagePreviewModal');
        if (img && modal) {
            img.src = src;
            modal.classList.remove('hidden');
        }
    }

    function closeImagePreviewModal() {
        const modal = document.getElementById('imagePreviewModal');
        if (modal) modal.classList.add('hidden');
    }

    function prevSlide() {
        if (currentIndex > 0) {
            currentIndex--;
            loadCurrentEmployeeToForm();
        } else if (selectedEmployees.length > 1) {
            currentIndex = selectedEmployees.length - 1;
            loadCurrentEmployeeToForm();
        }
    }

    function nextSlide() {
        if (currentIndex < selectedEmployees.length - 1) {
            currentIndex++;
            loadCurrentEmployeeToForm();
        } else if (selectedEmployees.length > 1) {
            currentIndex = 0;
            loadCurrentEmployeeToForm();
        }
    }

    function closeEditEmployeeModal() {
        document.getElementById('editEmployeeModal').classList.add('hidden');
    }
</script>