{{--
    Forma zawodnika: suma doświadczenia i słupki ostatnich meczów Lecha (od najstarszego). Pusty słupek: nie było go w kadrze meczowej,
    kropka nad słupkiem: gol. Parametry: $row (MatchReport::form()['players'][id] albo null), $matches (MatchReport::form()['matches']),
    opcjonalnie $bars = false (sama liczba) i $class.
--}}
@php
    $series = $row['series'] ?? array_fill(0, count($matches), null);
    $total = $row['total'] ?? 0.0;
    $showBars = $bars ?? true;
@endphp
<span class="inline-flex items-center gap-2 {{ $class ?? '' }}" title="{{ __('Form: experience from the last :count Lech matches', ['count' => count($matches)]) }}">
    <span class="min-w-7 text-right font-semibold tabular-nums {{ $total ? '' : 'text-zinc-300 dark:text-zinc-600' }}">{{ \App\Support\ClubStats::experience($total) }}</span>
    @if ($showBars && $matches !== [])
        <span class="flex h-6 items-end gap-[2px]" aria-hidden="true">
            @foreach ($matches as $i => $match)
                @php
                    $game = $series[$i] ?? null;
                    // Wysokość słupka: 6 i więcej doświadczenia to pełny słupek (np. cały mecz z golem).
                    $height = $game ? max(3, (int) round(min($game['experience'], 6) / 6 * 18)) : 2;
                    $tip = $match['opponent'] . ($match['date'] ? ' ' . $match['date'] : '') . ': '
                        . ($game ? \App\Support\ClubStats::experience($game['experience']) . ' ' . __('exp.') . ($game['goals'] ? ', ' . trans_choice(':count goal|:count goals', $game['goals']) : '') : __('not in the squad'));
                @endphp
                <span class="flex w-1.5 flex-col items-center justify-end gap-[2px]" title="{{ $tip }}">
                    @if ($game && $game['goals'])
                        <span class="size-1.5 rounded-full bg-amber-400"></span>
                    @endif
                    <span class="w-full rounded-sm {{ $game ? 'bg-club' : 'bg-zinc-200 dark:bg-zinc-700' }}" style="height: {{ $height }}px"></span>
                </span>
            @endforeach
        </span>
    @endif
</span>
