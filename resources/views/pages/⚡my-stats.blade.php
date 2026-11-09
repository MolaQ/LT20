<?php

use App\Models\Matchday;
use App\Models\Season;
use App\Models\Tip;
use App\Support\Premium;
use App\Support\PremiumStats;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Statystyki premium (przebudowa 2.0, etap I): forma, punkty w kolejkach, serie i odznaki, „co jeśli” z rywalem
 * i mapa typów (cała gra albo moja liga). Bez premium: podgląd zachęty z linkiem do wsparcia. Liczy App\Support\PremiumStats.
 */
new #[Layout('layouts::shell')] class extends Component {
    /** Mapa typów: 'all' (wszyscy gracze) albo 'league' (moja liga). */
    public string $mapScope = 'all';

    public function render(): View
    {
        return $this->view()->title(__('My stats'));
    }

    #[Computed]
    public function premium(): bool
    {
        return Premium::isActive(auth()->user());
    }

    #[Computed]
    public function season(): ?Season
    {
        return Season::current();
    }

    #[Computed]
    public function rows(): array
    {
        return $this->premium && $this->season ? PremiumStats::rows(auth()->user(), $this->season) : [];
    }

    #[Computed]
    public function focus(): ?Matchday
    {
        return $this->premium && $this->season ? PremiumStats::focusMatchday($this->season) : null;
    }

    /** Mój typ w kolejce „co jeśli” jako [Lech, rywal]. */
    #[Computed]
    public function myTip(): ?array
    {
        $tip = $this->focus ? Tip::where('matchday_id', $this->focus->id)->where('user_id', auth()->id())->first() : null;

        return $tip ? [(int) $tip->lech_goals, (int) $tip->opponent_goals] : null;
    }

    #[Computed]
    public function rival(): ?array
    {
        return $this->focus ? PremiumStats::rival(auth()->user(), $this->focus) : null;
    }

    /** Mapa typów dopiero po pierwszym gwizdku (wcześniej zdradzałaby typy). */
    #[Computed]
    public function tipMap(): ?array
    {
        if (!$this->focus || $this->focus->isOpenForTips()) {
            return null;
        }

        return PremiumStats::tipMap(PremiumStats::tips($this->focus, $this->mapScope === 'league' ? auth()->user() : null));
    }
}; ?>

<div class="flex flex-col gap-3.5">
    <div class="flex items-center justify-between">
        <h1 class="font-display text-[28px] font-extrabold uppercase tracking-tight">{{ __('My stats') }}</h1>
        <x-ui.chip variant="gold">★ {{ __('Premium') }}</x-ui.chip>
    </div>

    @if (!$this->premium)
        {{-- Bez premium: rozmyty podgląd i zachęta. --}}
        <x-ui.card hero class="text-center">
            <p class="font-display text-2xl font-extrabold uppercase">{{ __('The joy of numbers') }}</p>
            <p class="mt-1 text-sm opacity-90">{{ __('Form, streaks, badges, what-if with your rival and the map of everyone\'s tips. All in Premium.') }}</p>
            <div class="pointer-events-none my-4 grid select-none grid-cols-3 gap-2 blur-[3px]" aria-hidden="true">
                <x-ui.ring :value="67" color="gold" :label="__('form')" />
                <x-ui.ring :value="44" :label="__('outcomes')" />
                <x-ui.ring :value="12" color="good" :label="__('exact')" />
            </div>
            <x-ui.button variant="light" :href="route('support')">{{ __('Get Premium') }}</x-ui.button>
        </x-ui.card>
    @elseif (!$this->season)
        <x-ui.card>{{ __('No season yet.') }}</x-ui.card>
    @else
        @php
            $rows = $this->rows;
            $streaks = \App\Support\PremiumStats::streaks($rows);
            $played = max(1, $streaks['played']);
        @endphp

        {{-- Forma i serie --}}
        <x-ui.section-title>{{ __('Your form') }}</x-ui.section-title>
        <x-ui.card>
            <div class="grid grid-cols-3 gap-2">
                <x-ui.ring :value="\App\Support\PremiumStats::form($rows)" color="gold" :label="__('form (last 5)')" />
                <x-ui.ring :value="(int) round($streaks['outcome'] / $played * 100)" :label="__('correct outcomes')" />
                <x-ui.ring :value="(int) round($streaks['exact'] / $played * 100)" color="good" :label="__('exact scores')" />
            </div>
        </x-ui.card>
        <div class="grid grid-cols-3 gap-2.5">
            <x-ui.stat :value="$streaks['current']" :label="__('current streak')" tone="good" />
            <x-ui.stat :value="$streaks['best']" :label="__('best streak')" />
            <x-ui.stat :value="array_sum(array_column($rows, 'points'))" :label="__('tip points')" />
        </div>

        {{-- Punkty w kolejkach: typ (barwa klubu) i bonusy (złoto) --}}
        <x-ui.section-title>{{ __('Points per matchday') }}</x-ui.section-title>
        <x-ui.card>
            @if ($rows === [])
                <p class="text-sm text-ui-muted">{{ __('Charts appear after the first settled matchday.') }}</p>
            @else
                @php $peak = max(1, ...array_map(fn ($r) => $r['points'] + $r['bonus'], $rows)); @endphp
                <div class="flex h-40 items-end gap-1.5" x-data="{ on: false }" x-intersect.once="on = true">
                    @foreach ($rows as $i => $row)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" wire:key="bar-{{ $row['number'] }}">
                            <span class="ui-num text-xs font-bold">{{ $row['points'] + $row['bonus'] }}</span>
                            <div class="flex w-full flex-col justify-end overflow-hidden rounded-lg transition-[height] duration-700 ease-out"
                                :style="on ? 'height: {{ round(($row['points'] + $row['bonus']) / $peak * 100) }}%' : 'height: 0%'"
                                style="transition-delay: {{ $i * 60 }}ms">
                                <div class="bg-gold" style="flex: {{ $row['bonus'] }} 0 0"></div>
                                <div class="bg-club" style="flex: {{ $row['points'] }} 0 0"></div>
                            </div>
                            <span class="text-[11px] text-ui-muted">{{ $row['number'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 flex gap-4 text-xs text-ui-muted">
                    <span class="flex items-center gap-1"><span class="inline-block size-2.5 rounded-sm bg-club"></span>{{ __('tip') }}</span>
                    <span class="flex items-center gap-1"><span class="inline-block size-2.5 rounded-sm bg-gold"></span>{{ __('bonuses') }}</span>
                </p>
            @endif
        </x-ui.card>

        {{-- Odznaki --}}
        <x-ui.section-title>{{ __('Badges') }}</x-ui.section-title>
        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
            @foreach (\App\Support\PremiumStats::badges($rows) as $badge)
                <div wire:key="badge-{{ $badge['key'] }}" @class([
                    'rounded-2xl border p-3 transition',
                    'border-gold bg-gold-soft animate-ui-stamp' => $badge['earned'],
                    'border-ui-line bg-ui-surface opacity-60' => !$badge['earned'],
                ])>
                    <p class="font-display text-[17px] font-extrabold uppercase">{{ $badge['earned'] ? '★' : '☆' }} {{ $badge['label'] }}</p>
                    <p class="text-xs text-ui-muted">{{ $badge['hint'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Co jeśli: punkty za typ dla każdego wyniku, z rywalem po pierwszym gwizdku --}}
        @if ($this->focus)
            @php
                $rival = $this->rival;
                $grid = \App\Support\PremiumStats::whatIf($this->myTip, $rival['tip'] ?? null);
                $real = $this->focus->lech_goals !== null ? [$this->focus->lech_goals, $this->focus->opponent_goals] : null;
            @endphp
            <x-ui.section-title>{{ __('What if? Matchday :number', ['number' => $this->focus->number]) }}</x-ui.section-title>
            <x-ui.card>
                @if (!$this->myTip)
                    <p class="text-sm text-ui-muted">{{ __('Make a tip to see your points for every result.') }}</p>
                    <x-ui.button class="mt-3" :href="route('tips')">{{ __('Make a tip') }}</x-ui.button>
                @else
                    <p class="mb-2 text-sm">
                        {{ __('Your tip :score', ['score' => implode(':', $this->myTip)]) }}
                        @if ($rival)
                            · {{ $rival['name'] }}: {{ $rival['tip'] ? implode(':', $rival['tip']) : __('hidden until kick-off') }}
                        @endif
                    </p>
                    <div class="overflow-x-auto" data-no-swipe>
                        <table class="w-full border-separate border-spacing-1 text-center text-sm">
                            <thead>
                                <tr>
                                    <th class="text-xs text-ui-muted">{{ mb_strtoupper(config('club.short_name')) }} ↓</th>
                                    @foreach (range(0, \App\Support\PremiumStats::GRID) as $opponent)
                                        <th class="text-xs text-ui-muted">{{ $opponent }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grid as $lech => $cells)
                                    <tr>
                                        <th class="text-xs text-ui-muted">{{ $lech }}</th>
                                        @foreach ($cells as $opponent => $cell)
                                            @php
                                                $isReal = $real && $real[0] === $lech && $real[1] === $opponent;
                                                $tone = $cell['rival'] === null ? null : ($cell['me'] <=> $cell['rival']);
                                            @endphp
                                            <td @class([
                                                'rounded-lg py-2 font-bold ui-num',
                                                'bg-good-soft text-good' => $tone === 1,
                                                'bg-bad-soft text-bad' => $tone === -1,
                                                'bg-ui-raised' => $tone === 0 || $tone === null,
                                                'ring-2 ring-gold' => $isReal,
                                            ]) title="{{ $lech }}:{{ $opponent }}">
                                                {{ $cell['me'] }}@if ($cell['rival'] !== null)<span class="text-[11px] font-semibold opacity-70">/{{ $cell['rival'] }}</span>@endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-ui-muted">{{ __('Your points / your rival\'s points. Green: you win the tip duel, red: your rival does.') }}</p>
                @endif
            </x-ui.card>

            {{-- Mapa typów --}}
            <x-ui.section-title>{{ __('Tip map') }}</x-ui.section-title>
            <x-ui.card class="space-y-3">
                @if (!$this->tipMap)
                    <p class="text-sm text-ui-muted">{{ __('The map shows everyone\'s tips after kick-off.') }}</p>
                @else
                    <x-ui.segmented wire:model.live="mapScope" :options="['all' => __('Everyone'), 'league' => __('My league')]" />
                    @php $map = $this->tipMap; @endphp
                    <div class="grid gap-1" style="grid-template-columns: auto repeat({{ \App\Support\PremiumStats::GRID + 1 }}, minmax(0, 1fr))">
                        <span></span>
                        @foreach (range(0, \App\Support\PremiumStats::GRID) as $opponent)
                            <span class="text-center text-xs text-ui-muted">{{ $opponent }}{{ $opponent === \App\Support\PremiumStats::GRID ? '+' : '' }}</span>
                        @endforeach
                        @foreach ($map['grid'] as $lech => $cells)
                            <span class="pe-1 text-end text-xs text-ui-muted">{{ $lech }}{{ $lech === \App\Support\PremiumStats::GRID ? '+' : '' }}</span>
                            @foreach ($cells as $opponent => $count)
                                @php $share = $count / $map['max']; @endphp
                                <span class="grid aspect-square place-items-center rounded-lg text-xs font-bold ui-num"
                                    style="background: color-mix(in oklab, var(--club-primary) {{ round($share * 85) }}%, var(--ui-raised)); color: {{ $share > .5 ? 'white' : 'inherit' }}">
                                    {{ $count ?: '' }}
                                </span>
                            @endforeach
                        @endforeach
                    </div>
                    <p class="text-xs text-ui-muted">{{ trans_choice(':count tip|:count tips', $map['count'], ['count' => $map['count']]) }}</p>
                @endif
            </x-ui.card>
        @endif
    @endif
</div>
