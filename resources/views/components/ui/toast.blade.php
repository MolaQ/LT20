{{--
    Dymek u góry ekranu: komunikaty z sesji (success, warning, error) i zdarzenie $dispatch('ui-toast', { text: '…', variant: 'danger' })
    (z serwera: $this->dispatch('ui-toast', text: …, variant: …)). variant danger i warning barwią dymek.
--}}
@php
    $flash = collect(['success', 'warning', 'error'])->map(fn ($key) => session($key))->filter()->first();
@endphp

<div x-data="{ text: @js($flash), tone: @js(session('error') ? 'danger' : (session('warning') ? 'warning' : null)), timer: null, show(t, tone = null) { this.text = t; this.tone = tone; clearTimeout(this.timer); this.timer = setTimeout(() => this.text = null, 3200) } }"
    x-init="text && show(text, tone)" @ui-toast.window="show($event.detail.text, $event.detail.variant ?? null)" role="status" aria-live="polite"
    class="pointer-events-none fixed inset-x-0 top-[calc(.75rem+env(safe-area-inset-top))] z-[60] flex justify-center px-4">
    <div x-show="text" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-y-[150%]"
        x-transition:leave="transition duration-200" x-transition:leave-end="-translate-y-[150%]"
        class="rounded-2xl px-4 py-2.5 text-sm font-bold shadow-lg"
        :class="tone === 'danger' ? 'bg-bad text-white' : (tone === 'warning' ? 'bg-gold text-[#2a1d00]' : 'bg-ui-ink text-ui-bg')" x-text="text"></div>
</div>
