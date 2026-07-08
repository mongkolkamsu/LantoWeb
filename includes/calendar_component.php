<style>
    /* CSS สำหรับทั้งกล่องปฏิทิน และ กล่องเวลา สไตล์ Light Glassmorphism */
    .calendar-popup {
        position: absolute; display: none; z-index: 1000; 
        background: rgba(255, 255, 255, 0.85); 
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 1.2rem;
        border-radius: 1.5rem; /* ขอบมนลึกเข้าธีมเว็บ */
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.15); 
        border: 1px solid rgba(255, 255, 255, 0.6);
        font-family: 'Prompt', sans-serif;
        --cal-accent: #2563eb; 
        --cal-bg-hover: #eff6ff;
        --cal-text-today: #1d4ed8;
    }
    .calendar { width: 320px; }
    .calendar-header { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0 1rem 0; }
    .calendar-header button { background: none; border: 1px solid rgba(0,0,0,0.08); border-radius: 12px; padding: 0.4rem 0.8rem; cursor: pointer; font-size: 0.9rem; color: #475569; transition: all 0.2s; }
    .calendar-header button:hover { background: #f1f5f9; border-color: rgba(0,0,0,0.15); }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
    .day-name { padding: 0.5rem 0; font-size: 0.8rem; font-weight: 600; color: #64748b; }
    .day { padding: 0.6rem 0; font-size: 0.95rem; border-radius: 12px; cursor: pointer; color: #334155; transition: all 0.15s; position: relative; display: flex; align-items: center; justify-content: center; }
    .day:not(:empty):hover { background: #eff6ff; color: #2563eb; font-weight: 500; }
    
    .today { background: rgba(37, 99, 235, 0.08); font-weight: 600; color: var(--cal-text-today); border: 1px solid rgba(37, 99, 235, 0.2); }
    .range-single { border-radius: 12px !important; background: var(--cal-accent) !important; color: #fff !important; font-weight: 600; }

    /* ซ่อนแถบสกอร์บาร์ของดรอปดาวน์ทั้งหมดเพื่อให้ดูคลีน */
    .scrollbar-none::-webkit-scrollbar { display: none; }
    .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
    
    /* กล่องเลือกเดือน/ปี ดีไซน์ขอบมนลึกพิเศษ */
    .custom-dropdown-options {
        border-radius: 1.25rem !important;
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1) !important;
        overflow: hidden;
    }
</style>

<div id="calendarPopup" class="calendar-popup">
    <div class="calendar" id="dayCalendarView">
        <div class="calendar-header">
            <button type="button" id="calPrev">&larr;</button>
            <div class="flex space-x-2">
                <div class="relative" id="calMonthWrapper">
                    <div id="calMonthDisplay" class="text-sm font-semibold text-blue-600 bg-blue-50/60 px-3 py-1.5 rounded-xl border border-blue-100/50 flex items-center space-x-1 cursor-pointer select-none hover:bg-blue-100/80 transition-colors">
                        <span id="calMonthText">มกราคม</span>
                        <svg class="w-3 h-3 text-blue-500 transition-transform duration-200" id="calMonthIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    <div id="calMonthOptions" class="custom-dropdown-options absolute z-50 left-0 mt-1 w-32 bg-white rounded-xl hidden max-h-48 overflow-y-auto scrollbar-none text-sm text-gray-700"></div>
                </div>

                <div class="relative" id="calYearWrapper">
                    <div id="calYearDisplay" class="text-sm font-semibold text-blue-600 bg-gray-50 px-3 py-1.5 rounded-xl border border-gray-100 flex items-center space-x-1 cursor-pointer select-none hover:bg-gray-100 transition-colors">
                        <span id="calYearText">2569</span>
                        <svg class="w-3 h-3 text-blue-500 transition-transform duration-200" id="calYearIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    <div id="calYearOptions" class="custom-dropdown-options absolute z-50 left-0 mt-1 w-24 bg-white rounded-xl hidden max-h-48 overflow-y-auto scrollbar-none text-sm text-gray-700 text-center"></div>
                </div>
            </div>
            <button type="button" id="calNext">&rarr;</button>
        </div>
        <div class="calendar-grid" id="calGrid">
            <span class="day-name">จ</span><span class="day-name">อ</span><span class="day-name">พ</span>
            <span class="day-name">พฤ</span><span class="day-name">ศ</span><span class="day-name">ส</span><span class="day-name">อา</span>
        </div>
    </div>
</div>

<script>
    const calendarPopup = document.getElementById("calendarPopup");
    const grid = document.getElementById("calGrid");

    const now = new Date();
    let currentMonth = now.getMonth();
    let currentYear = now.getFullYear();
    let activeDateInput = null;
    let selectedDate = null; 

    const monthNames = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];

    const monthOptions = document.getElementById("calMonthOptions");
    const yearOptions = document.getElementById("calYearOptions");
    const monthDisplay = document.getElementById("calMonthDisplay");
    const yearDisplay = document.getElementById("calYearDisplay");
    const monthText = document.getElementById("calMonthText");
    const yearText = document.getElementById("calYearText");
    const monthIcon = document.getElementById("calMonthIcon");
    const yearIcon = document.getElementById("calYearIcon");

    if (monthOptions && yearOptions && monthOptions.children.length === 0) {
        monthNames.forEach((name, idx) => {
            let div = document.createElement("div");
            div.className = "p-2.5 hover:bg-blue-50 cursor-pointer transition-colors";
            div.textContent = name;
            div.onclick = (e) => {
                e.stopPropagation();
                currentMonth = idx;
                monthOptions.classList.add("hidden");
                monthIcon.classList.remove("rotate-180");
                renderCalendar();
            };
            monthOptions.appendChild(div);
        });

        const startYear = now.getFullYear() - 60;
        const endYear = now.getFullYear() + 5;
        for (let y = endYear; y >= startYear; y--) {
            let div = document.createElement("div");
            div.className = "p-2.5 hover:bg-blue-50 cursor-pointer transition-colors text-center";
            div.textContent = y + 543;
            div.onclick = (e) => {
                e.stopPropagation();
                currentYear = y;
                yearOptions.classList.add("hidden");
                yearIcon.classList.remove("rotate-180");
                renderCalendar();
            };
            yearOptions.appendChild(div);
        }

        monthDisplay.onclick = (e) => {
            e.stopPropagation();
            yearOptions.classList.add("hidden"); yearIcon.classList.remove("rotate-180");
            monthOptions.classList.toggle("hidden"); monthIcon.classList.toggle("rotate-180");
        };

        yearDisplay.onclick = (e) => {
            e.stopPropagation();
            monthOptions.classList.add("hidden"); monthIcon.classList.remove("rotate-180");
            yearOptions.classList.toggle("hidden"); yearIcon.classList.toggle("rotate-180");
        };
    }

    function renderCalendar() {
        grid.querySelectorAll(".day").forEach(d => d.remove());
        
        if (monthText) monthText.textContent = monthNames[currentMonth];
        if (yearText) yearText.textContent = currentYear + 543;

        let firstDay = new Date(currentYear, currentMonth, 1).getDay();
        firstDay = (firstDay + 6) % 7; 
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const today = new Date();

        for (let i = 0; i < firstDay; i++) {
            const blank = document.createElement("span");
            blank.className = "day";
            grid.appendChild(blank);
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const cell = document.createElement("span");
            cell.className = "day";
            cell.textContent = d;
            
            const cellDate = new Date(currentYear, currentMonth, d);

            if (selectedDate && cellDate.getTime() === selectedDate.getTime()) {
                cell.classList.add("range-single");
            } else if (d === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear()) {
                cell.classList.add("today");
            }

            cell.addEventListener("click", (e) => {
                e.stopPropagation();
                selectedDate = cellDate;
                
                const sD = cellDate.getDate().toString().padStart(2, '0');
                const sM = (cellDate.getMonth() + 1).toString().padStart(2, '0');
                const sY = cellDate.getFullYear() + 543; 

                activeDateInput.value = `${sD}/${sM}/${sY}`;
                calendarPopup.style.display = "none";
                renderCalendar();
            });

            grid.appendChild(cell);
        }
    }

    document.getElementById("calPrev").addEventListener("click", (e) => { e.stopPropagation(); currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } renderCalendar(); });
    document.getElementById("calNext").addEventListener("click", (e) => { e.stopPropagation(); currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } renderCalendar(); });

    function openCalendar(e) {
        activeDateInput = e.target;
        
        if (activeDateInput.value && activeDateInput.value.length === 10) {
            const parts = activeDateInput.value.split('/');
            if (parts.length === 3) {
                const d = parseInt(parts[0]);
                const m = parseInt(parts[1]) - 1;
                const y = parseInt(parts[2]) - 543;
                if (!isNaN(d) && !isNaN(m) && !isNaN(y) && m >= 0 && m <= 11 && d >= 1 && d <= 31) {
                    currentMonth = m;
                    currentYear = y;
                    selectedDate = new Date(currentYear, currentMonth, d);
                }
            }
        } else {
            currentMonth = now.getMonth();
            currentYear = now.getFullYear();
        }
        
        renderCalendar();

        const rect = activeDateInput.getBoundingClientRect();
        calendarPopup.style.display = "block";
        
        const popupHeight = calendarPopup.offsetHeight || 315; 
        const spaceBelow = window.innerHeight - rect.bottom;    
        
        if (spaceBelow < popupHeight && rect.top > popupHeight) {
            calendarPopup.style.top = (rect.top + window.scrollY - popupHeight - 8) + "px";
        } else {
            calendarPopup.style.top = (rect.bottom + window.scrollY + 6) + "px";
        }
        
        let leftPos = rect.left + window.scrollX;
        if (leftPos + 320 > window.innerWidth) leftPos = window.innerWidth - 340;
        calendarPopup.style.left = leftPos + "px";
    }

    // 🛠️ [แก้ไขจุดอัปเดตระบบพิมพ์อัจฉริยะ] ปลดล็อก readonly และดักจับการพิมพ์อัตโนมัติ
    function bindCalendarEvents() {
        document.querySelectorAll(".calendar-trigger").forEach(input => {
            // 1. ปลดล็อกเปิดทางให้พิมพ์ข้อมูลเองได้
            input.removeAttribute("readonly");
            // 2. ปรับข้อความใบ้ (Placeholder) สื่อสารกับพนักงานว่าพิมพ์ได้ด้วยนะ
            input.placeholder = "วว/ดด/ปปปป";

            input.removeEventListener("click", openCalendar); 
            input.addEventListener("click", openCalendar);
            
            // 3. ระบบ Auto-Mask เติมเครื่องหมายเครื่องหมายทับ (/) และขยับปฏิทินตามแบบ Realtime
            input.addEventListener("input", function(e) {
                let cleanValue = this.value.replace(/\D/g, ""); // กรองเก็บเฉพาะตัวเลขเท่านั้น
                if (cleanValue.length > 8) cleanValue = cleanValue.substring(0, 8); // ล็อกความยาว
                
                // คำนวณเพื่อยัดสแลชให้อัตโนมัติ
                let formatted = "";
                if (cleanValue.length > 0) {
                    formatted = cleanValue.substring(0, 2);
                    if (cleanValue.length > 2) {
                        formatted += "/" + cleanValue.substring(2, 4);
                        if (cleanValue.length > 4) {
                            formatted += "/" + cleanValue.substring(4, 8);
                        }
                    }
                }
                this.value = formatted; // แสดงข้อความที่จัดรูปแบบแล้วบนหน้าฟอร์ม
                
                // อัปเดตกล่องแอนิเมชันปฏิทินให้เด้งตามเดือนและปีเกิดที่พิมพ์ทันทีเมื่อพิมพ์ครบ 10 หลัก
                if (formatted.length === 10) {
                    const parts = formatted.split('/');
                    const d = parseInt(parts[0]);
                    const m = parseInt(parts[1]) - 1;
                    const y = parseInt(parts[2]) - 543; // ทอนค่ากลับเป็น ค.ศ. หลังบ้าน
                    
                    if (!isNaN(d) && !isNaN(m) && !isNaN(y) && m >= 0 && m <= 11 && d >= 1 && d <= 31) {
                        currentMonth = m;
                        currentYear = y;
                        selectedDate = new Date(currentYear, currentMonth, d);
                        renderCalendar(); // สั่งวาดปฏิทินใหม่ให้กระโดดข้ามปีทันที!
                    }
                }
            });
        });
    }

    document.addEventListener("DOMContentLoaded", bindCalendarEvents);

    document.addEventListener("click", (e) => {
        if (!calendarPopup.contains(e.target) && !e.target.classList.contains("calendar-trigger")) {
            calendarPopup.style.display = "none";
            if(monthOptions) monthOptions.classList.add("hidden");
            if(yearOptions) yearOptions.classList.add("hidden");
            if(monthIcon) monthIcon.classList.remove("rotate-180");
            if(yearIcon) yearIcon.classList.remove("rotate-180");
        }
    });
</script>