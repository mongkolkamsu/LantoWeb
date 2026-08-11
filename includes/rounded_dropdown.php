<?php
/**
 * 🎨 ฟังก์ชันกลางสำหรับเสกกล่อง Dropdown ขอบมนพรีเมียม (เวอร์ชันมีติ่งลูกศร + ช่องพิมพ์ค้นหา)
 */
function renderRoundedDropdown($id, $input_name, $placeholder, $options_array, $value = '', $enable_search = true) {
    ?>
    <div class="relative w-full text-left text-xs font-medium mb-1" id="custom-dropdown-<?php echo $id; ?>">
        <input type="hidden" id="<?php echo $id; ?>" name="<?php echo $input_name; ?>" value="<?php echo htmlspecialchars($value); ?>">

        <button type="button" onclick="toggleDropdown('<?php echo $id; ?>')" id="trigger-<?php echo $id; ?>"
            class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-3 text-slate-700 flex justify-between items-center shadow-sm hover:border-slate-300 transition-all cursor-pointer">
            <span id="label-<?php echo $id; ?>" class="<?php echo ($value !== '') ? 'text-slate-800 font-medium' : 'text-slate-500'; ?>"><?php echo $placeholder; ?></span>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" id="arrow-<?php echo $id; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>

        <!-- Dropdown Outer Container -->
        <div id="list-<?php echo $id; ?>" 
            class="hidden absolute top-full left-0 right-0 mt-2.5 z-50">
            
            <!-- 🎯 ติ่งลูกศรชี้ขึ้น (Popover Arrow / Caret) -->
            <div class="absolute -top-[6px] right-6 w-3 h-3 bg-white border-t border-l border-slate-200/90 rotate-45 z-20"></div>

            <!-- Inner Scrollable Box -->
            <div class="bg-white/95 backdrop-blur-xl border border-slate-200 rounded-2xl shadow-xl max-h-56 overflow-y-auto p-2 relative z-10">
                
                <!-- 🔎 ช่องพิมพ์ค้นหารายชื่อ/ตัวเลือก -->
                <?php if ($enable_search): ?>
                    <div class="p-1 sticky top-0 bg-white/95 z-10 border-b border-slate-100 mb-1" onclick="event.stopPropagation()">
                        <input type="text" placeholder="🔍 พิมพ์เพื่อค้นหาชื่อ/รหัส..." oninput="filterDropdownOptions('<?php echo $id; ?>', this.value)"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:border-blue-500 font-bold">
                    </div>
                <?php endif; ?>

                <div id="items-container-<?php echo $id; ?>">
                    <?php if (empty($options_array)): ?>
                        <div class="px-4 py-2.5 text-slate-400 text-center">ไม่มีข้อมูลในระบบ</div>
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
        const trigger = document.getElementById('trigger-' + id);
        
        // ปิดดร็อปดาวน์ตัวอื่นที่เปิดอยู่
        document.querySelectorAll('[id^="list-"]').forEach(el => {
            if (el.id !== 'list-' + id) {
                el.classList.add('hidden');
                el.style.position = 'absolute';
            }
        });
        document.querySelectorAll('[id^="arrow-"]').forEach(el => {
            if (el.id !== 'arrow-' + id) el.classList.remove('rotate-180');
        });

        const isHidden = list.classList.contains('hidden');
        
        if (isHidden) {
            const rect = trigger.getBoundingClientRect();
            list.style.position = 'fixed';
            list.style.zIndex = '999999';
            list.style.width = Math.max(rect.width, 200) + 'px';
            list.style.left = rect.left + 'px';
            
            // 🎯 บังคับเปิดลงด้านล่างเสมอ ไม่ดีดขึ้นบน
            list.style.bottom = 'auto';
            list.style.top = (rect.bottom + 4) + 'px';

            // เปิดแสดงผลทันที
            list.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        } else {
            list.classList.add('hidden');
            arrow.classList.remove('rotate-180');
            list.style.position = 'absolute';
        }
    }

    function selectDropdownOption(id, value, label) {
        const hiddenInput = document.getElementById(id);
        if (hiddenInput) hiddenInput.value = value;
        
        const labelSpan = document.getElementById('label-' + id);
        if (labelSpan) {
            labelSpan.textContent = label;
            labelSpan.className = "truncate text-slate-800 font-bold";
        }
        
        const list = document.getElementById('list-' + id);
        if (list) list.classList.add('hidden');
        
        const arrow = document.getElementById('arrow-' + id);
        if (arrow) arrow.classList.remove('rotate-180');

        if (id.startsWith('perm_role_') && typeof changeUserRoleDirectly === 'function') {
            const userId = id.replace('perm_role_', '');
            changeUserRoleDirectly(userId, value, label);
        }

        if (id === 'leave_status_select' && typeof switchLeaveItem === 'function') {
            switchLeaveItem(value);
        }

        if (id === 'branch_select' && typeof checkBranchDistance === 'function') {
            checkBranchDistance();
        }
    }

    function filterDropdownOptions(id, query) {
        const q = query.toLowerCase().trim();
        const items = document.querySelectorAll('#items-container-' + id + ' .dropdown-item');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(q)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('[id^="custom-dropdown-"]')) {
            document.querySelectorAll('[id^="list-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="arrow-"]').forEach(el => el.classList.remove('rotate-180'));
        }
    });
}
    // 🎯 สั่งปิดดร็อปดาวน์อัตโนมัติเมื่อมีการเลื่อนหน้าจอ เพื่อไม่ให้มันลอยตามจอ
    window.addEventListener('scroll', function() {
        document.querySelectorAll('[id^="list-"]').forEach(el => {
            el.classList.add('hidden');
            el.style.position = 'absolute';
        });
        document.querySelectorAll('[id^="arrow-"]').forEach(el => {
            el.classList.remove('rotate-180');
        });
    }, true);
</script>