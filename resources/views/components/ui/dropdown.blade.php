{{--
    Rozwijane menu (zamiennik flux:dropdown): pierwszy element to przycisk, x-ui.menu to lista.
    Kliknięcie w środku otwiera albo zamyka, kliknięcie obok zamyka. align: start, end; position: bottom, top.
--}}
@props(['align' => 'start', 'position' => 'bottom'])

<div x-data="{ open: false }" @click="open = !open" @click.outside="open = false" @keydown.escape="open = false"
    {{ $attributes->class('relative inline-block') }}>
    {{ $slot }}
</div>
