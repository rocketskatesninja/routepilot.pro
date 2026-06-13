import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

export function updateTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.classList.toggle('dark', systemTheme === 'dark');
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const getStoredAppearance = (): Appearance | null =>
    typeof localStorage === 'undefined' ? null : (localStorage.getItem('appearance') as Appearance | null);

const handleSystemThemeChange = () => {
    updateTheme(getStoredAppearance() || 'system');
};

export function initializeTheme() {
    // Client-only: window/document/localStorage don't exist during SSR.
    if (typeof window === 'undefined') {
        return;
    }

    updateTheme(getStoredAppearance() || 'system');
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const appearance = ref<Appearance>('system');

    onMounted(() => {
        initializeTheme();

        const savedAppearance = getStoredAppearance();
        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('appearance', value);
        }

        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
