/**
 * Lanto Web - Global Custom Rounded Dropdown Component (เวอร์ชันล็อกระบบเลื่อนสกรอลล์)
 * ระบบแปลงดรอปดาวน์พื้นฐานให้ขอบมนลึก 100% พร้อมระบบป้องกันการเลื่อนทะลุไปหน้าจอพื้นหลัง (Scroll Lock)
 */
document.addEventListener('DOMContentLoaded', function() {
    
    function transformSelect(select) {
        if (select.classList.contains('lanto-transformed')) return;
        select.classList.add('lanto-transformed');

        // ซ่อนแท็ก select ดั้งเดิมไว้เบื้องหลัง
        select.style.display = 'none';

        const container = document.createElement('div');
        container.className = 'relative w-full lanto-dropdown-container';

        const button = document.createElement('div');
        button.className = 'w-full px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs flex justify-between items-center cursor-pointer shadow-sm transition-all focus:ring-2 focus:ring-blue-500 text-slate-700 select-none';
        
        const buttonText = document.createElement('span');
        buttonText.textContent = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : 'เลือกข้อมูล';
        
        const chevron = document.createElement('div');
        chevron.className = 'transition-transform duration-200 text-slate-400 flex items-center';
        chevron.innerHTML = `<svg class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>`;

        button.appendChild(buttonText);
        button.appendChild(chevron);
        container.appendChild(button);

        // 🛠️ จุดที่ 1: เพิ่มคลาส 'overscroll-contain' เพื่อสั่งให้เบราว์เซอร์กักบริเวณการเลื่อนสกรอลล์ไว้ข้างในกล่องกระจกฝ้านี้
        const menu = document.createElement('div');
        menu.className = 'lanto-dropdown-menu absolute z-50 left-0 w-full bg-white/95 backdrop-blur-xl border border-slate-200/80 rounded-2xl shadow-xl hidden max-h-60 overflow-y-auto overscroll-contain scrollbar-none transition-all';
        container.appendChild(menu);

        function renderOptions() {
            menu.innerHTML = '';
            Array.from(select.options).forEach((option, index) => {
                const item = document.createElement('div');
                item.className = 'p-3 text-xs text-slate-700 hover:bg-blue-50 hover:text-blue-600 cursor-pointer transition-colors first:rounded-t-2xl last:rounded-b-2xl';
                item.textContent = option.text;
                
                if (option.selected) {
                    item.className += ' bg-blue-50/50 text-blue-600 font-medium';
                }

                item.addEventListener('click', (e) => {
                    e.stopPropagation();
                    select.selectedIndex = index;
                    buttonText.textContent = option.text;
                    select.dispatchEvent(new Event('change'));
                    menu.classList.add('hidden');
                    chevron.querySelector('svg').classList.remove('rotate-180');
                });
                menu.appendChild(item);
            });
        }

        renderOptions();

        // 🔒 จุดที่ 2: [ระบบล็อกแรงหมุนเมาส์อัจฉริยะ] ดักจับการหมุนลูกกลิ้งเมาส์ (Wheel Event) 
        // หากผู้ใช้สกรอลล์เมาส์อยู่บนเมนูดร็อปดาวน์ แรงหมุนจะถูกบังคับให้อยู่ข้างใน ไม่ทะลุไปขยับหน้าเว็บด้านหลัง
        menu.addEventListener('wheel', function(e) {
            const scrollTop = menu.scrollTop;
            const scrollHeight = menu.scrollHeight;
            const height = menu.clientHeight;
            const delta = e.deltaY;

            // ตรรกะตรวจเช็ค: ถ้ากำลังพยายามเลื่อนขึ้นในขณะที่อยู่บนสุดแล้ว (scrollTop === 0)
            // หรือกำลังพยายามเลื่อนลงในขณะที่อยู่ล่างสุดแล้ว (scrollTop + height >= scrollHeight)
            // ให้ทำการยกเลิกคำสั่ง (preventDefault) เพื่อไม่ให้แรงเลื่อนกระจายตัวออกไปข้างนอกกล่อง
            if ((delta < 0 && scrollTop === 0) || (delta > 0 && scrollTop + height >= scrollHeight)) {
                e.preventDefault();
            }
        }, { passive: false }); // จำเป็นต้องตั้งค่า passive เป็น false เพื่อให้คำสั่ง preventDefault ทำงานได้ในเบราว์เซอร์รุ่นใหม่ๆ

        button.addEventListener('click', (e) => {
            e.stopPropagation();
            
            document.querySelectorAll('.lanto-dropdown-menu').forEach(m => {
                if (m !== menu) m.classList.add('hidden');
            });
            document.querySelectorAll('.lanto-dropdown-container svg').forEach(svg => {
                if (svg !== chevron.querySelector('svg')) svg.classList.remove('rotate-180');
            });

            const rect = button.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const menuHeight = 240;

            if (spaceBelow < menuHeight && rect.top > menuHeight) {
                menu.classList.remove('top-full', 'mt-2');
                menu.classList.add('bottom-full', 'mb-2');
            } else {
                menu.classList.remove('bottom-full', 'mb-2');
                menu.classList.add('top-full', 'mt-2');
            }

            menu.classList.toggle('hidden');
            chevron.querySelector('svg').classList.toggle('rotate-180');
        });

        const updateTextHandler = () => {
            if (select.options[select.selectedIndex]) {
                buttonText.textContent = select.options[select.selectedIndex].text;
            }
        };
        select.addEventListener('change', updateTextHandler);

        const observer = new MutationObserver(() => {
            renderOptions();
            updateTextHandler();
        });
        observer.observe(select, { childList: true });

        select.parentNode.insertBefore(container, select);
        container.appendChild(select);
    }

    function initLantoDropdowns() {
        document.querySelectorAll('select.lanto-select').forEach(transformSelect);
    }

    initLantoDropdowns();
    window.refreshLantoDropdowns = initLantoDropdowns;

    document.addEventListener('click', () => {
        document.querySelectorAll('.lanto-dropdown-menu').forEach(m => m.classList.add('hidden'));
        document.querySelectorAll('.lanto-dropdown-container svg').forEach(c => c.classList.remove('rotate-180'));
    });
});