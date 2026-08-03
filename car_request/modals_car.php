<!-- 📌 MODAL 1: ฟอร์มยื่นคำขอจองรถ -->
<div id="bookingModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-2.5">
            <div>
                <h3 class="text-xs font-extrabold text-slate-800" id="modal_car_name">ยื่นขอจองรถ</h3>
                <p class="text-[10px] text-slate-400" id="modal_car_plate"></p>
            </div>
            <button type="button" onclick="closeBookingModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <form action="process.php" method="POST" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="request_car">
            <input type="hidden" name="car_id" id="modal_car_id" value="">
            <input type="hidden" name="booking_type" id="modal_booking_type" value="now">

            <!-- 🎯 ปุ่มเลือกโหมดการจอง -->
            <div class="grid grid-cols-2 gap-1 bg-slate-100 p-1 rounded-2xl text-xs font-bold">
                <button type="button" id="tab_now" onclick="switchBookingMode('now')" 
                    class="py-2 rounded-xl bg-blue-600 text-white shadow-xs transition-all cursor-pointer">
                    ⚡ ใช้งานทันที
                </button>
                <button type="button" id="tab_advance" onclick="switchBookingMode('advance')" 
                    class="py-2 rounded-xl text-slate-500 hover:text-slate-800 transition-all cursor-pointer">
                    📅 จองล่วงหน้า
                </button>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">สถานที่ปลายทาง / วัตถุประสงค์ <span class="text-rose-500">*</span></label>
                <input type="text" name="destination" required placeholder="เช่น ไปพบลูกค้า ที่สาขาบางนา" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">จำนวนผู้ร่วมเดินทาง (คน)</label>
                <input type="number" name="passenger_count" min="1" max="20" placeholder="ระบุจำนวน (ถ้ามี)" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">รายชื่อผู้ร่วมเดินทางไปด้วย</label>
                <textarea name="passengers_name" rows="2" placeholder="เช่น นาย A, นางสาว B (ถ้ามี)" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-medium focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <!-- วัน-เวลา เริ่มเดินทาง -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">วัน-เวลา เริ่มเดินทาง <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-2 relative">
                        <input type="text" name="start_date" id="modal_start_date" required autocomplete="off" 
                            class="calendar-trigger w-full bg-slate-50 border border-slate-200 rounded-xl pl-3 pr-8 py-2 font-medium focus:outline-none focus:border-blue-500 cursor-pointer" placeholder="วว/ดด/ปปปป">
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">📅</div>
                    </div>
                    <div class="relative">
                        <input type="text" name="start_time" id="modal_start_time" value="" placeholder="เวลาเริ่ม" required 
                            class="time-picker-trigger w-full bg-slate-50 border border-slate-200 rounded-xl px-2 py-2 font-bold focus:outline-none focus:border-blue-500 text-center cursor-pointer">
                    </div>
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeBookingModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md shadow-blue-500/20 transition-colors cursor-pointer">
                    ยืนยันส่งคำขอ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 🚗 MODAL NEW: บันทึกเลขไมล์ออกเดินทาง (สำหรับกดรับรถ) -->
<div id="startTripModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4 my-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-2.5">
            <div>
                <h3 class="text-xs font-extrabold text-slate-800" id="start_modal_title">รับรถ / เริ่มเดินทาง</h3>
                <p class="text-[10px] text-slate-400">ระบุเลขไมล์เริ่มต้นเพื่อเปิดการใช้งานรถ</p>
            </div>
            <button type="button" onclick="closeStartTripModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <form action="process.php" method="POST" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="start_trip">
            <input type="hidden" name="request_id" id="start_trip_request_id" value="">

            <div>
                <label class="block font-bold text-slate-700 mb-1">เลขไมล์เริ่มต้นก่อนออกเดินทาง (กม.) <span class="text-rose-500">*</span></label>
                <input type="number" name="start_mileage" id="input_trip_start_mileage" min="0" required placeholder="กรอกเลขไมล์ปัจจุบันของรถ" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-blue-600 focus:outline-none focus:border-blue-500 text-sm">
            </div>

            <div class="flex gap-2 pt-1">
                <button type="button" onclick="closeStartTripModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md shadow-blue-500/20 transition-colors cursor-pointer">
                    🚗 เริ่มเดินทาง
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 👑 MODAL 2: ฟอร์มเพิ่มรถใหม่ (เฉพาะสิทธิ์ Admin/HR/IT) -->
<?php if (!empty($can_manage_cars)): ?>
<div id="addCarModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4 my-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-2.5">
            <h3 class="text-xs font-extrabold text-purple-900 flex items-center gap-1.5"><span>➕</span> เพิ่มรถยนต์เข้าสู่ระบบ</h3>
            <button type="button" onclick="closeAddCarModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <form action="index.php" method="POST" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="add_car">

            <div>
                <label class="block font-bold text-slate-700 mb-1">ยี่ห้อ / รุ่นรถยนต์ <span class="text-rose-500">*</span></label>
                <input type="text" name="brand_model" required placeholder="เช่น Ford-เทา, Toyota Commuter" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-purple-500">
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">ทะเบียนรถ <span class="text-rose-500">*</span></label>
                    <input type="text" name="license_plate" required placeholder="เช่น 2 ฌค 7992" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">จังหวัด <span class="text-rose-500">*</span></label>
                    <input type="text" name="province" required placeholder="เช่น กรุงเทพมหานคร" value="กรุงเทพมหานคร"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-purple-500">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">จำนวนที่นั่ง</label>
                <input type="number" name="seats" min="1" max="50" value="4" required 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold focus:outline-none focus:border-purple-500">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeAddCarModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-md shadow-purple-500/20 transition-colors cursor-pointer">
                    บันทึกเพิ่มรถ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 👑 MODAL 3: ฟอร์มแก้ไขรถยนต์ (เฉพาะสิทธิ์ Admin/HR/IT) -->
<div id="editCarModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4 my-auto overflow-visible">
        <div class="flex justify-between items-center border-b border-slate-100 pb-2.5">
            <h3 class="text-xs font-extrabold text-purple-900 flex items-center gap-1.5"><span>✏️</span> แก้ไขข้อมูลรถยนต์</h3>
            <button type="button" onclick="closeEditCarModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <form action="index.php" method="POST" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="edit_car">
            <input type="hidden" name="car_id" id="edit_car_id" value="">

            <div>
                <label class="block font-bold text-slate-700 mb-1">ยี่ห้อ / รุ่นรถยนต์ <span class="text-rose-500">*</span></label>
                <input type="text" name="brand_model" id="edit_brand_model" required 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-purple-500">
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">ทะเบียนรถ <span class="text-rose-500">*</span></label>
                    <input type="text" name="license_plate" id="edit_license_plate" required 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">จังหวัด <span class="text-rose-500">*</span></label>
                    <input type="text" name="province" id="edit_province" required 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-medium focus:outline-none focus:border-purple-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">จำนวนที่นั่ง</label>
                    <input type="number" name="seats" id="edit_seats" min="1" max="50" required 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold focus:outline-none focus:border-purple-500">
                </div>
                
                <div class="relative">
                    <label class="block font-bold text-slate-700 mb-1">สถานะใช้งาน <span class="text-rose-500">*</span></label>
                    <input type="hidden" name="is_active" id="edit_is_active" value="1">

                    <button type="button" onclick="toggleEditStatusDropdown()" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-700 flex items-center justify-between hover:bg-slate-100 transition-colors cursor-pointer">
                        <span id="edit_is_active_label">🟢 พร้อมใช้งาน</span>
                        <span class="text-[10px] text-slate-400">▼</span>
                    </button>

                    <div id="editStatusDropdownMenu" class="hidden absolute top-full left-0 right-0 mt-1 bg-white/95 backdrop-blur-xl border border-slate-200/80 rounded-2xl shadow-xl z-50 p-1.5 space-y-0.5">
                        <div onclick="selectEditStatus(1, '🟢 พร้อมใช้งาน')" 
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer text-slate-700 hover:bg-purple-50 hover:text-purple-600 transition-colors">
                            🟢 พร้อมใช้งาน
                        </div>
                        <div onclick="selectEditStatus(0, '🔴 งดใช้งาน / ซ่อมบำรุง')" 
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer text-slate-700 hover:bg-rose-50 hover:text-rose-600 transition-colors">
                            🔴 งดใช้งาน / ซ่อมบำรุง
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeEditCarModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-md shadow-purple-500/20 transition-colors cursor-pointer">
                    บันทึกการแก้ไข
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 👑 MODAL 4: รายการคำขอรออนุมัติ (เฉพาะ Admin / HR / IT) -->
<div id="pendingApprovalModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4 my-auto max-h-[85vh] flex flex-col">
        
        <div class="flex justify-between items-center border-b border-slate-100 pb-2.5 shrink-0">
            <h3 class="text-xs font-extrabold text-slate-800 flex items-center gap-1.5">
                <span>✅</span> คำขอรอการอนุมัติ
                <?php if (!empty($pending_count)): ?>
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[10px] font-bold"><?php echo $pending_count; ?> รายการ</span>
                <?php endif; ?>
            </h3>
            <button type="button" onclick="closePendingApprovalModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <div class="overflow-y-auto space-y-3 pr-1">
            <?php if (empty($pending_requests)): ?>
                <div class="p-6 text-center text-slate-400 text-xs font-light">
                    🎉 ไม่มีคำขอรอการอนุมัติในขณะนี้
                </div>
            <?php else: ?>
                <?php foreach ($pending_requests as $p_req): 
                    $p_start    = new DateTime($p_req['start_datetime']);
                ?>
                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-200/80 text-xs space-y-2">
                        <div class="border-b border-slate-200/60 pb-1.5 flex items-center justify-between">
                            <div>
                                <h4 class="font-extrabold text-slate-800"><?php echo htmlspecialchars($p_req['brand_model']); ?></h4>
                                <span class="text-[10px] font-bold text-slate-500 bg-white px-1.5 py-0.5 rounded border border-slate-200">
                                    <?php echo htmlspecialchars($p_req['license_plate'] . ' ' . $p_req['province']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-1 text-[11px] text-slate-600">
                            <p>👤 <strong>ผู้จอง:</strong> <?php echo htmlspecialchars($p_req['requester_name']); ?> <?php echo !empty($p_req['passenger_count']) ? '(👥 ' . $p_req['passenger_count'] . ' คน)' : ''; ?></p>
                            
                            <!-- 🎯 เพิ่มส่วนแสดงรายชื่อผู้ร่วมเดินทาง -->
                            <?php if (!empty($p_req['passengers_name'])): ?>
                                <p>👥 <strong>ผู้ร่วมเดินทาง:</strong> <span class="text-slate-800 font-semibold"><?php echo htmlspecialchars($p_req['passengers_name']); ?></span></p>
                            <?php endif; ?>

                            <p>📍 <strong>จุดหมาย:</strong> <?php echo htmlspecialchars($p_req['destination']); ?></p>
                            <p class="text-blue-900 font-semibold">📅 <strong>เริ่มเดินทาง:</strong> <?php echo $p_start->format('d/m/Y H:i'); ?> น.</p>
                            <p class="text-purple-600 font-semibold">🚘 <strong>เลขไมล์:</strong> รอระบุตอนกดรับรถ</p>
                        </div>

                        <div class="flex gap-2 pt-1 border-t border-slate-200/60">
                            <form action="process.php" method="POST" class="flex-1">
                                <input type="hidden" name="action" value="approve_booking">
                                <input type="hidden" name="request_id" value="<?php echo $p_req['id']; ?>">
                                <button type="submit" class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-colors cursor-pointer">
                                    ✅ อนุมัติ
                                </button>
                            </form>

                            <button type="button" onclick="openRejectModalFromIndex(<?php echo $p_req['id']; ?>)" class="flex-1 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl text-xs border border-rose-200 transition-colors cursor-pointer">
                                ❌ ปฏิเสธ
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- 👑 MODAL 5: ระบุเหตุผลการปฏิเสธ (สำหรับหน้า Index) -->
<div id="indexRejectModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl space-y-4 my-auto">
        <h3 class="text-xs font-extrabold text-slate-800 border-b border-slate-100 pb-2">ระบุเหตุผลที่ไม่อนุมัติ</h3>
        <form action="process.php" method="POST" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="reject_booking">
            <input type="hidden" name="request_id" id="index_reject_request_id" value="">

            <div>
                <textarea name="reject_reason" required rows="3" placeholder="เช่น รถติดภารกิจซ่อมบำรุง / ติดคิวผู้บริหาร" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 font-medium focus:outline-none focus:border-rose-500"></textarea>
            </div>

            <div class="flex gap-2">
                <button type="button" onclick="closeIndexRejectModal()" class="flex-1 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl cursor-pointer">ยกเลิก</button>
                <button type="submit" class="flex-1 py-2 bg-rose-600 text-white font-bold rounded-xl shadow-xs cursor-pointer">ยืนยันปฏิเสธ</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 🏁 MODAL 6: บันทึกคืนรถยนต์ -->
<div id="returnCarModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-100 space-y-4 my-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-2.5">
            <div>
                <h3 class="text-xs font-extrabold text-slate-800" id="return_modal_title">บันทึกคืนรถยนต์</h3>
                <p class="text-[10px] text-slate-400">ระบุเลขไมล์และยืนยันเวลาคืนรถ</p>
            </div>
            <button type="button" onclick="closeReturnCarModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition-colors cursor-pointer">✕</button>
        </div>

        <form action="process.php" method="POST" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="return_car">
            <input type="hidden" name="request_id" id="return_request_id" value="">

            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/60 space-y-1.5">
                <div class="flex justify-between items-center text-[11px] text-slate-600">
                    <span>เวลาคืนรถ:</span>
                    <strong class="text-blue-600 font-extrabold" id="display_return_time">-</strong>
                </div>
                <div class="flex justify-between items-center text-[11px] text-slate-500 pt-1 border-t border-slate-200/60">
                    <span>เลขไมล์ก่อนออกเดินทาง:</span>
                    <strong class="text-slate-800" id="display_start_mileage">0 กม.</strong>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">เลขไมล์เมื่อคืนรถ (กม.) <span class="text-rose-500">*</span></label>
                <input type="number" name="end_mileage" id="input_end_mileage" required placeholder="กรอกเลขไมล์ปัจจุบัน" 
                    oninput="calculateDistance()"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-blue-600 focus:outline-none focus:border-blue-500 text-sm">
            </div>

            <div class="bg-blue-50/60 border border-blue-100 p-2.5 rounded-xl text-center">
                <span class="text-[11px] text-blue-700 font-medium">ระยะทางที่ใช้ไปทั้งหมด: </span>
                <strong class="text-xs text-blue-900 font-extrabold" id="display_distance">0 กม.</strong>
            </div>

            <div class="flex gap-2 pt-1">
                <button type="button" onclick="closeReturnCarModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md shadow-emerald-500/20 transition-colors cursor-pointer">
                    ยืนยันคืนรถ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentStartMileage = 0;

    function openReturnCarModal(reqId, carName, startMileage) {
        currentStartMileage = startMileage;
        document.getElementById('return_request_id').value = reqId;
        document.getElementById('return_modal_title').innerText = 'คืนรถ ' + carName;
        document.getElementById('display_start_mileage').innerText = startMileage.toLocaleString() + ' กม.';
        document.getElementById('input_end_mileage').value = '';
        document.getElementById('display_distance').innerText = '0 กม.';

        const now = new Date();
        const nowStr = now.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: 'numeric' }) + 
                       ' เวลา ' + now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' }) + ' น.';
        document.getElementById('display_return_time').innerText = nowStr;

        document.getElementById('returnCarModal').classList.remove('hidden');
    }

    function closeReturnCarModal() {
        document.getElementById('returnCarModal').classList.add('hidden');
    }

    function calculateDistance() {
        const endVal = parseInt(document.getElementById('input_end_mileage').value) || 0;
        const diff = endVal - currentStartMileage;
        if (diff >= 0) {
            document.getElementById('display_distance').innerText = diff.toLocaleString() + ' กม.';
        } else {
            document.getElementById('display_distance').innerText = 'เลขไมล์ไม่ถูกต้อง';
        }
    }
</script>

<style>
    #calendarPopup {
        padding: 0.75rem !important;
        border-radius: 1.25rem !important;
    }
    #calendarPopup .calendar {
        width: 250px !important;
    }
    #calendarPopup .calendar-header {
        padding: 0.1rem 0 0.4rem 0 !important;
    }
    #calendarPopup .day-name {
        padding: 0.2rem 0 !important;
        font-size: 0.7rem !important;
    }
    #calendarPopup .day {
        padding: 0.3rem 0 !important;
        font-size: 0.8rem !important;
        border-radius: 8px !important;
    }
</style>