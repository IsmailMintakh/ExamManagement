import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { useToast } from './Composables/useToast';

const appName = import.meta.env.VITE_APP_NAME || 'Exam Management';

// Initialize theme before app mounts to prevent flash
const savedTheme = localStorage.getItem('theme');
if (savedTheme) {
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.documentElement.classList.toggle('dark', savedTheme === 'dark');
} else {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
    document.documentElement.classList.toggle('dark', prefersDark);
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4f46e5',
        includeCSS: true,
        showSpinner: false,
        delay: 100,
    },
});

// ============ Toast notifications from flash ============
const toast = useToast();
router.on('navigate', (event) => {
    const flash = event.detail.page.props?.flash;
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error, 6000);
    if (flash?.warning) toast.warning(flash.warning, 6000);
    if (flash?.info) toast.info(flash.info);
});

// ============ Page transition class on body ============
router.on('start', () => {
    document.body.classList.add('page-transitioning');
});
router.on('finish', () => {
    setTimeout(() => {
        document.body.classList.remove('page-transitioning');
    }, 50);
});
