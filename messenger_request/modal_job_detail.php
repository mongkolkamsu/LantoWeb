<!-- 📌 MODAL POPUP แสดงรายละเอียดงานและจัดการสถานะ -->
<div id="jobDetailModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        
        <!-- Header Modal -->
        <div class="flex justify-between items-start border-b border-slate-100 pb-3">
            <div class="space-y-1.5">
                <div class="flex items-center gap-2 flex-wrap">
                    <span id="detail_job_no" class="text-xs font-black bg-blue-50 text-blue-600 px-2.5 py-1 rounded-lg border border-blue-100/80 inline-block"></span>
                    <span id="detail_status_badge"></span>
                </div>
                <h3 id="detail_job_title" class="text-base sm:text-lg font-black text-slate-800 leading-snug"></h3>
            </div>
            <button type="button" onclick="closeJobDetailModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center transition-colors cursor-pointer shrink-0">✕</button>
        </div>

        <!-- 🛵 TIMELINE FLOW (3 จุด) -->
        <div class="space-y-2 text-[11px] text-slate-600 bg-slate-50/80 p-3.5 rounded-2xl border border-slate-100">
            
            <p class="flex items-center gap-1 font-semibold text-slate-700">
                <span class="text-slate-400">👤 ผู้จอง:</span>
                <span id="detail_requester_name" class="text-slate-800 font-extrabold"></span>
            </p>

            <div class="my-2 pl-3 border-l-2 border-dashed border-slate-200 space-y-3 relative ml-1 transition-all">
                
                <!-- จุดที่ 1: รอรับงาน -->
                <div id="step_1_box" class="relative pl-2 transition-all">
                    <span id="step_1_dot" class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full transition-all"></span>
                    <p id="step_1_title" class="text-[10px] uppercase tracking-wider transition-colors">1. รอรับงาน</p>
                    <p id="step_1_sub" class="text-xs mt-0.5 transition-colors">รอแมสเซนเจอร์กดรับงาน</p>
                </div>

                <!-- จุดที่ 2: ต้นทาง -->
                <div id="step_2_box" class="relative pl-2 transition-all">
                    <span id="step_2_dot" class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full transition-all"></span>
                    <p id="step_2_title" class="text-[10px] uppercase tracking-wider transition-colors">2. ต้นทาง (สถานที่รับพัสดุ)</p>
                    <p id="detail_pickup_loc" class="text-xs mt-0.5 transition-colors"></p>
                    <p id="detail_pickup_contact_lbl" class="text-[10.5px] mt-0.5 transition-colors">
                        ผู้ติดต่อ: <span id="detail_pickup_contact_display"></span>
                    </p>
                </div>

                <!-- จุดที่ 3: ปลายทาง -->
                <div id="step_3_box" class="relative pl-2 transition-all">
                    <span id="step_3_dot" class="absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full transition-all"></span>
                    <p id="step_3_title" class="text-[10px] uppercase tracking-wider transition-colors">3. ปลายทาง (สถานที่ส่งพัสดุ)</p>
                    <p id="detail_dropoff_loc" class="text-xs mt-0.5 transition-colors"></p>
                    <p id="detail_dropoff_contact_lbl" class="text-[10.5px] mt-0.5 transition-colors">
                        ผู้รับ: <span id="detail_dropoff_contact_display"></span>
                    </p>
                    
                    <!-- 🗺️ ลิงก์แผนที่ปลายทาง -->
                    <div id="detail_dropoff_map_wrapper" class="pt-1.5">
                        <span id="detail_dropoff_map_content"></span>
                    </div>
                </div>

            </div>
        </div>

        <!-- 📦 ข้อมูลพัสดุ -->
        <div class="p-4 bg-slate-50/90 rounded-2xl border border-slate-200/80 text-xs text-slate-700">
            <div class="space-y-2.5 divide-y divide-slate-200/60">
                
                <div class="flex items-center justify-between pt-0.5">
                    <span class="text-slate-500 font-semibold flex items-center gap-1.5 shrink-0">
                        <span>📅</span> <span>วันที่ต้องการวิ่งงาน:</span>
                    </span>
                    <span id="detail_booking_date" class="font-bold text-slate-800 text-right"></span>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span class="text-slate-500 font-semibold flex items-center gap-1.5 shrink-0">
                        <span>📄</span> <span>ประเภทสิ่งของ:</span>
                    </span>
                    <span id="detail_item_type" class="font-bold text-slate-800 text-right"></span>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span class="text-slate-500 font-semibold flex items-center gap-1.5 shrink-0">
                        <span>🛵</span> <span>แมสเซนเจอร์ผู้รับงาน:</span>
                    </span>
                    <span id="detail_messenger_display" class="font-bold text-slate-800 text-right"></span>
                </div>

                <div class="pt-2.5 space-y-1.5">
                    <span class="text-slate-500 font-semibold flex items-center gap-1.5 block">
                        <span>📝</span> <span>รายละเอียดเพิ่มเติม / หมายเหตุ:</span>
                    </span>
                    <div id="detail_remark" class="text-slate-700 bg-white p-3 rounded-xl border border-slate-200/80 font-normal leading-relaxed break-words min-h-[40px]">
                        -
                    </div>
                </div>

            </div>
        </div>

        <!-- 🖼️ รูปภาพแนบประกอบ -->
        <div id="detail_photo_container" class="space-y-1.5 pt-1 text-xs">
            <p class="font-bold text-slate-700 flex items-center gap-1">
                <span>🖼️</span> <span>รูปภาพแนบประกอบพัสดุ:</span>
            </p>
            <div id="detail_photo_gallery" class="grid grid-cols-2 sm:grid-cols-4 gap-2"></div>
        </div>

        <!-- ✋ ปุ่มกดรับงาน (กรณีสถานะ pending) -->
        <div id="detail_accept_btn_container" class="hidden pt-2 text-xs">
            <button type="button" id="detail_accept_btn" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-2xl shadow-md transition-all text-xs cursor-pointer active:scale-98">
                ✋ กดรับงานนี้
            </button>
        </div>

    </div>
</div>

<!-- 🖼️ MODAL POPUP ขยายรูปภาพ -->
<div id="imagePreviewModal" class="hidden fixed inset-0 bg-slate-950/85 backdrop-blur-md z-[70] flex items-center justify-center p-4 cursor-pointer" onclick="closeImagePreviewModal()">
    <div class="relative max-w-4xl max-h-[90vh] w-full flex flex-col items-center justify-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeImagePreviewModal()" class="absolute -top-10 right-0 text-white font-black text-sm bg-white/20 hover:bg-white/30 w-8 h-8 rounded-full flex items-center justify-center transition-colors cursor-pointer">✕</button>
        <img id="preview_modal_img" src="" class="max-w-full max-h-[82vh] object-contain rounded-2xl shadow-2xl border border-white/20">
    </div>
</div>

<script>
    const currentSessionUserId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
    const currentSessionUserRole = <?php echo json_encode($_SESSION['user_role'] ?? $_SESSION['role'] ?? ''); ?>;

    function safeStr(val) {
        return (val !== null && val !== undefined) ? String(val).trim() : '';
    }

    function parsePhotoList(photoData) {
        if (!photoData) return [];
        if (Array.isArray(photoData)) return photoData;
        let str = safeStr(photoData);
        if (!str || str === 'null' || str === '[]' || str === '""' || str === "''") return [];

        str = str.replace(/\\/g, '');

        try {
            let parsed = JSON.parse(str);
            if (Array.isArray(parsed)) return parsed;
            if (typeof parsed === 'string' && parsed.trim() !== '') return [parsed];
        } catch (e) {}

        if (str.includes(',')) {
            return str.split(',').map(s => s.trim());
        }
        return [str];
    }

    function handleImgError(img, cleanName) {
        if (!img.dataset.step) {
            img.dataset.step = "1";
            img.src = "uploads/messenger_request/" + cleanName;
        } else if (img.dataset.step === "1") {
            img.dataset.step = "2";
            img.src = "../uploads/messenger_request/" + cleanName;
        } else if (img.dataset.step === "2") {
            img.dataset.step = "3";
            img.src = "messenger_request/" + cleanName;
        } else if (img.dataset.step === "3") {
            img.dataset.step = "4";
            img.src = cleanName;
        } else {
            if (img.parentElement) {
                img.parentElement.style.display = 'none';
            }
        }
    }

    function openImagePreviewModal(url) {
        const modal = document.getElementById('imagePreviewModal');
        const img = document.getElementById('preview_modal_img');
        if (modal && img) {
            img.src = url;
            modal.classList.remove('hidden');
        }
    }

    function closeImagePreviewModal() {
        const modal = document.getElementById('imagePreviewModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function openJobDetailModal(job) {
        if (!job) return;

        const st = safeStr(job.status) || 'pending';

        document.getElementById('detail_job_no').textContent = job.job_no || ('MSG-' + job.id);
        document.getElementById('detail_job_title').textContent = job.title || '-';

        // 🎯 แสดงป้ายสถานะหลัก 3 แบบในส่วน Header
        const badgeEl = document.getElementById('detail_status_badge');
        if (badgeEl) {
            if (st === 'completed') {
                badgeEl.innerHTML = '<span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full font-bold text-[10px]">✅ เสร็จสิ้น</span>';
            } else if (['accepted', 'picking_up', 'delivering'].includes(st)) {
                badgeEl.innerHTML = '<span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full font-bold text-[10px]">🛵 กำลังส่ง</span>';
            } else if (st === 'pending') {
                badgeEl.innerHTML = '<span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full font-bold text-[10px]">⏳ รอรับงาน</span>';
            } else if (st === 'cancelled') {
                badgeEl.innerHTML = '<span class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-full font-bold text-[10px]">❌ ยกเลิก</span>';
            } else {
                badgeEl.innerHTML = '';
            }
        }
        
        // ผู้จอง
        let reqNameText = job.requester_name || '-';
        let rPhone = safeStr(job.requester_phone);
        if (rPhone !== '') {
            reqNameText += ' (' + rPhone + ')';
        }
        document.getElementById('detail_requester_name').textContent = reqNameText;

        // TIMELINE FLOW
        const step1Dot = document.getElementById('step_1_dot');
        const step1Title = document.getElementById('step_1_title');
        const step1Sub = document.getElementById('step_1_sub');

        const step2Dot = document.getElementById('step_2_dot');
        const step2Title = document.getElementById('step_2_title');
        const step2Loc = document.getElementById('detail_pickup_loc');
        const step2ContactLbl = document.getElementById('detail_pickup_contact_lbl');

        const step3Dot = document.getElementById('step_3_dot');
        const step3Title = document.getElementById('step_3_title');
        const step3Loc = document.getElementById('detail_dropoff_loc');
        const step3ContactLbl = document.getElementById('detail_dropoff_contact_lbl');

        function setStepStyle(dot, title, mainText, subText, isActive, colorClass) {
            if (!dot || !title || !mainText) return;
            if (isActive) {
                dot.className = `absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-${colorClass}-500 ring-4 ring-${colorClass}-100`;
                title.className = `text-[10px] font-extrabold text-${colorClass}-600 uppercase tracking-wider`;
                mainText.className = 'font-bold text-slate-800 text-xs mt-0.5';
                if (subText) subText.className = 'text-[10.5px] text-slate-500 font-semibold mt-0.5';
            } else {
                dot.className = 'absolute -left-[17px] top-1 w-2.5 h-2.5 rounded-full bg-slate-300 ring-4 ring-slate-100';
                title.className = `text-[10px] font-bold text-slate-400 uppercase tracking-wider`;
                mainText.className = 'font-semibold text-slate-600 text-xs mt-0.5';
                if (subText) subText.className = 'text-[10.5px] text-slate-400 mt-0.5';
            }
        }

        if (st === 'pending') {
            setStepStyle(step1Dot, step1Title, step1Sub, null, true, 'amber');
            setStepStyle(step2Dot, step2Title, step2Loc, step2ContactLbl, false);
            setStepStyle(step3Dot, step3Title, step3Loc, step3ContactLbl, false);
        } else if (['accepted', 'picking_up', 'delivering'].includes(st)) {
            setStepStyle(step1Dot, step1Title, step1Sub, null, true, 'emerald');
            setStepStyle(step2Dot, step2Title, step2Loc, step2ContactLbl, true, 'blue');
            setStepStyle(step3Dot, step3Title, step3Loc, step3ContactLbl, false);
        } else if (st === 'completed') {
            setStepStyle(step1Dot, step1Title, step1Sub, null, true, 'emerald');
            setStepStyle(step2Dot, step2Title, step2Loc, step2ContactLbl, true, 'emerald');
            setStepStyle(step3Dot, step3Title, step3Loc, step3ContactLbl, true, 'emerald');
        }

        // 1. ต้นทาง
        document.getElementById('detail_pickup_loc').textContent = job.pickup_location || '-';
        let pickupContactText = job.pickup_contact || '-';
        let pPhone = safeStr(job.pickup_phone);
        if (pPhone !== '') {
            pickupContactText += ' (' + pPhone + ')';
        }
        document.getElementById('detail_pickup_contact_display').textContent = pickupContactText;

        // 2. ปลายทาง
        document.getElementById('detail_dropoff_loc').textContent = job.dropoff_location || '-';
        let dropoffContactText = job.dropoff_contact || '-';
        let dPhone = safeStr(job.dropoff_phone);
        if (dPhone !== '') {
            dropoffContactText += ' (' + dPhone + ')';
        }
        document.getElementById('detail_dropoff_contact_display').textContent = dropoffContactText;

        // 🗺️ ลิงก์แผนที่ปลายทาง
        let dMap = safeStr(job.dropoff_map_link || job.dropoff_map || job.map_link);
        const dMapContent = document.getElementById('detail_dropoff_map_content');
        if (dMapContent) {
            if (dMap !== '' && dMap !== '-' && dMap !== 'null') {
                dMapContent.innerHTML = `
                    <a href="${dMap}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/80 rounded-xl font-extrabold text-[11px] transition-all shadow-2xs hover:shadow-xs active:scale-95">
                        <span>🗺️</span> <span>เปิดดูแผนที่ Google Maps</span> <span class="text-emerald-500">➔</span>
                    </a>
                `;
            } else {
                dMapContent.innerHTML = `<span class="text-slate-400 font-bold text-xs">🗺️ แผนที่: -</span>`;
            }
        }

        // 3. วันที่ & ประเภทสิ่งของ
        document.getElementById('detail_booking_date').textContent = job.booking_date || '-';
        const itemTypeMap = { 
            'document': '📄 เอกสาร / ซองจดหมาย', 
            'parcel': '📦 กล่องพัสดุ / ของชิ้นเล็ก', 
            'other': '🏷️ อื่นๆ' 
        };
        document.getElementById('detail_item_type').textContent = itemTypeMap[job.item_type] || job.item_type || '-';
        document.getElementById('detail_remark').textContent = job.details || '-';

        // แมสเซนเจอร์
        let msgText = 'ยังไม่มีผู้รับงาน';
        if (job.messenger_name && safeStr(job.messenger_name) !== '') {
            msgText = job.messenger_name;
            let mPhone = safeStr(job.messenger_phone);
            if (mPhone !== '') {
                msgText += ' (' + mPhone + ')';
            }
        }
        document.getElementById('detail_messenger_display').textContent = msgText;

        // 🖼️ 4. ดึงและแสดงผลรูปภาพแนบประกอบ
        const photoGallery = document.getElementById('detail_photo_gallery');
        photoGallery.innerHTML = '';

        let rawPhotos = [
            ...parsePhotoList(job.item_photo),
            ...parsePhotoList(job.proof_photo)
        ];

        let validPhotos = [];
        rawPhotos.forEach(filename => {
            let f = safeStr(filename);
            if (f && f !== 'null' && f !== '[]' && f !== '""') {
                let cleanName = f.replace(/^.*[\\\/]/, '')
                                 .replace(/^["'\[\]]/g, '')
                                 .replace(/["'\[\]]$/g, '')
                                 .trim();

                if (cleanName && cleanName !== 'null' && !validPhotos.includes(cleanName)) {
                    validPhotos.push(cleanName);
                }
            }
        });

        if (validPhotos.length > 0) {
            validPhotos.forEach(cleanName => {
                const primaryUrl = 'uploads/messenger_request/' + cleanName;
                const card = document.createElement('div');
                card.className = 'block h-24 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 shadow-2xs hover:opacity-90 transition-all cursor-pointer group relative';
                card.onclick = function() { 
                    const currentImg = card.querySelector('img');
                    openImagePreviewModal(currentImg ? currentImg.src : primaryUrl); 
                };

                const img = document.createElement('img');
                img.src = primaryUrl;
                img.className = 'w-full h-full object-cover';
                img.onerror = function() { handleImgError(this, cleanName); };

                const overlay = document.createElement('div');
                overlay.className = 'absolute inset-0 bg-slate-900/20 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white transition-opacity';
                

                card.appendChild(img);
                card.appendChild(overlay);
                photoGallery.appendChild(card);
            });
        } else {
            photoGallery.innerHTML = '<p class="text-slate-400 font-bold col-span-4 pl-1">- ไม่มีรูปภาพแนบประกอบ -</p>';
        }

        // ควบคุมปุ่มกดรับงาน
        const acceptContainer = document.getElementById('detail_accept_btn_container');
        acceptContainer.classList.add('hidden');

        const canManageJob = (currentSessionUserRole === 'messenger' || currentSessionUserRole === 'hr' || currentSessionUserRole === 'admin');

        if (st === 'pending' && canManageJob) {
            acceptContainer.classList.remove('hidden');
            document.getElementById('detail_accept_btn').onclick = function() {
                confirmAcceptJobFromModal(job.id, job.job_no || ('MSG-' + job.id));
            };
        }

        // 🔒 ล็อคไม่ให้หน้าหลังเลื่อนขณะเปิด Modal
        document.body.classList.add('overflow-hidden');
        document.getElementById('jobDetailModal').classList.remove('hidden');
    }

    function closeJobDetailModal() {
        // 🔓 ปลดล็อคการเลื่อนเมื่อปิด Modal
        document.body.classList.remove('overflow-hidden');
        document.getElementById('jobDetailModal').classList.add('hidden');
    }

    function confirmAcceptJobFromModal(jobId, jobNo) {
        closeJobDetailModal();
        if (typeof LantoAlert !== 'undefined' && typeof LantoAlert.confirm === 'function') {
            LantoAlert.confirm('ยืนยันรับงาน', `คุณต้องการกดรับงาน ${jobNo} ใช่หรือไม่?`, function() {
                window.location.href = `process.php?action=accept_job&id=${jobId}`;
            }, null, 'approve');
        } else if (confirm(`ยืนยันกดรับงาน ${jobNo} ใช่หรือไม่?`)) {
            window.location.href = `process.php?action=accept_job&id=${jobId}`;
        }
    }

    document.getElementById('jobDetailModal').addEventListener('click', function(e) {
        if (e.target === this) closeJobDetailModal();
    });
</script>