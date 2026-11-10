// Obrazek na sociale (premium, Admin zawsze): przycisk x-share-image robi PNG z najbliższego elementu
// z atrybutem data-share-target (tabela, wyniki, kolejka, news...). Na czas zdjęcia element dostaje klasę
// is-sharing (CSS ukrywa wtedy [data-share-hide] i pokazuje [data-share-only]) oraz stopkę z adresem strony.
// Telefon otwiera systemowe „Udostępnij” (Facebook, Instagram, WhatsApp...), komputer pobiera plik.
// Biblioteka html-to-image ładuje się dopiero przy pierwszym użyciu (osobny plik po npm run build).

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

document.addEventListener('alpine:init', () => {
    window.Alpine.data('shareImage', (title, brand, errorText) => ({
        busy: false,

        async make() {
            const target = this.$el.closest('[data-share-target]');
            if (!target || this.busy) {
                return;
            }
            this.busy = true;

            const footer = document.createElement('div');
            footer.className = 'share-footer';
            footer.textContent = brand + ' · ' + new Date().toLocaleDateString('pl-PL');

            try {
                const { toBlob } = await import('html-to-image');
                target.classList.add('is-sharing');
                target.appendChild(footer);

                const background = getComputedStyle(document.body).backgroundColor;
                const blob = await toBlob(target, {
                    pixelRatio: 2,
                    backgroundColor: background,
                    filter: (node) => !(node instanceof HTMLElement && node.hasAttribute('data-share-hide')),
                });
                if (!blob) {
                    throw new Error('empty');
                }

                const file = new File([blob], slug(title) + '.png', { type: 'image/png' });
                if (navigator.canShare?.({ files: [file] })) {
                    try {
                        await navigator.share({ files: [file], title });
                    } catch (e) {
                        // Zamknięte okno udostępniania to nie błąd.
                        if (e.name !== 'AbortError') {
                            throw e;
                        }
                    }
                } else {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = file.name;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                }
            } catch (e) {
                console.error(e);
                toast(errorText);
            } finally {
                footer.remove();
                target.classList.remove('is-sharing');
                this.busy = false;
            }
        },
    }));
});
