<?php

use App\Support\SiteStats;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Licznik odwiedzin w stopce (wzór z jokerpila.pl): osoby teraz, goście dziś, wizyty i odsłony.
 * Odświeża się co 15 sekund, gdy stopka jest widoczna; zmieniona liczba wjeżdża od dołu i na chwilę się podświetla.
 */
new class extends Component {
    /** @return array{online: int, today: int, visits: int, total: int} */
    #[Computed]
    public function counts(): array
    {
        return SiteStats::visits();
    }
}; ?>

@php
    $counts = $this->counts;
    $stats = [
        ['online', $counts['online'], trans_choice('person now|people now', $counts['online']), __('People on the site in the last :minutes minutes', ['minutes' => \App\Support\SiteStats::ONLINE_MINUTES])],
        ['today', $counts['today'], trans_choice('guest today|guests today', $counts['today']), __('Visitors today')],
        ['visits', $counts['visits'], trans_choice('visit|visits', $counts['visits']), __('All visits (a visitor counts once a day)')],
        ['total', $counts['total'], trans_choice('page view|page views', $counts['total']), __('All page views')],
    ];
@endphp
<div wire:poll.15s.visible class="grid w-full grid-cols-4 divide-x divide-white/10 overflow-hidden rounded-2xl bg-white/5 tabular-nums ring-1 ring-white/10 sm:w-auto sm:min-w-96" aria-live="polite">
    @foreach ($stats as [$key, $value, $label, $title])
        <div class="flex min-w-0 flex-col items-center justify-center px-2 py-2 text-center sm:px-4" title="{{ $title }}">
            <span class="flex items-center gap-1.5 text-lg font-semibold leading-none text-white">
                @if ($key === 'online')
                    <span class="relative flex size-2 shrink-0" aria-hidden="true">
                        <span class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-60 motion-safe:animate-ping"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span>
                    </span>
                @endif
                <span class="overflow-hidden"><span wire:key="stat-{{ $key }}-{{ $value }}" class="stat-tick inline-block">{{ number_format($value, 0, ',', ' ') }}</span></span>
            </span>
            <span class="mt-1 truncate text-[11px] text-white/60">{{ $label }}</span>
        </div>
    @endforeach
</div>
