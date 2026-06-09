import './styles/obs.css';
import 'htmx.org';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    // Round countdown. Re-created on each 2s HTMX swap, so it cleans up its own
    // interval on teardown to avoid leaking timers over a long event.
    Alpine.data('timer', (endDate) => ({
        display: '--:--',
        isWarn: false,
        isExpired: false,
        _t: null,

        init() {
            this.tick();
            this._t = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            if (this._t) {
                clearInterval(this._t);
                this._t = null;
            }
        },

        tick() {
            const diff = new Date(endDate) - new Date();
            if (diff <= 0) {
                this.isExpired = true;
                this.isWarn = false;
                this.display = '0:00';
                return;
            }
            const m = Math.floor(diff / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            this.display = m + ':' + String(s).padStart(2, '0');
            this.isWarn = diff <= 30000;
            this.isExpired = false;
        },
    }));
});

Alpine.start();
