// Obrazek na sociale (premium, Admin zawsze): przycisk x-share-image robi PNG z najbliższego elementu
// z atrybutem data-share-target (tabela, wyniki, kolejka, news...). Zdjęcie robi się z oczyszczonej kopii
// (cleanCopy) z klasą is-sharing, bez elementów [data-share-hide] i ze stopką z adresem strony.
// Gotowy obrazek pokazuje się w podglądzie z dwoma przyciskami (20.11.2026): „Pobierz” i „Opublikuj na X”
// (na telefonie dodatkowo systemowe „Udostępnij”). Pod obrazkiem stoi instrukcja: X nie przyjmuje obrazka z linku,
// więc „Opublikuj na X” najpierw kopiuje obrazek do schowka, a potem otwiera nowy post z gotowym tekstem
// (labels.text, np. „#LechTYPER lechtyper.pl”) i w poście wystarczy Ctrl+V. Wspólny schowek „obraz + tekst”
// usunęliśmy, bo X i Facebook wklejały z niego tylko obrazek. Biblioteka html-to-image ładuje się przy najechaniu na przycisk (osobny plik po npm run build).

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
    // Tekst posta: tytuł obrazka, pusta linia i podpis gry (czytelniej niż wszystko w jednej linii).
    const fullText = labels.title + '\n\n' + labels.text;
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

    // Instrukcja publikacji na X (po kliknięciu zamienia się w wynik: skopiowane albo zapisane).
    const note = document.createElement('p');
    note.textContent = labels.xSteps;
    note.style.cssText = 'margin:0;padding:10px 12px;border-radius:10px;font-size:13px;line-height:1.45;background:rgba(11,78,162,.08)';

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
    button(labels.download, false, () => {
        const a = document.createElement('a');
        a.href = url;
        a.download = file.name;
        document.body.appendChild(a);
        a.click();
        a.remove();
    });
    // X (główny kanał gry): najpierw obrazek do schowka (zanim nowa karta zabierze stronie fokus, inaczej
    // przeglądarka odmawia zapisu), potem okno nowego posta z tekstem. Gdy schowek nie działa, obrazek
    // zapisuje się jako plik do dołączenia. Wynik widać w instrukcji pod obrazkiem i w dymku.
    button(labels.x, true, async () => {
        let ok = false;
        if (navigator.clipboard?.write && window.ClipboardItem) {
            try {
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                ok = true;
            } catch (e) {
                console.warn(e);
            }
        }
        const text = fullText + (labels.via ? ' ' + labels.via : '');
        window.open('https://x.com/intent/post?text=' + encodeURIComponent(text), '_blank', 'noopener');
        if (!ok) {
            const a = document.createElement('a');
            a.href = url;
            a.download = file.name;
            document.body.appendChild(a);
            a.click();
            a.remove();
        }
        note.textContent = ok ? labels.xPaste : labels.xHint;
        note.style.background = 'rgba(22,163,74,.12)';
        note.style.fontWeight = '600';
        toast(ok ? labels.xPaste : labels.xHint, 'success');
    });
    // Systemowe „Udostępnij” tylko na telefonie i tablecie: na komputerze z Windows okno udostępniania często
    // kończy się komunikatem „Spróbuj ponownie” (18.11.2026), tam lepsze są „Kopiuj obraz i tekst” i X.
    const touch = window.matchMedia?.('(pointer: coarse)').matches;
    if (touch && navigator.canShare?.({ files: [file] })) {
        button(labels.share, false, async () => {
            try {
                await navigator.share({ files: [file], title: labels.title, text: fullText });
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
    box.append(heading, img, note, buttons);
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

    // Przewijane pola (tabela z suwakami) rozwijamy na całą treść, żeby na obrazku nie było pasków przewijania
    // ani uciętych wierszy i kolumn.
    for (const el of copy.querySelectorAll('*')) {
        const style = getComputedStyle(el);
        if (/(auto|scroll)/.test(style.overflowX + style.overflowY)) {
            el.style.overflow = 'visible';
            el.style.maxHeight = 'none';
            el.style.maxWidth = 'none';
            el.style.height = 'auto';
        }
    }
    // Szeroka tabela: kopia rośnie o tyle, ile brakuje najbardziej ściśniętemu elementowi.
    const missing = Math.max(0, ...[copy, ...copy.querySelectorAll('*')].map((el) => el.scrollWidth - el.clientWidth));
    if (missing > 0) {
        copy.style.width = Math.ceil(copy.getBoundingClientRect().width + missing) + 'px';
    }

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
                // Grafika o stałej szerokości (data-share-size, np. grafiki kolejki 1080 px): bez stopki,
                // bo ma własną markę, a skala obrazka wynika z żądanej szerokości.
                const size = Number(target.dataset.shareSize || 0);
                if (!size) {
                    copy.appendChild(footer);
                }

                const options = {
                    pixelRatio: size ? size / target.getBoundingClientRect().width : 2,
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
