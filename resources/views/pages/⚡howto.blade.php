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
                        {{-- Atak i obrona symbolami z gry: miecze i tarcza w kolorach pytań (--q-attack, --q-defense) --}}
                        <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm tabular-nums text-ui-muted">
                            <span>{{ $tipPts }} {{ __('tip') }}</span> +
                            <span class="inline-flex items-center gap-1 font-semibold" style="color: var(--q-attack)"><x-ui.icon name="swords" variant="micro" class="size-4" />{{ $attack }} {{ __('attack') }}</span> −
                            <span class="inline-flex items-center gap-1 font-semibold" style="color: var(--q-defense)"><x-ui.icon name="shield" variant="micro" class="size-4" />{{ $rivalDefence }} {{ __('rival defence') }}</span>
                            = <b class="text-base text-ui-ink">{{ $goals }}</b>
                        </p>
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
            {{-- Atak i obrona tymi samymi symbolami i kolorami co na stronie typowania (miecze, tarcza: --q-attack, --q-defense) --}}
            @foreach ([
                ['swords', __('Goal or assist'), __('+1 attack for every goal and every assist of your player.'), 'background: var(--q-attack)', __('Attack')],
                ['shield', __('Clean sheet'), __('A goalkeeper or defender who played in a match without a conceded goal: +1 defence, so your rival scores one goal less.'), 'background: var(--q-defense)', __('Defence')],
                ['clock', __('Rest'), __('A player who played needs rest before you can pick him again. The longer he played, the longer the rest, so rotate your squad.'), 'background: #64748b', null],
            ] as [$icon, $title, $text, $style, $tag])
                <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                    <span class="flex items-center gap-2">
                        <span class="grid size-10 place-items-center rounded-xl text-white shadow-sm" style="{{ $style }}"><x-ui.icon :name="$icon" variant="micro" class="size-5" /></span>
                        @if ($tag)
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white" style="{{ $style }}">{{ $tag }}</span>
                        @endif
                    </span>
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
            // Zmęczony („na żółto”): w chwili gwizdka zostało najwyżej tyle godzin regeneracji; 0 = zasada wyłączona
            $tiredHours = \App\Support\Designation::value('tired_threshold_hours');
            $tiredFactor = rtrim(rtrim(number_format(\App\Support\Designation::number('tired_rest_factor'), 1, ',', ''), '0'), ',');
            $isTired = fn (float $after) => $tiredHours > 0 && $after > 0 && $after < $fullMatch && $fullMatch - $after <= $tiredHours;
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
                {{-- Legenda zegara czasu gry: ten sam symbol co przy zawodniku na stronie typowania --}}
                <div class="rounded-xl bg-ui-raised/60 p-3">
                    <p class="text-sm">{{ __('The clock next to a player shows his time on the pitch: the filled part of the circle is the part of the match he played.') }}</p>
                    <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:grid-cols-4">
                        @foreach ([[1, __('part of a half')], [2, __('a whole half')], [4, __('the whole match')], [6, __('teal circle: extra time')]] as [$to, $text])
                            <span class="flex items-center gap-2"><x-match-clock :to="$to" class="origin-left scale-125" /> <span class="ml-1">{{ $text }}</span></span>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-center text-sm min-[480px]:grid-cols-4">
                    @foreach ($restSteps as $rest)
                        <div class="flex flex-col items-center rounded-lg border border-ui-line p-2">
                            <x-match-clock :to="$rest['step']" class="my-1 scale-125" />
                            <div class="mt-1 text-[11px] leading-tight text-ui-muted">{{ $rest['label'] }}</div>
                            <div class="{{ \App\Support\Squad::lockColor($rest['hours']) }} mt-auto w-full whitespace-nowrap rounded px-1 pt-0.5 font-bold tabular-nums">{{ $rest['hours'] }} h</div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-ui-muted">{{ __('A substitute who did not come on needs no recovery.') }}</p>
                {{-- Zmęczony („na żółto”): może grać, ale po meczu regeneruje się dłużej --}}
                @if ($tiredHours > 0)
                    <div class="flex items-start gap-3 rounded-xl border border-amber-400/60 bg-amber-50 p-3 text-sm dark:border-amber-400/40 dark:bg-amber-500/10">
                        <span class="mt-0.5 inline-flex shrink-0 items-center rounded-md bg-amber-400 px-1.5 py-0.5 text-xs font-bold text-amber-950">×{{ $tiredFactor }}</span>
                        <span><b>{{ __('Almost recovered (yellow):') }}</b> {{ __('if at the kickoff at most :hours h of his recovery are left, he can play and counts normally, but after this match he needs :factor times longer to recover. Handy before a break in the season.', ['hours' => $tiredHours, 'factor' => $tiredFactor]) }}</span>
                    </div>
                @endif
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
                            <span class="size-6 rounded-full border-4 border-ui-surface {{ $after === 0 ? 'bg-club' : ($ready ? 'bg-green-600' : ($isTired($after) ? 'bg-amber-400' : 'bg-red-600')) }}"></span>
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
                            'border-red-500/40 bg-red-500/5' => $after > 0 && !$ready && !$isTired($after),
                            'border-amber-400/60 bg-amber-400/10' => $isTired($after),
                            'border-green-500/40 bg-green-500/5' => $after > 0 && $ready,
                        ])>
                            <p class="flex items-center gap-2 font-semibold">{{ $when }}
                                @if ($isTired($after))
                                    <span class="rounded bg-amber-400 px-1 text-[10px] font-bold text-amber-950">×{{ $tiredFactor }}</span>
                                @endif
                            </p>
                            <p class="mt-0.5 text-ui-muted">
                                @if ($after === 0)
                                    {{ $what }}
                                @elseif ($isTired($after))
                                    {{ __('Almost recovered (:hours h left): he can play and counts, but afterwards he needs :factor times longer to recover.', ['hours' => str_replace('.', ',', (string) ($fullMatch - $after)), 'factor' => $tiredFactor]) }}
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

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('How it scores') }}</p>
                {{-- Dwie strony, dwa osobne bonusy: każda ma swoje trafienia i swoje pudło --}}
                <div class="mt-3 grid gap-3 min-[420px]:grid-cols-2">
                    @foreach ([
                        'attack' => [__('Attack'), __('adds goals to your score'), __('One miss: attack bonus 0')],
                        'defense' => [__('Defence'), __('takes goals from your rival'), __('One miss: defence bonus 0')],
                    ] as $side => [$label, $what, $miss])
                        <div class="rounded-xl border p-3" style="border-color: color-mix(in srgb, var(--q-{{ $side }}) 45%, transparent); background: color-mix(in srgb, var(--q-{{ $side }}) 10%, transparent)">
                            <p class="flex items-center gap-2 font-display text-base font-bold" style="color: var(--q-{{ $side }})">
                                <x-ui.icon :name="$side === 'attack' ? 'swords' : 'shield'" variant="micro" class="size-5" /> {{ $label }}
                            </p>
                            <p class="mt-0.5 text-xs text-ui-muted">{{ $what }}</p>
                            <p class="mt-3 flex items-center gap-2.5 text-sm"><x-howto.q-mark :mark="$side" /> <span>{{ __('Every hit') }}: <b>+1</b></span></p>
                            <p class="mt-2.5 flex items-center gap-2.5 text-sm"><x-howto.q-mark :mark="'miss-'.$side" /> <b>{{ $miss }}</b></p>
                        </div>
                    @endforeach
                </div>
                {{-- Najważniejsza zasada: pudło zeruje tylko swoją stronę --}}
                <div class="mt-3 flex gap-3 rounded-xl border border-red-300 bg-red-50 p-3 text-sm dark:border-red-400/40 dark:bg-red-500/10">
                    <x-ui.icon name="exclamation-triangle" variant="mini" class="mt-0.5 size-5 text-red-500 dark:text-red-300" />
                    <span><b>{{ __('A miss wipes only its own side.') }}</b> {{ __('A miss in an attack question takes the attack bonus, your defence bonus stays. A miss in a defence question takes the defence bonus, your attack stays.') }} {{ __('Your players still count.') }}</span>
                </div>
                <p class="mt-3 flex items-center gap-2.5 text-sm text-ui-muted"><x-howto.q-mark mark="skip" /> {{ __('You can skip any question: a skipped question gives nothing and takes nothing.') }}</p>
            </div>

            <div class="rounded-2xl border border-ui-line bg-ui-surface p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ __('Example: the same 5 questions, three players') }}</p>
                <div class="mt-3 space-y-3">
                    @foreach ([
                        [__('Careful'), ['attack', 'defense', 'skip', 'skip', 'skip']],
                        [__('Risky'), ['attack', 'attack', 'defense', 'attack', 'miss-attack']],
                        [__('Unlucky'), ['attack', 'attack', 'miss-defense', 'defense', 'skip']],
                    ] as [$who, $marks])
                        <div>
                            <p class="text-sm font-semibold">{{ $who }}</p>
                            <div class="mt-1 flex items-center gap-3">
                                <span class="flex flex-1 gap-1.5">
                                    @foreach ($marks as $mark)
                                        <x-howto.q-mark :mark="$mark" />
                                    @endforeach
                                </span>
                                {{-- Bonus rozpisany na atak i obronę; strona z pudłem przekreślona do zera --}}
                                <span class="flex w-[5.5rem] shrink-0 flex-col gap-1 text-xs font-bold tabular-nums">
                                    @foreach (['attack' => 'swords', 'defense' => 'shield'] as $side => $icon)
                                        @php $hits = count(array_keys($marks, $side, true)); @endphp
                                        @if (in_array('miss-'.$side, $marks, true))
                                            <span class="inline-flex items-center justify-end gap-1 rounded-full bg-red-50 px-2 py-0.5 text-red-500 dark:bg-red-500/15 dark:text-red-300"><s class="font-semibold opacity-70">+{{ $hits }}</s> 0 <x-ui.icon :name="$icon" variant="micro" class="size-3.5" /></span>
                                        @else
                                            <span class="inline-flex items-center justify-end gap-1 rounded-full px-2 py-0.5" style="color: var(--q-{{ $side }}); background: color-mix(in srgb, var(--q-{{ $side }}) 14%, transparent)">+{{ $hits }} <x-ui.icon :name="$icon" variant="micro" class="size-3.5" /></span>
                                        @endif
                                    @endforeach
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                {{-- Legenda symboli: te same co na stronie typowania --}}
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-ui-line pt-3 text-xs text-ui-muted">
                    <span class="flex items-center gap-1.5"><x-howto.q-mark mark="attack" small /> {{ __('hit, attack') }}</span>
                    <span class="flex items-center gap-1.5"><x-howto.q-mark mark="defense" small /> {{ __('hit, defence') }}</span>
                    <span class="flex items-center gap-1.5"><x-howto.q-mark mark="miss-attack" small /> {{ __('miss (the corner shows its side)') }}</span>
                    <span class="flex items-center gap-1.5"><x-howto.q-mark mark="skip" small /> {{ __('skipped') }}</span>
                </div>
                <p class="mt-3 text-sm text-ui-muted">{{ __('Risky missed an attack question: his attack bonus drops from +3 to 0, but the defence +1 stays. Unlucky missed in defence: he loses the defence, his attack +2 stays.') }}</p>
            </div>
        </div>

        {{-- Strategie w kolorach, żeby część nie była jednolita --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['shield-check', '#059669', __('Play it safe'), __('Answer only the questions you are sure about. A small bonus is better than none.')],
                ['fire', '#dc2626', __('Go all in'), __('Answer everything when you feel the match. A big bonus wins duels, but one miss takes the whole bonus of its side.')],
                ['scale', '#7c3aed', __('Split the risk'), __('Put your sure answers on one side and the risky ones on the other. A miss then costs only one side.')],
                ['squares-2x2', '#0284c7', __('Mix it up'), __('Every competition has its own questions and its own bonus: risk in one, play safe in another.')],
            ] as [$icon, $color, $title, $text])
                <div class="flex gap-4 rounded-2xl border border-ui-line border-t-4 bg-ui-surface p-4 sm:block sm:p-5" style="border-top-color: {{ $color }}">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl text-white shadow-sm" style="background: {{ $color }}"><x-ui.icon :name="$icon" variant="mini" class="size-5" /></span>
                    <div class="min-w-0">
                        <p class="font-semibold sm:mt-3">{{ $title }}</p>
                        <p class="mt-1 text-sm text-ui-muted">{{ $text }}</p>
                    </div>
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
