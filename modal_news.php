<!-- ✍️ MODAL: ลงประกาศข่าวสารใหม่ (เฉพาะ HR, IT, Admin) -->
<div id="postNewsModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm md:max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2"><span>📢</span> ลงประกาศข่าวสารใหม่</h3>
            <button type="button" onclick="closePostNewsModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <form action="new_process.php" method="POST" enctype="multipart/form-data" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="post_news">

            <div>
                <label class="block font-bold text-slate-700 mb-1">หัวข้อประกาศ <span class="text-rose-500">*</span></label>
                <input type="text" name="title" required placeholder="เช่น แจ้งวันหยุดพิเศษประจำเดือน" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">เนื้อหาประกาศ <span class="text-rose-500">*</span></label>
                <textarea name="content" required rows="4" placeholder="รายละเอียดเนื้อหาประกาศ..." 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 font-medium focus:outline-none focus:border-blue-500 resize-none"></textarea>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">อัปโหลดรูปภาพประกอบ (ถ้ามี)</label>
                <input type="file" name="news_image" id="newsImageInput" accept="image/*" onchange="previewNewsImage(event)"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            </div>

            <div id="imagePreviewContainer" class="hidden relative w-full h-32 bg-slate-100 rounded-xl overflow-hidden border border-slate-200">
                <img id="newsImagePreview" src="" alt="Preview" class="w-full h-full object-cover">
                <button type="button" onclick="clearNewsImage()" class="absolute top-2 right-2 bg-slate-900/70 hover:bg-slate-900 text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold">✕</button>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closePostNewsModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md shadow-blue-500/20 transition-colors cursor-pointer">
                    โพสต์ประกาศ
                </button>
            </div>
        </form>
    </div>
</div>


<!-- 🔍 MODAL: แสดงรายละเอียดข่าวสาร -->
<div id="newsDetailModal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 space-y-5 my-auto max-h-[92vh] overflow-y-auto relative">
        
        <!-- หัวข้อ Modal และปุ่มปิด -->
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2"><span>📢</span> รายละเอียดประกาศองค์กร</h3>
            <button type="button" onclick="closeNewsDetailModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-sm transition-colors cursor-pointer">✕</button>
        </div>

        <!-- เนื้อหาข่าว -->
        <div class="space-y-4">
            <h2 id="modalNewsTitle" class="text-lg sm:text-xl font-black text-slate-900 tracking-tight"></h2>
            <p id="modalNewsDate" class="text-xs text-slate-400 font-medium"></p>
            
            <!-- รูปภาพขนาดใหญ่ (คลิกแล้วเด้งขยายใหญ่ขึ้นทันที) -->
            <div id="modalNewsImageWrapper" class="hidden w-full rounded-2xl overflow-hidden bg-slate-900/5 border border-slate-200 shadow-inner flex items-center justify-center p-2 cursor-pointer group relative" onclick="expandModalImage()">
                <img id="modalNewsImage" src="" alt="News Image" class="w-full max-h-[400px] object-contain rounded-xl group-hover:scale-[1.01] transition-transform">
                
            </div>

            <div class="pt-2">
                <p id="modalNewsContent" class="text-sm sm:text-base text-slate-700 leading-relaxed whitespace-pre-line"></p>
            </div>
        </div>

        <!-- แถบปุ่มควบคุม (ซ้าย: ก่อนหน้า | ขวา: ถัดไป) -->
        <div class="pt-5 border-t border-slate-100 flex justify-between items-center">
            
            <!-- ปุ่มก่อนหน้า (ลูกศรชี้ซ้ายถูกต้อง) -->
            <button type="button" onclick="navigateNews(-1)" class="group px-4 py-2 bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-600 font-bold rounded-2xl text-xs transition-all duration-200 cursor-pointer flex items-center gap-2 shadow-3xs active:scale-95 border border-slate-200/60">
                <span class="w-5 h-5 rounded-full bg-white group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center transition-colors shadow-2xs">
                    <svg class="w-3 h-3 transform group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </span>
                <span>ก่อนหน้า</span>
            </button>

            <!-- ปุ่มถัดไป (ลูกศรชี้ขวาถูกต้อง) -->
            <button type="button" onclick="navigateNews(1)" class="group px-4 py-2 bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-600 font-bold rounded-2xl text-xs transition-all duration-200 cursor-pointer flex items-center gap-2 shadow-3xs active:scale-95 border border-slate-200/60">
                <span>ถัดไป</span>
                <span class="w-5 h-5 rounded-full bg-white group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center transition-colors shadow-2xs">
                    <svg class="w-3 h-3 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
            </button>

        </div>
    </div>
</div>

<!-- 🔍 LIGHTBOX MODAL: สำหรับแสดงรูปภาพขนาดเต็มแบบเด้งซ้อนทับ -->
<div id="imageLightboxModal" class="hidden fixed inset-0 bg-slate-950/90 z-[99] flex items-center justify-center p-4 cursor-pointer" onclick="closeLightboxImage()">
    <div class="relative max-w-5xl w-full max-h-[95vh] flex items-center justify-center">
        <button type="button" class="absolute -top-10 right-0 text-white bg-white/20 hover:bg-white/40 w-8 h-8 rounded-full font-bold flex items-center justify-center text-sm transition-colors">✕</button>
        <img id="lightboxImageSrc" src="" alt="Full Size Image" class="max-w-full max-h-[90vh] object-contain rounded-2xl shadow-2xl">
    </div>
</div>

<script>
    let allNewsData = [];
    let currentModalIndex = 0;

    function openNewsDetailModal(newsItem, newsListArray = null) {
        if (newsListArray) {
            allNewsData = newsListArray;
            currentModalIndex = allNewsData.findIndex(n => n.id == newsItem.id);
            if (currentModalIndex === -1) currentModalIndex = 0;
        }
        renderModalContent(allNewsData[currentModalIndex] || newsItem);
        document.getElementById('newsDetailModal').classList.remove('hidden');
    }

    function renderModalContent(news) {
        document.getElementById('modalNewsTitle').innerText = news.title;
        document.getElementById('modalNewsContent').innerText = news.content;
        document.getElementById('modalNewsDate').innerText = 'เผยแพร่เมื่อ: ' + news.created_at;

        const imgWrapper = document.getElementById('modalNewsImageWrapper');
        const imgElement = document.getElementById('modalNewsImage');

        if (news.image) {
            imgElement.src = 'uploads/news/' + news.image;
            imgWrapper.classList.remove('hidden');
        } else {
            imgElement.src = '';
            imgWrapper.classList.add('hidden');
        }
    }

    // ฟังก์ชันกดเลื่อนก่อนหน้าหรือถัดไปใน Modal
    function navigateNews(direction) {
        if (!allNewsData.length) return;
        currentModalIndex += direction;
        if (currentModalIndex >= allNewsData.length) {
            currentModalIndex = 0; 
        } else if (currentModalIndex < 0) {
            currentModalIndex = allNewsData.length - 1; 
        }
        renderModalContent(allNewsData[currentModalIndex]);
    }

    // ฟังก์ชันคลิกเด้งรูปภาพขยายใหญ่เต็มจอ (Lightbox)
    function expandModalImage() {
        const imgElement = document.getElementById('modalNewsImage');
        const lightbox = document.getElementById('imageLightboxModal');
        const lightboxImg = document.getElementById('lightboxImageSrc');

        if (imgElement && imgElement.src) {
            lightboxImg.src = imgElement.src;
            lightbox.classList.remove('hidden');
        }
    }

    function closeLightboxImage() {
        document.getElementById('imageLightboxModal').classList.add('hidden');
    }

    function closeNewsDetailModal() {
        document.getElementById('newsDetailModal').classList.add('hidden');
    }

    function openPostNewsModal() {
        document.getElementById('postNewsModal').classList.remove('hidden');
    }
    function closePostNewsModal() {
        document.getElementById('postNewsModal').classList.add('hidden');
    }
    function previewNewsImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('newsImagePreview').src = e.target.result;
                document.getElementById('imagePreviewContainer').classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    }
    function clearNewsImage() {
        document.getElementById('newsImageInput').value = '';
        document.getElementById('imagePreviewContainer').classList.add('hidden');
        document.getElementById('newsImagePreview').src = '';
    }
</script>