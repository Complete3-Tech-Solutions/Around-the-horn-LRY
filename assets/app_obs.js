import './styles/obs.css';
import 'htmx.org';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

function fitObsStage() {
    const stage = document.getElementById('obs-stage');
    if (!stage) {
        return;
    }
    const scale = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
    stage.style.transform = `translate(-50%, -50%) scale(${scale})`;
}

function layoutObsMeters() {
    document.querySelectorAll('.obs-meter').forEach((meter) => {
        const fill = meter.querySelector('.obs-fill');
        const stat = meter.querySelector('.obs-stat');
        if (!fill || !stat) {
            return;
        }
        const pct = parseFloat(fill.style.width) || 0;
        const short = pct < 22;
        meter.classList.toggle('short', short);
        stat.classList.toggle('outside', short);
    });
}

document.addEventListener('alpine:init', () => {
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

window.addEventListener('resize', fitObsStage);
document.addEventListener('DOMContentLoaded', () => {
    fitObsStage();
    layoutObsMeters();
});
document.body.addEventListener('htmx:afterSwap', (event) => {
    if (event.detail.target.id !== 'obs-stage-inner') {
        return;
    }
    layoutObsMeters();
    const stage = document.getElementById('obs-stage');
    stage?.classList.toggle('obs-intro', event.detail.target.querySelector('.intro-body') !== null);
});

Alpine.start();
