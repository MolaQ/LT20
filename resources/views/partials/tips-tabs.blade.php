{{-- Zakładki strony „Moje typy” poza typowaniem: rozgrywki, statystyki, historia (App\Support\PlayerStats). --}}
@php
    $isPremium = \App\Support\Premium::isActive(auth()->user());
@endphp

@if ($tab === 'competitions')
    @php
        $trophyIcons = \App\Support\HallOfFame::iconUrls();
        // Kolor kafelka meczu według punktów za typ w tej kolejce: 3 niebieski, 2 zielony, 1 żółty, 0 czerwony.
        $tipClasses = [
            3 => 'border-blue-300 bg-blue-50 dark:border-blue-500/40 dark:bg-blue-500/10',
            2 => 'border-green-300 bg-green-50 dark:border-green-500/40 dark:bg-green-500/10',
            1 => 'border-yellow-300 bg-yellow-50 dark:border-yellow-500/40 dark:bg-yellow-500/10',
            0 => 'border-red-300 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10',
        ];
        // Forma w nagłówku: zielona W (wygrana), żółta R (remis), czerwona P (porażka).
        $formClasses = ['W' => 'bg-green-500 text-white', 'D' => 'bg-yellow-400 text-yellow-950', 'L' => 'bg-red-600 text-white'];
        $formLetters = ['W' => 'W', 'D' => 'R', 'L' => 'P'];
    @endphp

    {{--
        Kafelki meczów: kolumna na kolejkę (9 w rzędzie na dużym ekranie, po 3 na mniejszych), w Lidze Legend dwa mecze
        jedna pod drugim. Kolejki bez meczu: szare „odpadł” po odpadnięciu, inaczej puste miejsce na przyszły mecz.
        Puchar Polski na końcu listy.
    --}}
    @forelse (collect($this->myCompetitions)->sortBy(fn ($row) => $row['type'] === \App\Enums\CompetitionType::Cup ? 1 : 0)->values() as $row)
        @php
            $perRound = $row['type'] === \App\Enums\CompetitionType::Legends ? 2 : 1;
            $byRound = collect($row['matches'])->groupBy('round');
        @endphp
        <section class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface shadow-sm" wire:key="comp-{{ $loop->index }}">
            <div class="lech-bar flex flex-wrap items-center gap-3 px-4 py-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-ui-surface shadow-xs">
                    @if (isset($trophyIcons[$row['trophy']]))
                        <img src="{{ $trophyIcons[$row['trophy']] }}" alt="" class="size-7 object-contain" title="{{ __('Trophy to win') }}">
                    @else
                        <x-ui.icon name="trophy" class="text-amber-500" />
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold">{{ $row['name'] }}</div>
                    <div class="text-xs text-lech-200">{{ $row['status'] }}</div>
                </div>
                @if ($row['points'] !== null)
                    <div class="text-right">
                        <div class="text-xl font-bold tabular-nums text-white">{{ __(':points pts', ['points' => $row['points']]) }}</div>
                        {{-- Forma: plakietka na każdy rozegrany mecz w kolejności kolejek (W wygrana, R remis, P porażka) --}}
                        <div class="mt-1 flex flex-wrap justify-end gap-1">
                            @foreach ($row['matches'] as $formMatch)
                                @if ($formMatch['outcome'])
                                    <span class="{{ $formClasses[$formMatch['outcome']] }} flex size-5 items-center justify-center rounded text-[11px] font-black"
                                        title="{{ $row['type']->stageLabel($formMatch['round']) }}: {{ $formMatch['score'] }}">{{ $formLetters[$formMatch['outcome']] }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-3 gap-2 p-3 lg:grid-cols-9">
                @foreach (range(1, \App\Models\Matchday::PER_SEASON) as $round)
                    @php
                        $roundMatches = $byRound->get($round, collect())->values();
                        $gone = $row['eliminated_round'] && $round > $row['eliminated_round'];
                    @endphp
                    <div class="flex min-w-0 flex-col gap-1.5" wire:key="cr-{{ $loop->parent->index }}-{{ $round }}">
                        <div class="truncate text-center text-[11px] font-semibold uppercase tracking-wide text-ui-muted" title="{{ $row['type']->stageLabel($round) }}">{{ $row['type']->stageLabel($round) }}</div>
                        @foreach (range(0, $perRound - 1) as $slot)
                            @php
                                $match = $roundMatches[$slot] ?? null;
                            @endphp
                            @if ($match)
                                {{-- Kliknięcie otwiera uniwersalne okno pojedynku (pages::home.duel-modal) --}}
                                <button type="button" wire:click="$dispatch('show-duel', { id: {{ $match['fixture_id'] }} })" title="{{ __('Match details') }}"
                                    class="{{ $match['tip_points'] !== null ? $tipClasses[$match['tip_points']] : 'border-ui-line' }} flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-xl border px-1.5 py-2 text-center text-sm transition hover:shadow-md">
                                    <span class="w-full truncate text-xs font-medium">{{ $match['rival'] }}</span>
                                    <span class="flex items-center gap-1">
                                        @if ($match['outcome'])
                                            <span class="{{ $formClasses[$match['outcome']] }} flex size-5 items-center justify-center rounded text-[10px] font-black">{{ $formLetters[$match['outcome']] }}</span>
                                        @endif
                                        <span class="font-bold tabular-nums">{{ $match['score'] ?? '–:–' }}</span>
                                    </span>
                                    @if ($match['tip_points'] !== null)
                                        <span class="text-[11px] text-ui-muted">{{ __(':points pts', ['points' => $match['tip_points']]) }}</span>
                                    @endif
                                </button>
                            @elseif ($gone)
                                {{-- Zespół już odpadł z rozgrywek (puchary, Liga Legend) --}}
                                <div class="flex flex-1 flex-col items-center justify-center gap-0.5 rounded-xl border border-zinc-400 bg-ui-raised px-1.5 py-2 text-center text-ui-muted dark:border-zinc-600">
                                    <x-ui.icon name="x-circle" variant="micro" />
                                    <span class="w-full truncate text-[11px] font-medium">{{ __('Team eliminated') }}</span>
                                </div>
                            @else
                                {{-- Mecz jeszcze nieznany (przyszła kolejka albo runda) --}}
                                <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-zinc-300 px-1.5 py-2 text-zinc-300 dark:border-zinc-700 dark:text-zinc-600">
                                    <span class="font-bold">–:–</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <x-ui.card>
            <x-ui.text>{{ __('No competitions yet.') }}</x-ui.text>
        </x-ui.card>
    @endforelse
    <a href="{{ route('results') }}" wire:navigate class="text-sm font-semibold text-club underline-offset-2 hover:underline">{{ __('Results and tables') }}</a>
@elseif ($tab === 'stats')
    @php
        $data = $this->myStats;
        $stats = $data['mine'];
        $extras = $this->myExtras;
        $trophyIcons = \App\Support\HallOfFame::iconUrls();
        $formClasses = ['W' => 'bg-green-500 text-white', 'D' => 'bg-yellow-400 text-yellow-950', 'L' => 'bg-red-600 text-white'];
        $formLetters = ['W' => 'W', 'D' => 'R', 'L' => 'P'];
    @endphp

    {{-- Miejsce wśród graczy (premium) i forma --}}
    <x-lech-panel :title="__('Place among the players')" :subtitle="__('By points for tips in this season.')" icon="chart-bar">
        <x-slot:actions><x-premium-badge /></x-slot:actions>
        <div class="flex flex-wrap items-center justify-between gap-4">
            @if (!$data['rank'])
                <x-ui.text>{{ __('Available after the first settled matchday.') }}</x-ui.text>
            @elseif ($isPremium)
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black tabular-nums text-club">{{ $data['rank']['place'] }}.</span>
                    <span class="text-sm text-ui-muted">{{ __('of :count', ['count' => $data['rank']['count']]) }}</span>
                </div>
            @else
                <x-ui.text size="sm">
                    {{ __('Your exact place among the players is a premium feature.') }}
                    <a href="{{ route('support') }}" wire:navigate class="font-semibold text-club underline-offset-2 hover:underline">{{ __('Premium') }}</a>
                </x-ui.text>
            @endif

            @if (count($stats['form']) > 0)
                <div class="flex items-center gap-1">
                    <span class="me-2 text-[11px] font-semibold uppercase tracking-wide text-ui-muted">{{ __('Form') }}</span>
                    @foreach ($stats['form'] as $result)
                        <span class="{{ $formClasses[$result] }} flex size-6 items-center justify-center rounded text-xs font-black">{{ $formLetters[$result] }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </x-lech-panel>

    @include('partials.player-stats-tiles', ['data' => $data, 'user' => auth()->user()])

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <x-lech-panel :title="__('Favourite tips (all seasons)')" icon="heart">
            <div class="space-y-3">
                @foreach (['mine' => __('Yours'), 'all' => __('All players')] as $key => $label)
                    @php
                        $fav = $extras['favourites'][$key];
                    @endphp
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-medium">{{ $label }}</span>
                        @if ($fav)
                            <span class="flex items-center gap-2">
                                <span class="rounded-md bg-club text-club-ink px-2 py-0.5 font-black tabular-nums">{{ $fav['score'] }}</span>
                                <span class="text-xs text-ui-muted">{{ __(':count of :total tips', ['count' => $fav['count'], 'total' => $fav['total']]) }}</span>
                            </span>
                        @else
                            <span class="text-ui-muted">—</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-lech-panel>

        <x-lech-panel :title="__('Best season')" icon="star">
            @if ($extras['best_season'])
                <p class="text-3xl font-black text-club">{{ $extras['best_season']['season']?->title }}</p>
                <x-ui.text size="sm">{{ __(':points pts for tips', ['points' => $extras['best_season']['points']]) }}</x-ui.text>
            @else
                <x-ui.text>{{ __('Available after the first settled matchday.') }}</x-ui.text>
            @endif
        </x-lech-panel>

        {{-- Premium: najczęstszy typ w rozgrywkach --}}
        <x-lech-panel :title="__('Favourite tip in each competition')" icon="sparkles">
            <x-slot:actions><x-premium-badge /></x-slot:actions>
            @if (!$isPremium)
                <x-ui.text size="sm">
                    {{ __('Premium shows the most popular tip in every competition of the season.') }}
                    <a href="{{ route('support') }}" wire:navigate class="font-semibold text-club underline-offset-2 hover:underline">{{ __('See premium') }}</a>
                </x-ui.text>
            @else
                <div class="space-y-2">
                    @forelse ($extras['competitions'] as $row)
                        <div class="flex items-center gap-2 text-sm" wire:key="cfav-{{ $loop->index }}">
                            @if (isset($trophyIcons[$row['trophy']]))
                                <img src="{{ $trophyIcons[$row['trophy']] }}" alt="" class="size-5 object-contain">
                            @else
                                <x-ui.icon name="trophy" variant="micro" class="text-amber-500" />
                            @endif
                            <span class="min-w-0 flex-1 truncate" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                            <span class="rounded-md bg-club text-club-ink px-1.5 py-0.5 text-xs font-black tabular-nums">{{ $row['score'] }}</span>
                            <span class="w-20 text-right text-xs text-ui-muted">{{ __(':count of :total tips', ['count' => $row['count'], 'total' => $row['total']]) }}</span>
                        </div>
                    @empty
                        <x-ui.text>{{ __('No tips yet.') }}</x-ui.text>
                    @endforelse
                </div>
            @endif
        </x-lech-panel>
    </div>
@elseif ($tab === 'club')
    {{-- Mój klub: zawodnicy Lecha w klubie gracza i ich dorobek (desygnowanie) --}}
    @include('partials.club-players', ['clubPlayers' => $this->myClub, 'clubTitle' => __('My squad')])
@elseif ($tab === 'history')
    @php
        $history = $this->myHistory;
        $records = $history['records'];
        $recordTiles = [
            [__('Matches'), $records['played'], 'calendar-days'],
            [__('Won, drawn, lost'), $records['won'] . '–' . $records['drawn'] . '–' . $records['lost'], 'scale'],
            [__('Goals'), $records['for'] . ':' . $records['against'], 'bolt'],
            [__('Most goals in a match'), $records['record_goals'], 'fire'],
            [__('Longest unbeaten run'), $records['unbeaten_best'], 'shield-check'],
            [__('Longest winning streak'), $records['wins_best'], 'arrow-trending-up'],
        ];
    @endphp

    <x-lech-panel :title="__('Personal records (all seasons)')" icon="trophy">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($recordTiles as [$label, $value, $tileIcon])
                <div class="flex flex-col gap-1 rounded-xl border border-ui-line bg-zinc-50/60 p-3 dark:bg-white/5">
                    <x-ui.icon :name="$tileIcon" variant="mini" class="text-lech-600 dark:text-lech-300" />
                    <div class="text-2xl font-black leading-tight tabular-nums">{{ $value }}</div>
                    <div class="text-[11px] font-semibold uppercase leading-snug tracking-wide text-ui-muted">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </x-lech-panel>

    {{-- Starty w rozgrywkach (tabele końcowe zakończonych sezonów) w barwach rozgrywek --}}
    <x-lech-panel :title="__('History in competitions')" icon="building-library" flush>
        <x-slot:actions>
            <x-ui.btn size="xs" icon="archive-box" :href="route('team.show', auth()->user())" wire:navigate>{{ __('Trophy cabinet') }}</x-ui.btn>
        </x-slot:actions>
        @if (count($history['competitions']) === 0)
            <div class="p-4"><x-ui.text>{{ __('History appears after the first finished season.') }}</x-ui.text></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[34rem] text-sm">
                    <thead class="bg-zinc-50 text-[11px] font-bold uppercase tracking-wider text-ui-muted dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-left">{{ __('Competition') }}</th>
                            <th class="px-2 py-2 text-right">{{ __('Seasons') }}</th>
                            <th class="px-2 py-2 text-right">{{ __('Best place') }}</th>
                            <th class="px-2 py-2 text-right">{{ __('Titles') }}</th>
                            <th class="px-2 py-2 text-right">{{ __('Wins') }}</th>
                            <th class="px-2 py-2 text-right">{{ __('Goals') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Points') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ui-line">
                        @foreach ($history['competitions'] as $row)
                            <tr wire:key="hist-{{ $loop->index }}">
                                <td class="px-4 py-2.5">
                                    <span class="flex items-center gap-2 font-semibold">
                                        <span class="comp-badge {{ \App\Support\CompetitionColors::dashed($row['key']) ? 'comp-dashed' : '' }} size-3 shrink-0 rounded-full" style="{{ \App\Support\CompetitionColors::style($row['key']) }}"></span>
                                        {{ $row['label'] }}
                                    </span>
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ $row['seasons'] }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ $row['best'] }}.</td>
                                <td class="px-2 py-2.5 text-right">
                                    @if ($row['titles'] > 0)
                                        <span class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-black text-amber-950">{{ $row['titles'] }}</span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ $row['won'] }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ $row['goals'] }}</td>
                                <td class="px-4 py-2.5 text-right font-black tabular-nums">{{ $row['points'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-lech-panel>
@elseif ($tab === 'scalps')
    {{-- Moje skalpy (premium): gracze, z którymi mam lepszy bilans bezpośredni, od najwyżej w rankingu Hall of Fame. --}}
    @if (!$isPremium)
        <x-premium-lock :title="__('My scalps')" :text="__('With premium you see the rivals you beat more often than they beat you, all seasons and competitions together.')" />
    @elseif (count($this->myScalps) === 0)
        <x-lech-panel :title="__('My scalps')" icon="fire">
            <x-ui.text>{{ __('No scalps yet. Win more matches than you lose against a player and they will appear here.') }}</x-ui.text>
        </x-lech-panel>
    @else
        @include('partials.rival-list', [
            'rivals' => $this->myScalps,
            'title' => __('My scalps'),
            'subtitle' => __('Players you have a better head-to-head record against, from the highest in the Hall of Fame ranking. Bots and banned accounts are left out.'),
            'icon' => 'fire',
            'tone' => 'scalps',
        ])
    @endif
@elseif ($tab === 'blacklist')
    {{-- Czarna lista (premium): rywale, z którymi mam ujemny bilans bezpośredni, od najgorszego bilansu. --}}
    @if (!$isPremium)
        <x-premium-lock :title="__('Blacklist')" :text="__('With premium you see the rivals you lose to more often than you beat them, all seasons and competitions together.')" />
    @elseif (count($this->myBlacklist) === 0)
        <x-lech-panel :title="__('Blacklist')" icon="no-symbol">
            <x-ui.text>{{ __('Your blacklist is empty. Nobody has a better head-to-head record against you.') }}</x-ui.text>
        </x-lech-panel>
    @else
        @include('partials.rival-list', [
            'rivals' => $this->myBlacklist,
            'title' => __('Blacklist'),
            'subtitle' => __('Players you have a worse head-to-head record against, from the worst record. Bots and banned accounts are left out.'),
            'icon' => 'no-symbol',
            'tone' => 'blacklist',
        ])
    @endif
@endif
