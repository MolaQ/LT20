// Service worker #LechTYPER (PWA): szybki start z pamięci telefonu i ostatnio oglądane strony bez internetu.
// Zmień VERSION przy zmianie strategii, żeby stare pamięci zostały usunięte.
const VERSION = 'v1';
const STATIC = `static-${VERSION}`;
const PAGES = `pages-${VERSION}`;
const OFFLINE_URL = '/offline';
const MAX_PAGES = 40;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((cache) => cache.addAll([OFFLINE_URL])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => ![STATIC, PAGES].includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

// Usuwa najstarsze strony, gdy pamięć ma ich za dużo.
const trim = async () => {
    const cache = await caches.open(PAGES);
    const keys = await cache.keys();
    await Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_PAGES)).map((k) => cache.delete(k)));
};

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Tylko GET z naszej domeny. Livewire, logowanie i płatności idą zawsze do sieci.
    if (req.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/livewire') || url.pathname.startsWith('/payments')) {
        return;
    }

    // Zbudowane pliki (CSS, JS, czcionki) mają w nazwie skrót treści, więc mogą siedzieć w pamięci na stałe.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(STATIC).then((cache) => cache.put(req, copy));
                }
                return res;
            })),
        );
        return;
    }

    // Strony: najpierw sieć (świeże wyniki), a bez internetu ostatnia zapisana wersja albo strona offline.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    if (res.ok && res.type === 'basic') {
                        const copy = res.clone();
                        caches.open(PAGES).then((cache) => cache.put(req, copy)).then(trim);
                    }
                    return res;
                })
                .catch(async () => (await caches.match(req)) || caches.match(OFFLINE_URL)),
        );
    }
});
