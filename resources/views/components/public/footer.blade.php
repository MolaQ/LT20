{{--
    Stopka każdej strony (layouts::shell): statystyki (teraz na stronie, gracze, typy, kolejki sezonu)
    i linki do stron informacyjnych, w tym Biuro skarg i wniosków (z liczbą trwających głosowań).
--}}
@php
    $online = \App\Support\SiteStats::online();
    $game = \App\Support\SiteStats::game();
    $voting = \Illuminate\Support\Facades\Cache::remember('site-stats:office-voting', 300,
        fn () => \App\Models\OfficeRequest::where('status', \App\Models\OfficeRequest::VOTING)->count());
    // Kafelki: [ikona, etykieta, liczba, podpis, kolor ikony, dodatki]; liczby „nabijają się” po pokazaniu (uiCountUp).
    $stats = array_values(array_filter([
        $online ? ['users', __('Online now'), $online['online'], __(':members logged in, :guests guests', ['members' => $online['members'], 'guests' => $online['guests']]), 'text-green-600 bg-green-500/10', ['live' => true]] : null,
        ['user-group', __('Players'), $game['players'], null, 'text-club bg-club/10', []],
        ['pencil-square', __('Tips placed'), $game['tips'], null, 'text-amber-600 bg-amber-500/10', []],
        $game['season'] ? ['calendar-days', __('Matchdays played'), $game['played'], $game['season'], 'text-purple-600 bg-purple-500/10', ['of' => $game['matchdays']]] : null,
    ]));
    $links = [
        [route('rules'), __('Rules')],
        [route('faq'), __('FAQ')],
        [route('support'), __('Premium and support')],
        [route('hall-of-fame'), __('Hall of Fame')],
        [route('system'), __('System information')],
    ];
@endphp

<footer class="mt-8 border-t border-ui-line pt-6 text-sm">
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($stats as [$icon, $label, $value, $hint, $tone, $extra])
            <div class="group relative overflow-hidden rounded-2xl border border-ui-line bg-gradient-to-br from-ui-surface to-ui-raised/60 p-4 transition duration-200 hover:border-club/30 hover:shadow-md motion-safe:hover:-translate-y-0.5">
                <div class="flex items-center gap-3">
                    <span class="{{ $tone }} grid size-10 shrink-0 place-items-center rounded-xl transition-transform duration-300 motion-safe:group-hover:scale-110">
                        <x-ui.icon :name="$icon" variant="mini" class="size-5" />
                    </span>
                    <dt class="flex items-center gap-1.5 text-xs font-medium text-ui-muted">
                        {{ $label }}
                        @if ($extra['live'] ?? false)
                            {{-- Pulsująca kropka „na żywo” (bez ruchu przy ograniczonych animacjach). --}}
                            <span class="relative flex size-2" title="{{ __('Live') }}">
                                <span class="absolute inline-flex size-full rounded-full bg-green-500 opacity-60 motion-safe:animate-ping"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-green-500"></span>
                            </span>
                        @endif
                    </dt>
                </div>
                <dd class="mt-3 text-2xl font-semibold leading-none tabular-nums text-ui-ink">
                    <span x-data="uiCountUp({{ (int) $value }})" x-text="shown">{{ number_format($value, 0, ',', ' ') }}</span>
                    @isset($extra['of'])
                        <span class="text-base font-medium text-ui-muted">/ {{ $extra['of'] }}</span>
                    @endisset
                </dd>
                @isset($extra['of'])
                    {{-- Postęp sezonu: pasek rośnie po pokazaniu kafelka. --}}
                    @php
                        $percent = $extra['of'] > 0 ? (int) round(100 * $value / $extra['of']) : 0;
                    @endphp
                    <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-ui-line/70" x-data="{ on: false }" x-intersect.once="on = true">
                        <div class="h-full rounded-full bg-purple-500 transition-[width] duration-1000 ease-out" :style="`width: ${on ? {{ $percent }} : 0}%`" style="width: {{ $percent }}%"></div>
                    </div>
                @endisset
                @if ($hint)
                    <dd class="mt-1.5 truncate text-xs text-ui-muted">{{ $hint }}</dd>
                @endif
            </div>
        @endforeach
    </dl>

    <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <nav aria-label="{{ __('Footer') }}" class="flex flex-wrap gap-x-4 gap-y-2">
            @foreach ($links as [$href, $label])
                <a href="{{ $href }}" wire:navigate class="text-ui-muted hover:text-ui-ink hover:underline">{{ $label }}</a>
            @endforeach
        </nav>

        <a href="{{ route('office') }}" wire:navigate
            class="inline-flex shrink-0 items-center gap-2 self-start rounded-full border border-ui-line px-3 py-1.5 font-semibold text-ui-ink hover:bg-ui-raised">
            <x-ui.icon name="inbox" variant="micro" class="size-4 text-club" />
            {{ __('Complaints and requests office') }}
            @if ($voting > 0)
                <span class="rounded-full bg-club px-1.5 text-xs text-club-ink" title="{{ trans_choice(':count bot name is being voted on|:count bot names are being voted on', $voting, ['count' => $voting]) }}">{{ $voting }}</span>
            @endif
        </a>
    </div>

    <p class="mt-5 text-xs text-ui-muted">© {{ now()->year }} {{ config('app.name') }} · {{ __('An unofficial tipping game for Lech Poznań fans.') }}</p>
</footer>
