<?php
/**
 * 🎨 ฟังก์ชัน Dropdown ขอบมนพรีเมียม (พิมพ์ค้นหาในช่องหลักได้โดยตรง + ไม่เด้งปิดตอนเลื่อนลูกกลิ้ง)
 */
function renderRoundedDropdown($id, $input_name, $placeholder, $options_array, $value = '', $enable_search = true) {
    // หาชื่อเริ่มต้นที่จะนำมาแสดงในช่องพิมพ์
    $initial_display = '';
    if ($value !== '') {
        foreach ($options_array as $opt) {
            if ((string)$opt['id'] === (string)$value) {
                $initial_display = $opt['name'];
                break;
            }
        }
    }
    if ($initial_display === '' && !empty($placeholder)) {
        $initial_display = $placeholder;
    }
    ?>
    <div class="relative w-full text-left text-xs font-medium mb-1" id="custom-dropdown-<?php echo $id; ?>">
        <!-- ค่า value จริงสำหรับส่ง Form -->
        <input type="hidden" id="<?php echo $id; ?>" name="<?php echo $input_name; ?>" value="<?php echo htmlspecialchars($value); ?>">

        <!-- ช่องหลัก: พิมพ์ค้นหาได้โดยตรง หรือคลิกเพื่อเปิดดูรายการ -->
        <div class="relative w-full">
            <input type="text"
                   id="input-display-<?php echo $id; ?>"
                   autocomplete="off"
                   value="<?php echo htmlspecialchars($initial_display); ?>"
                   placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                   onfocus="openDropdown('<?php echo $id; ?>')"
                   onclick="openDropdown('<?php echo $id; ?>')"
                   oninput="handleDropdownInput('<?php echo $id; ?>', this.value)"
                   class="w-full bg-white border border-slate-200 rounded-2xl pl-4 pr-10 py-3 text-slate-800 font-bold placeholder-slate-400 shadow-sm hover:border-slate-300 focus:outline-none focus:border-blue-500 transition-all cursor-pointer">
            
            <button type="button" 
                    onclick="toggleDropdown('<?php echo $id; ?>')" 
                    id="trigger-btn-<?php echo $id; ?>"
                    tabindex="-1"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                <svg class="w-4 h-4 transition-transform duration-200" id="arrow-<?php echo $id; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>

        <!-- Dropdown Outer Container -->
        <div id="list-<?php echo $id; ?>" 
             class="hidden absolute top-full left-0 right-0 mt-2.5 z-50">
            
            <!-- ติ่งลูกศรชี้ขึ้น -->
            <div class="absolute -top-[6px] right-6 w-3 h-3 bg-white border-t border-l border-slate-200/90 rotate-45 z-20"></div>

            <!-- กล่องรายการตัวเลือกแบบเลื่อนได้ -->
            <div class="bg-white/95 backdrop-blur-xl border border-slate-200 rounded-2xl shadow-xl max-h-56 overflow-y-auto p-2 relative z-10">
                <div id="items-container-<?php echo $id; ?>">
                    <?php if (empty($options_array)): ?>
                        <div class="px-4 py-2.5 text-slate-400 text-center">ไม่มีข้อมูลในระบบ</div>
                    <?php else: ?>
                        <?php foreach ($options_array as $opt): 
                            $data_attrs = $opt['data_attributes'] ?? '';
                        ?>
                            <div onclick="selectDropdownOption('<?php echo $id; ?>', '<?php echo $opt['id']; ?>', '<?php echo htmlspecialchars($opt['name'], ENT_QUOTES); ?>')"
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

    function openDropdown(id) {
        const list = document.getElementById('list-' + id);
        const arrow = document.getElementById('arrow-' + id);
        const input = document.getElementById('input-display-' + id);
        if (!list || !input) return;

        // ปิดตัวอื่นที่เปิดค้างอยู่
        document.querySelectorAll('[id^="list-"]').forEach(el => {
            if (el.id !== 'list-' + id) {
                el.classList.add('hidden');
                el.style.position = 'absolute';
            }
        });
        document.querySelectorAll('[id^="arrow-"]').forEach(el => {
            if (el.id !== 'arrow-' + id) el.classList.remove('rotate-180');
        });

        const rect = input.getBoundingClientRect();
        list.style.position = 'fixed';
        list.style.zIndex = '999999';
        list.style.width = Math.max(rect.width, 200) + 'px';
        list.style.left = rect.left + 'px';
        list.style.bottom = 'auto';
        list.style.top = (rect.bottom + 4) + 'px';

        list.classList.remove('hidden');
        if (arrow) arrow.classList.add('rotate-180');
    }

    function toggleDropdown(id) {
        const list = document.getElementById('list-' + id);
        if (!list) return;
        if (list.classList.contains('hidden')) {
            openDropdown(id);
        } else {
            list.classList.add('hidden');
            const arrow = document.getElementById('arrow-' + id);
            if (arrow) arrow.classList.remove('rotate-180');
        }
    }

    // เมื่อพิมพ์ในช่องหลัก ให้เปิดเมนูพร้อมกรองตัวเลือกทันที
    function handleDropdownInput(id, query) {
        openDropdown(id);
        filterDropdownOptions(id, query);
    }

    function selectDropdownOption(id, value, label) {
        const hiddenInput = document.getElementById(id);
        if (hiddenInput) hiddenInput.value = value;
        
        const inputDisplay = document.getElementById('input-display-' + id);
        if (inputDisplay) {
            inputDisplay.value = label;
        }
        
        const list = document.getElementById('list-' + id);
        if (list) list.classList.add('hidden');
        
        const arrow = document.getElementById('arrow-' + id);
        if (arrow) arrow.classList.remove('rotate-180');

        // คืนค่ารายการทั้งหมดให้พร้อมแสดงในครั้งต่อไป
        filterDropdownOptions(id, '');

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

    // ปิดเมื่อคลิกนอกพื้นที่ Dropdown
    document.addEventListener('click', function(e) {
        if (!e.target.closest('[id^="custom-dropdown-"]') && !e.target.closest('[id^="list-"]')) {
            document.querySelectorAll('[id^="list-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="arrow-"]').forEach(el => el.classList.remove('rotate-180'));
        }
    });

    // 🎯 แก้บัคเลื่อนลูกกลิ้ง: ถ้าเลื่อนภายในเมนู Dropdown จะไม่เด้งปิด
    window.addEventListener('scroll', function(e) {
        if (e.target && e.target.closest && e.target.closest('[id^="list-"]')) {
            return;
        }
        document.querySelectorAll('[id^="list-"]').forEach(el => {
            el.classList.add('hidden');
            el.style.position = 'absolute';
        });
        document.querySelectorAll('[id^="arrow-"]').forEach(el => {
            el.classList.remove('rotate-180');
        });
    }, true);
}
</script>
