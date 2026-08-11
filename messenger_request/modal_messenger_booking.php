<?php
// 📊 ดึงประวัติสถานที่ส่งปลายทางทั้งหมดในระบบสำหรับทำ Custom Autocomplete
$history_destinations = [];
if (isset($pdo)) {
    try {
        $stmt_hist = $pdo->query("
            SELECT DISTINCT dropoff_location, dropoff_contact, dropoff_phone
            FROM messenger_requests
            WHERE dropoff_location IS NOT NULL AND dropoff_location != ''
            ORDER BY id DESC
            LIMIT 100
        ");
        $history_destinations = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
?>

<!-- 📌 MODAL POPUP ฟอร์มจองแมสเซนเจอร์ -->
<div id="bookingModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-5 sm:p-7 shadow-2xl border border-slate-100 space-y-5 my-auto max-h-[92vh] overflow-y-auto">
        
        <!-- Header Modal -->
        <div class="flex justify-between items-center border-b border-slate-100 pb-3.5">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2">
                    <span>🛵</span> ฟอร์มจองแมสเซนเจอร์
                </h3>
                <p class="text-xs text-blue-600 font-bold mt-0.5" id="modal_date_display">
                    📅 วันที่ต้องการวิ่งงาน: -
                </p>
            </div>
            <button type="button" onclick="closeBookingModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
        </div>

        <form method="POST" action="process.php" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="action" value="create_request">
            <input type="hidden" name="booking_date" id="modal_booking_date_input" value="">
            <input type="hidden" name="urgent_level" value="normal">

            <!-- 🔘 0. เลือกรูปแบบการวิ่งงาน (Quick Mode) -->
            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/80 space-y-2">
                <label class="text-xs font-black text-slate-700 block">🎯 รูปแบบการรับ-ส่งพัสดุ:</label>
                <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                    <button type="button" id="mode_btn_outbound" onclick="setDeliveryMode('outbound')" class="py-2.5 px-3 rounded-xl border transition-all flex items-center justify-center gap-1.5 bg-blue-600 text-white border-blue-600 shadow-xs cursor-pointer">
                        <span>📤</span> <span>ส่งออกจากแผนกเรา</span>
                    </button>
                    <button type="button" id="mode_btn_inbound" onclick="setDeliveryMode('inbound')" class="py-2.5 px-3 rounded-xl border transition-all flex items-center justify-center gap-1.5 bg-white text-slate-700 border-slate-200 hover:bg-slate-100 cursor-pointer">
                        <span>📥</span> <span>ไปรับของกลับมาหาเรา</span>
                    </button>
                </div>
            </div>

            <!-- 👤 1. ข้อมูลผู้จอง / จุดรับพัสดุ (ต้นทาง) -->
            <div class="space-y-3 border-b border-slate-100 pb-4">
                <h4 id="sec1_title" class="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <span id="sec1_badge" class="w-5 h-5 bg-blue-100 text-blue-700 rounded-md flex items-center justify-center text-[11px]">1</span>
                    ข้อมูลผู้จอง / สถานที่รับพัสดุ (ต้นทาง)
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    
                    <div class="space-y-1 sm:col-span-2 relative" id="pickup_search_container">
                        <label class="font-bold text-slate-700 flex justify-between items-center">
                            <span id="sec1_loc_label">สถานที่ / จุดวางของต้นทาง <span class="text-rose-500">*</span></span>
                            <span id="pickup_loc_hint" class="text-[10px] text-emerald-600 font-medium hidden">💡 พิมพ์เพื่อดูประวัติที่เคยกรอก</span>
                        </label>

                        <div id="pickup_suggestion_box" class="hidden absolute bottom-full mb-1 left-0 right-0 bg-white border border-slate-200/90 rounded-2xl shadow-xl z-50 p-2 space-y-1 max-h-52 overflow-y-auto">
                        </div>
                        
                        <input type="text" 
                               id="pickup_location_input" 
                               name="pickup_location" 
                               autocomplete="off"
                               oninput="filterSuggestions('pickup', this.value)"
                               onfocus="filterSuggestions('pickup', this.value)"
                               required 
                               value="<?php echo htmlspecialchars(($user_info['branch_name'] ?? 'สำนักงานใหญ่') . ' - แผนก ' . ($user_info['dept_name'] ?? '')); ?>"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-semibold focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="space-y-1">
                        <label id="sec1_name_label" class="font-bold text-slate-700">ผู้ติดต่อต้นทาง <span class="text-rose-500">*</span></label>
                        <input type="text" id="pickup_contact_input" name="pickup_contact" required placeholder="คุณ..." value="<?php echo htmlspecialchars($fullname); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-semibold focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">เบอร์โทรต้นทาง <span class="text-rose-500">*</span></label>
                        <input type="text" id="pickup_phone_input" name="pickup_phone" required value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" placeholder="08X-XXX-XXXX" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-semibold focus:outline-none focus:border-blue-500">
                    </div>

                </div>
            </div>

            <!-- 📦 2. ข้อมูลเอกสาร / พัสดุ -->
            <div class="space-y-3 border-b border-slate-100 pb-4">
                <h4 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-5 h-5 bg-amber-100 text-amber-700 rounded-md flex items-center justify-center text-[11px]">2</span>
                    ข้อมูลเอกสาร / พัสดุฝากส่ง
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="space-y-1 sm:col-span-2">
                        <label class="font-bold text-slate-700">หัวข้อ / เรื่องฝากส่ง <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" required placeholder="เช่น ส่งเอกสารวางบิลบริษัท A, ไปรับพัสดุจากไปรษณีย์" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-semibold focus:outline-none focus:border-amber-500">
                    </div>

                    <div class="space-y-1 sm:col-span-2">
                        <label class="font-bold text-slate-700">ประเภทสิ่งของ <span class="text-rose-500">*</span></label>
                        <?php renderRoundedDropdown('modal_item_type', 'item_type', '📄 เอกสาร / ซองจดหมาย', $item_type_options, 'document', false); ?>
                    </div>

                    <div class="space-y-1 sm:col-span-2">
                        <label class="font-bold text-slate-700">รายละเอียดเพิ่มเติม / หมายเหตุ</label>
                        <textarea name="details" rows="2" placeholder="เช่น ระวังแตก, เอกสารสำคัญห้ามพับ, ฝากไว้ที่เคาน์เตอร์ประชาสัมพันธ์" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-2.5 font-semibold focus:outline-none focus:border-amber-500"></textarea>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <label class="font-bold text-slate-700 flex justify-between items-center">
                            <span>📷 ถ่ายภาพ / แนบรูปถ่ายเอกสารหรือพัสดุ (ถ้ามี)</span>
                            <span class="text-[10px] text-slate-400 font-normal">รองรับภาพถ่ายจากกล้อง หรือไฟล์รูปในเครื่อง</span>
                        </label>
                        
                        <input type="file" 
                                id="item_photo_input" 
                                name="item_photo[]" 
                                accept="image/*" 
                                multiple 
                                onchange="previewItemPhotos(this)" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2 text-slate-600 text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">

                        <div id="item_photo_preview_container" class="hidden grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2">
                        </div>
                    </div>

                </div>
            </div>

            <!-- 🏁 3. สถานที่ส่งพัสดุ (ปลายทาง) -->
            <div class="space-y-3">
                <h4 id="sec3_title" class="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <span id="sec3_badge" class="w-5 h-5 bg-emerald-100 text-emerald-700 rounded-md flex items-center justify-center text-[11px]">3</span>
                    สถานที่ส่งพัสดุ (ปลายทาง)
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    
                    <div class="space-y-1 sm:col-span-2 relative" id="dropoff_search_container">
                        <label class="font-bold text-slate-700 flex justify-between items-center">
                            <span id="sec3_loc_label">รายละเอียดสถานที่ส่งปลายทาง / ชื่อบริษัทลูกค้า <span class="text-rose-500">*</span></span>
                            <span id="dropoff_loc_hint" class="text-[10px] text-emerald-600 font-medium">💡 พิมพ์เพื่อดูประวัติที่เคยกรอก</span>
                        </label>

                        <div id="dropoff_suggestion_box" class="hidden absolute bottom-full mb-1 left-0 right-0 bg-white border border-slate-200/90 rounded-2xl shadow-xl z-50 p-2 space-y-1 max-h-52 overflow-y-auto">
                        </div>
                        
                        <input type="text" 
                               id="dropoff_location_input" 
                               name="dropoff_location" 
                               autocomplete="off"
                               oninput="filterSuggestions('dropoff', this.value)"
                               onfocus="filterSuggestions('dropoff', this.value)"
                               required 
                               placeholder="พิมพ์ชื่อบริษัท, สาขา, หรือสถานที่..." 
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-semibold focus:outline-none focus:border-emerald-500">
                    </div>

                    <div class="space-y-1">
                        <label id="sec3_name_label" class="font-bold text-slate-700">ชื่อผู้รับปลายทาง <span class="text-rose-500">*</span></label>
                        <input type="text" id="dropoff_contact_input" name="dropoff_contact" required placeholder="คุณ..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-semibold focus:outline-none focus:border-emerald-500">
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">เบอร์โทรผู้รับปลายทาง <span class="text-rose-500">*</span></label>
                        <input type="text" id="dropoff_phone_input" name="dropoff_phone" required placeholder="08X-XXX-XXXX" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-semibold focus:outline-none focus:border-emerald-500">
                    </div>

                    <!-- 🗺️ ลิงก์แผนที่ Google Maps ปลายทาง -->
                    <div id="dropoff_map_container" class="space-y-1 sm:col-span-2">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <span>🗺️ ลิงก์แผนที่ / พิกัด Google Maps ปลายทาง</span>
                            <span class="text-[10px] text-slate-400 font-normal">(ระบุหรือไม่ก็ได้)</span>
                        </label>
                        <input type="url" name="dropoff_map_link" id="dropoff_map_link_input" placeholder="https://maps.app.goo.gl/... หรือวางลิงก์จาก Google Maps" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 font-semibold text-blue-600 focus:outline-none focus:border-emerald-500">
                    </div>

                </div>
            </div>

            <!-- ปุ่มกดยืนยัน -->
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeBookingModal()" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 font-extrabold rounded-2xl text-slate-600 transition-colors text-xs cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-2xl shadow-md transition-all text-xs cursor-pointer active:scale-98">
                    🚀 ยืนยันการจองแมสเซนเจอร์
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const destinationHistory = <?php echo json_encode($history_destinations); ?>;

    const defaultUserBranchDept = <?php echo json_encode(($user_info['branch_name'] ?? 'สำนักงานใหญ่') . ' - แผนก ' . ($user_info['dept_name'] ?? '')); ?>;
    const defaultUserFullname   = <?php echo json_encode($fullname); ?>;
    const defaultUserPhone      = <?php echo json_encode($user_info['phone'] ?? ''); ?>;

    let selectedFilesDataTransfer = new DataTransfer();
    let currentDeliveryMode = 'outbound';

    function openBookingModal(dateAd, dateThDisplay) {
        document.getElementById('modal_booking_date_input').value = dateThDisplay;
        document.getElementById('modal_date_display').textContent = '📅 วันที่ต้องการวิ่งงาน: ' + dateThDisplay;
        document.getElementById('bookingModal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        setDeliveryMode('outbound');
    }

    function closeBookingModal() {
        document.getElementById('bookingModal').classList.add('hidden');
        hideSuggestionBox('pickup');
        hideSuggestionBox('dropoff');
        removeItemPhoto();
        document.body.classList.remove('overflow-hidden');
    }

    function previewItemPhotos(input) {
        if (input.files && input.files.length > 0) {
            Array.from(input.files).forEach(file => {
                selectedFilesDataTransfer.items.add(file);
            });
            input.files = selectedFilesDataTransfer.files;
        }
        renderPhotoPreviews();
    }

    function renderPhotoPreviews() {
        const container = document.getElementById('item_photo_preview_container');
        container.innerHTML = '';

        if (selectedFilesDataTransfer.files.length > 0) {
            container.classList.remove('hidden');

            Array.from(selectedFilesDataTransfer.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const card = document.createElement('div');
                    card.className = 'relative h-28 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200/90 shadow-2xs group';
                    card.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover">
                        <button type="button" 
                                onclick="removeSinglePhoto(${index})" 
                                title="ลบรูปนี้" 
                                class="absolute top-1.5 right-1.5 bg-rose-500/90 hover:bg-rose-600 text-white w-6 h-6 rounded-full font-bold flex items-center justify-center text-xs shadow-md transition-all cursor-pointer">
                            ✕
                        </button>
                    `;
                    container.appendChild(card);
                }
                reader.readAsDataURL(file);
            });
        } else {
            container.classList.add('hidden');
        }
    }

    function removeSinglePhoto(index) {
        const dt = new DataTransfer();
        const files = selectedFilesDataTransfer.files;

        for (let i = 0; i < files.length; i++) {
            if (i !== index) {
                dt.items.add(files[i]);
            }
        }

        selectedFilesDataTransfer = dt;
        const input = document.getElementById('item_photo_input');
        if (input) input.files = selectedFilesDataTransfer.files;

        renderPhotoPreviews();
    }

    function removeItemPhoto() {
        selectedFilesDataTransfer = new DataTransfer();
        const input = document.getElementById('item_photo_input');
        if (input) input.files = selectedFilesDataTransfer.files;
        
        const container = document.getElementById('item_photo_preview_container');
        if (container) {
            container.innerHTML = '';
            container.classList.add('hidden');
        }
    }

    function setDeliveryMode(mode) {
        currentDeliveryMode = mode;
        const btnOutbound = document.getElementById('mode_btn_outbound');
        const btnInbound  = document.getElementById('mode_btn_inbound');
        
        const pickupLoc   = document.getElementById('pickup_location_input');
        const pickupName  = document.getElementById('pickup_contact_input');
        const pickupPhone = document.getElementById('pickup_phone_input');

        const dropLoc     = document.getElementById('dropoff_location_input');
        const dropName    = document.getElementById('dropoff_contact_input');
        const dropPhone   = document.getElementById('dropoff_phone_input');

        const sec1Title   = document.getElementById('sec1_title');
        const sec1Badge   = document.getElementById('sec1_badge');
        const sec1LocLbl  = document.getElementById('sec1_loc_label');
        const sec1NameLbl = document.getElementById('sec1_name_label');
        const pickupHint  = document.getElementById('pickup_loc_hint');

        const sec3Title   = document.getElementById('sec3_title');
        const sec3Badge   = document.getElementById('sec3_badge');
        const sec3LocLbl  = document.getElementById('sec3_loc_label');
        const sec3NameLbl = document.getElementById('sec3_name_label');
        const dropHint    = document.getElementById('dropoff_loc_hint');

        hideSuggestionBox('pickup');
        hideSuggestionBox('dropoff');

        if (mode === 'outbound') {
            btnOutbound.className = 'py-2.5 px-3 rounded-xl border transition-all flex items-center justify-center gap-1.5 bg-blue-600 text-white border-blue-600 shadow-xs cursor-pointer';
            btnInbound.className  = 'py-2.5 px-3 rounded-xl border transition-all flex items-center justify-center gap-1.5 bg-white text-slate-700 border-slate-200 hover:bg-slate-100 cursor-pointer';

            if(sec1Title) sec1Title.childNodes[2].nodeValue = ' ข้อมูลผู้จอง / สถานที่รับพัสดุ (ต้นทาง)';
            if(sec1Badge) sec1Badge.className = 'w-5 h-5 bg-blue-100 text-blue-700 rounded-md flex items-center justify-center text-[11px]';
            if(sec1LocLbl) sec1LocLbl.innerHTML = 'สถานที่ / จุดวางของต้นทาง <span class="text-rose-500">*</span>';
            if(sec1NameLbl) sec1NameLbl.innerHTML = 'ผู้ติดต่อต้นทาง <span class="text-rose-500">*</span>';
            if(pickupHint) pickupHint.classList.add('hidden');

            if(sec3Title) sec3Title.childNodes[2].nodeValue = ' สถานที่ส่งพัสดุ (ปลายทาง)';
            if(sec3Badge) sec3Badge.className = 'w-5 h-5 bg-emerald-100 text-emerald-700 rounded-md flex items-center justify-center text-[11px]';
            if(sec3LocLbl) sec3LocLbl.innerHTML = 'รายละเอียดสถานที่ส่งปลายทาง / ชื่อบริษัทลูกค้า <span class="text-rose-500">*</span>';
            if(sec3NameLbl) sec3NameLbl.innerHTML = 'ชื่อผู้รับปลายทาง <span class="text-rose-500">*</span>';
            if(dropHint) dropHint.classList.remove('hidden');

            pickupLoc.value   = defaultUserBranchDept;
            pickupName.value  = defaultUserFullname;
            pickupPhone.value = defaultUserPhone;

            dropLoc.value     = '';
            dropName.value    = '';
            dropPhone.value   = '';
            dropLoc.placeholder = 'พิมพ์ชื่อบริษัท, สาขา, หรือสถานที่...';
        } else {
            btnInbound.className  = 'py-2.5 px-3 rounded-xl border transition-all flex items-center justify-center gap-1.5 bg-blue-600 text-white border-blue-600 shadow-xs cursor-pointer';
            btnOutbound.className = 'py-2.5 px-3 rounded-xl border transition-all flex items-center justify-center gap-1.5 bg-white text-slate-700 border-slate-200 hover:bg-slate-100 cursor-pointer';

            if(sec1Title) sec1Title.childNodes[2].nodeValue = ' สถานที่ไปรับพัสดุ (ต้นทางข้างนอก)';
            if(sec1Badge) sec1Badge.className = 'w-5 h-5 bg-amber-100 text-amber-700 rounded-md flex items-center justify-center text-[11px]';
            if(sec1LocLbl) sec1LocLbl.innerHTML = 'สถานที่ / ร้านค้า / บริษัทที่ไปรับของ <span class="text-rose-500">*</span>';
            if(sec1NameLbl) sec1NameLbl.innerHTML = 'ชื่อผู้ติดต่อต้นทาง (คนให้ของ) <span class="text-rose-500">*</span>';
            if(pickupHint) pickupHint.classList.remove('hidden');

            if(sec3Title) sec3Title.childNodes[2].nodeValue = ' สถานที่นำพัสดุมาส่ง (ปลายทาง - แผนกเรา)';
            if(sec3Badge) sec3Badge.className = 'w-5 h-5 bg-blue-100 text-blue-700 rounded-md flex items-center justify-center text-[11px]';
            if(sec3LocLbl) sec3LocLbl.innerHTML = 'แผนก / จุดรับของปลายทาง <span class="text-rose-500">*</span>';
            if(sec3NameLbl) sec3NameLbl.innerHTML = 'ชื่อผู้รับ (ผู้จอง) <span class="text-rose-500">*</span>';
            if(dropHint) dropHint.classList.add('hidden');

            dropLoc.value     = defaultUserBranchDept;
            dropName.value    = defaultUserFullname;
            dropPhone.value   = defaultUserPhone;

            pickupLoc.value   = '';
            pickupName.value  = '';
            pickupPhone.value = '';
            pickupLoc.placeholder = 'พิมพ์ชื่อบริษัท, สาขา, หรือสถานที่ไปรับของ...';
        }
    }

    function filterSuggestions(target, keyword) {
        if (target === 'pickup' && currentDeliveryMode !== 'inbound') return;
        if (target === 'dropoff' && currentDeliveryMode !== 'outbound') return;

        const box = document.getElementById(target + '_suggestion_box');
        if (!destinationHistory || destinationHistory.length === 0) {
            box.classList.add('hidden');
            return;
        }

        const searchTerm = keyword.trim().toLowerCase();
        const matches = destinationHistory.filter(item => {
            if (!searchTerm) return true;
            return item.dropoff_location.toLowerCase().includes(searchTerm) ||
                   (item.dropoff_contact && item.dropoff_contact.toLowerCase().includes(searchTerm));
        });

        if (matches.length === 0) {
            box.classList.add('hidden');
            return;
        }

        let html = `<div class="text-[10px] font-extrabold text-slate-400 px-2 py-1 border-b border-slate-100 flex justify-between items-center"><span>📍 ประวัติสถานที่ที่เคยบันทึกไว้ (คลิกเพื่อเลือก)</span><button type="button" onclick="hideSuggestionBox('${target}')" class="text-slate-400 hover:text-slate-600">✕</button></div>`;
        
        matches.forEach(item => {
            const loc     = item.dropoff_location.replace(/'/g, "\\'");
            const contact = (item.dropoff_contact || '').replace(/'/g, "\\'");
            const phone   = (item.dropoff_phone || '').replace(/'/g, "\\'");

            html += `
                <div onclick="selectSuggestion('${target}', '${loc}', '${contact}', '${phone}')" class="p-2.5 hover:bg-emerald-50 rounded-xl cursor-pointer transition-colors border border-transparent hover:border-emerald-200/80 group">
                    <p class="font-extrabold text-slate-800 text-xs group-hover:text-emerald-700">🏢 ${item.dropoff_location}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-0.5">👤 ผู้ติดต่อ: ${item.dropoff_contact || '-'} | 📞 โทร: ${item.dropoff_phone || '-'}</p>
                </div>
            `;
        });

        box.innerHTML = html;
        box.classList.remove('hidden');
    }

    function selectSuggestion(target, location, contact, phone) {
        document.getElementById(target + '_location_input').value = location;
        document.getElementById(target + '_contact_input').value  = contact;
        document.getElementById(target + '_phone_input').value    = phone;
        hideSuggestionBox(target);
    }

    function hideSuggestionBox(target) {
        const box = document.getElementById(target + '_suggestion_box');
        if (box) box.classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        const pickupContainer  = document.getElementById('pickup_search_container');
        const dropoffContainer = document.getElementById('dropoff_search_container');

        if (pickupContainer && !pickupContainer.contains(e.target)) {
            hideSuggestionBox('pickup');
        }
        if (dropoffContainer && !dropoffContainer.contains(e.target)) {
            hideSuggestionBox('dropoff');
        }
    });

    document.getElementById('bookingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBookingModal();
        }
    });
</script>