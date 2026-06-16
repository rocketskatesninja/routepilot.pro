import '../css/app.css';
// Landing styles ship in the global head bundle (not a lazy page chunk) so the
// hero's `.hero-animate { opacity: 0 }` entrance rule is present at the SSR
// first paint — otherwise the text paints visible, then the late chunk hides it
// and fades it back in (a visible "load twice" flash).
import '../css/landing.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, createSSRApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import { applyBrand } from './composables/useBrand';
import { ensureUserSubscription } from './echo';
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

        // Live notifications: subscribe to the signed-in user's private channel
        // (see the navigate hook below for the keep-in-sync across login/logout).
        const auth = props.initialPage.props.auth as { user?: { id?: number } } | undefined;
        syncNotificationSubscription(auth?.user?.id ?? null);
    },
    progress: {
        color: '#0ea5e9',
    },
});

// Live notifications: refresh the unread badge (+ the list if open) on a push,
// preserving other component state. Keeps the subscription synced to the user.
function syncNotificationSubscription(userId: number | null): void {
    ensureUserSubscription(userId, () =>
        router.reload({ only: ['auth', 'notifications'], preserveScroll: true, preserveState: true }),
    );
}

// Re-apply the tenant brand on every Inertia visit — the tenant changes on
// impersonation start/stop and cross-tenant navigation. Only act when the
// shared tenant prop is present (skip partial reloads that omit it).
router.on('navigate', (event) => {
    const props = event.detail.page.props as { tenant?: Tenant | null; auth?: { user?: { id?: number } } };
    if ('tenant' in props) {
        applyBrand(props.tenant?.brand_color);
    }
    if ('auth' in props) {
        syncNotificationSubscription(props.auth?.user?.id ?? null);
    }
});

// This will set light / dark mode on page load...
initializeTheme();
