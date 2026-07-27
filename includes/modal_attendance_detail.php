<!-- 📌 Popup แสดงรายละเอียดการสแกนลงงาน พร้อมรูปสแกนและสถานะ -->
<div id="attendanceDetailModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 relative animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
        
        <!-- หัวข้อ Popup & ปุ่มปิด -->
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <span>📍</span> รายละเอียดการบันทึกเวลา
            </h3>
            <button type="button" onclick="closeAttendanceModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <!-- 🖼️ กรอบแสดงรูปสแกนใบหน้า (รองรับการกดคลิกขยายรูปขนาดเต็ม) -->
        <div class="relative w-full h-80 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200/80 flex items-center justify-center shadow-inner group">
            <img id="m_img" src="" alt="รูปสแกนใบหน้า" 
                class="w-full h-full object-cover hidden cursor-pointer group-hover:scale-105 transition-transform duration-300" 
                onclick="openImagePreview(this.src)"
                title="คลิกเพื่อดูรูปขนาดใหญ่"
                onerror="this.classList.add('hidden'); document.getElementById('m_no_img').classList.remove('hidden'); document.getElementById('m_img_hint').classList.add('hidden');">
            
            <div id="m_no_img" class="text-center p-4 text-slate-400 space-y-1">
                <span class="text-3xl">📷</span>
                <p class="text-xs font-semibold">ไม่มีรูปภาพสแกนใบหน้า</p>
            </div>

            <!-- ป้ายแนะนำให้กดขยายรูป -->
            <span id="m_img_hint" class="hidden absolute bottom-3 right-3 bg-black/60 text-white text-[10px] font-medium px-2.5 py-1 rounded-xl backdrop-blur-xs pointer-events-none shadow-md">
                🔍 คลิกเพื่อขยาย
            </span>
        </div>

        <!-- เนื้อหาข้อมูลรายละเอียดพนักงาน -->
        <div class="space-y-2.5">
            <!-- ซอกข้อมูลชื่อพนักงาน -->
            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                <h4 class="text-sm font-bold text-slate-900 leading-tight" id="m_fullname">-</h4>
                <p class="text-xs font-semibold text-slate-500 mt-0.5" id="m_emp_code">รหัส: -</p>
            </div>

            <!-- แถวที่ 1: ประเภทสแกน & เวลาจริง -->
            <div class="grid grid-cols-2 gap-2.5 text-xs">
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">ประเภทการสแกน</span>
                    <p class="font-extrabold" id="m_type">-</p>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">เวลาสแกนจริง</span>
                    <p class="font-extrabold text-slate-800" id="m_time">-</p>
                </div>
            </div>

            <!-- แถวที่ 2: พิกัดสาขา & ช่องสถานะการลงเวลา -->
            <div class="grid grid-cols-2 gap-2.5 text-xs">
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-0.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">พิกัดสถานที่ / สาขา</span>
                    <p class="font-bold text-slate-700 truncate" id="m_branch">📍 -</p>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">สถานะการลงเวลา</span>
                    <div>
                        <span id="m_status_badge" class="inline-block px-2.5 py-0.5 rounded-lg text-xs font-extrabold">
                            -
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ปุ่มปิดด้านล่าง -->
        <button type="button" onclick="closeAttendanceModal()" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors cursor-pointer">
            ปิดหน้าต่าง
        </button>
    </div>
</div>

<!-- 🔍 Fullscreen Image Lightbox Modal -->
<div id="attendanceImageModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[60] flex items-center justify-center p-4 cursor-zoom-out" onclick="closeImagePreview()">
    <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeImagePreview()" class="absolute -top-11 right-0 text-white/80 hover:text-white text-lg font-bold bg-white/10 hover:bg-white/20 w-9 h-9 rounded-full flex items-center justify-center transition-all cursor-pointer">✕</button>
        <img id="attendance_preview_img" src="" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain border border-white/10">
    </div>
</div>

<script>
    function openAttendanceModal(fullname, empCode, type, time, branch, imageUrl, statusType, statusText) {
        document.getElementById('m_fullname').textContent = fullname;
        document.getElementById('m_emp_code').textContent = 'รหัส: ' + empCode;
        const typeElem = document.getElementById('m_type');
        if (type === 'check_in') {
            typeElem.textContent = '📥 สแกนเข้างาน (IN)';
            typeElem.className = 'font-extrabold text-emerald-600';
        } else {
            typeElem.textContent = '📤 สแกนออกงาน (OUT)';
            typeElem.className = 'font-extrabold text-orange-600';
        }
        document.getElementById('m_time').textContent = time + ' น.';
        document.getElementById('m_branch').textContent = '📍 ' + (branch || 'นอกสถานที่');

        // จัดการแสดงผลรูปภาพสแกน
        const imgElem = document.getElementById('m_img');
        const noImgElem = document.getElementById('m_no_img');
        const hintElem = document.getElementById('m_img_hint');

        if (imageUrl && imageUrl.trim() !== '') {
            imgElem.src = imageUrl;
            imgElem.classList.remove('hidden');
            noImgElem.classList.add('hidden');
            if (hintElem) hintElem.classList.remove('hidden');
        } else {
            imgElem.src = '';
            imgElem.classList.add('hidden');
            noImgElem.classList.remove('hidden');
            if (hintElem) hintElem.classList.add('hidden');
        }

        // จัดการป้ายสถานะ
        const badge = document.getElementById('m_status_badge');
        badge.textContent = statusText;

        if (statusType === 'early') {
            badge.className = "inline-block px-2.5 py-0.5 rounded-lg text-xs font-extrabold bg-blue-100 text-blue-700 border border-blue-200";
        } else if (statusType === 'ontime') {
            badge.className = "inline-block px-2.5 py-0.5 rounded-lg text-xs font-extrabold bg-emerald-100 text-emerald-700 border border-emerald-200";
        } else if (statusType === 'late') {
            badge.className = "inline-block px-2.5 py-0.5 rounded-lg text-xs font-extrabold bg-rose-100 text-rose-700 border border-rose-200";
        } else {
            badge.className = "inline-block px-2.5 py-0.5 rounded-lg text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200";
        }

        document.getElementById('attendanceDetailModal').classList.remove('hidden');
    }

    function closeAttendanceModal() {
        document.getElementById('attendanceDetailModal').classList.add('hidden');
    }

    // ฟังก์ชันเปิด-ปิด ดูรูปขนาดใหญ่
    function openImagePreview(src) {
        if (!src) return;
        const targetImg = document.getElementById('attendance_preview_img');
        const modal = document.getElementById('attendanceImageModal');
        if (targetImg && modal) {
            targetImg.src = src;
            modal.classList.remove('hidden');
        }
    }

    function closeImagePreview() {
        const modal = document.getElementById('attendanceImageModal');
        if (modal) modal.classList.add('hidden');
    }
</script>