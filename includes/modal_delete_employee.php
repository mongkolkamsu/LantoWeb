<!-- 📌 Modal ยืนยันการลบพนักงาน -->
<div id="deleteEmployeeModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 relative animate-in fade-in zoom-in duration-150">
        
        <div class="text-center space-y-2 pt-2">
            <div class="w-14 h-14 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center text-2xl font-bold mx-auto border border-rose-200 shadow-sm">
                🗑️
            </div>
            <h3 class="text-base font-extrabold text-slate-900">ยืนยันการลบข้อมูลพนักงาน</h3>
            <p class="text-slate-500 text-xs font-medium">คุณต้องการลบรายชื่อพนักงานที่เลือกจำนวน <span id="deleteCountText" class="text-rose-600 font-bold">0</span> รายการใช่หรือไม่?</p>
            <p class="text-[10px] text-rose-500 bg-rose-50 p-2.5 rounded-xl font-bold border border-rose-100">⚠️ การดำเนินการนี้ไม่สามารถยกเลิกหรือย้อนกลับข้อมูลได้</p>
        </div>

        <form method="POST" action="manage_employees.php">
            <input type="hidden" name="action" value="delete_employees">
            <input type="hidden" name="delete_ids" id="delete_target_ids" value="">

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeDeleteEmployeeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors cursor-pointer">
                    ยกเลิก
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition-colors shadow-md shadow-rose-500/20 cursor-pointer">
                    ยืนยันลบรายการ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDeleteModalFromBulk() {
        const ids = getSelectedEmployeeIds();
        if (ids.length === 0) return;

        document.getElementById('delete_target_ids').value = ids.join(',');
        document.getElementById('deleteCountText').textContent = ids.length;
        document.getElementById('deleteEmployeeModal').classList.remove('hidden');
    }

    function closeDeleteEmployeeModal() {
        document.getElementById('deleteEmployeeModal').classList.add('hidden');
    }
</script>