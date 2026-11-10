// Edytor treści newsa w panelu (dashboard/news). Pole contenteditable z paskiem narzędzi:
// pogrubienie, kursywa, podkreślenie, przekreślenie, nagłówki, listy, cytat, link, ikona social mediów i obrazek.
// HTML trafia do własności Livewire "body"; serwer czyści go białą listą (App\Support\NewsHtml), więc tu
// dbamy tylko o wygodę. Obrazek: wgranie przez Livewire (własność "image", metoda storeImage), potem
// po kliknięciu w obrazek pasek szerokości (25–100%) i wyrównania.

const WIDTHS = [25, 33, 50, 66, 75, 100];

const escapeHtml = (text) =>
    String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const normalizeUrl = (url) => {
    const value = String(url ?? '').trim();
    if (value === '') {
        return '';
    }

    return /^(https?:\/\/|mailto:|\/)/i.test(value) ? value : 'https://' + value;
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('newsEditor', () => ({
        widths: WIDTHS,
        range: null,
        panel: null, // null, 'link' albo 'social'
        linkUrl: '',
        social: 'facebook',
        socialUrl: '',
        socialLabel: '',
        image: null, // zaznaczony obrazek w edytorze
        imageAlt: '',
        uploading: false,
        progress: 0,
        empty: true,
        timer: null,

        init() {
            document.execCommand('defaultParagraphSeparator', false, 'p');
            this.load(this.$wire.body ?? '');
        },

        // Treść z serwera (otwarcie formularza nowego albo edytowanego newsa).
        load(html) {
            this.$refs.area.innerHTML = html || '<p><br></p>';
            this.image = null;
            this.panel = null;
            this.refreshEmpty();
        },

        refreshEmpty() {
            this.empty = this.$refs.area.textContent.trim() === '' && !this.$refs.area.querySelector('img');
        },

        // Zapis do Livewire bez wysyłania żądania (pójdzie razem z "Zapisz").
        sync() {
            this.refreshEmpty();
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.$wire.$set('body', this.empty ? '' : this.$refs.area.innerHTML, false), 150);
        },

        saveRange() {
            const sel = window.getSelection();
            if (sel.rangeCount && this.$refs.area.contains(sel.getRangeAt(0).commonAncestorContainer)) {
                this.range = sel.getRangeAt(0).cloneRange();
            }
        },

        restoreRange() {
            this.$refs.area.focus();
            if (this.range) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(this.range);
            }
        },

        cmd(name, value = null) {
            this.restoreRange();
            document.execCommand(name, false, value);
            this.saveRange();
            this.sync();
        },

        block(tag) {
            const current = document.queryCommandValue('formatBlock').toLowerCase();
            this.cmd('formatBlock', current === tag ? 'p' : tag);
        },

        insertHtml(html) {
            this.restoreRange();
            document.execCommand('insertHTML', false, html);
            this.saveRange();
            this.sync();
        },

        openPanel(name) {
            this.saveRange();
            this.panel = this.panel === name ? null : name;
            this.linkUrl = '';
            this.socialUrl = '';
            this.socialLabel = '';
        },

        addLink() {
            const url = normalizeUrl(this.linkUrl);
            this.panel = null;
            if (!url) {
                return;
            }
            const text = this.range && !this.range.collapsed ? null : url;
            if (text) {
                this.insertHtml(`<a href="${escapeHtml(url)}">${escapeHtml(text)}</a>&nbsp;`);
            } else {
                this.cmd('createLink', url);
            }
        },

        addSocial(names) {
            const url = normalizeUrl(this.socialUrl);
            this.panel = null;
            if (!url) {
                return;
            }
            const label = this.socialLabel.trim() || names[this.social] || this.social;
            this.insertHtml(`<a href="${escapeHtml(url)}" class="social-link social-${escapeHtml(this.social)}">${escapeHtml(label)}</a>&nbsp;`);
        },

        pickImage() {
            this.saveRange();
            this.$refs.file.click();
        },

        uploadImage(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) {
                return;
            }
            this.uploading = true;
            this.progress = 0;
            this.$wire.upload(
                'image',
                file,
                async () => {
                    const url = await this.$wire.storeImage();
                    this.uploading = false;
                    if (url) {
                        this.insertHtml(`<img src="${escapeHtml(url)}" alt="" data-width="100" data-align="center" class="news-img news-img-center" style="width: 100%">`);
                    }
                },
                () => {
                    this.uploading = false;
                },
                (e) => {
                    this.progress = e.detail.progress;
                },
            );
        },

        // Kliknięcie w obrazek pokazuje jego pasek (szerokość, wyrównanie, opis, usunięcie).
        clickArea(event) {
            this.image = event.target.tagName === 'IMG' ? event.target : null;
            this.imageAlt = this.image?.getAttribute('alt') ?? '';
            this.saveRange();
        },

        setWidth(width) {
            if (!this.image) {
                return;
            }
            this.image.dataset.width = width;
            this.image.style.width = width + '%';
            this.sync();
        },

        setAlign(align) {
            if (!this.image) {
                return;
            }
            this.image.dataset.align = align;
            this.image.className = 'news-img news-img-' + align;
            this.sync();
        },

        setAlt() {
            this.image?.setAttribute('alt', this.imageAlt);
            this.sync();
        },

        removeImage() {
            this.image?.remove();
            this.image = null;
            this.sync();
        },

        // Wklejony tekst bez stylów z innych stron (formatowanie robi się przyciskami).
        paste(event) {
            const text = event.clipboardData.getData('text/plain');
            document.execCommand('insertText', false, text);
            this.sync();
        },
    }));
});
