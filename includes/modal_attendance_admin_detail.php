<!-- 📑 POPUP MODAL แสดงรายละเอียดสแกนเข้า - ออกงาน (Dynamic Border Status) -->
<div id="attendanceAdminDetailModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-3xl w-full p-6 shadow-2xl border border-slate-100 space-y-5 relative animate-in fade-in zoom-in duration-150 max-h-[92vh] overflow-y-auto">
        
        <!-- Header & ปุ่มปิด Modal มุมขวาบน -->
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <span>⏰</span> รายละเอียดหลักฐานการสแกนเข้า - ออกงาน
                </h3>
                <p class="text-slate-400 text-xs mt-0.5" id="att_admin_subtitle">ประจำวันที่ -</p>
            </div>
            <button type="button" onclick="closeAttendanceAdminDetailModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <!-- 👤 ข้อมูลพนักงานแถบบน -->
        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100 flex justify-between items-center gap-3 text-xs">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-blue-100 border border-blue-200 overflow-hidden flex items-center justify-center font-bold text-blue-600 shrink-0">
                    <img id="att_admin_avatar" src="" class="w-full h-full object-cover hidden" onerror="this.classList.add('hidden');">
                    <span id="att_admin_avatar_fallback" class="text-sm font-black">LT</span>
                </div>
                <div class="min-w-0">
                    <h4 class="font-extrabold text-slate-900 text-sm leading-tight truncate" id="att_admin_fullname">-</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 truncate" id="att_admin_dept_branch">-</p>
                </div>
            </div>
            <div class="text-right shrink-0">
                <span class="bg-blue-600 text-white px-3.5 py-1.5 rounded-xl text-xs font-black shadow-md shadow-blue-500/20 inline-block tracking-wider" id="att_admin_emp_code">ID: -</span>
                <p class="text-[11px] font-bold text-slate-600 mt-1.5 whitespace-nowrap" id="att_admin_shift">กะงาน: -</p>
            </div>
        </div>

        <!-- 🖼️ 2 กล่องการ์ดหลักฐานการสแกน -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- 🔵 กล่องซ้าย: สแกนเข้างาน (CHECK-IN) -->
            <div id="att_admin_in_card" class="bg-white border-2 rounded-2xl p-4 space-y-3 flex flex-col justify-between shadow-xs transition-colors duration-300">
                <div>
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2.5 mb-3">
                        <span class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                            <span id="att_admin_in_dot" class="w-2.5 h-2.5 rounded-full bg-slate-400 animate-pulse"></span>
                            สแกนเข้างาน (CHECK-IN)
                        </span>
                    </div>

                    <!-- 🎯 กรอบรูปถ่ายขยายเป็น h-80 -->
                    <div class="relative w-full h-80 bg-slate-50 rounded-xl overflow-hidden border border-slate-200 shadow-inner flex items-center justify-center">
                        <img id="att_admin_in_img" src="" alt="" 
                             class="w-full h-full object-cover hidden cursor-pointer hover:scale-105 transition-transform duration-300"
                             onclick="openAttendanceAdminImageLightbox(this.src)" 
                             onerror="this.classList.add('hidden'); document.getElementById('att_admin_in_no_img').classList.remove('hidden');"
                             title="คลิกดูรูปใหญ่">
                        <div id="att_admin_in_no_img" class="flex flex-col items-center text-slate-400 gap-1 text-center p-2">
                            <span class="text-3xl">📷</span>
                            <span class="text-xs font-semibold">ไม่มีรูปถ่าย / ยังไม่ได้สแกนเข้า</span>
                        </div>
                    </div>
                </div>

                <!-- รายละเอียดเวลา + สาขา + สถานะ -->
                <div class="bg-slate-50/80 p-3 rounded-xl border border-slate-200/80 space-y-2 text-xs shadow-3xs">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-medium">เวลาสแกนเข้า:</span>
                        <span class="font-black text-slate-800 text-sm" id="att_admin_in_time">-</span>
                    </div>
                    <div class="flex justify-between items-center border-t border-slate-200/65 pt-1.5">
                        <span class="text-slate-500 font-medium">สาขาที่สแกน:</span>
                        <span class="font-bold text-slate-700 truncate max-w-[150px]" id="att_admin_in_branch">📍 -</span>
                    </div>
                    <div class="flex justify-between items-center border-t border-slate-200/65 pt-1.5">
                        <span class="text-slate-500 font-medium">สถานะ:</span>
                        <span id="att_admin_in_status_badge" class="px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold">-</span>
                    </div>
                </div>
            </div>

            <!-- ⚫ กล่องขวา: สแกนออกงาน (CHECK-OUT) -->
            <div id="att_admin_out_card" class="bg-white border-2 border-dashed border-slate-300 rounded-2xl p-4 space-y-3 flex flex-col justify-between shadow-xs transition-colors duration-300">
                <div>
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2.5 mb-3">
                        <span class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                            <span id="att_admin_out_dot" class="w-2.5 h-2.5 rounded-full bg-slate-400 animate-pulse"></span>
                            สแกนออกงาน (CHECK-OUT)
                        </span>
                    </div>

                    <!-- 🎯 กรอบรูปถ่ายขยายเป็น h-80 -->
                    <div class="relative w-full h-80 bg-slate-50 rounded-xl overflow-hidden border border-slate-200 shadow-inner flex items-center justify-center">
                        <img id="att_admin_out_img" src="" alt="" 
                             class="w-full h-full object-cover hidden cursor-pointer hover:scale-105 transition-transform duration-300"
                             onclick="openAttendanceAdminImageLightbox(this.src)" 
                             onerror="this.classList.add('hidden'); document.getElementById('att_admin_out_no_img').classList.remove('hidden');"
                             title="คลิกดูรูปใหญ่">
                        <div id="att_admin_out_no_img" class="flex flex-col items-center text-slate-400 gap-1 text-center p-2">
                            <span class="text-3xl">📷</span>
                            <span class="text-xs font-semibold">ไม่มีรูปถ่าย / ยังไม่ได้สแกนออก</span>
                        </div>
                    </div>
                </div>

                <!-- รายละเอียดเวลา + สาขา + สถานะ -->
                <div class="bg-slate-50/80 p-3 rounded-xl border border-slate-200/80 space-y-2 text-xs shadow-3xs">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-medium">เวลาสแกนออก:</span>
                        <span class="font-black text-slate-800 text-sm" id="att_admin_out_time">-</span>
                    </div>
                    <div class="flex justify-between items-center border-t border-slate-200/65 pt-1.5">
                        <span class="text-slate-500 font-medium">สาขาที่สแกน:</span>
                        <span class="font-bold text-slate-700 truncate max-w-[150px]" id="att_admin_out_branch">📍 -</span>
                    </div>
                    <div class="flex justify-between items-center border-t border-slate-200/65 pt-1.5">
                        <span class="text-slate-500 font-medium">สถานะ:</span>
                        <span id="att_admin_out_status_badge" class="px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold">-</span>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- 🔍 Lightbox ขยายรูปสแกน -->
<div id="attendanceAdminImageLightbox" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[60] flex items-center justify-center p-4 cursor-zoom-out" onclick="closeAttendanceAdminImageLightbox()">
    <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeAttendanceAdminImageLightbox()" class="absolute -top-11 right-0 text-white/80 hover:text-white text-lg font-bold bg-white/10 hover:bg-white/20 w-9 h-9 rounded-full flex items-center justify-center transition-all cursor-pointer">✕</button>
        <img id="att_admin_lightbox_img" src="" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain border border-white/10">
    </div>
</div>

<script>
    function openAttendanceAdminDetailModal(data) {
        const modal = document.getElementById('attendanceAdminDetailModal');
        if (!modal) return;

        const setText = (id, val, defaultText = '-') => {
            const el = modal.querySelector('#' + id);
            if (el) el.textContent = (val && val !== 'null' && val !== 'undefined') ? val : defaultText;
        };

        // ข้อมูลหลัก
        setText('att_admin_subtitle', 'ประจำวันที่ ' + (data.scanDate || '-'));
        setText('att_admin_fullname', data.fullname);
        setText('att_admin_emp_code', 'ID: ' + (data.empCode || '-'));
        setText('att_admin_dept_branch', (data.deptName || 'ไม่ระบุแผนก') + ' • ' + (data.branchName || 'สำนักงานใหญ่'));
        setText('att_admin_shift', 'กะงาน: ' + (data.shiftName || 'กะปกติ') + ' (' + (data.shiftStart || '08:30') + ')');

        // รูปโปรไฟล์
        const avatarImg = modal.querySelector('#att_admin_avatar');
        const avatarFallback = modal.querySelector('#att_admin_avatar_fallback');
        if (data.avatarUrl && data.avatarUrl.trim() !== '' && data.avatarUrl !== 'null') {
            avatarImg.src = data.avatarUrl;
            avatarImg.classList.remove('hidden');
            if (avatarFallback) avatarFallback.classList.add('hidden');
        } else {
            avatarImg.classList.add('hidden');
            if (avatarFallback) {
                avatarFallback.textContent = data.fullname ? data.fullname.charAt(0) : 'LT';
                avatarFallback.classList.remove('hidden');
            }
        }

        // 🟢 ฝั่งซ้าย: ข้อมูลสแกนเข้างาน
        setText('att_admin_in_time', data.inTime || '-');
        setText('att_admin_in_branch', '📍 ' + (data.branchName || 'สำนักงานใหญ่'));
        
        const inBadge = modal.querySelector('#att_admin_in_status_badge');
        const inCard = modal.querySelector('#att_admin_in_card');
        const inDot = modal.querySelector('#att_admin_in_dot');
        
        // Reset Card Style
        inCard.className = "bg-white border-2 rounded-2xl p-4 space-y-3 flex flex-col justify-between shadow-xs transition-colors duration-300";

        if (inBadge) {
            if (data.status === 'early') { // ฟ้า
                inBadge.textContent = '🔵 เข้าก่อนเวลา';
                inBadge.className = 'px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold bg-sky-100 text-sky-800 border border-sky-300';
                inCard.classList.add('border-sky-400', 'shadow-sky-500/10');
                inDot.className = 'w-2.5 h-2.5 rounded-full bg-sky-500 animate-pulse';
            } else if (data.status === 'ontime') { // เขียว
                inBadge.textContent = '🟢 มาตรงเวลา';
                inBadge.className = 'px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300';
                inCard.classList.add('border-emerald-400', 'shadow-emerald-500/10');
                inDot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
            } else if (data.status === 'late') { // ส้มเหลือง
                inBadge.textContent = '🟡 เข้างานสาย';
                inBadge.className = 'px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300';
                inCard.classList.add('border-amber-400', 'shadow-amber-500/10');
                inDot.className = 'w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse';
            } else { // แดง (ขาดสแกน)
                inBadge.textContent = '🔴 ขาดสแกนเข้า';
                inBadge.className = 'px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold bg-rose-100 text-rose-800 border border-rose-300';
                inCard.classList.add('border-rose-400', 'shadow-rose-500/10');
                inDot.className = 'w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse';
            }
        }

        const inImg = modal.querySelector('#att_admin_in_img');
        const inNoImg = modal.querySelector('#att_admin_in_no_img');
        if (data.inImgUrl && data.inImgUrl.trim() !== '' && !data.inImgUrl.endsWith('/uploads/scan-in/') && data.inImgUrl !== 'null') {
            inImg.src = data.inImgUrl;
            inImg.classList.remove('hidden');
            if (inNoImg) inNoImg.classList.add('hidden');
        } else {
            inImg.src = '';
            inImg.classList.add('hidden');
            if (inNoImg) inNoImg.classList.remove('hidden');
        }

        // ⚫ ฝั่งขวา: ข้อมูลสแกนออกงาน
        setText('att_admin_out_time', data.outTime || '-');
        setText('att_admin_out_branch', '📍 ' + (data.branchName || 'สำนักงานใหญ่'));

        const outBadge = modal.querySelector('#att_admin_out_status_badge');
        const outCard = modal.querySelector('#att_admin_out_card');
        const outDot = modal.querySelector('#att_admin_out_dot');
        
        // Reset Card Style
        outCard.className = "bg-white border-2 rounded-2xl p-4 space-y-3 flex flex-col justify-between shadow-xs transition-colors duration-300";

        if (outBadge) {
            if (data.outTime && data.outTime !== '-') {
                outBadge.textContent = '✅ สแกนออกงานแล้ว';
                outBadge.className = 'px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold bg-slate-100 text-slate-800 border border-slate-400';
                outCard.classList.add('border-slate-500', 'shadow-slate-500/10'); // ออกงานแล้วเป็นกรอบสีเทาเข้ม
                outDot.className = 'w-2.5 h-2.5 rounded-full bg-slate-600 animate-pulse';
            } else {
                outBadge.textContent = '⏳ ยังไม่สแกนออก';
                outBadge.className = 'px-2.5 py-0.5 rounded-lg text-[11px] font-extrabold bg-slate-50 text-slate-400 border border-slate-200';
                outCard.classList.add('border-dashed', 'border-slate-300'); // ยังไม่ออกเป็นกรอบเส้นประ
                outDot.className = 'w-2.5 h-2.5 rounded-full bg-slate-300';
            }
        }

        const outImg = modal.querySelector('#att_admin_out_img');
        const outNoImg = modal.querySelector('#att_admin_out_no_img');
        if (data.outImgUrl && data.outImgUrl.trim() !== '' && !data.outImgUrl.endsWith('/uploads/scan-out/') && data.outImgUrl !== 'null') {
            outImg.src = data.outImgUrl;
            outImg.classList.remove('hidden');
            if (outNoImg) outNoImg.classList.add('hidden');
        } else {
            outImg.src = '';
            outImg.classList.add('hidden');
            if (outNoImg) outNoImg.classList.remove('hidden');
        }

        modal.classList.remove('hidden');
    }

    function closeAttendanceAdminDetailModal() {
        const modal = document.getElementById('attendanceAdminDetailModal');
        if (modal) modal.classList.add('hidden');
    }

    function openAttendanceAdminImageLightbox(src) {
        if (!src) return;
        const lightbox = document.getElementById('attendanceAdminImageLightbox');
        const img = document.getElementById('att_admin_lightbox_img');
        if (lightbox && img) {
            img.src = src;
            lightbox.classList.remove('hidden');
        }
    }

    function closeAttendanceAdminImageLightbox() {
        const lightbox = document.getElementById('attendanceAdminImageLightbox');
        if (lightbox) lightbox.classList.add('hidden');
    }
</script>