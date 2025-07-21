import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Global Alert System
window.showGlobalAlert = function ({ message, type = 'info', duration = 6000 }) {
    const container = document.getElementById('global-alert-container');
    if (!container) return;

    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert-global shadow-lg rounded-lg px-6 py-4 mb-2 flex items-center bg-base-100 border border-base-300 animate-fade-in-up`;
    alert.style.minWidth = '320px';
    alert.style.maxWidth = '400px';
    alert.style.transition = 'opacity 0.3s, transform 0.3s';
    alert.style.opacity = '1';
    alert.style.transform = 'translateY(0)';

    // Color by type
    let icon, border, text;
    switch (type) {
        case 'success':
            border = 'border-green-400';
            text = 'text-green-700';
            icon = `<svg class="w-5 h-5 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
            break;
        case 'error':
            border = 'border-red-400';
            text = 'text-red-700';
            icon = `<svg class="w-5 h-5 mr-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`;
            break;
        case 'warning':
            border = 'border-yellow-400';
            text = 'text-yellow-700';
            icon = `<svg class="w-5 h-5 mr-3 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path></svg>`;
            break;
        default:
            border = 'border-blue-400';
            text = 'text-blue-700';
            icon = `<svg class="w-5 h-5 mr-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01"></path></svg>`;
    }

    alert.classList.add(border);
    alert.innerHTML = `
        <div class="flex items-center flex-1">
            ${icon}
            <span class="${text} font-medium flex-1">${message}</span>
        </div>
        <button class="ml-4 p-1 rounded hover:bg-base-200 transition" aria-label="Close">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;

    // Close logic
    const close = () => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 300);
    };
    alert.querySelector('button').onclick = close;
    setTimeout(close, duration);

    container.appendChild(alert);
};

// Animation CSS (inject if not present)
if (!document.getElementById('global-alert-anim')) {
    const style = document.createElement('style');
    style.id = 'global-alert-anim';
    style.innerHTML = `
    @keyframes fade-in-up {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up { animation: fade-in-up 0.4s cubic-bezier(.4,0,.2,1); }
    `;
    document.head.appendChild(style);
}
