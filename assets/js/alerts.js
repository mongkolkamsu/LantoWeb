/**
 * Lanto Web - Custom Glassmorphism Alert System (รองรับ Prompt กรอกข้อความ)
 */
window.LantoAlert = {
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
            iconBg = 'bg-blue-500/10 border border-blue-500/20 text-blue-600';
            iconHtml = `<svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
        } else {
            iconBg = 'bg-amber-500/10 border border-amber-500/30 text-amber-600';
            iconHtml = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
        }

        const overlay = document.createElement('div');
        overlay.id = 'lanto-alert-overlay';
        overlay.className = 'fixed inset-0 bg-slate-900/20 backdrop-blur-sm flex items-center justify-center z-[100] p-4 transition-opacity duration-100 opacity-0';
        
        const buttonHtml = type === 'loading' ? '' : `
            <button id="lanto-alert-btn" class="w-full bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 text-white font-medium py-2.5 rounded-2xl transition-all duration-150 shadow-md shadow-blue-700/10 cursor-pointer text-sm active:scale-95">
                ตกลง
            </button>
        `;

        overlay.innerHTML = `
            <div id="lanto-alert-card" class="bg-white/80 backdrop-blur-2xl border border-white/80 p-6 rounded-3xl shadow-2xl max-w-sm w-full text-center transform scale-95 transition-all duration-100 opacity-0 shadow-slate-300/50">
                <div class="w-16 h-16 ${iconBg} rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-sm">
                    ${iconHtml}
                </div>
                <h3 class="text-lg font-semibold text-slate-800 mb-1">${title}</h3>
                <p class="text-slate-500 text-sm ${type === 'loading' ? '' : 'mb-6'} px-2">${message}</p>
                ${buttonHtml}
            </div>
        `;

        document.body.appendChild(overlay);

        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            const card = document.getElementById('lanto-alert-card');
            if (card) {
                card.classList.remove('opacity-0', 'scale-95');
                card.classList.add('scale-100', 'opacity-100');
            }
        });

        if (type !== 'loading') {
            const closeAlert = () => {
                overlay.classList.add('opacity-0');
                const card = document.getElementById('lanto-alert-card');
                if (card) card.classList.add('scale-95', 'opacity-0');
                
                setTimeout(() => {
                    overlay.remove();
                    if (typeof callback === 'function') callback();
                }, 100);
            };
            document.getElementById('lanto-alert-btn').addEventListener('click', closeAlert);
        }
    },

    confirm(title, message, onConfirm = null, onCancel = null, type = 'warning') {
        const oldAlert = document.getElementById('lanto-alert-overlay');
        if (oldAlert) oldAlert.remove();

        let iconBg = 'bg-amber-500/10 border border-amber-500/30 text-amber-600';
        let iconHtml = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
        let btnBg = 'bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-800 hover:to-blue-700 shadow-blue-700/20';

        if (type === 'approve' || type === 'success') {
            iconBg = 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600';
            iconHtml = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>`;
            btnBg = 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-emerald-600/25';
        } else if (type === 'reject' || type === 'error' || type === 'danger') {
            iconBg = 'bg-rose-500/10 border border-rose-500/30 text-rose-600';
            iconHtml = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>`;
            btnBg = 'bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-700 hover:to-pink-700 shadow-rose-600/25';
        }

        const overlay = document.createElement('div');
        overlay.id = 'lanto-alert-overlay';
        overlay.className = 'fixed inset-0 bg-slate-900/20 backdrop-blur-sm flex items-center justify-center z-[100] p-4 transition-opacity duration-100 opacity-0';

        overlay.innerHTML = `
            <div id="lanto-alert-card" class="bg-white/80 backdrop-blur-2xl border border-white/80 p-6 rounded-3xl shadow-2xl max-w-sm w-full text-center transform scale-95 transition-all duration-100 opacity-0 shadow-slate-300/50">
                <div class="w-16 h-16 ${iconBg} rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-sm">
                    ${iconHtml}
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">${title}</h3>
                <p class="text-slate-500 text-xs mb-6 px-2">${message || 'โปรดยืนยันการทำรายการ'}</p>
                <div class="flex gap-2">
                    <button id="lanto-confirm-cancel" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2.5 rounded-2xl transition-all duration-150 text-xs cursor-pointer active:scale-95">
                        ยกเลิก
                    </button>
                    <button id="lanto-confirm-ok" class="flex-1 ${btnBg} text-white font-bold py-2.5 rounded-2xl transition-all duration-150 shadow-md text-xs cursor-pointer active:scale-95">
                        ยืนยัน
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            const card = document.getElementById('lanto-alert-card');
            if (card) {
                card.classList.remove('opacity-0', 'scale-95');
                card.classList.add('scale-100', 'opacity-100');
            }
        });

        const closeAlert = (isConfirmed) => {
            overlay.classList.add('opacity-0');
            const card = document.getElementById('lanto-alert-card');
            if (card) card.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                overlay.remove();
                if (isConfirmed && typeof onConfirm === 'function') onConfirm();
                if (!isConfirmed && typeof onCancel === 'function') onCancel();
            }, 100);
        };

        document.getElementById('lanto-confirm-ok').addEventListener('click', () => closeAlert(true));
        document.getElementById('lanto-confirm-cancel').addEventListener('click', () => closeAlert(false));
    },

    // 🎯 ฟังก์ชัน Prompt สำหรับรับข้อความจากผู้ใช้งาน (เช่น เหตุผลการปฏิเสธ)
    prompt(title, message, placeholder = '', onConfirm = null, onCancel = null) {
        const oldAlert = document.getElementById('lanto-alert-overlay');
        if (oldAlert) oldAlert.remove();

        const iconBg = 'bg-rose-500/10 border border-rose-500/30 text-rose-600';
        const iconHtml = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>`;
        const btnBg = 'bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-700 hover:to-pink-700 shadow-rose-600/25';

        const overlay = document.createElement('div');
        overlay.id = 'lanto-alert-overlay';
        overlay.className = 'fixed inset-0 bg-slate-900/20 backdrop-blur-sm flex items-center justify-center z-[100] p-4 transition-opacity duration-100 opacity-0';

        overlay.innerHTML = `
            <div id="lanto-alert-card" class="bg-white/90 backdrop-blur-2xl border border-white/80 p-6 rounded-3xl shadow-2xl max-w-sm w-full text-center transform scale-95 transition-all duration-100 opacity-0 shadow-slate-300/50">
                <div class="w-16 h-16 ${iconBg} rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-sm">
                    ${iconHtml}
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">${title}</h3>
                <p class="text-slate-500 text-xs mb-3 px-2">${message}</p>
                <textarea id="lanto-prompt-input" rows="3" placeholder="${placeholder}" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-3 text-xs text-slate-800 focus:outline-none focus:border-rose-500 transition-all mb-4 resize-none font-medium"></textarea>
                <div class="flex gap-2">
                    <button id="lanto-prompt-cancel" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2.5 rounded-2xl transition-all duration-150 text-xs cursor-pointer active:scale-95">
                        ยกเลิก
                    </button>
                    <button id="lanto-prompt-ok" class="flex-1 ${btnBg} text-white font-bold py-2.5 rounded-2xl transition-all duration-150 shadow-md text-xs cursor-pointer active:scale-95">
                        ยืนยัน
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            const card = document.getElementById('lanto-alert-card');
            if (card) {
                card.classList.remove('opacity-0', 'scale-95');
                card.classList.add('scale-100', 'opacity-100');
            }
            const input = document.getElementById('lanto-prompt-input');
            if (input) input.focus();
        });

        const closeAlert = (isConfirmed) => {
            const inputVal = document.getElementById('lanto-prompt-input')?.value || '';
            overlay.classList.add('opacity-0');
            const card = document.getElementById('lanto-alert-card');
            if (card) card.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                overlay.remove();
                if (isConfirmed && typeof onConfirm === 'function') onConfirm(inputVal);
                if (!isConfirmed && typeof onCancel === 'function') onCancel();
            }, 100);
        };

        document.getElementById('lanto-prompt-ok').addEventListener('click', () => closeAlert(true));
        document.getElementById('lanto-prompt-cancel').addEventListener('click', () => closeAlert(false));
    },

    success(title, message, callback = null) { this._create(title, message, 'success', callback); },
    error(title, message, callback = null) { this._create(title, message, 'error', callback); },
    warning(title, message, callback = null) { this._create(title, message, 'warning', callback); },
    loading(title, message) { this._create(title, message, 'loading'); },
    close() {
        const overlay = document.getElementById('lanto-alert-overlay');
        if (overlay) {
            overlay.classList.add('opacity-0');
            setTimeout(() => overlay.remove(), 100);
        }
    }
};