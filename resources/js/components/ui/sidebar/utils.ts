import { createContext } from 'radix-vue';
import type { ComputedRef, Ref } from 'vue';

export const SIDEBAR_COOKIE_NAME = 'sidebar_state';
export const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7;
export const SIDEBAR_WIDTH = '18rem';
export const SIDEBAR_WIDTH_MOBILE = '18rem';
// A 36px icon square plus the group's 8px gutters, less the inset frame's 2px.
export const SIDEBAR_WIDTH_ICON = '4.25rem'; // a 52px icon square + the group's padding
export const SIDEBAR_KEYBOARD_SHORTCUT = 'b';

export const [useSidebar, provideSidebarContext] = createContext<{
    state: ComputedRef<'expanded' | 'collapsed'>;
    open: Ref<boolean>;
    setOpen: (value: boolean) => void;
    isMobile: Ref<boolean>;
    openMobile: Ref<boolean>;
    setOpenMobile: (value: boolean) => void;
    toggleSidebar: () => void;
}>('Sidebar');
