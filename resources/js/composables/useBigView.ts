import { ref } from 'vue';

/**
 * Big view: everything 20% bigger (text, buttons, the menu) for easier tapping. One
 * setting per browser, kept like light/dark; the script at the top of app.blade.php
 * applies it before the page first paints, so nothing jumps. The sizes in app.css hang
 * off the root font size, which the `big-view` class on <html> raises.
 */
const KEY = 'big-view';
const on = ref(false);

const apply = (value: boolean) => document.documentElement.classList.toggle('big-view', value);

export function initializeBigView(): void {
    // Client-only: there's no document or localStorage during SSR.
    if (typeof window === 'undefined') return;
    try {
        on.value = localStorage.getItem(KEY) === '1';
    } catch {
        on.value = false;
    }
    apply(on.value);
}

export function useBigView() {
    const set = (value: boolean) => {
        on.value = value;
        apply(value);
        try {
            localStorage.setItem(KEY, value ? '1' : '0');
        } catch {
            // Storage blocked (a private window): it still applies until the page reloads.
        }
    };
    return { on, set, toggle: () => set(!on.value) };
}
