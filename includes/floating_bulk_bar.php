<!-- 📌 Floating Bulk Action Bar -->
<div id="bulkActionBar" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900/90 text-white backdrop-blur-md px-6 py-3.5 rounded-2xl shadow-2xl border border-slate-700/50 flex items-center gap-5 z-40 animate-in fade-in slide-in-from-bottom-4 duration-200">
    <div class="flex items-center gap-2 text-xs font-bold">
        <span class="w-6 h-6 bg-blue-600 text-white rounded-lg flex items-center justify-center text-[11px]" id="selectedCount">0</span>
        <span class="text-slate-200">รายการที่เลือก</span>
    </div>
    <div class="h-4 w-px bg-slate-700"></div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="openEditModalFromBulk()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-blue-600/30 flex items-center gap-1.5 cursor-pointer active:scale-95">
            <span>✏️</span> แก้ไขข้อมูล
        </button>
        <button type="button" onclick="openDeleteModalFromBulk()" class="px-4 py-2 bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 cursor-pointer active:scale-95">
            <span>🗑️</span> ลบรายการที่เลือก
        </button>
    </div>
    <button type="button" onclick="clearSelections()" class="text-slate-400 hover:text-white text-xs font-bold transition-colors ml-2 cursor-pointer" title="ยกเลิกการเลือก">✕</button>
</div>

<script>
    function updateBulkBar() {
        const checkedBoxes = document.querySelectorAll('.emp-checkbox:checked');
        const bar = document.getElementById('bulkActionBar');
        const countSpan = document.getElementById('selectedCount');
        const selectAllHeader = document.getElementById('selectAll');

        if (checkedBoxes.length > 0) {
            bar.classList.remove('hidden');
            countSpan.textContent = checkedBoxes.length;
        } else {
            bar.classList.add('hidden');
            if (selectAllHeader) selectAllHeader.checked = false;
        }
    }

    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.emp-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
        updateBulkBar();
    }

    function clearSelections() {
        const checkboxes = document.querySelectorAll('.emp-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        const selectAllHeader = document.getElementById('selectAll');
        if (selectAllHeader) selectAllHeader.checked = false;
        updateBulkBar();
    }

    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('emp-checkbox')) {
            updateBulkBar();
        }
    });

    // 🎯 ดึงข้อมูลพนักงานส่งเข้า Modal
    function getSelectedEmployeesData() {
        const checkedBoxes = document.querySelectorAll('.emp-checkbox:checked');
        return Array.from(checkedBoxes).map(cb => ({
            id: cb.value,
            code: cb.dataset.code || '',
            firstname: cb.dataset.firstname || '', // 👈 เพิ่มบรรทัดนี้
            lastname: cb.dataset.lastname || '',   // 👈 เพิ่มบรรทัดนี้
            fullname: cb.dataset.fullname || '',
            email: cb.dataset.email || '',
            phone: cb.dataset.phone || '',         // 👈 เพิ่มบรรทัดนี้
            role: cb.dataset.role || 'employee',
            birth: cb.dataset.birth || '',
            startdate: cb.dataset.startdate || '',
            avatar: cb.dataset.avatar || '',
            idcard: cb.dataset.idcard || '',
            dept: cb.dataset.dept || '',
            branch: cb.dataset.branch || '',
            type: cb.dataset.type || '',
            shift: cb.dataset.shift || '',
            status: cb.dataset.status || 'active'
        }));
    }

    function getSelectedEmployeeIds() {
        return getSelectedEmployeesData().map(emp => emp.id);
    }
</script>