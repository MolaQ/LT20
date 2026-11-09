// Wygląd 2.0: komponenty Alpine (Alpine przychodzi z Livewire) dla skorupy aplikacji.
// Wszystkie animacje respektują prefers-reduced-motion.

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Krótka wibracja przy dotknięciu (telefony, które ją obsługują).
const vibrate = (ms = 12) => {
    try {
        navigator.vibrate?.(ms);
    } catch (e) {
        // Brak wsparcia: nic nie robimy.
    }
};

// Motyw: auto (według telefonu), light, dark. Ten sam klucz co Flux, żeby stare i nowe strony się zgadzały.
const THEME_KEY = 'flux.appearance';

const applyTheme = (mode) => {
    const dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
};

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    applyTheme(localStorage.getItem(THEME_KEY) ?? 'system');
});

// Konfetti w barwach klubu na płótnie przykrywającym ekran.
const confetti = (count = 120) => {
    if (reducedMotion()) {
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'pointer-events-none fixed inset-0 z-[100]';
    document.body.appendChild(canvas);

    const dpr = window.devicePixelRatio || 1;
    const w = window.innerWidth;
    const h = window.innerHeight;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';

    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    const css = getComputedStyle(document.documentElement);
    const colors = [css.getPropertyValue('--club-primary'), css.getPropertyValue('--club-primary-dark'), '#ffffff', css.getPropertyValue('--club-accent')];
    const parts = Array.from({ length: count }, () => ({
        x: w / 2,
        y: h * 0.35,
        vx: (Math.random() - 0.5) * 10,
        vy: -Math.random() * 10 - 3,
        s: Math.random() * 6 + 4,
        a: Math.random() * 6,
        va: (Math.random() - 0.5) * 0.4,
        c: colors[Math.floor(Math.random() * colors.length)],
    }));

    let frame = 0;
    const loop = () => {
        ctx.clearRect(0, 0, w, h);
        parts.forEach((p) => {
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.28;
            p.vx *= 0.99;
            p.a += p.va;
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.a);
            ctx.fillStyle = p.c;
            ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2);
            ctx.restore();
        });

        if (++frame < 120) {
            requestAnimationFrame(loop);
        } else {
            canvas.remove();
        }
    };
    requestAnimationFrame(loop);
};

window.ui = { confetti, vibrate, applyTheme, reducedMotion };

// PWA: rejestracja service workera (tryb offline i instalacja na ekranie). Tylko https albo localhost.
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

// Zdarzenie instalacji przychodzi raz, zanim Alpine zbuduje menu, więc trzymamy je globalnie.
let installPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    installPrompt = e;
    window.dispatchEvent(new CustomEvent('ui-installable'));
});

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // Licznik +/− (np. gole w typie). Wartość przez x-modelable, więc działa z wire:model.
    Alpine.data('uiCounter', (min = 0, max = 9) => ({
        value: min,
        bump: false,
        change(step) {
            const next = Math.min(max, Math.max(min, Number(this.value) + step));
            if (next === Number(this.value)) {
                return;
            }
            // Pole Livewire może być tekstem ('2'), więc zachowujemy typ wartości.
            this.value = typeof this.value === 'string' ? String(next) : next;
            vibrate();
            this.bump = false;
            this.$nextTick(() => (this.bump = true));
        },
    }));

    // Arkusz od dołu: otwierany zdarzeniem, zamykany tłem, klawiszem Esc albo przeciągnięciem w dół.
    Alpine.data('uiSheet', (name) => ({
        open: false,
        drag: 0,
        startY: null,
        init() {
            window.addEventListener('ui-sheet', (e) => {
                if (e.detail?.name === name) {
                    this.open = true;
                }
            });
        },
        close() {
            this.open = false;
            this.drag = 0;
        },
        down(e) {
            if (e.target.closest('a,button,input,select,textarea')) {
                return;
            }
            this.startY = e.clientY;
            e.currentTarget.setPointerCapture(e.pointerId);
        },
        move(e) {
            if (this.startY !== null) {
                this.drag = Math.max(0, e.clientY - this.startY);
            }
        },
        up() {
            if (this.startY === null) {
                return;
            }
            this.startY = null;
            if (this.drag > 80) {
                this.close();
            }
            this.drag = 0;
        },
    }));

    // Liczba, która się „nabija” od zera po pojawieniu się na ekranie.
    Alpine.data('uiCountUp', (to, prefix = '') => ({
        shown: prefix + '0',
        init() {
            const run = () => {
                const dur = reducedMotion() ? 0 : 900;
                const t0 = performance.now();
                const step = (now) => {
                    const k = dur ? Math.min(1, (now - t0) / dur) : 1;
                    this.shown = prefix + Math.round(to * (1 - Math.pow(1 - k, 3)));
                    if (k < 1) {
                        requestAnimationFrame(step);
                    }
                };
                requestAnimationFrame(step);
            };
            const io = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    io.disconnect();
                    run();
                }
            });
            io.observe(this.$el);
        },
    }));

    // Przycisk „Zainstaluj aplikację”. Na iPhonie zamiast przycisku podpowiedź z „Udostępnij”.
    Alpine.data('uiInstall', () => ({
        canInstall: !!installPrompt,
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.matchMedia('(display-mode: standalone)').matches && !navigator.standalone,
        init() {
            window.addEventListener('ui-installable', () => (this.canInstall = true));
        },
        async install() {
            if (!installPrompt) {
                return;
            }
            installPrompt.prompt();
            await installPrompt.userChoice;
            installPrompt = null;
            this.canInstall = false;
        },
    }));

    // Przełącznik motywu: system / light / dark.
    Alpine.data('uiTheme', () => ({
        mode: localStorage.getItem(THEME_KEY) ?? 'system',
        set(mode) {
            this.mode = mode;
            if (mode === 'system') {
                localStorage.removeItem(THEME_KEY);
            } else {
                localStorage.setItem(THEME_KEY, mode);
            }
            applyTheme(mode);
            vibrate();
        },
    }));

    // Przesunięcie palcem w lewo/prawo przechodzi do sąsiedniej zakładki dolnego paska.
    Alpine.data('uiSwipeNav', (urls, current) => ({
        x0: null,
        y0: 0,
        start(e) {
            // Tabele i paski przewijane w bok mają pierwszeństwo przed zmianą zakładki.
            if (e.target.closest('[data-no-swipe], .overflow-x-auto, input, textarea')) {
                this.x0 = null;
                return;
            }
            this.x0 = e.touches[0].clientX;
            this.y0 = e.touches[0].clientY;
        },
        end(e) {
            if (this.x0 === null) {
                return;
            }
            const dx = e.changedTouches[0].clientX - this.x0;
            const dy = e.changedTouches[0].clientY - this.y0;
            this.x0 = null;
            if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.5) {
                return;
            }
            const next = urls[current + (dx < 0 ? 1 : -1)];
            if (next) {
                window.Livewire?.navigate ? window.Livewire.navigate(next) : (window.location.href = next);
            }
        },
    }));
});
