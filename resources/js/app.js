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
// Inertia v2 deprecated `navigate` — flash toasts now hook into `success`
// (fires after every successful Inertia response, including same-component
// re-renders) and `error` (fires on validation failures). We also emit a
// generic "Saved" / "Error" toast when the controller didn't set a flash,
// so every action gives the user clear visual confirmation.
const toast = useToast();

// Only fire the generic-success fallback for mutating requests. GET-driven
// navigations (clicking a link, filtering) shouldn't pop a toast.
const MUTATION_METHODS = new Set(['post', 'put', 'patch', 'delete']);

// Some endpoints are file-downloads that re-trigger a navigate but don't
// need a "Saved" toast — skip generic toasts for those routes.
const SKIP_GENERIC_TOAST = /\/(pdf|download|export|admit-cards|datesheet)/i;

let lastVisitMethod = 'get';
let lastVisitUrl = '';
router.on('before', (event) => {
    lastVisitMethod = (event.detail.visit?.method || 'get').toLowerCase();
    lastVisitUrl = String(event.detail.visit?.url || '');
});

router.on('success', (event) => {
    const flash = event.detail.page?.props?.flash || {};
    let shown = false;
    if (flash.success) { toast.success(flash.success); shown = true; }
    if (flash.error) { toast.error(flash.error, 6000); shown = true; }
    if (flash.warning) { toast.warning(flash.warning, 6000); shown = true; }
    if (flash.info) { toast.info(flash.info); shown = true; }

    // Generic confirmation when the backend forgot to set a flash but
    // the request was a write (POST/PUT/PATCH/DELETE). Better than silent.
    if (!shown
        && MUTATION_METHODS.has(lastVisitMethod)
        && !SKIP_GENERIC_TOAST.test(lastVisitUrl)) {
        toast.success('Done.');
    }
});

router.on('error', (errors) => {
    // `errors` here is the validation-error map. Show the first message so
    // the user knows what failed.
    const first = errors && typeof errors === 'object' ? Object.values(errors)[0] : null;
    toast.error(typeof first === 'string' && first ? first : 'Please fix the errors and try again.', 6000);
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
