import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

interface ZiggyConfig {
    location: string;
    [key: string]: unknown;
}

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) }).use(plugin);

            // route() needs Ziggy's config in Node (no @routes browser global).
            // It travels in shared props (HandleInertiaRequests); feed it through.
            const ziggy = props.initialPage.props.ziggy as ZiggyConfig | undefined;
            if (ziggy) {
                app.use(ZiggyVue, { ...ziggy, location: new URL(ziggy.location) });
            } else {
                app.use(ZiggyVue);
            }

            return app;
            // NB: no applyBrand()/initializeTheme() here — they touch `document`,
            // which doesn't exist server-side. Brand is injected via app.blade.php.
        },
    }),
);
