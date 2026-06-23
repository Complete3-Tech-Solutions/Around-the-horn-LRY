/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

import Alpine from 'alpinejs'
window.Alpine = Alpine
Alpine.start()

import 'htmx.org';
import htmx from 'htmx.org';
window.htmx = htmx;

// Export Fireworks pour l'utiliser dans les templates
import { Fireworks } from 'fireworks-js'
window.Fireworks = Fireworks;

// Moderator /admin actions are POST → redirect → GET; without this, mobile
// browsers reload at scrollY 0 and the control deck jumps back to the header.
const ADMIN_SCROLL_KEY = 'ia-admin-scroll-y';

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') {
        return;
    }
    if (!location.pathname.startsWith('/admin')) {
        return;
    }
    sessionStorage.setItem(ADMIN_SCROLL_KEY, String(window.scrollY));
}, true);

const restoreAdminScroll = () => {
    if (!location.pathname.startsWith('/admin')) {
        return;
    }
    const raw = sessionStorage.getItem(ADMIN_SCROLL_KEY);
    if (raw === null) {
        return;
    }
    sessionStorage.removeItem(ADMIN_SCROLL_KEY);
    const y = parseInt(raw, 10);
    if (!Number.isFinite(y) || y < 0) {
        return;
    }
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    const apply = () => window.scrollTo(0, y);
    apply();
    requestAnimationFrame(() => {
        apply();
        requestAnimationFrame(apply);
    });
};

document.addEventListener('DOMContentLoaded', restoreAdminScroll);