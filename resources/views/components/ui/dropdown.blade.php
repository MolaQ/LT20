{{--
    Rozwijane menu (zamiennik flux:dropdown): pierwszy element to przycisk, x-ui.menu to lista.
    Kliknięcie w środku otwiera albo zamyka, kliknięcie obok zamyka. align: start, end; position: bottom, top.
    Lista ma pozycję fixed liczoną przy otwarciu, więc nie ucina jej tabela ani karta z overflow (np. menu „…” w wierszu);
    przewinięcie albo zmiana rozmiaru okna ją zamyka.
--}}
@props(['align' => 'start', 'position' => 'bottom'])

<div x-data="{
        open: false,
        pos: { top: 0, bottom: 0, left: 0, right: 0 },
        toggle() {
            this.open = ! this.open;
            if (this.open) {
                const r = this.$el.getBoundingClientRect();
                this.pos = { top: r.bottom + 4, bottom: window.innerHeight - r.top + 4, left: r.left, right: document.documentElement.clientWidth - r.right };
            }
        },
    }" @click="toggle()" @click.outside="open = false" @keydown.escape="open = false"
    @scroll.window="open = false" @resize.window="open = false"
    {{ $attributes->class('relative inline-block') }}>
    {{ $slot }}
</div>
