<?php
/**
 * 🎨 ฟังก์ชันกลางสำหรับเสกกล่อง Dropdown ขอบมนพรีเมียมสากล (เวอร์ชันแก้บัคส่งค่าว่าง)
 */
function renderRoundedDropdown($id, $input_name, $placeholder, $options_array, $value = '') {
    ?>
    <div class="relative w-full text-left text-xs font-medium mb-1" id="custom-dropdown-<?php echo $id; ?>">
        <input type="hidden" id="<?php echo $id; ?>" name="<?php echo $input_name; ?>" value="<?php echo htmlspecialchars($value); ?>">

        <button type="button" onclick="toggleDropdown('<?php echo $id; ?>')" id="trigger-<?php echo $id; ?>"
            class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-3 text-slate-700 flex justify-between items-center shadow-sm hover:border-slate-300 transition-all cursor-pointer">
            <span id="label-<?php echo $id; ?>" class="<?php echo ($value !== '') ? 'text-slate-800 font-medium' : 'text-slate-500'; ?>"><?php echo $placeholder; ?></span>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" id="arrow-<?php echo $id; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>

        <div id="list-<?php echo $id; ?>" 
            class="hidden absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl border border-slate-200 rounded-2xl shadow-xl max-h-48 overflow-y-auto z-50 p-1.5 transition-all">
            
            <?php if (empty($options_array)): ?>
                <div class="px-4 py-2.5 text-slate-400">ไม่มีข้อมูลในระบบ</div>
            <?php else: ?>
                <?php foreach ($options_array as $opt): 
                    $data_attrs = $opt['data_attributes'] ?? '';
                ?>
                    <div onclick="selectDropdownOption('<?php echo $id; ?>', '<?php echo $opt['id']; ?>', '<?php echo htmlspecialchars($opt['name']); ?>')"
                         data-value="<?php echo $opt['id']; ?>"
                         class="dropdown-item px-3 py-2.5 rounded-xl text-slate-700 hover:bg-blue-50 hover:text-blue-700 transition-colors cursor-pointer flex items-center justify-between"
                         <?php echo $data_attrs; ?>>
                        <span><?php echo $opt['name']; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>

<script>
if (typeof dropdownScriptsLoaded === 'undefined') {
    var dropdownScriptsLoaded = true;

    function toggleDropdown(id) {
        const list = document.getElementById('list-' + id);
        const arrow = document.getElementById('arrow-' + id);
        
        document.querySelectorAll('[id^="list-"]').forEach(el => {
            if (el.id !== 'list-' + id) el.classList.add('hidden');
        });
        document.querySelectorAll('[id^="arrow-"]').forEach(el => {
            if (el.id !== 'arrow-' + id) el.classList.remove('rotate-180');
        });

        list.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
    }

    function selectDropdownOption(id, value, label) {
        const hiddenInput = document.getElementById(id);
        hiddenInput.value = value;
        
        const labelSpan = document.getElementById('label-' + id);
        labelSpan.textContent = label;
        labelSpan.className = "text-slate-800 font-medium";
        
        document.getElementById('list-' + id).classList.add('hidden');
        document.getElementById('arrow-' + id).classList.remove('rotate-180');

        // 🎯 เพิ่มสั่งงาน Trigger สำหรับ Dropdown สถานะการลา
        if (id === 'leave_status_select' && typeof switchLeaveItem === 'function') {
            switchLeaveItem(value);
        }

        if (id === 'branch_select' && typeof checkBranchDistance === 'function') {
            checkBranchDistance();
        }
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('[id^="custom-dropdown-"]')) {
            document.querySelectorAll('[id^="list-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="arrow-"]').forEach(el => el.classList.remove('rotate-180'));
        }
    });
}
</script>