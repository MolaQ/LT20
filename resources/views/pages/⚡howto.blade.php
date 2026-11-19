<?php

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Poradnik dla nowych graczy (16.11.2026): od typu do pytań, w czterech częściach z przykładami.
 *  1. Typ i punktacja (przykłady dla wyniku 2:1, wynik pojedynku = punkty za typ + atak − obrona rywala),
 *  2. Skład i rozgrywki (każde rozgrywki mają osobny skład i pytania; jak przełączyć),
 *  3. Doświadczenie i forma (17.11.2026): za co, w klubie, o czym decyduje (bloki w barwach rozgrywek), forma ze słupkami,
 *  4. Pytania tak/nie, atak albo obrona, zerowanie bonusu, pytanie unieważnione i strategie,
 *  5. Pierwszy mecz sezonu (zaproszenie).
 * Liczby (limit zawodników, pytania w zestawie, punkty czystego konta) czytane z ustawień gry, żeby poradnik nie kłamał.
 * Te same treści jako grafiki na x.com powstają poza serwisem (podglady/poradnik).
 */
new #[Layout('layouts::public')] class extends Component {
    public function render(): View
    {
        return $this->view()->title(__('How to play: a guide for beginners'));
    }
}; ?>

<div class="flex w-full flex-col gap-8">
    @php
        $maxPlayers = \App\Support\Designation::value('max_players');
        $perSet = \App\Support\Questions::PER_SET;
        $nextMatchday = \App\Models\Matchday::whereHas('season', fn ($q) => $q->where('status', \App\Enums\SeasonStatus::Active->value))
            ->whereNotNull('kickoff_at')->where('kickoff_at', '>', now())->orderBy('kickoff_at')->first();
        // Przykład z części 1: mecz kończy się 2:1 dla Lecha.
        $examples = [
            ['2:1', 3, [true, true, true], __('Exact score: the outcome, the difference and the score. That is a Koziołek!')],
            ['3:2', 2, [true, true, false], __('Lech wins by one goal: the outcome and the difference are right.')],
            ['3:0', 1, [true, false, false], __('Lech wins, but by a different margin: only the outcome.')],
            ['1:1', 0, [false, false, false], __('A draw instead of a Lech win: no points.')],
        ];
        $parts = [
            ['tip', '1', __('Your tip and points')],
            ['squad', '2', __('Squad and competitions')],
            ['experience', '3', __('Experience and form')],
            ['questions', '4', __('Questions and the bonus')],
            ['match', '5', __('Your first match')],
        ];
        // Część 3: doświadczenie za mecz z ustawień gry (Support\Designation), żeby przykłady zgadzały się z rozliczeniem.
        $exp = fn (string $key) => \App\Support\Designation::number($key);
        $num = fn (float $value) => \App\Support\ClubStats::experience($value);
        $expRows = [
            [$exp('exp_start'), __('Starting eleven'), __('in the line-up from the first whistle')],
            [$exp('exp_sub'), __('On the bench'), __('also when he does not come on')],
            [$exp('exp_full_half'), __('Every full half'), __('extra time is two more halves')],
            [$exp('exp_partial_half'), __('Part of a half'), __('came on or went off during it')],
            [$exp('exp_clean_sheet'), __('Clean sheet'), __('a goalkeeper or defender who played, Lech conceded nothing')],
            [$exp('exp_goal'), __('Every goal and assist'), __('a goal in a penalty shoot-out counts too')],
        ];
        $expExamples = [
            [__('Start, the whole match and a goal'), [$exp('exp_start'), 2 * $exp('exp_full_half'), $exp('exp_goal')], 1],
            [__('On at half-time, one assist'), [$exp('exp_sub'), $exp('exp_full_half'), $exp('exp_assist')], 1],
            [__('On late in the second half'), [$exp('exp_sub'), $exp('exp_partial_half')], 1],
            [__('On the bench, did not come on'), [$exp('exp_sub')], 1],
            [__('Academy graduate: start and the whole match'), [$exp('exp_start'), 2 * $exp('exp_full_half')], \App\Support\MatchReport::ACADEMY_EXPERIENCE],
        ];
        // O czym decyduje doświadczenie: bloki w barwach rozgrywek (Support\CompetitionColors).
        $expDecides = [
            ['cup', __('Puchar Polski'), __('Decides draws'), __('A draw goes to the team whose fielded players have more experience in the club. Equal experience: the earlier saved tip.')],
            ['champions', __('Liga Mistrzów, Europy and Konferencji'), __('From the round of 16'), __('Group matches end as they are. In the knockout rounds a draw is decided like in Puchar Polski.')],
            ['league_1', __('Ekstraklasa and the other leagues'), __('No effect on the result'), __('A draw stays a draw: 1 point each. Experience still grows, so collect it for the cups.')],
            ['legends', __('Liga Legend'), __('No effect on the result'), __('Two duels per matchday, a draw is not a loss. Experience is not a tie-breaker in the table.')],
        ];
        // Forma: makieta dwóch zawodników na 10 ostatnich meczach (od najstarszego).
        $formMatches = collect(['Legia', 'Raków', 'Jagiellonia', 'Pogoń', 'Górnik', 'Widzew', 'Cracovia', 'Śląsk', 'Motor', 'Piast'])
            ->map(fn ($opponent, $i) => ['id' => $i, 'opponent' => $opponent, 'date' => null])->all();
        $formSample = [
            [__('Plays every match'), ['total' => 37.0, 'series' => [[4, 1, 0], [3, 0, 1], [5, 1, 0], [3, 0], [6, 2, 1], null, [4, 1], [3, 0], [5, 1, 1], [4, 1]]]],
            [__('Comes back to the team'), ['total' => 18.0, 'series' => [null, null, [1.5, 0], [2, 0], [1, 0], [3, 0], null, [3.5, 1], [4, 1], [3, 0]]]],
            [__('Rarely in the squad'), ['total' => 4.0, 'series' => [null, null, null, null, [0.5, 0], null, [1, 0], [0.5, 0], null, [2, 0]]]],
        ];
        foreach ($formSample as $i => [$label, $row]) {
            $formSample[$i][1]['series'] = array_map(fn ($g) => $g ? ['experience' => (float) $g[0], 'goals' => $g[1], 'assists' => $g[2] ?? 0] : null, $row['series']);
        }
    @endphp

    <x-page-banner :title="__('How to play: a guide for beginners')" :subtitle="__('Five short parts: from your first tip to the questions. Read it once and you know everything you need for the season.')">
        <nav class="flex flex-wrap gap-2 pt-2" aria-label="{{ __('Parts of the guide') }}">
            @foreach ($parts as [$anchor, $number, $label])
                <a href="#{{ $anchor }}" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-sm font-semibold transition hover:bg-white/20">
                    <span class="grid size-5 place-items-center rounded-full bg-white text-xs font-bold text-lech-900">{{ $number }}</span>{{ $label }}
                </a>
            @endforeach
        </nav>
    </x-page-banner>

    {{-- 1. Typ i punktacja --}}
    <section id="tip" class="scroll-mt-24 space-y-4">
        <x-howto.part-heading number="1" :title="__('Your tip and points')" :lead="__('Before every Lech match you tip its score after 90 minutes, e.g. 2:1 for Lech. You can change it as many times as you like until kick-off. One tip counts in all your competitions.')" />

        <div class="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
            <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Up to 3 points for the tip') }}</p>
                <ul class="mt-3 space-y-3">
                    @foreach ([[__('Outcome'), __('Lech wins, draws or loses')], [__('Goal difference'), __('e.g. Lech wins by one goal')], [__('Exact score'), __('a Koziołek: the full 3 points')]] as [$name, $hint])
                        <li class="flex items-center gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-club font-display text-lg font-bold text-club-ink">+1</span>
                            <span><span class="block font-semibold">{{ $name }}</span><span class="block text-sm text-ui-muted">{{ $hint }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Example: the match ends 2:1 for Lech') }}</p>
                <div class="mt-3 grid gap-2">
                    @foreach ($examples as [$score, $points, $hits, $text])
                        <div class="flex items-center gap-3 rounded-xl bg-ui-raised/50 px-3 py-2.5">
                            <span class="w-14 shrink-0 rounded-lg bg-ui-surface py-1 text-center font-display text-lg font-bold tabular-nums shadow-sm">{{ $score }}</span>
                            <span class="flex shrink-0 gap-1" aria-hidden="true">
                                @foreach ($hits as $hit)
                                    <span @class(['size-2.5 rounded-full', 'bg-green-500' => $hit, 'bg-ui-line' => !$hit])></span>
                                @endforeach
                            </span>
                            <span class="min-w-0 flex-1 text-sm">{{ $text }}</span>
                            <span @class(['shrink-0 rounded-full px-2.5 py-1 text-sm font-bold tabular-nums', 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-200' => $points > 0, 'bg-ui-raised text-ui-muted' => $points === 0])>{{ trans_choice(':count pt|:count pts', $points, ['count' => $points]) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Pojedynek: punkty za typ to gole, atak dokłada, obrona rywala zabiera --}}
        <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Your tip is your goals') }}</p>
            <p class="mt-1 text-sm">{{ __('In every competition you play a duel against another team. Your goals in the duel are your points for the tip plus your attack, minus the defence of your rival (never below 0). Attack and defence come from your squad and the questions, see parts 2 and 4.') }}</p>
            <div class="mt-4 grid items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                @foreach ([[__('You'), 3, 1, 1, 3], [__('Rival'), 2, 0, 1, 1]] as $index => [$who, $tipPts, $attack, $rivalDefence, $goals])
                    @if ($index === 1)
                        <div class="text-center font-display text-3xl font-bold tabular-nums">3 : 1</div>
                    @endif
                    <div @class(['rounded-xl p-4', 'bg-club/10' => $index === 0, 'bg-ui-raised/60' => $index === 1])>
                        <p class="font-semibold">{{ $who }}</p>
                        <p class="mt-1 font-mono text-sm tabular-nums text-ui-muted">{{ $tipPts }} {{ __('tip') }} + {{ $attack }} {{ __('attack') }} − {{ $rivalDefence }} {{ __('rival defence') }} = <b class="text-ui-ink">{{ $goals }}</b></p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-sm text-ui-muted">{{ __('A win gives 3 points in the table, a draw 1. In the cups a draw goes to the team with more experienced players, then to the one who saved the tip earlier.') }}</p>
        </div>
    </section>

    {{-- 2. Skład i rozgrywki --}}
    <section id="squad" class="scroll-mt-24 space-y-4">
        <x-howto.part-heading number="2" :title="__('Squad and competitions')" :lead="__('For every competition you pick up to :count Lech players. What they do in the real match adds attack or defence to your team.', ['count' => $maxPlayers])" />

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['bolt', __('Goal or assist'), __('+1 attack for every goal and every assist of your player.'), 'text-green-600 dark:text-green-400'],
                ['shield-check', __('Clean sheet'), __('A goalkeeper or defender who played in a match without a conceded goal: +1 defence, so your rival scores one goal less.'), 'text-club'],
                ['clock', __('Rest'), __('A player who played needs rest before you can pick him again. The longer he played, the longer the rest, so rotate your squad.'), 'text-amber-500'],
            ] as [$icon, $title, $text, $color])
                <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                    <x-ui.icon :name="$icon" class="size-7 {{ $color }}" />
                    <p class="mt-2 font-semibold">{{ $title }}</p>
                    <p class="mt-1 text-sm text-ui-muted">{{ $text }}</p>
                </div>
            @endforeach
        </div>

        {{--
            Regeneracja po meczu (19.11.2026): grafika dla początkujących. Godziny z ustawień (Designation, rest_hours_1-8),
            przykład na osi czasu: kolejka w niedzielę o 15:00, następne w środę o 18:00 (+75 h) i w sobotę o 20:30 (+149,5 h).
        --}}
        @php
            $restSteps = collect(range(1, 8))->map(fn (int $step) => [
                'step' => $step,
                'hours' => \App\Support\Designation::value('rest_hours_'.$step),
                'label' => __($step % 2 === 1 ? 'under :minutes min' : ':minutes min', ['minutes' => [1 => 45, 2 => 45, 3 => 90, 4 => 90, 5 => 105, 6 => 105, 7 => 120, 8 => 120][$step]]),
            ]);
            $fullMatch = \App\Support\Designation::value('rest_hours_4');
            $scale = max(170, $fullMatch + 20);
            $examples = [
                [__('Sunday 15:00'), 0, __('Kickoff of the matchday. Your player plays the whole match (90 min).')],
                [__('Wednesday 18:00'), 75, null],
                [__('Saturday 20:30'), 149.5, null],
            ];
        @endphp
        <div id="recovery" class="scroll-mt-24 space-y-5 rounded-2xl border border-ui-line bg-ui-surface p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-500"><x-ui.icon name="clock" class="size-6" /></span>
                <div>
                    <p class="font-display text-lg font-semibold">{{ __('How recovery works') }}</p>
                    <p class="mt-1 text-sm text-ui-muted">{{ __('Like in real football: a player who played needs time to recover before he can play for your club again. The longer he was on the pitch, the longer it takes.') }}</p>
                </div>
            </div>

            {{-- 1. Ile trwa regeneracja: czas gry => godziny --}}
            <div class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">1. {{ __('Time on the pitch decides how long') }}</p>
                <div class="grid grid-cols-4 gap-2 text-center text-sm">
                    @foreach ($restSteps as $rest)
                        <div class="rounded-lg border border-ui-line p-2">
                            <div class="flex justify-center"><x-match-clock :to="$rest['step']" /></div>
                            <div class="mt-1 text-[11px] text-ui-muted">{{ $rest['label'] }}</div>
                            <div class="{{ \App\Support\Squad::lockColor($rest['hours']) }} mt-1 whitespace-nowrap rounded px-1 font-bold tabular-nums">{{ $rest['hours'] }} h</div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-ui-muted">{{ __('The clock shows the time on the pitch, extra time is the second, smaller circle. A substitute who did not come on needs no recovery.') }}</p>
            </div>

            {{-- 2. Przykład na osi czasu --}}
            <div class="space-y-3">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">2. {{ __('Example: counted from the kickoff') }}</p>
                <div class="relative h-24 sm:h-20">
                    {{-- Oś i pasek regeneracji --}}
                    <div class="absolute inset-x-0 top-4 h-3 rounded-full bg-ui-raised"></div>
                    <div class="absolute left-0 top-4 h-3 rounded-full bg-gradient-to-r from-red-600 via-orange-500 to-yellow-400" style="width: {{ round($fullMatch / $scale * 100, 2) }}%"></div>
                    <div class="absolute top-0 -translate-x-1/2 text-[11px] font-bold tabular-nums text-ui-ink" style="left: {{ round($fullMatch / $scale * 100, 2) }}%">{{ $fullMatch }} h</div>
                    @foreach ($examples as [$when, $after])
                        @php
                            $ready = $after >= $fullMatch;
                            $left = round($after / $scale * 100, 2);
                        @endphp
                        {{-- Pierwszy znacznik przy lewej krawędzi, żeby podpis nie wychodził poza ramkę na telefonie --}}
                        <div @class(['absolute top-2.5 flex flex-col', 'items-start' => $after === 0, '-translate-x-1/2 items-center' => $after !== 0]) style="left: {{ $after === 0 ? 0 : min(94, $left) }}%">
                            <span class="size-6 rounded-full border-4 border-ui-surface {{ $after === 0 ? 'bg-club' : ($ready ? 'bg-green-600' : 'bg-red-600') }}"></span>
                            <span class="mt-1 whitespace-nowrap text-center text-[11px] font-semibold leading-tight">{{ $when }}</span>
                            @if ($after > 0)
                                <span class="whitespace-nowrap text-[10px] tabular-nums text-ui-muted">+{{ str_replace('.', ',', (string) $after) }} h</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ($examples as [$when, $after, $what])
                        @php
                            $ready = $after >= $fullMatch;
                        @endphp
                        <div @class([
                            'rounded-xl border p-3 text-sm',
                            'border-club/40 bg-club/5' => $after === 0,
                            'border-red-500/40 bg-red-500/5' => $after > 0 && !$ready,
                            'border-green-500/40 bg-green-500/5' => $after > 0 && $ready,
                        ])>
                            <p class="font-semibold">{{ $when }}</p>
                            <p class="mt-0.5 text-ui-muted">
                                @if ($after === 0)
                                    {{ $what }}
                                @elseif ($ready)
                                    {{ __('Recovered: you can pick him again.') }}
                                @else
                                    {{ __('Still recovering: if you pick him, the pick gives nothing.') }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 3. Zasady w punktach --}}
            <div class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">3. {{ __('Good to know') }}</p>
                <ul class="grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ([
                        ['flag', __('Counted from the kickoff of the matchday in which you fielded him, not from the end of the match.')],
                        ['squares-2x2', __('Recovery counts in your club in all competitions at once.')],
                        ['x-circle', __('A recovering player in your squad gives no points and no experience, but takes a place. Swap him for someone else.')],
                        ['eye', __('On the tipping page a recovering player has a coloured badge with the time when he is ready again.')],
                    ] as [$icon, $text])
                        <li class="flex items-start gap-2 rounded-xl bg-ui-raised/60 p-3">
                            <x-ui.icon :name="$icon" variant="mini" class="mt-0.5 size-4 shrink-0 text-club" />
                            <span>{{ $text }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Przełączanie rozgrywek: najczęstszy błąd nowych graczy --}}
        <div class="overflow-hidden rounded-2xl border-2 border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
            <div class="grid gap-5 p-5 md:grid-cols-[1.2fr_1fr] md:items-center">
                <div>
                    <p class="flex items-center gap-2 font-display text-lg font-semibold text-amber-950 dark:text-amber-100">
                        <x-ui.icon name="exclamation-triangle" class="size-6" /> {{ __('Set every competition, not only Ekstraklasa') }}
                    </p>
                    <p class="mt-2 text-sm text-amber-950/90 dark:text-amber-100/90">{{ __('Your league, Puchar Polski, the European cups and Liga Legend each have their own squad and their own questions. On the tipping page the first one is picked for you. Click the tiles in “Your competitions” to switch and set each of them.') }}</p>
                    <ol class="mt-3 space-y-1 text-sm text-amber-950 dark:text-amber-100">
                        <li>1. {{ __('Tip the score.') }}</li>
                        <li>2. {{ __('Click a competition tile, pick its players and answer its questions.') }}</li>
                        <li>3. {{ __('Click the next tile and repeat. A tile marked “Set” is ready.') }}</li>
                    </ol>
                </div>
                {{-- Makieta kafelków rozgrywek --}}
                <div class="grid grid-cols-2 gap-2" aria-hidden="true">
                    @foreach ([['league_1', 'Ekstraklasa', __('Now setting'), 'ring-2 ring-amber-500 ring-offset-2 ring-offset-amber-50 dark:ring-offset-zinc-900'], ['cup', 'Puchar Polski', __('To set'), ''], ['conference', 'Liga Konferencji', __('To set'), ''], ['legends', 'Liga Legend', __('Set'), '']] as [$key, $name, $state, $classes])
                        <div class="comp-banner rounded-xl px-3 py-2.5 {{ $classes }}" style="{{ \App\Support\CompetitionColors::style($key) }}">
                            <p class="relative z-10 truncate text-sm font-semibold">{{ $name }}</p>
                            <span class="comp-badge relative z-10 mt-1 inline-block rounded-full px-2 py-px text-[10px] font-bold uppercase tracking-wide">{{ $state }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 3. Doświadczenie i forma --}}
    <section id="experience" class="scroll-mt-24 space-y-4">
        <x-howto.part-heading number="3" :title="__('Experience and form')" :lead="__('Every Lech player earns experience in the real matches. Experience decides drawn cup ties. Form shows who plays regularly now.')" />

        <div class="grid gap-4 lg:grid-cols-[1fr_1.3fr]">
            <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Experience for one Lech match') }}</p>
                <ul class="mt-3 space-y-2.5">
                    @foreach ($expRows as [$value, $name, $hint])
                        <li class="flex items-center gap-3">
                            <span class="grid h-9 w-12 shrink-0 place-items-center rounded-xl bg-club font-display text-base font-bold tabular-nums text-club-ink">+{{ $num($value) }}</span>
                            <span><span class="block font-semibold">{{ $name }}</span><span class="block text-sm text-ui-muted">{{ $hint }}</span></span>
                        </li>
                    @endforeach
                    <li class="flex items-center gap-3">
                        <span class="grid h-9 w-12 shrink-0 place-items-center rounded-xl bg-sky-100 font-display text-base font-bold text-sky-800 dark:bg-sky-500/20 dark:text-sky-200">×{{ \App\Support\MatchReport::ACADEMY_EXPERIENCE }}</span>
                        <span><span class="flex items-center gap-1 font-semibold">{{ __('Academy graduate') }} <x-ui.icon name="academic-cap" variant="micro" class="text-sky-600 dark:text-sky-300" /></span><span class="block text-sm text-ui-muted">{{ __('everything he earns counts double') }}</span></span>
                    </li>
                </ul>
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Examples') }}</p>
                    <div class="mt-3 grid gap-2">
                        @foreach ($expExamples as [$text, $parts, $factor])
                            @php
                                $sum = array_sum($parts) * $factor;
                            @endphp
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-ui-raised/50 px-3 py-2.5">
                                <span class="min-w-0 flex-1 text-sm">{{ $text }}</span>
                                <span class="font-mono text-sm tabular-nums text-ui-muted">{{ $factor > 1 ? '(' : '' }}{{ collect($parts)->map(fn ($p) => $num($p))->implode(' + ') }}{{ $factor > 1 ? ') × '.$factor : '' }} =</span>
                                <span class="rounded-full bg-club px-2.5 py-1 font-display text-sm font-bold tabular-nums text-club-ink">{{ $num($sum) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                    <p class="flex items-center gap-2 font-semibold"><x-ui.icon name="user-group" class="size-6 text-club" /> {{ __('Experience in your club') }}</p>
                    <p class="mt-1 text-sm text-ui-muted">{{ __('Every club counts the experience of its players separately. A player collects it in your club only in the matchdays in which you fielded him, so the same player can have a different experience in every club.') }}</p>
                    <ul class="mt-3 space-y-1.5 text-sm">
                        <li class="flex gap-2"><x-ui.icon name="check-circle" variant="micro" class="mt-0.5 text-green-600 dark:text-green-400" /> {{ __('Fielded and played: everything he earned in the Lech match.') }}</li>
                        <li class="flex gap-2"><x-ui.icon name="check-circle" variant="micro" class="mt-0.5 text-green-600 dark:text-green-400" /> {{ __('Fielded, but not in the Lech matchday squad: :value, like the bench.', ['value' => $num($exp('exp_sub'))]) }}</li>
                        <li class="flex gap-2"><x-ui.icon name="no-symbol" variant="micro" class="mt-0.5 text-red-600 dark:text-red-400" /> {{ __('Resting player: the pick does not count and gives nothing.') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- O czym decyduje: bloki w barwach rozgrywek --}}
        <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('What experience decides') }}</p>
            <p class="mt-1 text-sm">{{ __('In a duel the experience of your team is the sum of the experience in your club of all players you fielded in that competition, this match included.') }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($expDecides as [$key, $name, $badge, $text])
                    <div class="comp-banner rounded-2xl p-4" style="{{ \App\Support\CompetitionColors::style($key) }}">
                        <p class="relative z-10 font-display text-base font-semibold leading-tight">{{ $name }}</p>
                        <span class="comp-badge relative z-10 mt-2 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide">{{ $badge }}</span>
                        <p class="relative z-10 mt-2 text-sm opacity-90">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
            {{-- Przykład: remis w Pucharze Polski --}}
            <div class="comp-banner mt-4 rounded-2xl p-4" style="{{ \App\Support\CompetitionColors::style('cup') }}">
                <p class="relative z-10 text-xs font-bold uppercase tracking-wider opacity-80">{{ __('Example: Puchar Polski ends 2:2') }}</p>
                <div class="relative z-10 mt-3 grid items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                    @foreach ([[__('You'), [12, 8, 4.5], true], [__('Rival'), [10, 6, 3], false]] as $index => [$who, $players, $wins])
                        @if ($index === 1)
                            <div class="text-center font-display text-3xl font-bold tabular-nums">2 : 2</div>
                        @endif
                        <div @class(['rounded-xl bg-white/10 p-3', 'ring-2 ring-[var(--c-accent)]' => $wins])>
                            <p class="flex items-center justify-between gap-2 font-semibold">{{ $who }}
                                @if ($wins)
                                    <span class="comp-badge rounded-full px-2 py-px text-[10px] font-bold uppercase">{{ __('Goes through') }}</span>
                                @endif
                            </p>
                            <p class="mt-1 font-mono text-sm tabular-nums opacity-90">{{ collect($players)->map(fn ($p) => $num($p))->implode(' + ') }} = <b>{{ $num(array_sum($players)) }}</b> {{ __('exp.') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Forma --}}
        <div class="grid gap-4 lg:grid-cols-[1.3fr_1fr]">
            <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                <p class="flex items-center gap-2 font-semibold"><x-ui.icon name="fire" class="size-6 text-amber-500" /> {{ __('Form') }}</p>
                <p class="mt-1 text-sm text-ui-muted">{{ __('Form is the experience of a Lech player from the last :count Lech matches with a report. It is the same in every club, because it comes from the real matches.', ['count' => \App\Support\MatchReport::FORM_MATCHES]) }}</p>
                <div class="mt-4 space-y-2">
                    @foreach ($formSample as [$label, $row])
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-ui-raised/50 px-3 py-2">
                            <span class="text-sm font-medium">{{ $label }}</span>
                            @include('partials.form-bars', ['row' => $row, 'matches' => $formMatches])
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ui-muted">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-1.5 rounded-sm bg-club"></span>{{ __('one match: the higher, the more experience') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-0.5 w-1.5 rounded-sm bg-zinc-300 dark:bg-zinc-600"></span>{{ __('not in the squad') }}</span>
                    <span class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-amber-400"></span>{{ __('goal') }}</span>
                    <span class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-sky-400"></span>{{ __('assist') }}</span>
                </p>
            </div>
            <div class="flex flex-col gap-4">
                <x-ui.callout icon="information-circle">{{ __('Form gives no points and does not decide any tie. It shows who plays regularly now, so it helps to pick the squad: such a player is more likely to score, assist, keep a clean sheet and earn experience. Remember about rest.') }}</x-ui.callout>
                <x-ui.callout icon="light-bulb">{{ __('When you pick the squad, the flame next to a player shows his form and the last number his experience in your club.') }}</x-ui.callout>
            </div>
        </div>
    </section>

    {{-- 4. Pytania i bonus --}}
    <section id="questions" class="scroll-mt-24 space-y-4">
        <x-howto.part-heading number="4" :title="__('Questions and the bonus')" :lead="__('Every competition has up to :count yes/no questions about the match, e.g. “Will Lech have more corners?”. Each answer is a bet: you choose whether a hit adds attack or defence.', ['count' => $perSet])" />

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('How it scores') }}</p>
                <ul class="mt-3 space-y-3 text-sm">
                    <li class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-green-100 font-bold text-green-800 dark:bg-green-500/20 dark:text-green-200">OF</span><span>{{ __('A hit marked as attack: +1 attack (your goals).') }}</span></li>
                    <li class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-100 font-bold text-sky-800 dark:bg-sky-500/20 dark:text-sky-200">DEF</span><span>{{ __('A hit marked as defence: +1 defence (your rival scores less).') }}</span></li>
                    <li class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-red-100 font-bold text-red-800 dark:bg-red-500/20 dark:text-red-200">✕</span><span><b>{{ __('One miss sets the whole question bonus of that competition to zero.') }}</b> {{ __('Your players still count.') }}</span></li>
                    <li class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-ui-raised font-bold text-ui-muted">–</span><span>{{ __('You can skip any question: a skipped question gives nothing and takes nothing.') }}</span></li>
                </ul>
            </div>

            <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Example: the same 5 questions, two players') }}</p>
                <div class="mt-3 space-y-3">
                    @foreach ([[__('Careful'), ['ok', 'ok', 'skip', 'skip', 'skip'], '+2'], [__('Risky'), ['ok', 'ok', 'ok', 'ok', 'miss'], '0']] as [$who, $marks, $bonus])
                        <div class="flex items-center gap-3">
                            <span class="w-20 shrink-0 text-sm font-semibold">{{ $who }}</span>
                            <span class="flex flex-1 gap-1.5">
                                @foreach ($marks as $mark)
                                    <span @class([
                                        'grid size-8 place-items-center rounded-lg text-sm font-bold',
                                        'bg-green-500 text-white' => $mark === 'ok',
                                        'bg-red-500 text-white' => $mark === 'miss',
                                        'border border-dashed border-ui-line text-ui-muted' => $mark === 'skip',
                                    ])>{{ ['ok' => '✓', 'miss' => '✕', 'skip' => '–'][$mark] }}</span>
                                @endforeach
                            </span>
                            <span @class(['rounded-full px-2.5 py-1 font-display font-bold tabular-nums', 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-200' => $bonus !== '0', 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200' => $bonus === '0'])>{{ $bonus }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-sm text-ui-muted">{{ __('Four hits and one miss give 0. Two sure hits give +2.') }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['shield-check', __('Play it safe'), __('Answer only the questions you are sure about. A small bonus is better than none.')],
                ['fire', __('Go all in'), __('Answer everything when you feel the match. A big bonus wins duels, but one miss takes all of it.')],
                ['squares-2x2', __('Mix it up'), __('Every competition has its own questions and its own bonus: risk in one, play safe in another.')],
            ] as [$icon, $title, $text])
                <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                    <x-ui.icon :name="$icon" class="size-7 text-club" />
                    <p class="mt-2 font-semibold">{{ $title }}</p>
                    <p class="mt-1 text-sm text-ui-muted">{{ $text }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.callout icon="light-bulb">{{ __('Attack or defence? Defence cannot take your rival below 0 goals, attack always counts. If your rival is likely to score little, mark your sure hits as attack.') }}</x-ui.callout>
            <x-ui.callout icon="information-circle">{{ __('A question with a mistake, or one that cannot be settled, is voided: it does not count for anyone and does not set anything to zero.') }}</x-ui.callout>
        </div>
    </section>

    {{-- 5. Pierwszy mecz --}}
    <section id="match" class="scroll-mt-24 space-y-4">
        <x-howto.part-heading number="5" :title="__('Your first match')" :lead="__('That is all. Tip the score, set the squad and the questions in every competition, and wait for the whistle.')" />

        <div class="lech-banner relative overflow-hidden rounded-2xl px-6 py-8 text-center sm:px-10">
            <div class="pointer-events-none absolute -right-16 -top-16 size-72 rounded-full bg-lech-500/25 blur-3xl"></div>
            @if ($nextMatchday)
                <p class="text-xs font-bold uppercase tracking-widest text-lech-200">{{ __('Matchday :number', ['number' => $nextMatchday->number]) }} · {{ $nextMatchday->kickoff_at->translatedFormat('l, j F, H:i') }}</p>
                <p class="mt-3 font-display text-3xl font-bold sm:text-4xl">{{ $nextMatchday->is_home ? 'Lech Poznań – '.$nextMatchday->opponent : $nextMatchday->opponent.' – Lech Poznań' }}</p>
                <p class="mt-2 text-lech-100">{{ __('Tipping closes at kick-off.') }}</p>
            @else
                <p class="font-display text-3xl font-bold">{{ __('See you at the next Lech match!') }}</p>
            @endif
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                @auth
                    <a href="{{ route('tips') }}" wire:navigate class="rounded-full bg-white px-6 py-3 text-sm font-bold uppercase tracking-wide text-lech-900 shadow-lg transition hover:-translate-y-0.5">{{ __('Tip now') }}</a>
                @else
                    <a href="{{ route('register') }}" class="rounded-full bg-white px-6 py-3 text-sm font-bold uppercase tracking-wide text-lech-900 shadow-lg transition hover:-translate-y-0.5">{{ __('Join the game') }}</a>
                @endauth
                <a href="{{ route('rules') }}" wire:navigate class="rounded-full border border-white/30 px-6 py-3 text-sm font-bold transition hover:bg-white/10">{{ __('Rules of the game') }}</a>
            </div>
        </div>
    </section>
</div>
