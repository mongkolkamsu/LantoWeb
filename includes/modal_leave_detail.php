<!-- 📢 ดึงไฟล์ระบบ Alert Glassmorphism มาใช้งาน -->
<script src="../assets/js/alerts.js"></script>

<!-- 📑 DOUBLE POPUP MODAL (เด้งขึ้นมาพร้อมกัน 2 กล่อง แยกฝั่งซ้าย-ขวา) -->
<div id="leaveDetailModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
    
    <!-- Wrapper รวม 2 กล่อง วางเรียงคู่กัน -->
    <div class="flex flex-col lg:flex-row items-stretch justify-center gap-5 max-w-5xl w-full my-auto animate-in fade-in zoom-in duration-150">
        
        <!-- 🟢 MODAL 1 (กล่องซ้าย): รายละเอียดคำขออนุมัติ & ปุ่มดำเนินการ -->
        <div class="bg-white rounded-3xl lg:w-1/2 w-full p-6 shadow-2xl border border-slate-100 flex flex-col justify-between relative space-y-4 max-h-[85vh] overflow-y-auto">
            <div class="space-y-4">
                <!-- Header -->
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <span>📑</span> รายละเอียดคำขออนุมัติใบลา
                    </h3>
                    <button type="button" onclick="closeLeaveDetailModal()" class="lg:hidden w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors">✕</button>
                </div>

                <!-- 🖼️ กรอบรูปภาพเอกสาร/หลักฐานแนบการลา -->
                <div class="relative w-full h-48 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200/85 shadow-inner group">
                    <img id="m_leave_img" src="" alt="เอกสารหลักฐาน" 
                        class="absolute inset-0 w-full h-full object-cover hidden cursor-pointer group-hover:scale-105 transition-transform duration-300 z-10" 
                        onclick="openLeaveImageLightbox(this.src)"
                        title="คลิกเพื่อดูรูปขนาดใหญ่"
                        onerror="this.classList.add('hidden'); document.getElementById('m_no_img').classList.remove('hidden'); document.getElementById('m_img_hint').classList.add('hidden');">
                    
                    <div id="m_no_img" class="absolute inset-0 flex flex-col items-center justify-center text-center p-4 text-slate-400 space-y-1 z-0">
                        <span class="text-3xl">📄</span>
                        <p class="text-xs font-semibold">ไม่มีเอกสาร / รูปภาพแนบประกอบ</p>
                    </div>

                    <span id="m_img_hint" class="hidden absolute bottom-3 right-3 bg-black/60 text-white text-[10px] font-medium px-2.5 py-1 rounded-xl backdrop-blur-xs pointer-events-none shadow-md z-20">
                        🔍 คลิกเพื่อขยาย
                    </span>
                </div>

                <!-- ข้อมูลพนักงานและรายละเอียดใบลา -->
                <div class="space-y-2 text-xs">
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 flex justify-between items-center">
                        <div>
                            <h4 class="font-bold text-slate-900 leading-tight" id="m_fullname">-</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5" id="m_dept">-</p>
                        </div>
                        <span class="bg-slate-200 text-slate-700 px-2 py-0.5 rounded text-[10px] font-extrabold shrink-0" id="m_emp_code">ID: -</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                            <span class="text-[10px] text-slate-400 font-bold uppercase">ประเภทการลา</span>
                            <p class="font-extrabold text-blue-600" id="m_leave_type">-</p>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                            <span class="text-[10px] text-slate-400 font-bold uppercase">สถานะ</span>
                            <div>
                                <span id="m_status_badge" class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold border">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                            <span class="text-[10px] text-slate-400 font-bold uppercase">ช่วงวันที่ลา</span>
                            <p class="font-bold text-slate-700" id="m_leave_range">-</p>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                            <span class="text-[10px] text-slate-400 font-bold uppercase">ระยะเวลาที่ขอลา</span>
                            <p class="font-extrabold text-slate-800" id="m_duration">-</p>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">วันที่ส่งคำขอ</span>
                        <p class="font-bold text-slate-700" id="m_sub_date">-</p>
                    </div>

                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">เหตุผลการลา</span>
                        <p class="font-medium text-slate-700 leading-relaxed" id="m_reason">-</p>
                    </div>

                    <!-- 🔴 กล่องแสดงเหตุผลที่ไม่ใหอนุมัติ -->
                    <div id="m_reject_reason_box" class="hidden bg-rose-50/80 border border-rose-200/80 p-2.5 rounded-xl space-y-0.5">
                        <span class="text-[10px] text-rose-500 font-bold uppercase">เหตุผลที่ไม่ใหอนุมัติ (จาก HR)</span>
                        <p class="font-bold text-rose-700 leading-relaxed" id="m_reject_reason_text">-</p>
                    </div>
                </div>
            </div>

            <!-- โซนปุ่มดำเนินการ & ปิด Pop-up -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
                <div id="m_action_box" class="hidden flex gap-2">
                    <form id="form_leave_approve" method="POST" action="process_leave.php" class="flex-1">
                        <input type="hidden" name="leave_id" id="m_leave_id_approve" value="">
                        <input type="hidden" name="action" value="approve">
                        <button type="button" onclick="confirmLeaveAction('approve')" 
                                class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-colors shadow-md shadow-emerald-500/20 cursor-pointer">
                            ✅ อนุมัติคำขอ
                        </button>
                    </form>

                    <form id="form_leave_reject" method="POST" action="process_leave.php" class="flex-1">
                        <input type="hidden" name="leave_id" id="m_leave_id_reject" value="">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="reject_reason" id="m_reject_reason" value="">
                        <button type="button" onclick="confirmLeaveAction('reject')" 
                                class="w-full py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 text-xs font-bold rounded-xl transition-colors cursor-pointer">
                            ❌ ปฏิเสธคำขอ
                        </button>
                    </form>
                </div>

                <button type="button" onclick="closeLeaveDetailModal()" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors cursor-pointer">
                    ปิดหน้าต่าง
                </button>
            </div>
        </div>

        <!-- 🔵 MODAL 2 (กล่องขวา): ผังขั้นตอนการพิจารณา (Workflow Stepper 4 ขั้นตอน) -->
        <div class="bg-white rounded-3xl lg:w-1/2 w-full p-6 shadow-2xl border border-slate-100 flex flex-col justify-between relative space-y-5 max-h-[85vh] overflow-y-auto">
            <div>
                <!-- Header -->
                <div class="flex justify-between items-start border-b border-slate-100 pb-4 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-bold shrink-0 shadow-sm border border-blue-100">
                            🔄
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800 leading-tight">
                                ผังขั้นตอนการพิจารณา
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">ประวัติและลำดับขั้นตอนอนุมัติแบบรายละเอียด</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeLeaveDetailModal()" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer shrink-0">✕</button>
                </div>

                <!-- 🔄 TIMELINE WORKFLOW STEPPER -->
                <div class="relative pl-8 space-y-5">
                    
                    <!-- STEP 1: ยื่นคำขอ -->
                    <div class="relative">
                        <div id="m_line_step_1" class="absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-emerald-500 rounded-full"></div>
                        <div id="m_icon_step_1" class="absolute -left-8 top-1 w-7 h-7 rounded-full bg-emerald-500 text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white shrink-0 z-10">✓</div>
                        
                        <div id="m_card_step_1" class="bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs transition-all">
                            <div class="flex justify-between items-center gap-2 border-b border-slate-200/50 pb-2">
                                <span class="font-extrabold text-sm text-slate-800">1. ยื่นคำขอลา</span>
                                <span id="m_badge_step_1" class="px-2.5 py-1 rounded-lg text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200/60 shrink-0">✅ สำเร็จ</span>
                            </div>
                            <div class="text-xs space-y-1 text-slate-600">
                                <p>ผู้ยื่นเรื่อง: <span class="font-bold text-slate-800" id="m_text_step_1_user">-</span></p>
                                <p>เวลาที่ส่งคำขอ: <span class="font-bold text-slate-700" id="m_text_step_1_time">-</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: หัวหน้าแผนก -->
                    <div class="relative">
                        <div id="m_line_step_2" class="absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-slate-200 rounded-full transition-colors"></div>
                        <div id="m_icon_step_2" class="absolute -left-8 top-1 w-7 h-7 rounded-full bg-slate-200 text-slate-600 font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white shrink-0 z-10 transition-all">2</div>
                        
                        <div id="m_card_step_2" class="bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs transition-all">
                            <div class="flex justify-between items-center gap-2 border-b border-slate-200/50 pb-2">
                                <span class="font-extrabold text-sm text-slate-800">2. หัวหน้าแผนก</span>
                                <span id="m_badge_step_2" class="px-2.5 py-1 rounded-lg text-xs font-extrabold bg-slate-100 text-slate-500 shrink-0">-</span>
                            </div>
                            <div class="text-xs space-y-1 text-slate-600">
                                <p>ผู้พิจารณา: <span class="font-bold text-slate-800" id="m_text_step_2_approver">-</span></p>
                                <p>เวลาดำเนินการ: <span class="font-bold text-slate-700" id="m_text_step_2_time">-</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: HR -->
                    <div class="relative">
                        <div id="m_line_step_3" class="absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-slate-200 rounded-full transition-colors"></div>
                        <div id="m_icon_step_3" class="absolute -left-8 top-1 w-7 h-7 rounded-full bg-slate-200 text-slate-600 font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white shrink-0 z-10 transition-all">3</div>
                        
                        <div id="m_card_step_3" class="bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs transition-all">
                            <div class="flex justify-between items-center gap-2 border-b border-slate-200/50 pb-2">
                                <span class="font-extrabold text-sm text-slate-800">3. ฝ่ายบุคคล (HR)</span>
                                <span id="m_badge_step_3" class="px-2.5 py-1 rounded-lg text-xs font-extrabold bg-slate-100 text-slate-500 shrink-0">-</span>
                            </div>
                            <div class="text-xs space-y-1 text-slate-600">
                                <p>ผู้พิจารณา: <span class="font-bold text-slate-800" id="m_text_step_3_approver">ฝ่ายบุคคล (HR)</span></p>
                                <p>เวลาดำเนินการ: <span class="font-bold text-slate-700" id="m_text_step_3_time">-</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: สรุปผล -->
                    <div class="relative">
                        <div id="m_icon_step_4" class="absolute -left-8 top-1 w-7 h-7 rounded-full bg-slate-200 text-slate-600 font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white shrink-0 z-10 transition-all">4</div>
                        
                        <div id="m_card_step_4" class="bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs transition-all">
                            <div class="flex justify-between items-center gap-2 border-b border-slate-200/50 pb-2">
                                <span class="font-extrabold text-sm text-slate-800">4. ผลการอนุมัติ</span>
                                <span id="m_badge_step_4" class="px-2.5 py-1 rounded-lg text-xs font-extrabold bg-slate-100 text-slate-500 shrink-0">-</span>
                            </div>
                            <div class="text-xs space-y-1">
                                <p class="text-slate-600">สถานะคำขอ: <span class="font-extrabold text-slate-800" id="m_text_step_4">-</span></p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ปุ่มปิด Pop-up ฝั่งขวา -->
            <div class="pt-3 border-t border-slate-100 mt-4">
                <button type="button" onclick="closeLeaveDetailModal()" class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-2xl transition-colors cursor-pointer">
                    ปิดหน้าต่าง
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 🔍 Lightbox ขยายดูรูปขนาดใหญ่ -->
<div id="leaveImageLightbox" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[60] flex items-center justify-center p-4 cursor-zoom-out" onclick="closeLeaveImageLightbox()">
    <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeLeaveImageLightbox()" class="absolute -top-11 right-0 text-white/80 hover:text-white text-lg font-bold bg-white/10 hover:bg-white/20 w-9 h-9 rounded-full flex items-center justify-center transition-all cursor-pointer">✕</button>
        <img id="lightbox_img" src="" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain border border-white/10">
    </div>
</div>

<script>
    function confirmLeaveAction(actionType) {
        const modal = document.getElementById('leaveDetailModal');
        const isApprove = actionType === 'approve';

        if (isApprove) {
            const title = 'ยืนยันการอนุมัติคำขอลานี้?';
            const message = 'เมื่ออนุมัติแล้ว ระบบจะบันทึกการลาของพนักงานลงในฐานข้อมูล';

            const doSubmit = function() {
                const form = modal ? modal.querySelector('#form_leave_approve') : document.getElementById('form_leave_approve');
                if (form) form.submit();
            };

            if (window.LantoAlert && typeof window.LantoAlert.confirm === 'function') {
                window.LantoAlert.confirm(title, message, function() {
                    if (typeof window.LantoAlert.loading === 'function') {
                        window.LantoAlert.loading('กำลังบันทึกข้อมูล...', 'โปรดรอสักครู่');
                    }
                    doSubmit();
                }, null, 'approve');
            } else {
                if (confirm(title)) doSubmit();
            }
        } else {
            const title = 'ยืนยันที่จะปฏิเสธคำขอลานี้?';
            const message = 'โปรดระบุเหตุผลในการไม่อนุมัติใบลา เพื่อแจ้งให้พนักงานทราบ';

            const doSubmitReject = function(reason) {
                if (!reason || !reason.trim()) {
                    if (window.LantoAlert && typeof window.LantoAlert.warning === 'function') {
                        window.LantoAlert.warning('กรุณาระบุเหตุผล', 'จำเป็นต้องใส่เหตุผลในการไม่อนุมัติใบลา');
                    } else {
                        alert('กรุณาระบุเหตุผลในการไม่อนุมัติใบลา');
                    }
                    return;
                }

                const form = modal ? modal.querySelector('#form_leave_reject') : document.getElementById('form_leave_reject');
                const reasonInput = modal ? modal.querySelector('#m_reject_reason') : document.getElementById('m_reject_reason');
                if (reasonInput) reasonInput.value = reason.trim();

                if (window.LantoAlert && typeof window.LantoAlert.loading === 'function') {
                    window.LantoAlert.loading('กำลังบันทึกข้อมูล...', 'โปรดรอสักครู่');
                }
                if (form) form.submit();
            };

            if (window.LantoAlert && typeof window.LantoAlert.prompt === 'function') {
                window.LantoAlert.prompt(title, message, 'ระบุเหตุผล เช่น ติดภารกิจด่วน, เอกสารไม่ครบถ้วน...', doSubmitReject);
            } else {
                const reason = prompt(title + '\n' + message);
                if (reason !== null) {
                    doSubmitReject(reason);
                }
            }
        }
    }

    function openLeaveDetailModal(fullname, empCode, deptName, leaveType, range, duration, subDate, reason, imageUrl, status, leaveId, rejectReason) {
        const modal = document.getElementById('leaveDetailModal');
        if (!modal) return;

        // ฟังก์ชันช่วยใส่ข้อความแบบกัน Error
        const setText = (selector, val, defaultText = '-') => {
            const el = modal.querySelector(selector);
            if (el) el.textContent = (val && val !== 'null' && val !== 'undefined') ? val : defaultText;
        };

        setText('#m_fullname', fullname);
        const empCodeEl = modal.querySelector('#m_emp_code');
        if (empCodeEl) empCodeEl.textContent = 'ID: ' + (empCode && empCode !== 'null' ? empCode : '-');

        setText('#m_dept', deptName, 'ไม่ระบุแผนก');
        setText('#m_leave_type', leaveType);
        setText('#m_leave_range', range);
        setText('#m_duration', duration);
        setText('#m_sub_date', subDate);
        setText('#m_reason', reason);

        const statusBadge = modal.querySelector('#m_status_badge');
        const actionBox = modal.querySelector('#m_action_box');
        const rejectBox = modal.querySelector('#m_reject_reason_box');

        if (status === 'approved') {
            if (statusBadge) {
                statusBadge.textContent = '✅ อนุมัติแล้ว';
                statusBadge.className = 'inline-block px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200';
            }
            if (actionBox) actionBox.classList.add('hidden');
            if (rejectBox) rejectBox.classList.add('hidden');
        } else if (status === 'rejected') {
            if (statusBadge) {
                statusBadge.textContent = '❌ ไม่อนุมัติ';
                statusBadge.className = 'inline-block px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-rose-50 text-rose-700 border border-rose-200';
            }
            if (actionBox) actionBox.classList.add('hidden');
            
            if (rejectBox) {
                setText('#m_reject_reason_text', rejectReason, 'ไม่ระบุเหตุผล');
                rejectBox.classList.remove('hidden');
            }
        } else {
            if (statusBadge) {
                statusBadge.textContent = '⏳ รออนุมัติ';
                statusBadge.className = 'inline-block px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-amber-50 text-amber-700 border border-amber-200';
            }
            
            const appEl = modal.querySelector('#m_leave_id_approve');
            const rejEl = modal.querySelector('#m_leave_id_reject');
            if (appEl) appEl.value = leaveId || '';
            if (rejEl) rejEl.value = leaveId || '';
            
            if (actionBox) actionBox.classList.remove('hidden');
            if (rejectBox) rejectBox.classList.add('hidden');
        }

        // 🎯 คำนวณและอัปเดต ผังขั้นตอนการพิจารณา (ฝั่งขวาขนาดใหม่)
        const l2 = modal.querySelector('#m_line_step_2');
        const l3 = modal.querySelector('#m_line_step_3');

        const i2 = modal.querySelector('#m_icon_step_2');
        const i3 = modal.querySelector('#m_icon_step_3');
        const i4 = modal.querySelector('#m_icon_step_4');

        const c1 = modal.querySelector('#m_card_step_1');
        const c2 = modal.querySelector('#m_card_step_2');
        const c3 = modal.querySelector('#m_card_step_3');
        const c4 = modal.querySelector('#m_card_step_4');

        const b2 = modal.querySelector('#m_badge_step_2');
        const b3 = modal.querySelector('#m_badge_step_3');
        const b4 = modal.querySelector('#m_badge_step_4');

        // อัปเดตข้อมูล Step 1 เสมอ
        setText('#m_text_step_1_user', fullname || 'ผู้ดูแลระบบ');
        setText('#m_text_step_1_time', subDate || '-');

        if (l2 && l3 && i2 && i3 && i4 && b2 && b3 && b4) {
            if (status === 'pending_head' || status === 'pending') {
                // เส้นเชื่อม
                l2.className = "absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-slate-200 rounded-full";
                l3.className = "absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-slate-200 rounded-full";

                // Step 2 (กำลังรอหัวหน้า)
                i2.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-amber-500 text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white animate-pulse";
                i2.textContent = "2";
                if(c2) c2.className = "bg-amber-50/60 border border-amber-200/80 rounded-2xl p-4 space-y-2 shadow-xs transition-all";
                b2.textContent = "⏳ รอหัวหน้าอนุมัติ";
                b2.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-amber-100 text-amber-800 border border-amber-200";
                setText('#m_text_step_2_approver', 'ผู้อนุมัติ / หัวหน้าแผนก');
                setText('#m_text_step_2_time', subDate ? subDate + ' (รอพิจารณา)' : '-');

                // Step 3 (รอคิว)
                i3.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-slate-200 text-slate-500 font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i3.textContent = "3";
                if(c3) c3.className = "bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs opacity-75";
                b3.textContent = "⏳ รอกระบวนการก่อนหน้า";
                b3.className = "px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-500";
                setText('#m_text_step_3_time', 'รอการอนุมัติจากขั้นที่ 2');

                // Step 4 (สรุปผล)
                i4.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-slate-200 text-slate-500 font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i4.textContent = "4";
                if(c4) c4.className = "bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs opacity-75";
                b4.textContent = "⏳ รอดำเนินการ";
                b4.className = "px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-500";
                const t4 = modal.querySelector('#m_text_step_4');
                if(t4) t4.innerHTML = '<span class="text-amber-600 font-bold">อยู่ในระหว่างพิจารณา</span>';

            } else if (status === 'pending_hr') {
                // เส้นเชื่อม
                l2.className = "absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-emerald-500 rounded-full";
                l3.className = "absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-slate-200 rounded-full";

                // Step 2 (ผ่านแล้ว)
                i2.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-emerald-500 text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i2.textContent = "✓";
                if(c2) c2.className = "bg-emerald-50/40 border border-emerald-200/80 rounded-2xl p-4 space-y-2 shadow-xs";
                b2.textContent = "✅ หัวหน้าอนุมัติแล้ว";
                b2.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200";
                setText('#m_text_step_2_approver', 'ผู้อนุมัติ / หัวหน้าแผนก');
                setText('#m_text_step_2_time', subDate || '-');

                // Step 3 (กำลังรอ HR)
                i3.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-sky-500 text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white animate-pulse";
                i3.textContent = "3";
                if(c3) c3.className = "bg-sky-50/60 border border-sky-200/80 rounded-2xl p-4 space-y-2 shadow-xs";
                b3.textContent = "⏳ รอ HR อนุมัติ";
                b3.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-sky-100 text-sky-800 border border-sky-200";
                setText('#m_text_step_3_time', 'กำลังรอ HR ตรวจสอบขั้นสุดท้าย');

                // Step 4
                i4.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-slate-200 text-slate-500 font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i4.textContent = "4";
                if(c4) c4.className = "bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs opacity-75";
                b4.textContent = "⏳ รอดำเนินการ";
                b4.className = "px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-500";
                const t4 = modal.querySelector('#m_text_step_4');
                if(t4) t4.innerHTML = '<span class="text-sky-600 font-bold">รอการอนุมัติขั้นสุดท้าย</span>';

            } else if (status === 'approved') {
                // เส้นเชื่อม
                l2.className = "absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-emerald-500 rounded-full";
                l3.className = "absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-emerald-500 rounded-full";

                // Step 2 & 3 & 4 สำเร็จทั้งหมด
                i2.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-emerald-500 text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i2.textContent = "✓";
                if(c2) c2.className = "bg-emerald-50/40 border border-emerald-200/80 rounded-2xl p-4 space-y-2 shadow-xs";
                b2.textContent = "✅ ผ่านแล้ว";
                b2.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200";
                setText('#m_text_step_2_approver', 'ผู้อนุมัติ / หัวหน้าแผนก');

                i3.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-emerald-500 text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i3.textContent = "✓";
                if(c3) c3.className = "bg-emerald-50/40 border border-emerald-200/80 rounded-2xl p-4 space-y-2 shadow-xs";
                b3.textContent = "✅ HR อนุมัติแล้ว";
                b3.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200";

                i4.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-emerald-600 text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i4.textContent = "✓";
                if(c4) c4.className = "bg-emerald-50/80 border border-emerald-200/90 rounded-2xl p-4 space-y-2 shadow-xs";
                b4.textContent = "🎉 อนุมัติสมบูรณ์";
                b4.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300";
                
                const t4 = modal.querySelector('#m_text_step_4');
                if(t4) t4.innerHTML = '<span class="text-emerald-600 font-extrabold">อนุมัติคำขอเรียบร้อยแล้ว</span>';

            } else if (status === 'rejected') {
                // เส้นเชื่อม
                l2.className = "absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-rose-300 rounded-full";
                l3.className = "absolute -left-[23px] top-8 bottom-[-20px] w-0.5 bg-slate-200 rounded-full";

                // Step 2 ปฏิเสธ (ตรงตามรูปในตัวอย่าง)
                i2.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-rose-500 text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i2.textContent = "✕";
                if(c2) c2.className = "bg-rose-50/70 border border-rose-200 rounded-2xl p-4 space-y-2 shadow-xs";
                b2.textContent = "✕ ไม่อนุมัติ";
                b2.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-rose-100 text-rose-700 border border-rose-200/80";
                setText('#m_text_step_2_approver', 'ผู้อนุมัติ / ฝ่ายบริหาร');
                setText('#m_text_step_2_time', subDate || '-');

                // Step 3 ยุติกระบวนการ
                i3.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-slate-200 text-slate-500 font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i3.textContent = "3";
                if(c3) c3.className = "bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs opacity-75";
                b3.textContent = "✕ ยุติกระบวนการ";
                b3.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-slate-200/80 text-slate-600";
                setText('#m_text_step_3_approver', 'ฝ่ายบุคคล (HR)');
                setText('#m_text_step_3_time', 'กระบวนการยุติแล้ว');

                // Step 4 ปฏิเสธคำขอ
                i4.className = "absolute -left-8 top-1 w-7 h-7 rounded-full bg-slate-200 text-slate-500 font-black flex items-center justify-center text-xs shadow-md ring-4 ring-white";
                i4.textContent = "4";
                if(c4) c4.className = "bg-slate-50/80 border border-slate-200/80 rounded-2xl p-4 space-y-2 shadow-xs";
                b4.textContent = "✕ ปฏิเสธคำขอ";
                b4.className = "px-2.5 py-1 rounded-lg text-xs font-extrabold bg-rose-100 text-rose-700 border border-rose-200/80";
                
                const t4 = modal.querySelector('#m_text_step_4');
                if(t4) t4.innerHTML = '<span class="text-rose-600 font-extrabold">คำขอไม่ผ่านการพิจารณาอนุมัติ</span>';
            }
        }

        const imgElem = modal.querySelector('#m_leave_img');
        const noImgElem = modal.querySelector('#m_no_img');
        const hintElem = modal.querySelector('#m_img_hint');

        if (imgElem) {
            if (imageUrl && imageUrl.trim() !== '' && imageUrl !== 'null') {
                imgElem.src = imageUrl;
                imgElem.classList.remove('hidden');
                if (noImgElem) noImgElem.classList.add('hidden');
                if (hintElem) hintElem.classList.remove('hidden');
            } else {
                imgElem.src = '';
                imgElem.classList.add('hidden');
                if (noImgElem) noImgElem.classList.remove('hidden');
                if (hintElem) hintElem.classList.add('hidden');
            }
        }

        modal.classList.remove('hidden');
    }

    function closeLeaveDetailModal() {
        const modal = document.getElementById('leaveDetailModal');
        if (modal) modal.classList.add('hidden');
    }

    function openLeaveImageLightbox(src) {
        if (!src) return;
        const lightbox = document.getElementById('leaveImageLightbox');
        const targetImg = document.getElementById('lightbox_img');
        if (lightbox && targetImg) {
            targetImg.src = src;
            lightbox.classList.remove('hidden');
        }
    }

    function closeLeaveImageLightbox() {
        const lightbox = document.getElementById('leaveImageLightbox');
        if (lightbox) lightbox.classList.add('hidden');
    }
</script>