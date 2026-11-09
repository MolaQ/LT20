{{-- Dymek u góry ekranu: komunikaty z sesji (success, warning, error) i zdarzenie $dispatch('ui-toast', { text: '…' }). --}}
@php
    $flash = collect(['success', 'warning', 'error'])->map(fn ($key) => session($key))->filter()->first();
@endphp

<div x-data="{ text: @js($flash), timer: null, show(t) { this.text = t; clearTimeout(this.timer); this.timer = setTimeout(() => this.text = null, 2800) } }"
    x-init="text && show(text)" @ui-toast.window="show($event.detail.text)" role="status" aria-live="polite"
    class="pointer-events-none fixed inset-x-0 top-[calc(.75rem+env(safe-area-inset-top))] z-[60] flex justify-center px-4">
    <div x-show="text" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-y-[150%]"
        x-transition:leave="transition duration-200" x-transition:leave-end="-translate-y-[150%]"
        class="rounded-2xl bg-ui-ink px-4 py-2.5 text-sm font-bold text-ui-bg shadow-lg" x-text="text"></div>
</div>
