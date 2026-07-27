<!-- 🔤 โหลดฟอนต์ Noto Sans Thai สำหรับ Pop-up Modal -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- 📥 กล่องป็อปอัปหลักฐานแสดงข้อมูลสแกนเวลางาน -->
<div id="proof-modal-overlay" style="font-family: 'Noto Sans Thai', sans-serif;" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-md items-center justify-center z-50 p-4 transition-all opacity-0">
    <div class="bg-white/90 backdrop-blur-2xl border border-white/80 p-5 md:p-6 rounded-3xl shadow-2xl max-w-md w-full space-y-4 transform scale-95 transition-transform max-h-[90vh] overflow-y-auto">
        
        <!-- Header Bar -->
        <div class="flex justify-between items-center border-b border-slate-200/60 pb-2.5">
            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                📸 เอกสารหลักฐานอนุมัติเวลา
            </h3>
            <button onclick="closeProofModal()" class="w-8 h-8 bg-slate-100 hover:bg-slate-200/80 text-slate-400 hover:text-slate-600 rounded-full flex items-center justify-center transition-all cursor-pointer text-sm font-bold">
                ✕
            </button>
        </div>

        <!-- 🖼️ กรอบรูปภาพทรงสี่เหลี่ยมขอบมน -->
        <div class="w-full h-56 sm:h-64 rounded-2xl bg-slate-100 border-2 border-slate-200/60 shadow-inner overflow-hidden relative flex items-center justify-center">
            <!-- รูปภาพจริง -->
            <img id="modal-photo" src="" alt="Proof Photo" class="w-full h-full object-cover hidden">
            
            <!-- กล่องแสดงกรณีไม่พบรูปถ่ายหลักฐานในระบบ -->
            <div id="modal-photo-missing" class="hidden flex flex-col items-center justify-center text-slate-400 p-4 text-center space-y-2">
                <div class="w-12 h-12 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500 text-xl font-bold">
                    ⚠️
                </div>
                <p class="text-xs font-semibold text-slate-600">ไม่พบไฟล์รูปถ่ายหลักฐาน</p>
                <p class="text-[10px] text-slate-400">รายการนี้ไม่มีไฟล์รูปภาพในระบบ หรือไฟล์อาจถูกลบ</p>
            </div>
        </div>

        <!-- รายละเอียดข้อมูลการสแกน -->
        <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-4 text-xs space-y-2.5 font-medium text-slate-700">
            <div class="flex justify-between items-center">
                <span class="text-slate-600 font-bold">วันที่สแกน:</span>
                <span id="modal-date" class="text-slate-900 font-bold">---</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-600 font-bold">เวลาสแกน:</span>
                <!-- 🎯 เอา font-mono ออก เพื่อให้เป็นฟอนต์ Noto Sans Thai ปกติ -->
                <span id="modal-time" class="text-slate-900 font-bold">---</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-600 font-bold">สถานะการแสดง:</span>
                <span id="modal-status" class="inline-block text-[10px] px-2 py-0.5 rounded border">---</span>
            </div>
            <div class="flex justify-between items-center border-t border-slate-200/40 pt-2.5 mt-1">
                <span class="text-slate-600 font-bold">สาขาที่สแกน:</span>
                <span id="modal-branch" class="text-blue-700 font-bold">---</span>
            </div>
        </div>

        <div class="pt-1">
            <button onclick="closeProofModal()" class="w-full bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white text-xs py-2.5 rounded-xl font-semibold tracking-wide shadow-md shadow-blue-700/10 transition-all cursor-pointer">
                ตกลง
            </button>
        </div>
    </div>
</div>

<script>
    function openProofModal(logType, photoLog, branchName, statusTitle, statusColorClass, dateStr, timeStr) {
        const overlay = document.getElementById('proof-modal-overlay');
        const photoImg = document.getElementById('modal-photo');
        const missingBox = document.getElementById('modal-photo-missing');
        const dateSpan = document.getElementById('modal-date');
        const timeSpan = document.getElementById('modal-time');
        const statusSpan = document.getElementById('modal-status');
        const branchSpan = document.getElementById('modal-branch');

        const folder = (logType === 'check_in') ? 'scan-in' : 'scan-out';
        
        if (photoLog && photoLog.trim() !== '') {
            photoImg.src = '../uploads/' + folder + '/' + photoLog;
            photoImg.classList.remove('hidden');
            missingBox.classList.add('hidden');
        } else {
            photoImg.src = '';
            photoImg.classList.add('hidden');
            missingBox.classList.remove('hidden');
            missingBox.classList.add('flex');
        }

        dateSpan.innerText = dateStr;
        timeSpan.innerText = timeStr;
        statusSpan.innerText = statusTitle;
        statusSpan.className = "inline-block text-[10px] px-2 py-0.5 rounded border " + statusColorClass;
        branchSpan.innerText = branchName;

        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            overlay.querySelector('div').classList.remove('scale-95');
        }, 10);
    }

    function closeProofModal() {
        const overlay = document.getElementById('proof-modal-overlay');
        overlay.classList.add('opacity-0');
        overlay.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            overlay.classList.remove('flex');
            overlay.classList.add('hidden');
        }, 300);
    }
</script>