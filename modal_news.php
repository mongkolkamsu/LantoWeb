<?php
// ตรวจสอบสิทธิ์ผู้ดูแลระบบ (Admin, HR, IT Support)
$can_manage_news = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'it_support', 'hr'], true);
?>

<!-- MODAL 1: ลงประกาศข่าวสารใหม่ (เฉพาะ HR, IT, Admin) -->
<div id="postNewsModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>ลงประกาศข่าวสารใหม่</span>
            </h3>
            <button type="button" onclick="closePostNewsModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <form action="new_process.php" method="POST" enctype="multipart/form-data" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="post_news">

            <div>
                <label class="block font-bold text-slate-700 mb-1">หัวข้อประกาศ <span class="text-rose-500">*</span></label>
                <input type="text" name="title" required placeholder="ระบุหัวข้อประกาศ..." 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">เนื้อหาประกาศ <span class="text-rose-500">*</span></label>
                <textarea name="content" required rows="4" placeholder="รายละเอียดเนื้อหาประกาศ..." 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 font-medium focus:outline-none focus:border-blue-500 resize-none"></textarea>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1 flex justify-between items-center">
                    <span>อัปโหลดรูปภาพประกอบ (เลือกได้หลายรูป)</span>
                    <span class="text-[10px] text-slate-400 font-normal">รองรับ JPG, PNG, WEBP</span>
                </label>
                <input type="file" name="news_images[]" id="postNewsImagesInput" accept="image/*" multiple onchange="handlePostImagesSelect(this)"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            </div>

            <!-- กล่องแสดงภาพตัวอย่างพร้อมปุ่มลบ -->
            <div id="postImagesPreviewContainer" class="hidden space-y-1 pt-1">
                <p class="font-bold text-slate-600 text-[11px]">รูปภาพที่เลือก:</p>
                <div id="postImagesGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2"></div>
            </div>

            <div class="flex gap-2 pt-2 border-t border-slate-100">
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

<!-- MODAL 2: แก้ไขข่าวสาร (เฉพาะ Admin, HR, IT) -->
<div id="editNewsModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                <svg class="w-4.5 h-4.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <span>แก้ไขประกาศข่าวสาร</span>
            </h3>
            <button type="button" onclick="closeEditNewsModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <form action="new_process.php" method="POST" enctype="multipart/form-data" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="edit_news">
            <input type="hidden" name="news_id" id="editNewsId" value="">
            <input type="hidden" name="existing_images" id="editExistingImagesInput" value="[]">

            <div>
                <label class="block font-bold text-slate-700 mb-1">หัวข้อประกาศ <span class="text-rose-500">*</span></label>
                <input type="text" name="title" id="editNewsTitle" required 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">เนื้อหาประกาศ <span class="text-rose-500">*</span></label>
                <textarea name="content" id="editNewsContent" required rows="4" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 font-medium focus:outline-none focus:border-blue-500 resize-none"></textarea>
            </div>

            <!-- รายการรูปเดิมที่มีอยู่ -->
            <div id="editExistingImagesWrapper" class="hidden space-y-1.5 pt-1">
                <label class="block font-bold text-slate-700">รูปภาพเดิม (คลิก ✕ เพื่อลบรูปที่ไม่ต้องการ):</label>
                <div id="editExistingImagesGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2"></div>
            </div>

            <!-- เพิ่มรูปภาพใหม่ -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">เพิ่มรูปภาพใหม่ประกอบข่าว (เลือกได้หลายรูป)</label>
                <input type="file" name="news_images[]" id="editNewsImagesInput" accept="image/*" multiple onchange="handleEditImagesSelect(this)"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            </div>

            <!-- กล่องแสดงรูปใหม่ที่เพิ่งเลือก -->
            <div id="editNewImagesPreviewContainer" class="hidden space-y-1 pt-1">
                <p class="font-bold text-slate-600 text-[11px]">รูปภาพใหม่ที่เลือกเพิ่ม:</p>
                <div id="editNewImagesGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2"></div>
            </div>

            <div class="flex gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeEditNewsModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md shadow-blue-500/20 transition-colors cursor-pointer">
                    บันทึกการแก้ไข
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: แสดงรายละเอียดข่าวสาร -->
<div id="newsDetailModal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 space-y-5 my-auto max-h-[92vh] overflow-y-auto relative">
        
        <!-- หัวข้อ Modal และปุ่มปิด -->
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-base sm:text-lg font-extrabold text-slate-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                </svg>
                <span>รายละเอียดประกาศองค์กร</span>
            </h3>
            <button type="button" onclick="closeNewsDetailModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-sm transition-colors cursor-pointer">✕</button>
        </div>

        <!-- เนื้อหาข่าว -->
        <div class="space-y-4">
            <div class="flex justify-between items-start gap-3 flex-wrap">
                <div class="space-y-1">
                    <h2 id="modalNewsTitle" class="text-lg sm:text-xl font-black text-slate-900 tracking-tight"></h2>
                    <p id="modalNewsDate" class="text-xs text-slate-400 font-medium"></p>
                </div>

                <!-- ปุ่มจัดการสำหรับผู้ดูแลระบบ (แก้ไข / ลบ) -->
                <?php if ($can_manage_news): ?>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="triggerEditFromDetail()" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 rounded-xl text-xs font-bold transition-all cursor-pointer active:scale-95 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-amber-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <span>แก้ไขข่าว</span>
                    </button>
                    <button type="button" onclick="triggerDeleteNews()" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 rounded-xl text-xs font-bold transition-all cursor-pointer active:scale-95 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <span>ลบข่าว</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- รูปภาพแกลเลอรี (รองรับทั้ง 1 รูป และหลายรูป) -->
            <div id="modalNewsGalleryContainer" class="hidden space-y-2">
                <div id="modalNewsGalleryGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-2.5"></div>
            </div>

            <div class="pt-2">
                <p id="modalNewsContent" class="text-sm sm:text-base text-slate-700 leading-relaxed whitespace-pre-line"></p>
            </div>
        </div>

        <!-- แถบปุ่มควบคุม (ก่อนหน้า | ถัดไป) -->
        <div class="pt-5 border-t border-slate-100 flex justify-between items-center">
            <button type="button" onclick="navigateNews(-1)" class="group px-4 py-2 bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-600 font-bold rounded-2xl text-xs transition-all duration-200 cursor-pointer flex items-center gap-2 shadow-3xs active:scale-95 border border-slate-200/60">
                <span class="w-5 h-5 rounded-full bg-white group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center transition-colors shadow-2xs">
                    <svg class="w-3 h-3 transform group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </span>
                <span>ก่อนหน้า</span>
            </button>

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

<!-- LIGHTBOX MODAL -->
<div id="imageLightboxModal" class="hidden fixed inset-0 bg-slate-950/90 z-[99] flex items-center justify-center p-4 cursor-pointer" onclick="closeLightboxImage()">
    <div class="relative max-w-5xl w-full max-h-[95vh] flex items-center justify-center">
        <button type="button" class="absolute -top-10 right-0 text-white bg-white/20 hover:bg-white/40 w-8 h-8 rounded-full font-bold flex items-center justify-center text-sm transition-colors">✕</button>
        <img id="lightboxImageSrc" src="" alt="Full Size Image" class="max-w-full max-h-[90vh] object-contain rounded-2xl shadow-2xl">
    </div>
</div>

<script>
    let allNewsData = [];
    let currentModalIndex = 0;

    let postFilesDT = new DataTransfer();
    let editFilesDT = new DataTransfer();
    let currentExistingImages = [];

    function parseNewsImages(imgData) {
        if (!imgData) return [];
        if (Array.isArray(imgData)) return imgData;
        let str = String(imgData).trim();
        if (!str || str === 'null' || str === '[]' || str === '""') return [];
        str = str.replace(/\\/g, '');
        try {
            let parsed = JSON.parse(str);
            if (Array.isArray(parsed)) return parsed;
            if (typeof parsed === 'string' && parsed.trim()) return [parsed];
        } catch(e) {}
        if (str.includes(',')) {
            return str.split(',').map(s => s.trim()).filter(Boolean);
        }
        return [str];
    }

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

        const galleryContainer = document.getElementById('modalNewsGalleryContainer');
        const galleryGrid = document.getElementById('modalNewsGalleryGrid');
        galleryGrid.innerHTML = '';

        const images = parseNewsImages(news.image);

        if (images.length > 0) {
            images.forEach(imgName => {
                const fullUrl = 'uploads/news/' + imgName;
                const card = document.createElement('div');
                card.className = 'relative h-40 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 shadow-2xs hover:opacity-90 transition-all cursor-pointer group';
                card.onclick = function() { openLightboxImage(fullUrl); };
                card.innerHTML = `
                    <img src="${fullUrl}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-slate-900/20 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white transition-opacity">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                        </svg>
                    </div>
                `;
                galleryGrid.appendChild(card);
            });
            galleryContainer.classList.remove('hidden');
        } else {
            galleryContainer.classList.add('hidden');
        }
    }

    function navigateNews(direction) {
        if (!allNewsData.length) return;
        currentModalIndex += direction;
        if (currentModalIndex >= allNewsData.length) currentModalIndex = 0;
        else if (currentModalIndex < 0) currentModalIndex = allNewsData.length - 1;
        renderModalContent(allNewsData[currentModalIndex]);
    }

    // --- ส่วนลงประกาศข่าวใหม่ ---
    function openPostNewsModal() {
        postFilesDT = new DataTransfer();
        const input = document.getElementById('postNewsImagesInput');
        if (input) input.files = postFilesDT.files;
        renderPostImagesGrid();
        document.getElementById('postNewsModal').classList.remove('hidden');
    }

    function closePostNewsModal() {
        document.getElementById('postNewsModal').classList.add('hidden');
    }

    function handlePostImagesSelect(input) {
        if (input.files && input.files.length > 0) {
            Array.from(input.files).forEach(file => postFilesDT.items.add(file));
            input.files = postFilesDT.files;
        }
        renderPostImagesGrid();
    }

    function renderPostImagesGrid() {
        const container = document.getElementById('postImagesPreviewContainer');
        const grid = document.getElementById('postImagesGrid');
        grid.innerHTML = '';

        if (postFilesDT.files.length > 0) {
            container.classList.remove('hidden');
            Array.from(postFilesDT.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const card = document.createElement('div');
                    card.className = 'relative h-24 bg-slate-100 rounded-xl overflow-hidden border border-slate-200 group';
                    card.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover">
                        <button type="button" onclick="removePostImage(${index})" title="ลบรูปนี้"
                            class="absolute top-1 right-1 bg-rose-600/90 hover:bg-rose-700 text-white w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shadow-md cursor-pointer transition-colors">
                            ✕
                        </button>
                    `;
                    grid.appendChild(card);
                };
                reader.readAsDataURL(file);
            });
        } else {
            container.classList.add('hidden');
        }
    }

    function removePostImage(index) {
        const dt = new DataTransfer();
        for (let i = 0; i < postFilesDT.files.length; i++) {
            if (i !== index) dt.items.add(postFilesDT.files[i]);
        }
        postFilesDT = dt;
        document.getElementById('postNewsImagesInput').files = postFilesDT.files;
        renderPostImagesGrid();
    }

    // --- ส่วนแก้ไขข่าวสาร ---
    function triggerEditFromDetail() {
        const currentNews = allNewsData[currentModalIndex];
        if (!currentNews) return;
        closeNewsDetailModal();
        openEditNewsModal(currentNews);
    }

    function openEditNewsModal(news) {
        document.getElementById('editNewsId').value = news.id;
        document.getElementById('editNewsTitle').value = news.title;
        document.getElementById('editNewsContent').value = news.content;

        currentExistingImages = parseNewsImages(news.image);
        updateExistingImagesHiddenInput();
        renderEditExistingImages();

        editFilesDT = new DataTransfer();
        document.getElementById('editNewsImagesInput').files = editFilesDT.files;
        renderEditNewImagesGrid();

        document.getElementById('editNewsModal').classList.remove('hidden');
    }

    function closeEditNewsModal() {
        document.getElementById('editNewsModal').classList.add('hidden');
    }

    function updateExistingImagesHiddenInput() {
        document.getElementById('editExistingImagesInput').value = JSON.stringify(currentExistingImages);
    }

    function renderEditExistingImages() {
        const wrapper = document.getElementById('editExistingImagesWrapper');
        const grid = document.getElementById('editExistingImagesGrid');
        grid.innerHTML = '';

        if (currentExistingImages.length > 0) {
            wrapper.classList.remove('hidden');
            currentExistingImages.forEach((imgName, index) => {
                const card = document.createElement('div');
                card.className = 'relative h-24 bg-slate-100 rounded-xl overflow-hidden border border-slate-200 group';
                card.innerHTML = `
                    <img src="uploads/news/${imgName}" class="w-full h-full object-cover">
                    <button type="button" onclick="removeExistingImage(${index})" title="ลบรูปนี้"
                        class="absolute top-1 right-1 bg-rose-600/90 hover:bg-rose-700 text-white w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shadow-md cursor-pointer transition-colors">
                        ✕
                    </button>
                `;
                grid.appendChild(card);
            });
        } else {
            wrapper.classList.add('hidden');
        }
    }

    function removeExistingImage(index) {
        currentExistingImages.splice(index, 1);
        updateExistingImagesHiddenInput();
        renderEditExistingImages();
    }

    function handleEditImagesSelect(input) {
        if (input.files && input.files.length > 0) {
            Array.from(input.files).forEach(file => editFilesDT.items.add(file));
            input.files = editFilesDT.files;
        }
        renderEditNewImagesGrid();
    }

    function renderEditNewImagesGrid() {
        const container = document.getElementById('editNewImagesPreviewContainer');
        const grid = document.getElementById('editNewImagesGrid');
        grid.innerHTML = '';

        if (editFilesDT.files.length > 0) {
            container.classList.remove('hidden');
            Array.from(editFilesDT.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const card = document.createElement('div');
                    card.className = 'relative h-24 bg-slate-100 rounded-xl overflow-hidden border border-slate-200 group';
                    card.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover">
                        <button type="button" onclick="removeEditNewImage(${index})" title="ลบรูปนี้"
                            class="absolute top-1 right-1 bg-rose-600/90 hover:bg-rose-700 text-white w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shadow-md cursor-pointer transition-colors">
                            ✕
                        </button>
                    `;
                    grid.appendChild(card);
                };
                reader.readAsDataURL(file);
            });
        } else {
            container.classList.add('hidden');
        }
    }

    function removeEditNewImage(index) {
        const dt = new DataTransfer();
        for (let i = 0; i < editFilesDT.files.length; i++) {
            if (i !== index) dt.items.add(editFilesDT.files[i]);
        }
        editFilesDT = dt;
        document.getElementById('editNewsImagesInput').files = editFilesDT.files;
        renderEditNewImagesGrid();
    }

    function triggerDeleteNews() {
        const currentNews = allNewsData[currentModalIndex];
        if (!currentNews) return;

        if (typeof LantoAlert !== 'undefined' && typeof LantoAlert.confirm === 'function') {
            LantoAlert.confirm('ยืนยันการลบ', `คุณต้องการลบข่าว "${currentNews.title}" ใช่หรือไม่?`, function() {
                window.location.href = `new_process.php?action=delete_news&id=${currentNews.id}`;
            }, null, 'reject');
        } else if (confirm(`คุณต้องการลบข่าว "${currentNews.title}" ใช่หรือไม่?`)) {
            window.location.href = `new_process.php?action=delete_news&id=${currentNews.id}`;
        }
    }

    function openLightboxImage(url) {
        const lightbox = document.getElementById('imageLightboxModal');
        const lightboxImg = document.getElementById('lightboxImageSrc');
        if (lightbox && lightboxImg) {
            lightboxImg.src = url;
            lightbox.classList.remove('hidden');
        }
    }

    function closeLightboxImage() {
        document.getElementById('imageLightboxModal').classList.add('hidden');
    }

    function closeNewsDetailModal() {
        document.getElementById('newsDetailModal').classList.add('hidden');
    }
</script>