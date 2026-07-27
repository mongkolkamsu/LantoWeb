<style>
    /* CSS สำหรับกล่องปฏิทิน สไตล์ Glassmorphism */
    .calendar-popup {
        position: absolute; display: none; z-index: 1000; 
        background: rgba(255, 255, 255, 0.95); 
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 1.2rem;
        border-radius: 1.5rem;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.2); 
        border: 1px solid rgba(255, 255, 255, 0.8);
        font-family: 'Noto Sans Thai', 'Prompt', sans-serif;
        --cal-accent: #2563eb; 
        --cal-bg-hover: #eff6ff;
        --cal-text-today: #1d4ed8;

        /* 🚫 ป้องกันการคลุมดำข้อความเวลาลากเมาส์ */
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }
    .calendar { width: 320px; }
    .calendar-header { display: flex; justify-content: space-between; align-items: center; padding: 0.3rem 0 0.8rem 0; }
    .calendar-header button { background: none; border: 1px solid rgba(0,0,0,0.08); border-radius: 12px; padding: 0.4rem 0.8rem; cursor: pointer; font-size: 0.9rem; color: #475569; transition: all 0.2s; }
    .calendar-header button:hover { background: #f1f5f9; border-color: rgba(0,0,0,0.15); }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
    .day-name { padding: 0.5rem 0; font-size: 0.8rem; font-weight: 600; color: #64748b; }
    .day { padding: 0.6rem 0; font-size: 0.95rem; border-radius: 12px; cursor: pointer; color: #334155; transition: all 0.15s; position: relative; display: flex; align-items: center; justify-content: center; }
    .day:not(:empty):hover { background: #eff6ff; color: #2563eb; font-weight: 500; }
    
    .today { background: rgba(37, 99, 235, 0.08); font-weight: 600; color: var(--cal-text-today); border: 1px solid rgba(37, 99, 235, 0.2); }
    
    /* CSS ไฮไลต์ช่วงวัน */
    .range-single { border-radius: 12px !important; background: var(--cal-accent) !important; color: #fff !important; font-weight: 600; }
    .range-start { border-radius: 12px 0 0 12px !important; background: var(--cal-accent) !important; color: #fff !important; font-weight: 600; }
    .range-end { border-radius: 0 12px 12px 0 !important; background: var(--cal-accent) !important; color: #fff !important; font-weight: 600; }
    .range-in-between { border-radius: 0 !important; background: #dbeafe !important; color: #1e40af !important; font-weight: 600; }

    /* CSS สำหรับตารางเลือกเดือน */
    .month-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
    .month-card { padding: 0.75rem 0.5rem; font-size: 0.85rem; font-weight: 600; border-radius: 12px; border: 1px solid #f1f5f9; background: #f8fafc; cursor: pointer; transition: all 0.15s; color: #334155; }
    .month-card:hover { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .month-active { background: #2563eb !important; color: #ffffff !important; border-color: #2563eb !important; }
    .month-in-between { background: #dbeafe !important; color: #1e40af !important; border-color: #bfdbfe !important; }

    .scrollbar-none::-webkit-scrollbar { display: none; }
    .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div id="calendarPopup" class="calendar-popup">
    <div class="calendar">
        <!-- 🔘 แถบเลือกโหมด: วัน หรือ เดือน -->
        <div class="flex border-b border-slate-200/80 mb-3 pb-2 text-[11px] font-bold gap-1.5">
            <button type="button" id="tabDayMode" onclick="switchCalTab('day')" class="flex-1 py-1.5 rounded-xl bg-blue-600 text-white transition-all shadow-xs">📅 เลือกวัน/ช่วงวัน</button>
            <button type="button" id="tabMonthMode" onclick="switchCalTab('month')" class="flex-1 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">🗓️ เลือกเดือน/ช่วงเดือน</button>
        </div>

        <!-- 1️⃣ VIEW: โหมดเลือกรายวัน / ช่วงวัน -->
        <div id="dayCalendarView">
            <div class="calendar-header">
                <button type="button" id="calPrev">&larr;</button>
                <div class="flex space-x-2">
                    <div class="relative" id="calMonthWrapper">
                        <div id="calMonthDisplay" class="text-xs font-bold text-blue-600 bg-blue-50/60 px-2.5 py-1 rounded-xl border border-blue-100/50 flex items-center space-x-1 cursor-pointer select-none hover:bg-blue-100/80 transition-colors">
                            <span id="calMonthText">มกราคม</span>
                            <svg class="w-3 h-3 text-blue-500 transition-transform duration-200" id="calMonthIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                        <div id="calMonthOptions" class="custom-dropdown-options absolute z-50 left-0 mt-1 w-32 bg-white rounded-xl hidden max-h-48 overflow-y-auto scrollbar-none text-xs text-gray-700"></div>
                    </div>

                    <div class="relative" id="calYearWrapper">
                        <div id="calYearDisplay" class="text-xs font-bold text-blue-600 bg-gray-50 px-2.5 py-1 rounded-xl border border-gray-100 flex items-center space-x-1 cursor-pointer select-none hover:bg-gray-100 transition-colors">
                            <span id="calYearText">2569</span>
                            <svg class="w-3 h-3 text-blue-500 transition-transform duration-200" id="calYearIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                        <div id="calYearOptions" class="custom-dropdown-options absolute z-50 left-0 mt-1 w-24 bg-white rounded-xl hidden max-h-48 overflow-y-auto scrollbar-none text-xs text-gray-700 text-center"></div>
                    </div>
                </div>
                <button type="button" id="calNext">&rarr;</button>
            </div>

            <div class="text-[10px] text-slate-400 text-center mb-1.5 font-medium">
                💡 กดค้างลาก หรือ คลิกเลือกวันเริ่ม-วันจบ
            </div>

            <div class="calendar-grid" id="calGrid">
                <span class="day-name">จ</span><span class="day-name">อ</span><span class="day-name">พ</span>
                <span class="day-name">พฤ</span><span class="day-name">ศ</span><span class="day-name">ส</span><span class="day-name">อา</span>
            </div>
        </div>

        <!-- 2️⃣ VIEW: โหมดเลือกรายเดือน / ช่วงเดือน -->
        <div id="monthCalendarView" class="hidden">
            <div class="calendar-header">
                <button type="button" id="mCalPrev">&larr;</button>
                <div class="text-xs font-bold text-blue-600 bg-blue-50 px-4 py-1 rounded-xl border border-blue-100">
                    <span id="mCalYearText">2569</span>
                </div>
                <button type="button" id="mCalNext">&rarr;</button>
            </div>

            <div class="text-[10px] text-slate-400 text-center mb-2 font-medium">
                💡 คลิก 1 ครั้งเลือกเดือนเดียว หรือ คลิก 2 ครั้งเลือกช่วงเดือน
            </div>

            <div class="month-grid" id="monthGrid"></div>
        </div>

    </div>
</div>

<script>
    const calendarPopup = document.getElementById("calendarPopup");
    const grid = document.getElementById("calGrid");
    const monthGrid = document.getElementById("monthGrid");

    const now = new Date();
    let currentMonth = now.getMonth();
    let currentYear = now.getFullYear();
    let activeDateInput = null;
    let activeTabMode = 'day'; // 'day' หรือ 'month'

    // ตัวแปรเลือกช่วงวัน (Day Mode)
    let isMouseDown = false;
    let hasDraggedAcross = false;
    let rangeStartDate = null;
    let rangeEndDate = null;

    // ตัวแปรเลือกช่วงเดือน (Month Mode)
    let rangeStartMonth = null; // index 0-11
    let rangeEndMonth = null;

    const monthNames = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    const monthShortNames = ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];

    const monthOptions = document.getElementById("calMonthOptions");
    const yearOptions = document.getElementById("calYearOptions");
    const monthDisplay = document.getElementById("calMonthDisplay");
    const yearDisplay = document.getElementById("calYearDisplay");
    const monthText = document.getElementById("calMonthText");
    const yearText = document.getElementById("calYearText");
    const monthIcon = document.getElementById("calMonthIcon");
    const yearIcon = document.getElementById("calYearIcon");

    // สลับ Tab
    function switchCalTab(mode) {
        activeTabMode = mode;
        const btnDay = document.getElementById('tabDayMode');
        const btnMonth = document.getElementById('tabMonthMode');
        const viewDay = document.getElementById('dayCalendarView');
        const viewMonth = document.getElementById('monthCalendarView');

        if (mode === 'day') {
            btnDay.className = "flex-1 py-1.5 rounded-xl bg-blue-600 text-white transition-all shadow-xs";
            btnMonth.className = "flex-1 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all";
            viewDay.classList.remove('hidden');
            viewMonth.classList.add('hidden');
            renderCalendar();
        } else {
            btnMonth.className = "flex-1 py-1.5 rounded-xl bg-blue-600 text-white transition-all shadow-xs";
            btnDay.className = "flex-1 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all";
            viewMonth.classList.remove('hidden');
            viewDay.classList.add('hidden');
            renderMonthGrid();
        }
    }

    if (monthOptions && yearOptions && monthOptions.children.length === 0) {
        monthNames.forEach((name, idx) => {
            let div = document.createElement("div");
            div.className = "p-2 hover:bg-blue-50 cursor-pointer transition-colors";
            div.textContent = name;
            div.onclick = (e) => {
                e.stopPropagation();
                currentMonth = idx;
                monthOptions.classList.add("hidden");
                if (monthIcon) monthIcon.classList.remove("rotate-180");
                renderCalendar();
            };
            monthOptions.appendChild(div);
        });

        const startYear = now.getFullYear() - 60;
        const endYear = now.getFullYear() + 5;
        for (let y = endYear; y >= startYear; y--) {
            let div = document.createElement("div");
            div.className = "p-2 hover:bg-blue-50 cursor-pointer transition-colors text-center";
            div.textContent = y + 543;
            div.onclick = (e) => {
                e.stopPropagation();
                currentYear = y;
                yearOptions.classList.add("hidden");
                if (yearIcon) yearIcon.classList.remove("rotate-180");
                renderCalendar();
                renderMonthGrid();
            };
            yearOptions.appendChild(div);
        }

        if (monthDisplay) {
            monthDisplay.onclick = (e) => {
                e.stopPropagation();
                yearOptions.classList.add("hidden"); if (yearIcon) yearIcon.classList.remove("rotate-180");
                monthOptions.classList.toggle("hidden"); if (monthIcon) monthIcon.classList.toggle("rotate-180");
            };
        }

        if (yearDisplay) {
            yearDisplay.onclick = (e) => {
                e.stopPropagation();
                monthOptions.classList.add("hidden"); if (monthIcon) monthIcon.classList.remove("rotate-180");
                yearOptions.classList.toggle("hidden"); if (yearIcon) yearIcon.classList.toggle("rotate-180");
            };
        }
    }

    function formatDateThai(dObj) {
        const sD = dObj.getDate().toString().padStart(2, '0');
        const sM = (dObj.getMonth() + 1).toString().padStart(2, '0');
        const sY = dObj.getFullYear() + 543;
        return `${sD}/${sM}/${sY}`;
    }

    // 🎯 1. วาดปฏิทินรายวัน
    function renderCalendar() {
        grid.querySelectorAll(".day").forEach(d => d.remove());
        
        if (monthText) monthText.textContent = monthNames[currentMonth];
        if (yearText) yearText.textContent = currentYear + 543;

        let firstDay = new Date(currentYear, currentMonth, 1).getDay();
        firstDay = (firstDay + 6) % 7; 
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const today = new Date();
        today.setHours(0,0,0,0);

        for (let i = 0; i < firstDay; i++) {
            const blank = document.createElement("span");
            blank.className = "day";
            grid.appendChild(blank);
        }

        let effStart = rangeStartDate;
        let effEnd = rangeEndDate;

        if (effStart && effEnd && effStart > effEnd) {
            let temp = effStart;
            effStart = effEnd;
            effEnd = temp;
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const cell = document.createElement("span");
            cell.className = "day";
            cell.textContent = d;
            
            const cellDate = new Date(currentYear, currentMonth, d);
            cellDate.setHours(0,0,0,0);
            cell.dataset.time = cellDate.getTime();

            if (effStart && effEnd) {
                if (cellDate.getTime() === effStart.getTime() && cellDate.getTime() === effEnd.getTime()) {
                    cell.classList.add("range-single");
                } else if (cellDate.getTime() === effStart.getTime()) {
                    cell.classList.add("range-start");
                } else if (cellDate.getTime() === effEnd.getTime()) {
                    cell.classList.add("range-end");
                } else if (cellDate > effStart && cellDate < effEnd) {
                    cell.classList.add("range-in-between");
                }
            } else if (effStart && cellDate.getTime() === effStart.getTime()) {
                cell.classList.add("range-single");
            } else if (cellDate.getTime() === today.getTime()) {
                cell.classList.add("today");
            }

            cell.addEventListener("mousedown", (e) => {
                e.preventDefault();
                e.stopPropagation();
                isMouseDown = true;
                hasDraggedAcross = false;

                if (!rangeStartDate || (rangeStartDate && rangeEndDate)) {
                    rangeStartDate = cellDate;
                    rangeEndDate = null;
                    if (activeDateInput) activeDateInput.value = formatDateThai(rangeStartDate);
                }
                renderCalendar();
            });

            cell.addEventListener("mouseenter", (e) => {
                if (isMouseDown && rangeStartDate) {
                    hasDraggedAcross = true;
                    rangeEndDate = cellDate;
                    renderCalendar();
                }
            });

            cell.addEventListener("click", (e) => {
                e.stopPropagation();
                if (hasDraggedAcross) return;

                if (rangeStartDate && !rangeEndDate && cellDate.getTime() !== rangeStartDate.getTime()) {
                    if (cellDate < rangeStartDate) {
                        rangeEndDate = rangeStartDate;
                        rangeStartDate = cellDate;
                    } else {
                        rangeEndDate = cellDate;
                    }
                    applySelectionAndClose();
                } else if (rangeStartDate && rangeEndDate) {
                    rangeStartDate = cellDate;
                    rangeEndDate = null;
                    if (activeDateInput) activeDateInput.value = formatDateThai(rangeStartDate);
                    renderCalendar();
                }
            });

            grid.appendChild(cell);
        }
    }

    // 🎯 2. วาดการ์ดเลือกรายเดือน
    function renderMonthGrid() {
        monthGrid.innerHTML = '';
        document.getElementById('mCalYearText').textContent = currentYear + 543;

        let mStart = rangeStartMonth;
        let mEnd = rangeEndMonth;

        if (mStart !== null && mEnd !== null && mStart > mEnd) {
            let t = mStart; mStart = mEnd; mEnd = t;
        }

        monthNames.forEach((mName, idx) => {
            const card = document.createElement("div");
            card.className = "month-card";
            card.textContent = mName;

            if (mStart !== null && mEnd !== null) {
                if (idx === mStart || idx === mEnd) {
                    card.classList.add("month-active");
                } else if (idx > mStart && idx < mEnd) {
                    card.classList.add("month-in-between");
                }
            } else if (mStart !== null && idx === mStart) {
                card.classList.add("month-active");
            }

            card.addEventListener("click", (e) => {
                e.stopPropagation();
                
                if (rangeStartMonth === null || (rangeStartMonth !== null && rangeEndMonth !== null)) {
                    rangeStartMonth = idx;
                    rangeEndMonth = null;
                    if (activeDateInput) {
                        activeDateInput.value = `${mName} ${currentYear + 543}`;
                    }
                    renderMonthGrid();
                } else {
                    if (idx === rangeStartMonth) {
                        // เลือกเดือนเดิมซ้ำ = เดือนเดียว
                        if (activeDateInput) {
                            activeDateInput.value = `${mName} ${currentYear + 543}`;
                        }
                    } else {
                        if (idx < rangeStartMonth) {
                            rangeEndMonth = rangeStartMonth;
                            rangeStartMonth = idx;
                        } else {
                            rangeEndMonth = idx;
                        }
                        if (activeDateInput) {
                            activeDateInput.value = `${monthNames[rangeStartMonth]} ${currentYear + 543} - ${monthNames[rangeEndMonth]} ${currentYear + 543}`;
                        }
                    }
                    calendarPopup.style.display = "none";
                    renderMonthGrid();
                }
            });

            monthGrid.appendChild(card);
        });
    }

    function applySelectionAndClose() {
        if (rangeStartDate && rangeEndDate) {
            if (rangeStartDate > rangeEndDate) {
                let temp = rangeStartDate;
                rangeStartDate = rangeEndDate;
                rangeEndDate = temp;
            }
            if (rangeStartDate.getTime() === rangeEndDate.getTime()) {
                if (activeDateInput) activeDateInput.value = formatDateThai(rangeStartDate);
            } else {
                if (activeDateInput) activeDateInput.value = `${formatDateThai(rangeStartDate)} - ${formatDateThai(rangeEndDate)}`;
            }
            calendarPopup.style.display = "none";
        } else if (rangeStartDate) {
            if (activeDateInput) activeDateInput.value = formatDateThai(rangeStartDate);
        }
        renderCalendar();
    }

    document.addEventListener("mouseup", () => {
        if (isMouseDown) {
            isMouseDown = false;
            if (hasDraggedAcross && rangeStartDate && rangeEndDate) {
                applySelectionAndClose();
            }
        }
    });

    document.getElementById("calPrev").addEventListener("click", (e) => { e.stopPropagation(); currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } renderCalendar(); });
    document.getElementById("calNext").addEventListener("click", (e) => { e.stopPropagation(); currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } renderCalendar(); });

    document.getElementById("mCalPrev").addEventListener("click", (e) => { e.stopPropagation(); currentYear--; renderMonthGrid(); });
    document.getElementById("mCalNext").addEventListener("click", (e) => { e.stopPropagation(); currentYear++; renderMonthGrid(); });

    function openCalendar(e) {
        activeDateInput = e.target;
        
        if (activeDateInput.value && activeDateInput.value.length >= 5) {
            const rawVal = activeDateInput.value;
            // เช็กว่าเป็นรูปแบบเดือนไทยหรือไม่
            let isMonthText = monthNames.some(m => rawVal.includes(m));
            if (isMonthText) {
                switchCalTab('month');
            } else {
                switchCalTab('day');
            }
        } else {
            switchCalTab('day');
        }
        
        renderCalendar();
        renderMonthGrid();

        calendarPopup.style.display = "block";

        const rect = activeDateInput.getBoundingClientRect();
        const popupHeight = calendarPopup.offsetHeight || 340; 
        const popupWidth = calendarPopup.offsetWidth || 340;

        // 🎯 คำนวณตำแหน่งแนวตั้ง (Top)
        const spaceBelow = window.innerHeight - rect.bottom;    
        if (spaceBelow < popupHeight && rect.top > popupHeight) {
            calendarPopup.style.top = (rect.top + window.scrollY - popupHeight - 8) + "px";
        } else {
            calendarPopup.style.top = (rect.bottom + window.scrollY + 6) + "px";
        }
        
        // 🎯 คำนวณตำแหน่งแนวนอน (Left) - วางชิดซ้ายตรงกับช่อง Input พอดี
        let leftPos = rect.left + window.scrollX;
        
        // ป้องกันปฏิทินล้นขอบขวาของหน้าจอ
        if (leftPos + popupWidth > window.innerWidth - 16) {
            leftPos = window.innerWidth - popupWidth - 16;
        }
        
        // ป้องกันปฏิทินล้นขอบซ้ายของหน้าจอ
        if (leftPos < 16) {
            leftPos = 16;
        }
        
        calendarPopup.style.left = leftPos + "px";
    }

    function bindCalendarEvents() {
        document.querySelectorAll(".calendar-trigger").forEach(input => {
            input.removeAttribute("readonly");
            input.placeholder = "วว/ดด/ปปปป, ช่วงวัน หรือ เดือน";

            input.removeEventListener("click", openCalendar); 
            input.addEventListener("click", openCalendar);
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