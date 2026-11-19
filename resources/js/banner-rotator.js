/**
 * Rotator banerów partnerów (pasek sezonu i prawa kolumna): banery zmieniają się same co kilka sekund,
 * bez przeładowania strony. Zatrzymuje się, gdy kursor jest nad banerem albo karta przeglądarki jest ukryta.
 * Wyświetlenie liczy się, gdy baner był widoczny w co najmniej połowie przez sekundę, raz na baner na stronę.
 * options: slides ([{ id }], id = null dla grafiki bez liczenia, np. baner sponsora sezonu),
 * interval (ms), viewsUrl, token (CSRF).
 */
export function registerBannerRotator() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('bannerRotator', (options = {}) => ({
            slides: options.slides ?? [],
            index: 0,
            paused: false,
            visible: false,
            seen: new Set(),
            queue: [],
            rotateTimer: null,
            viewTimer: null,
            flushTimer: null,
            observer: null,

            init() {
                if (this.slides.length > 1) {
                    this.rotateTimer = setInterval(() => {
                        if (!this.paused && !document.hidden) {
                            this.go(this.index + 1);
                        }
                    }, options.interval ?? 9000);
                }

                this.observer = new IntersectionObserver((entries) => {
                    this.visible = entries.some((entry) => entry.isIntersecting && entry.intersectionRatio >= 0.5);
                    this.watchView();
                }, { threshold: [0, 0.5] });
                this.observer.observe(this.$el);

                this.onHide = () => this.flush();
                this.onVisibility = () => this.watchView();
                window.addEventListener('pagehide', this.onHide);
                document.addEventListener('visibilitychange', this.onVisibility);
            },

            destroy() {
                clearInterval(this.rotateTimer);
                clearTimeout(this.viewTimer);
                this.observer?.disconnect();
                window.removeEventListener('pagehide', this.onHide);
                document.removeEventListener('visibilitychange', this.onVisibility);
                this.flush();
            },

            go(i) {
                this.index = (i + this.slides.length) % this.slides.length;
                this.watchView();
            },

            // Sekunda widoczności obecnego banera = jedno wyświetlenie.
            watchView() {
                clearTimeout(this.viewTimer);
                const id = this.slides[this.index]?.id;
                if (!id || this.seen.has(id) || !this.visible || document.hidden) {
                    return;
                }

                const index = this.index;
                this.viewTimer = setTimeout(() => {
                    if (this.index !== index || !this.visible || document.hidden) {
                        return;
                    }
                    this.seen.add(id);
                    this.queue.push(id);
                    clearTimeout(this.flushTimer);
                    this.flushTimer = setTimeout(() => this.flush(), 2000);
                }, 1000);
            },

            flush() {
                clearTimeout(this.flushTimer);
                if (!this.queue.length || !options.viewsUrl) {
                    return;
                }

                const ids = this.queue.splice(0, 10);
                fetch(options.viewsUrl, {
                    method: 'POST',
                    keepalive: true,
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': options.token ?? '' },
                    body: JSON.stringify({ ids }),
                }).catch(() => {
                    // Licznik nie jest ważniejszy od strony: błąd sieci pomijamy.
                });
            },
        }));
    });
}
