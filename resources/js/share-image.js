// Obrazek na sociale (premium, Admin zawsze): przycisk x-share-image robi PNG z najbliższego elementu
// z atrybutem data-share-target (tabela, wyniki, kolejka, news...). Zdjęcie robi się z oczyszczonej kopii
// (cleanCopy) z klasą is-sharing, bez elementów [data-share-hide] i ze stopką z adresem strony.
// Gotowy obrazek pokazuje się w podglądzie z przyciskami „Pobierz” i „Udostępnij”. Udostępnianie jest
// osobnym kliknięciem, bo przeglądarka otwiera systemowe „Udostępnij” tylko zaraz po kliknięciu, a zdjęcie
// trwa chwilę. Biblioteka html-to-image ładuje się przy najechaniu na przycisk (osobny plik po npm run build).

const slug = (text) =>
    String(text)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/ł/g, 'l')
        .replace(/Ł/g, 'L')
        .replace(/[^a-zA-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase()
        .slice(0, 60) || 'lechtyper';

const toast = (text, variant = 'danger') => window.dispatchEvent(new CustomEvent('ui-toast', { detail: { text, variant } }));

let library = null;
const loadLibrary = () => (library ??= import('html-to-image'));

// Podgląd gotowego obrazka (zwykły DOM ze stylami w atrybutach, bez zależności od klas Tailwinda).
const showPreview = (blob, file, labels) => {
    const url = URL.createObjectURL(blob);
    const overlay = document.createElement('div');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', labels.title);
    overlay.style.cssText =
        'position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(10,20,40,.6)';

    const box = document.createElement('div');
    box.style.cssText =
        'display:flex;flex-direction:column;gap:12px;max-width:min(640px,100%);max-height:100%;padding:16px;border-radius:16px;background:var(--ui-surface,#fff);color:var(--ui-ink,#0d1a33);box-shadow:0 20px 50px rgba(0,0,0,.3)';

    const heading = document.createElement('div');
    heading.textContent = labels.title;
    heading.style.cssText = 'font-weight:600;font-size:15px';

    const img = document.createElement('img');
    img.src = url;
    img.alt = '';
    img.style.cssText = 'display:block;max-width:100%;max-height:60vh;object-fit:contain;border-radius:10px;border:1px solid var(--ui-line,#d6dfee)';

    const buttons = document.createElement('div');
    buttons.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end';

    const button = (text, primary, onClick) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = text;
        b.style.cssText =
            'height:40px;padding:0 16px;border-radius:12px;font-weight:600;font-size:14px;cursor:pointer;' +
            (primary ? 'border:0;background:var(--ui-club,#0b4ea2);color:var(--ui-club-ink,#fff)' : 'border:1px solid var(--ui-line,#d6dfee);background:transparent;color:inherit');
        b.addEventListener('click', onClick);
        buttons.appendChild(b);

        return b;
    };

    const close = () => {
        overlay.remove();
        URL.revokeObjectURL(url);
        document.removeEventListener('keydown', onKey);
    };
    const onKey = (e) => e.key === 'Escape' && close();

    button(labels.close, false, close);
    button(labels.download, !navigator.canShare?.({ files: [file] }), () => {
        const a = document.createElement('a');
        a.href = url;
        a.download = file.name;
        document.body.appendChild(a);
        a.click();
        a.remove();
    });
    // X (główny kanał gry): zapis obrazka i okno nowego posta z tekstem, adresem strony i hashtagiem.
    // X nie przyjmuje obrazka z linku, więc obrazek dołącza się z pobranego pliku (podpowiedź w dymku).
    button(labels.x, false, () => {
        const a = document.createElement('a');
        a.href = url;
        a.download = file.name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        const text = labels.title + (labels.hashtag ? ' #' + labels.hashtag : '') + (labels.via ? ' ' + labels.via : '');
        const intent = 'https://x.com/intent/post?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(location.href.split('#')[0]);
        window.open(intent, '_blank', 'noopener');
        toast(labels.xHint, 'success');
    });
    if (navigator.canShare?.({ files: [file] })) {
        button(labels.share, true, async () => {
            try {
                await navigator.share({ files: [file], title: labels.title });
            } catch (e) {
                // Zamknięte okno udostępniania to nie błąd.
                if (e.name !== 'AbortError') {
                    console.error(e);
                    toast(labels.error);
                }
            }
        });
    }

    overlay.addEventListener('click', (e) => e.target === overlay && close());
    document.addEventListener('keydown', onKey);
    box.append(heading, img, buttons);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    buttons.lastChild.focus();
};

// Kopia elementu do zdjęcia. Biblioteka zapisuje element jako obrazek SVG, a w SVG nie wolno używać atrybutów
// o nazwach z „:” i „@” (wire:click, @click, :class z Livewire i Alpine): przeglądarka odrzucała wtedy cały obrazek
// („error”). Kopia bez nich, bez [data-share-hide] i <template>, leży poza ekranem obok oryginału, więc dziedziczy
// te same kolory i zmienne CSS.
const cleanCopy = (target) => {
    const copy = target.cloneNode(true);
    copy.querySelectorAll('[data-share-hide], template, script').forEach((node) => node.remove());
    for (const el of [copy, ...copy.querySelectorAll('*')]) {
        for (const attr of [...el.attributes]) {
            if (!/^[a-zA-Z_][a-zA-Z0-9_.-]*$/.test(attr.name)) {
                el.removeAttribute(attr.name);
            }
        }
    }
    copy.removeAttribute('id');
    copy.classList.add('is-sharing');
    copy.style.width = target.getBoundingClientRect().width + 'px';
    copy.style.margin = '0';

    const holder = document.createElement('div');
    holder.setAttribute('aria-hidden', 'true');
    holder.style.cssText = 'position:fixed;left:-10000px;top:0;pointer-events:none';
    holder.appendChild(copy);
    target.parentNode.appendChild(holder);

    return { holder, copy };
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('shareImage', (title, brand, labels) => ({
        busy: false,

        // Najechanie albo dotknięcie przycisku: biblioteka zaczyna się ładować, zanim padnie kliknięcie.
        prepare() {
            loadLibrary().catch(() => {});
        },

        async make() {
            const target = this.$el.closest('[data-share-target]');
            if (!target || this.busy) {
                return;
            }
            this.busy = true;

            const footer = document.createElement('div');
            footer.className = 'share-footer';
            footer.textContent = brand + ' · ' + new Date().toLocaleDateString('pl-PL');

            let holder = null;
            try {
                const { toBlob } = await loadLibrary();
                const prepared = cleanCopy(target);
                holder = prepared.holder;
                const copy = prepared.copy;
                copy.appendChild(footer);

                const options = {
                    pixelRatio: 2,
                    // Obrazek, który się nie wczyta (np. z innej domeny), zamienia się w przezroczysty zamiast przerywać zdjęcie.
                    imagePlaceholder: 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==',
                    backgroundColor: getComputedStyle(document.body).backgroundColor,
                };
                let blob = null;
                try {
                    blob = await toBlob(copy, options);
                } catch (e) {
                    // Czcionki, których nie da się wczytać, nie mogą blokować obrazka: druga próba bez nich.
                    console.warn(e);
                    blob = await toBlob(copy, { ...options, skipFonts: true });
                }
                if (!blob) {
                    throw new Error('empty image');
                }

                showPreview(blob, new File([blob], slug(title) + '.png', { type: 'image/png' }), { ...labels, title });
            } catch (e) {
                // Szczegóły błędu w konsoli (F12) i w dymku, żeby łatwiej było zgłosić problem.
                console.error('share-image', e);
                const reason = e instanceof Error ? e.message : e?.type ?? String(e);
                toast(labels.error + (reason ? ' (' + reason + ')' : ''));
            } finally {
                holder?.remove();
                this.busy = false;
            }
        },
    }));
});
