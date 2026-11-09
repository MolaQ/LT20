{{-- Zakładki strony „Kolejki”: kolejki sezonu i kalendarz meczów Lecha, z którego kolejki biorą mecze.
     $tab ('matchdays' albo 'calendar') zamiast routeIs, bo akcje Livewire idą innym adresem. --}}
<nav class="-mb-2 flex gap-1 border-b border-ui-line">
    @foreach ([['matchdays', 'calendar', route('dashboard.matchdays'), __('Season matchdays')], ['calendar', 'calendar-days', route('dashboard.lech-calendar'), __('Lech calendar')]] as [$key, $icon, $href, $label])
        <a href="{{ $href }}" wire:navigate @class([
            '-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold transition',
            'border-club text-ui-ink' => $tab === $key,
            'border-transparent text-ui-muted hover:text-ui-ink' => $tab !== $key,
        ])>
            <x-ui.icon :name="$icon" variant="mini" class="size-4" />{{ $label }}
        </a>
    @endforeach
</nav>
