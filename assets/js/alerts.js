/**
 * Lanto Web - Custom Glassmorphism Alert System (อัปเดตเวอร์ชันรองรับ Loading)
 */

const LantoAlert = {
    _create(title, message, type = 'success', callback = null) {
        const oldAlert = document.getElementById('lanto-alert-overlay');
        if (oldAlert) oldAlert.remove();

        let iconHtml = '';
        let iconBg = '';
        
        if (type === 'success') {
            iconBg = 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600';
            iconHtml = `<svg class="w-8 h-8 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
        } else if (type === 'error') {
            iconBg = 'bg-rose-500/10 border border-rose-500/30 text-rose-600';
            iconHtml = `<svg class="w-8 h-8 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`;
        } else if (type === 'loading') {
            // อนิเมชันวงล้อหมุนติ้วๆ (Spinner) สำหรับช่วงโหลดข้อมูล
            iconBg = 'bg-blue-500/10 border border-blue-500/20 text-blue-600';
            iconHtml = `<svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
        } else {
            iconBg = 'bg-amber-500/10 border border-amber-500/30 text-amber-600';
            iconHtml = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
        }

        const overlay = document.createElement('div');
        overlay.id = 'lanto-alert-overlay';
        overlay.className = 'fixed inset-0 bg-slate-900/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 transition-opacity duration-300 opacity-0';
        
        // ถ้าเป็นสถานะโหลด จะไม่มีปุ่มกดปิด (ให้ปิดผ่านโค้ดเท่านั้น)
        const buttonHtml = type === 'loading' ? '' : `
            <button id="lanto-alert-btn" class="w-full bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white font-medium py-2.5 rounded-2xl transition-all duration-200 shadow-md shadow-blue-700/10 cursor-pointer text-sm">
                ตกลง
            </button>
        `;

        overlay.innerHTML = `
            <div id="lanto-alert-card" class="bg-white/70 backdrop-blur-2xl border border-white/80 p-6 rounded-3xl shadow-2xl max-w-sm w-full text-center transform scale-90 transition-all duration-300 opacity-0 shadow-slate-300/50">
                <div class="w-16 h-16 ${iconBg} rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-sm">
                    ${iconHtml}
                </div>
                <h3 class="text-lg font-semibold text-slate-800 mb-1">${title}</h3>
                <p class="text-slate-500 text-sm ${type === 'loading' ? '' : 'mb-6'} px-2">${message}</p>
                ${buttonHtml}
            </div>
        `;

        document.body.appendChild(overlay);

        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            const card = document.getElementById('lanto-alert-card');
            if(card) card.classList.remove('opacity-0', 'scale-90');
            if(card) card.classList.add('scale-100', 'opacity-100');
        }, 10);

        if (type !== 'loading') {
            const closeAlert = () => {
                overlay.classList.add('opacity-0');
                const card = document.getElementById('lanto-alert-card');
                if(card) card.classList.remove('scale-100');
                if(card) card.classList.add('scale-90', 'opacity-0');
                
                setTimeout(() => {
                    overlay.remove();
                    if (typeof callback === 'function') callback();
                }, 300);
            };
            document.getElementById('lanto-alert-btn').addEventListener('click', closeAlert);
        }
    },

    success(title, message, callback = null) { this._create(title, message, 'success', callback); },
    error(title, message, callback = null) { this._create(title, message, 'error', callback); },
    warning(title, message, callback = null) { this._create(title, message, 'warning', callback); },
    
    // ฟังก์ชันเปิดหน้าต่างโหลดข้อมูล
    loading(title, message) { this._create(title, message, 'loading'); },
    
    // ฟังก์ชันสั่งปิดหน้าต่างแจ้งเตือนจากโค้ด JavaScript
    close() {
        const overlay = document.getElementById('lanto-alert-overlay');
        if (overlay) {
            overlay.classList.add('opacity-0');
            setTimeout(() => overlay.remove(), 300);
        }
    }
};