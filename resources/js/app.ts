import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, createSSRApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import { applyBrand } from './composables/useBrand';
import type { Tenant } from './types';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // Overlay the tenant's brand color (if any) on top of the theme.
        const tenant = props.initialPage.props.tenant as Tenant | null;
        applyBrand(tenant?.brand_color);

        // Hydrate the server-rendered markup in place when SSR produced it;
        // otherwise (SSR off / empty shell) do a fresh client render. Using
        // createApp on SSR'd DOM would discard and re-render it — which replays
        // the hero entrance animation, making the text appear to "load twice".
        const vueApp = el.hasChildNodes() ? createSSRApp({ render: () => h(App, props) }) : createApp({ render: () => h(App, props) });

        vueApp.use(plugin).use(ZiggyVue).mount(el);
    },
    progress: {
        color: '#0ea5e9',
    },
});

// Re-apply the tenant brand on every Inertia visit — the tenant changes on
// impersonation start/stop and cross-tenant navigation. Only act when the
// shared tenant prop is present (skip partial reloads that omit it).
router.on('navigate', (event) => {
    const props = event.detail.page.props as { tenant?: Tenant | null };
    if ('tenant' in props) {
        applyBrand(props.tenant?.brand_color);
    }
});

// This will set light / dark mode on page load...
initializeTheme();
