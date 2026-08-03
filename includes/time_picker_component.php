<style>
    /* CSS ป็อปอัปเลือกเวลา สไตล์ Glassmorphism */
    .time-picker-popup {
        position: absolute; display: none; z-index: 1050;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 1.2rem;
        border-radius: 1.5rem;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.8);
        font-family: 'Noto Sans Thai', 'Prompt', sans-serif;
        width: 260px;
        user-select: none;
    }
    .tp-title { font-size: 0.85rem; font-weight: 700; color: #475569; text-align: center; margin-bottom: 0.75rem; }

    /* คอลัมน์เลือก ชั่วโมง - นาที */
    .tp-wheels-container { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .tp-column { height: 160px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 12px; background: #f8fafc; padding: 4px; scroll-behavior: smooth; }
    .tp-column::-webkit-scrollbar { width: 4px; }
    .tp-column::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    
    .tp-item {
        padding: 0.4rem 0; text-align: center; font-size: 0.85rem; font-weight: 600;
        color: #475569; border-radius: 8px; cursor: pointer; transition: all 0.15s;
    }
    .tp-item:hover { background: #e2e8f0; }
    .tp-item.active { background: #2563eb !important; color: #ffffff !important; font-weight: 700; }
</style>

<div id="timePickerPopup" class="time-picker-popup">
    <div class="tp-title">⏰ เลือกเวลา</div>

    <!-- เลือกระบุชั่วโมง และ นาที -->
    <div class="tp-wheels-container">
        <div>
            <div class="text-[10px] font-bold text-slate-400 text-center mb-1">ชั่วโมง</div>
            <div class="tp-column" id="tpHourCol"></div>
        </div>
        <div>
            <div class="text-[10px] font-bold text-slate-400 text-center mb-1">นาที</div>
            <div class="tp-column" id="tpMinuteCol"></div>
        </div>
    </div>

    <div class="mt-3 flex gap-2">
        <button type="button" onclick="confirmTimePicker()" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-xs transition-all cursor-pointer">
            ตกลง
        </button>
    </div>
</div>

<script>
    const tpPopup = document.getElementById('timePickerPopup');
    const tpHourCol = document.getElementById('tpHourCol');
    const tpMinuteCol = document.getElementById('tpMinuteCol');

    let activeTimeInput = null;
    let selectedHour = '08';
    let selectedMinute = '00';

    // สร้างรายการชั่วโมง (00 - 23)
    for (let h = 0; h < 24; h++) {
        const val = h.toString().padStart(2, '0');
        const div = document.createElement('div');
        div.className = 'tp-item';
        div.dataset.hour = val;
        div.textContent = val;
        div.onclick = (e) => {
            e.stopPropagation();
            selectedHour = val;
            updateTimeSelection();
        };
        tpHourCol.appendChild(div);
    }

    // สร้างรายการนาทีทีละ 1 นาที (00 - 59)
    for (let m = 0; m < 60; m++) {
        const val = m.toString().padStart(2, '0');
        const div = document.createElement('div');
        div.className = 'tp-item';
        div.dataset.minute = val;
        div.textContent = val;
        div.onclick = (e) => {
            e.stopPropagation();
            selectedMinute = val;
            updateTimeSelection();
        };
        tpMinuteCol.appendChild(div);
    }

    function updateTimeSelection(shouldScroll = false) {
        tpHourCol.querySelectorAll('.tp-item').forEach(el => {
            const isActive = el.dataset.hour === selectedHour;
            el.classList.toggle('active', isActive);
            if (isActive && shouldScroll) {
                el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });

        tpMinuteCol.querySelectorAll('.tp-item').forEach(el => {
            const isActive = el.dataset.minute === selectedMinute;
            el.classList.toggle('active', isActive);
            if (isActive && shouldScroll) {
                el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });

        if (activeTimeInput) {
            activeTimeInput.value = `${selectedHour}:${selectedMinute}`;
        }
    }

    function confirmTimePicker() {
        updateTimeSelection();
        tpPopup.style.display = 'none';
    }

    function openTimePicker(e) {
        activeTimeInput = e.target;
        
        if (activeTimeInput.value && activeTimeInput.value.includes(':')) {
            const parts = activeTimeInput.value.split(':');
            selectedHour = parts[0].padStart(2, '0');
            selectedMinute = parts[1].padStart(2, '0');
        } else {
            const now = new Date();
            selectedHour = now.getHours().toString().padStart(2, '0');
            selectedMinute = now.getMinutes().toString().padStart(2, '0');
        }

        tpPopup.style.display = 'block';
        updateTimeSelection(true);

        const rect = activeTimeInput.getBoundingClientRect();
        const popupHeight = tpPopup.offsetHeight || 250;
        const popupWidth = tpPopup.offsetWidth || 260;

        const spaceBelow = window.innerHeight - rect.bottom;
        if (spaceBelow < popupHeight && rect.top > popupHeight) {
            tpPopup.style.top = (rect.top + window.scrollY - popupHeight - 8) + 'px';
        } else {
            tpPopup.style.top = (rect.bottom + window.scrollY + 6) + 'px';
        }

        let leftPos = rect.left + window.scrollX;
        if (leftPos + popupWidth > window.innerWidth - 16) {
            leftPos = window.innerWidth - popupWidth - 16;
        }
        if (leftPos < 16) leftPos = 16;

        tpPopup.style.left = leftPos + 'px';
    }

    function bindTimePickerEvents() {
        document.querySelectorAll('.time-picker-trigger').forEach(input => {
            input.readOnly = true;
            input.placeholder = '00:00';
            input.removeEventListener('click', openTimePicker);
            input.addEventListener('click', openTimePicker);
        });
    }

    document.addEventListener('DOMContentLoaded', bindTimePickerEvents);

    document.addEventListener('click', (e) => {
        if (!tpPopup.contains(e.target) && !e.target.classList.contains('time-picker-trigger')) {
            tpPopup.style.display = 'none';
        }
    });
</script>