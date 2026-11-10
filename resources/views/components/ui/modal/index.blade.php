{{--
    Okno (zamiennik flux:modal): na telefonie arkusz od dołu, na komputerze okno na środku ekranu. Otwieranie i zamykanie
    tym samym zdarzeniem co x-ui.sheet: $dispatch('ui-sheet', { name }) / { name, open: false }, z serwera
    $this->dispatch('ui-sheet', name: …). Przy zamknięciu okno wysyła zdarzenie close (@close="$wire.resetForm()").
    class (szerokość) trafia do samego okna.
--}}
@props(['name'])

<div x-data="uiSheet(@js($name))" x-cloak @keydown.escape.window="open && close()" {{ $attributes->except('class') }}>
    <div x-show="open" x-transition.opacity @click="close()" class="fixed inset-0 z-40 bg-black/45"></div>
    <div x-show="open" role="dialog" aria-modal="true"
        x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full opacity-0 md:translate-y-4" x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-y-full opacity-0 md:translate-y-4"
        :style="drag ? `transform: translateY(${drag}px)` : ''"
        class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-center md:inset-0 md:items-center md:p-6">
        <div {{ $attributes->only('class')->class('pointer-events-auto flex max-h-[85dvh] w-full max-w-xl flex-col gap-3 overflow-y-auto rounded-t-[26px] bg-ui-surface px-5 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pt-2.5 text-ui-ink shadow-xl md:max-h-[90dvh] md:rounded-[22px] md:pb-5 md:pt-5') }}>
            <div class="-mx-5 -mt-2.5 flex touch-none cursor-grab justify-center pb-1 pt-2.5 md:hidden" @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()">
                <span class="h-1.5 w-11 rounded-full bg-ui-line"></span>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
