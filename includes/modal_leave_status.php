<?php
require_once __DIR__ . '/rounded_dropdown.php';
?>

<!-- 📑 POPUP MODAL ติดตามสถานะคำขอลา (ปรับดีไซน์ Soft Minimal สบายตา) -->
<div id="leaveStatusModal" class="hidden fixed inset-0 bg-slate-900/65 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm md:max-w-xl w-full p-5 md:p-7 shadow-2xl border border-slate-100 space-y-4 relative animate-in fade-in zoom-in duration-150 max-h-[92vh] overflow-y-auto">
        
        <!-- Header & ปุ่มปิด Modal -->
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                    <span>📑</span> ผังติดตามสถานะการลา
                </h3>
                <p class="text-[10px] text-slate-400 mt-0.5">ติดตามลำดับขั้นตอนการอนุมัติแบบเรียลไทม์</p>
            </div>
            <button type="button" onclick="closeLeaveStatusModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <!-- 📊 การ์ดสรุปสิทธิ์วันลาประจำปี (Soft Minimal Light Theme) -->
        <div class="bg-slate-50 border border-slate-200/80 p-3.5 rounded-2xl space-y-2.5">
            <div class="flex justify-between items-center">
                <span class="text-[11px] font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span>📊</span> สิทธิ์วันลาประจำปี (<?php echo (date('Y') + 543); ?>)
                </span>
                <span class="text-[10px] font-bold text-slate-400">อนุมัติแล้ว</span>
            </div>
            
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <!-- 1. ลากิจ -->
                <div class="bg-white border border-slate-100 p-2.5 rounded-xl shadow-2xs space-y-0.5">
                    <p class="text-[10px] text-slate-500 font-semibold">💼 ลากิจ</p>
                    <p class="font-black text-slate-800 text-xs">
                        <span class="text-blue-600 font-extrabold text-sm"><?php echo $leave_quota['business']['used'] ?? 0; ?></span>
                        <span class="text-slate-400 font-normal text-[10px]">/ <?php echo $leave_quota['business']['max'] ?? 3; ?> วัน</span>
                    </p>
                </div>

                <!-- 2. ลาป่วย -->
                <div class="bg-white border border-slate-100 p-2.5 rounded-xl shadow-2xs space-y-0.5">
                    <p class="text-[10px] text-slate-500 font-semibold">🤒 ลาป่วย</p>
                    <p class="font-black text-slate-800 text-xs">
                        <span class="text-amber-600 font-extrabold text-sm"><?php echo $leave_quota['sick']['used'] ?? 0; ?></span>
                        <span class="text-slate-400 font-normal text-[10px]">/ <?php echo $leave_quota['sick']['max'] ?? 30; ?> วัน</span>
                    </p>
                </div>

                <!-- 3. พักร้อน -->
                <div class="bg-white border border-slate-100 p-2.5 rounded-xl shadow-2xs space-y-0.5">
                    <p class="text-[10px] text-slate-500 font-semibold">🏖️ พักร้อน</p>
                    <p class="font-black text-slate-800 text-xs">
                        <span class="text-emerald-600 font-extrabold text-sm"><?php echo $leave_quota['vacation']['used'] ?? 0; ?></span>
                        <span class="text-slate-400 font-normal text-[10px]">/ <?php echo $leave_quota['vacation']['max'] ?? 6; ?> วัน</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- 🚫 กรณีไม่มีประวัติการยื่นลา -->
        <div id="m_status_no_data" class="hidden py-8 text-center space-y-2">
            <span class="text-4xl block">🍃</span>
            <p class="font-bold text-slate-700 text-xs">ยังไม่มีประวัติการยื่นคำขอลาในปีนี้</p>
            <p class="text-[11px] text-slate-400">เมื่อคุณยื่นคำขอลา ระบบจะแสดงผังขั้นตอนการอนุมัติที่นี่</p>
        </div>

        <!-- 📄 เนื้อหาหลัก (กรณีมีข้อมูลการลา) -->
        <div id="m_status_content" class="space-y-3.5">
            
            <!-- 🎨 Rounded Dropdown เรียงจากยื่นล่าสุดขึ้นก่อน -->
            <div id="m_status_select_wrapper" class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">เลือกรายการคำขอลา (ส่งล่าสุดก่อน):</label>
                <?php 
                    $first_label = !empty($leave_dropdown_options) ? $leave_dropdown_options[0]['name'] : 'เลือกรายการใบลา';
                    renderRoundedDropdown('leave_status_select', 'selected_leave_index', $first_label, $leave_dropdown_options ?? [], 0);
                ?>
            </div>

            <!-- 🖼️ รูปภาพหลักฐาน/เอกสารแนบ -->
            <div id="m_status_img_box" class="hidden relative w-full h-40 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 shadow-inner group">
                <img id="m_status_img" src="" alt="เอกสารประกอบ" 
                     class="w-full h-full object-cover cursor-pointer group-hover:scale-105 transition-transform duration-300"
                     onclick="openStatusImageLightbox(this.src)"
                     title="คลิกเพื่อดูรูปขนาดใหญ่">
                <span class="absolute bottom-2.5 right-2.5 bg-black/60 text-white text-[10px] font-medium px-2.5 py-1 rounded-xl backdrop-blur-xs pointer-events-none shadow-md">
                    🔍 คลิกเพื่อขยาย
                </span>
            </div>

            <!-- 📋 กล่องสรุปรายละเอียดคำขอ -->
            <div class="bg-slate-50/80 p-3.5 rounded-2xl border border-slate-100 space-y-2 text-xs">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500 font-medium">ประเภทการลา:</span>
                    <span class="font-extrabold text-blue-600" id="m_status_type">-</span>
                </div>
                <div class="flex justify-between items-center border-t border-slate-200/60 pt-1.5">
                    <span class="text-slate-500 font-medium">วันที่ขอลา:</span>
                    <span class="font-bold text-slate-700" id="m_status_date_range">-</span>
                </div>
                <div class="flex justify-between items-center border-t border-slate-200/60 pt-1.5">
                    <span class="text-slate-500 font-medium">ระยะเวลา:</span>
                    <span class="font-bold text-slate-700" id="m_status_days">-</span>
                </div>
                <div class="flex justify-between items-center border-t border-slate-200/60 pt-1.5">
                    <span class="text-slate-500 font-medium">วันที่ยื่นเรื่อง:</span>
                    <span class="font-semibold text-slate-600" id="m_status_sub_date">-</span>
                </div>
                <div class="border-t border-slate-200/60 pt-1.5 space-y-0.5">
                    <span class="text-slate-500 font-medium block">เหตุผลการลา:</span>
                    <p class="font-semibold text-slate-800 text-[11px] leading-relaxed bg-white p-2 rounded-xl border border-slate-200/60 shadow-3xs" id="m_status_reason">-</p>
                </div>
            </div>

            <!-- 🔄 TIMELINE WORKFLOW STEPPER -->
            <div class="space-y-2 pt-1">
                <p class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider flex items-center justify-between">
                    <span>ผังขั้นตอนการพิจารณา</span>
                    <span class="text-[10px] font-normal text-blue-600">4 ขั้นตอน</span>
                </p>
                
                <div class="relative pl-6 space-y-4 pt-1">
                    
                    <!-- 🟢 STEP 1: ยื่นคำขอลา -->
                    <div class="relative flex items-start gap-3">
                        <div id="line_step_1" class="absolute -left-[17px] top-6 bottom-0 w-0.5 bg-emerald-500 rounded-full"></div>
                        <div class="absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white shrink-0">
                            ✓
                        </div>
                        <div class="min-w-0 flex-1 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="font-extrabold text-slate-800">1. ยื่นคำขอลา</span>
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200">✅ สำเร็จ</span>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-0.5">ส่งคำขอเข้าสู่ระบบเรียบร้อยแล้ว</p>
                        </div>
                    </div>

                    <!-- 👔 STEP 2: หัวหน้าแผนก -->
                    <div class="relative flex items-start gap-3">
                        <div id="line_step_2" class="absolute -left-[17px] top-6 bottom-0 w-0.5 bg-slate-200 rounded-full transition-colors"></div>
                        <div id="icon_step_2" class="absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-slate-200 text-slate-600 font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white shrink-0 transition-all">
                            2
                        </div>
                        <div class="min-w-0 flex-1 text-xs">
                            <div class="flex justify-between items-center gap-1">
                                <div class="truncate">
                                    <span class="font-extrabold text-slate-800">2. หัวหน้าแผนก</span>
                                    <span id="m_status_head_name" class="text-[11px] font-bold text-blue-600 block truncate"></span>
                                </div>
                                <span id="badge_step_2" class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-slate-100 text-slate-500 shrink-0">-</span>
                            </div>
                            <p id="text_step_2" class="text-[10px] text-slate-400 mt-0.5">รอหัวหน้าแผนกตรวจสอบ</p>
                        </div>
                    </div>

                    <!-- 💼 STEP 3: ฝ่ายบุคคล (HR) -->
                    <div class="relative flex items-start gap-3">
                        <div id="line_step_3" class="absolute -left-[17px] top-6 bottom-0 w-0.5 bg-slate-200 rounded-full transition-colors"></div>
                        <div id="icon_step_3" class="absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-slate-200 text-slate-600 font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white shrink-0 transition-all">
                            3
                        </div>
                        <div class="min-w-0 flex-1 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="font-extrabold text-slate-800">3. ฝ่ายบุคคล (HR)</span>
                                <span id="badge_step_3" class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-slate-100 text-slate-500">-</span>
                            </div>
                            <p id="text_step_3" class="text-[10px] text-slate-400 mt-0.5">รอการอนุมัติจากขั้นที่ 2</p>
                        </div>
                    </div>

                    <!-- 🎉 STEP 4: สรุปผลการอนุมัติ -->
                    <div class="relative flex items-start gap-3">
                        <div id="icon_step_4" class="absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-slate-200 text-slate-600 font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white shrink-0 transition-all">
                            4
                        </div>
                        <div class="min-w-0 flex-1 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="font-extrabold text-slate-800">4. สรุปผลการอนุมัติ</span>
                                <span id="badge_step_4" class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-slate-100 text-slate-500">-</span>
                            </div>
                            <p id="text_step_4" class="text-[10px] text-slate-400 mt-0.5">รอกระบวนการเสร็จสิ้น</p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- 🔴 กล่องแสดงเหตุผลหากปฏิเสธคำขอ -->
            <div id="m_status_reject_box" class="hidden bg-rose-50 border border-rose-200 p-3 rounded-2xl text-xs space-y-1">
                <span class="text-[10px] font-bold text-rose-500 uppercase">เหตุผลที่ไม่ให่อนุมัติ:</span>
                <p class="font-bold text-rose-700 leading-relaxed" id="m_status_reject_reason">-</p>
            </div>

        </div>


    </div>
</div>

<!-- 🔍 LIGHTBOX แสดงรูปภาพขนาดใหญ่ -->
<div id="statusImageLightbox" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[60] flex items-center justify-center p-4 cursor-zoom-out" onclick="closeStatusImageLightbox()">
    <div class="relative max-w-lg max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeStatusImageLightbox()" class="absolute -top-11 right-0 text-white/80 hover:text-white text-lg font-bold bg-white/10 hover:bg-white/20 w-9 h-9 rounded-full flex items-center justify-center transition-all cursor-pointer">✕</button>
        <img id="status_lightbox_img" src="" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain border border-white/10">
    </div>
</div>

<script>
    let globalLeavesList = [];

    function openLeaveStatusModal(leavesList = []) {
        const modal = document.getElementById('leaveStatusModal');
        if (!modal) return;

        globalLeavesList = Array.isArray(leavesList) ? leavesList : [];

        const noDataBox = document.getElementById('m_status_no_data');
        const contentBox = document.getElementById('m_status_content');

        if (!globalLeavesList || globalLeavesList.length === 0) {
            if (noDataBox) noDataBox.classList.remove('hidden');
            if (contentBox) contentBox.classList.add('hidden');
            modal.classList.remove('hidden');
            return;
        }

        if (noDataBox) noDataBox.classList.add('hidden');
        if (contentBox) contentBox.classList.remove('hidden');

        // แสดงรายการยื่นล่าสุดก่อนเสมอ (Index 0)
        renderLeaveDetail(globalLeavesList[0]);
        modal.classList.remove('hidden');
    }

    function switchLeaveItem(index) {
        const idx = parseInt(index);
        if (globalLeavesList[idx]) {
            renderLeaveDetail(globalLeavesList[idx]);
        }
    }

    function renderLeaveDetail(data) {
        if (!data) return;

        document.getElementById('m_status_type').textContent = data.leaveType || 'ลาหยุด';
        document.getElementById('m_status_date_range').textContent = data.dateRange || '-';
        document.getElementById('m_status_days').textContent = data.totalDays || '-';
        document.getElementById('m_status_sub_date').textContent = data.subDate || '-';
        document.getElementById('m_status_reason').textContent = data.reason || 'ไม่ได้ระบุเหตุผล';

        const headNameElem = document.getElementById('m_status_head_name');
        if (headNameElem) {
            headNameElem.textContent = (data.headName && data.headName.trim() !== '' && data.headName !== 'null') 
                ? '(' + data.headName + ')' 
                : '';
        }

        const imgBox = document.getElementById('m_status_img_box');
        const imgElem = document.getElementById('m_status_img');
        if (data.attachment && data.attachment.trim() !== '') {
            imgElem.src = data.attachment;
            imgBox.classList.remove('hidden');
        } else {
            imgBox.classList.add('hidden');
        }

        const l2 = document.getElementById('line_step_2');
        const l3 = document.getElementById('line_step_3');

        const i2 = document.getElementById('icon_step_2');
        const i3 = document.getElementById('icon_step_3');
        const i4 = document.getElementById('icon_step_4');

        const b2 = document.getElementById('badge_step_2');
        const b3 = document.getElementById('badge_step_3');
        const b4 = document.getElementById('badge_step_4');

        const t2 = document.getElementById('text_step_2');
        const t3 = document.getElementById('text_step_3');
        const t4 = document.getElementById('text_step_4');

        const rejectBox = document.getElementById('m_status_reject_box');
        const rejectReason = document.getElementById('m_status_reject_reason');

        rejectBox.classList.add('hidden');

        const status = data.status || 'pending_head';

        if (status === 'pending_head' || status === 'pending') {
            l2.className = "absolute -left-[17px] top-6 bottom-0 w-0.5 bg-slate-200 rounded-full";
            l3.className = "absolute -left-[17px] top-6 bottom-0 w-0.5 bg-slate-200 rounded-full";

            i2.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-amber-500 text-white font-black flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white animate-pulse";
            i2.textContent = "2";
            b2.textContent = "⏳ รอหัวหน้าอนุมัติ";
            b2.className = "px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-200";
            t2.textContent = "อยู่ในระหว่างหัวหน้าแผนกพิจารณา";

            i3.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i3.textContent = "3";
            b3.textContent = "⏳ รอกระบวนการก่อนหน้า";
            b3.className = "px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-500";
            t3.textContent = "รอการอนุมัติจากขั้นที่ 2";

            i4.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i4.textContent = "4";
            b4.textContent = "⏳ รอดำเนินการ";
            b4.className = "px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-500";
            t4.textContent = "รอกระบวนการเสร็จสิ้น";

        } else if (status === 'pending_hr') {
            l2.className = "absolute -left-[17px] top-6 bottom-0 w-0.5 bg-emerald-500 rounded-full";
            l3.className = "absolute -left-[17px] top-6 bottom-0 w-0.5 bg-slate-200 rounded-full";

            i2.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i2.textContent = "✓";
            b2.textContent = "✅ หัวหน้าอนุมัติแล้ว";
            b2.className = "px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200";
            t2.textContent = "ผ่านการพิจารณาจากหัวหน้าแล้ว";

            i3.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-sky-500 text-white font-black flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white animate-pulse";
            i3.textContent = "3";
            b3.textContent = "⏳ รอ HR อนุมัติ";
            b3.className = "px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-sky-100 text-sky-800 border border-sky-200";
            t3.textContent = "กำลังรอ HR ตรวจสอบขั้นสุดท้าย";

            i4.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i4.textContent = "4";
            b4.textContent = "⏳ รอดำเนินการ";
            b4.className = "px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-500";
            t4.textContent = "รอกระบวนการเสร็จสิ้น";

        } else if (status === 'approved') {
            l2.className = "absolute -left-[17px] top-6 bottom-0 w-0.5 bg-emerald-500 rounded-full";
            l3.className = "absolute -left-[17px] top-6 bottom-0 w-0.5 bg-emerald-500 rounded-full";

            i2.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i2.textContent = "✓";
            b2.textContent = "✅ ผ่านแล้ว";
            b2.className = "px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200";

            i3.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i3.textContent = "✓";
            b3.textContent = "✅ HR อนุมัติแล้ว";
            b3.className = "px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200";

            i4.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i4.textContent = "✓";
            b4.textContent = "🎉 อนุมัติสมบูรณ์";
            b4.className = "px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300";
            t4.textContent = "ตัดโควตาวันลาและบันทึกข้อมูลเรียบร้อย";

        } else if (status === 'rejected') {
            l2.className = "absolute -left-[17px] top-6 bottom-0 w-0.5 bg-rose-300 rounded-full";
            l3.className = "absolute -left-[17px] top-6 bottom-0 w-0.5 bg-slate-200 rounded-full";

            i2.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-rose-500 text-white font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i2.textContent = "✕";
            b2.textContent = "❌ ไม่อนุมัติ";
            b2.className = "px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-rose-100 text-rose-800 border border-rose-200";
            t2.textContent = "คำขอถูกปฏิเสธในขั้นตอนนี้";

            i3.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-slate-200 text-slate-400 font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i3.textContent = "3";
            b3.textContent = "❌ ยุติกระบวนการ";
            b3.className = "px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-500";
            t3.textContent = "คำขอไม่ผ่านการพิจารณา";

            i4.className = "absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-slate-200 text-slate-400 font-bold flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white";
            i4.textContent = "4";
            b4.textContent = "❌ ยกเลิก";
            b4.className = "px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-500";

            if (data.rejectReason) {
                rejectReason.textContent = data.rejectReason;
                rejectBox.classList.remove('hidden');
            }
        }
    }

    function closeLeaveStatusModal() {
        const modal = document.getElementById('leaveStatusModal');
        if (modal) modal.classList.add('hidden');
    }

    function openStatusImageLightbox(src) {
        if (!src) return;
        const lightbox = document.getElementById('statusImageLightbox');
        const targetImg = document.getElementById('status_lightbox_img');
        if (lightbox && targetImg) {
            targetImg.src = src;
            lightbox.classList.remove('hidden');
        }
    }

    function closeStatusImageLightbox() {
        const lightbox = document.getElementById('statusImageLightbox');
        if (lightbox) lightbox.classList.add('hidden');
    }
</script>