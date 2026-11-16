<?php

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Poradnik dla nowych graczy (16.11.2026): od typu do pytań, w czterech częściach z przykładami.
 *  1. Typ i punktacja (przykłady dla wyniku 2:1, wynik pojedynku = punkty za typ + atak − obrona rywala),
 *  2. Skład i rozgrywki (każde rozgrywki mają osobny skład i pytania; jak przełączyć),
 *  3. Pytania tak/nie, atak albo obrona, zerowanie bonusu, pytanie unieważnione i strategie,
 *  4. Pierwszy mecz sezonu (zaproszenie).
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
            ['questions', '3', __('Questions and the bonus')],
            ['match', '4', __('Your first match')],
        ];
    @endphp

    <x-page-banner :title="__('How to play: a guide for beginners')" :subtitle="__('Four short parts: from your first tip to the questions. Read it once and you know everything you need for the season.')">
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
            <p class="mt-1 text-sm">{{ __('In every competition you play a duel against another team. Your goals in the duel are your points for the tip plus your attack, minus the defence of your rival (never below 0). Attack and defence come from your squad and the questions, see parts 2 and 3.') }}</p>
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
                    @foreach ([['Ekstraklasa', __('Now setting'), 'lech-bar text-white ring-2 ring-club/70'], ['Puchar Polski', __('To set'), 'bg-red-700 text-white'], ['Liga Konferencji', __('To set'), 'bg-emerald-700 text-white'], ['Liga Legend', __('Set'), 'bg-zinc-800 text-white']] as [$name, $state, $classes])
                        <div class="rounded-xl px-3 py-2.5 shadow-sm {{ $classes }}">
                            <p class="truncate text-sm font-semibold">{{ $name }}</p>
                            <span class="mt-1 inline-block rounded-full bg-black/25 px-2 py-px text-[10px] font-bold uppercase tracking-wide">{{ $state }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 3. Pytania i bonus --}}
    <section id="questions" class="scroll-mt-24 space-y-4">
        <x-howto.part-heading number="3" :title="__('Questions and the bonus')" :lead="__('Every competition has up to :count yes/no questions about the match, e.g. “Will Lech have more corners?”. Each answer is a bet: you choose whether a hit adds attack or defence.', ['count' => $perSet])" />

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

    {{-- 4. Pierwszy mecz --}}
    <section id="match" class="scroll-mt-24 space-y-4">
        <x-howto.part-heading number="4" :title="__('Your first match')" :lead="__('That is all. Tip the score, set the squad and the questions in every competition, and wait for the whistle.')" />

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
