{{--
    Arkusz od dołu (też zamiast okien Flux). Otwieranie: $dispatch('ui-sheet', { name: 'more' }), zamykanie: { name, open: false }
    albo z serwera $this->dispatch('ui-sheet', name: …, open: false). Zamyka tło, Esc i przeciągnięcie w dół. wide: szerszy na komputerze.
--}}
@props(['name', 'title' => null, 'wide' => false])

<div x-data="uiSheet(@js($name))" x-cloak @keydown.escape.window="close()" {{ $attributes }}>
    <div x-show="open" x-transition.opacity @click="close()" class="fixed inset-0 z-40 bg-black/45"></div>
    <div x-show="open" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif
        x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
        x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
        :style="drag ? `transform: translateY(${drag}px)` : ''"
        class="fixed inset-x-0 bottom-0 z-50 mx-auto flex max-h-[85dvh] {{ $wide ? 'max-w-2xl' : 'max-w-xl' }} flex-col gap-3 overflow-y-auto rounded-t-[26px] bg-ui-surface px-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pt-2.5 text-ui-ink">
        {{-- Uchwyt: tu łapiemy palcem i ciągniemy w dół (reszta arkusza normalnie się przewija). --}}
        <div class="-mx-4 -mt-2.5 flex touch-none cursor-grab justify-center pb-1 pt-2.5" @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()">
            <span class="h-1.5 w-11 rounded-full bg-ui-line"></span>
        </div>
        @if ($title)
            <h2 class="font-display text-2xl font-extrabold">{{ $title }}</h2>
        @endif
        {{ $slot }}
    </div>
</div>
