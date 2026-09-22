import { ref } from 'vue';

/**
 * Shared open-state for the ⌘K command palette so any control (the sidebar
 * Search button, a keyboard shortcut) can open the single palette instance.
 * Module-level ref = one source of truth across the app.
 */
const isOpen = ref(false);

export function useCommandPalette() {
    return {
        isOpen,
        open: () => (isOpen.value = true),
        close: () => (isOpen.value = false),
    };
}
