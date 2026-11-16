<?php

use App\Models\Matchday;
use App\Support\HomeStats;
use App\Support\MatchdayInsights;
use App\Support\Premium;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Puls kolejki na stronie głównej (16.11.2026): ilu graczy już typuje (pierścień), typy, składy, odpowiedzi
 * na pytania i pojedynki tej kolejki, a pod spodem ostatnio typujący. Odświeża się co 20 sekund, gdy jest
 * widoczny; liczby dochodzą do nowych wartości płynnie (uiTween). Rozkład typów (kto wygra) tylko premium,
 * tak jak na stronie wyników (App\Support\MatchdayInsights).
 */
new class extends Component {
    public int $matchdayId;

    #[Computed]
    public function matchday(): ?Matchday
    {
        return Matchday::find($this->matchdayId);
    }

    /** @return array{tips: int, players: int, percent: int, squads: int, answers: int, duels: int, recent: array<int, string>}|null */
    #[Computed]
    public function live(): ?array
    {
        return $this->matchday ? HomeStats::live($this->matchday) : null;
    }

    #[Computed]
    public function premium(): bool
    {
        return Premium::isActive(auth()->user());
    }

    /** Rozkład typów (wygrana Lecha, remis, wygrana rywala) i najczęstsze wyniki: tylko premium. */
    #[Computed]
    public function split(): ?array
    {
        return $this->premium && $this->matchday ? MatchdayInsights::tips($this->matchday) : null;
    }
}; ?>

<div wire:poll.20s.visible>
    @if ($live = $this->live)
        @php
            $matchday = $this->matchday;
            $circle = 2 * M_PI * 42;
            $tiles = [
                ['pencil-square', $live['tips'], __('Tips')],
                ['user-group', $live['squads'], __('Squads picked')],
                ['question-mark-circle', $live['answers'], __('Answers to questions')],
                ['bolt', $live['duels'], __('Duels this matchday')],
            ];
        @endphp
        <section class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ui-line px-5 py-3">
                <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest">
                    <span class="relative flex size-2.5">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-500 opacity-60 motion-reduce:hidden"></span>
                        <span class="relative inline-flex size-2.5 rounded-full bg-red-500"></span>
                    </span>
                    {{ __('Live') }}
                    <span class="font-semibold normal-case tracking-normal text-ui-muted">· {{ __('Matchday :number', ['number' => $matchday->number]) }}@if ($matchday->opponent) · {{ $matchday->is_home ? 'Lech – '.$matchday->opponent : $matchday->opponent.' – Lech' }}@endif</span>
                </span>
                <span class="text-[11px] text-ui-muted">{{ __('Updates by itself') }}</span>
            </div>

            <div class="grid gap-5 p-5 md:grid-cols-[auto_1fr] md:items-center md:gap-8">
                {{-- Pierścień: odsetek graczy, którzy już wytypowali tę kolejkę --}}
                <div class="flex items-center gap-4">
                    <div class="relative size-28 shrink-0">
                        <svg viewBox="0 0 100 100" class="size-full -rotate-90" aria-hidden="true">
                            <circle cx="50" cy="50" r="42" fill="none" stroke-width="9" class="stroke-ui-raised" />
                            <circle cx="50" cy="50" r="42" fill="none" stroke-width="9" stroke-linecap="round" class="stroke-club transition-[stroke-dashoffset] duration-1000 ease-out"
                                stroke-dasharray="{{ $circle }}" style="stroke-dashoffset: {{ $circle * (1 - $live['percent'] / 100) }}" />
                        </svg>
                        <div class="absolute inset-0 grid place-items-center text-center">
                            <div>
                                <span class="font-display text-2xl font-bold tabular-nums"><span x-data="uiTween" data-to="{{ $live['percent'] }}" x-text="shown">{{ $live['percent'] }}</span>%</span>
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-ui-muted">{{ __('tipped') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="min-w-0 md:max-w-48">
                        <p class="font-display text-lg font-semibold leading-tight">{{ __(':tips of :players players have already tipped', ['tips' => $live['tips'], 'players' => $live['players']]) }}</p>
                        @if ($live['percent'] < 100 && $matchday->isOpenForTips())
                            <p class="mt-1 text-sm text-ui-muted">{{ __('Do not be the last one.') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Kafelki z liczbami kolejki --}}
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ($tiles as [$icon, $value, $label])
                        <div x-data="uiTween" data-to="{{ $value }}" :class="bump && 'ring-2 ring-club/50'" class="rounded-xl bg-ui-raised/60 px-4 py-3 transition">
                            <x-ui.icon :name="$icon" variant="mini" class="text-club" />
                            <div class="mt-1 font-display text-2xl font-bold leading-none tabular-nums" x-text="shown">{{ $value }}</div>
                            <div class="mt-1 text-[11px] font-semibold uppercase leading-tight tracking-wide text-ui-muted">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Kto wygra według graczy: premium widzi rozkład, reszta zachętę --}}
            @if ($live['tips'] > 0)
                <div class="border-t border-ui-line px-5 py-4">
                    @if ($split = $this->split)
                        @php
                            $parts = [
                                ['Lech', $split['lech'], 'bg-club'],
                                [__('A draw'), $split['draw'], 'bg-zinc-400'],
                                [$matchday->opponent ?: __('Opponent'), $split['opponent'], 'bg-amber-500'],
                            ];
                        @endphp
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span class="font-bold uppercase tracking-wider text-ui-muted">{{ __('Who wins according to the players') }}</span>
                            @if ($split['popular'] !== [])
                                <span class="flex items-center gap-1.5 text-ui-muted">{{ __('Most popular') }}:
                                    @foreach ($split['popular'] as $popular)
                                        <span class="rounded-md bg-ui-raised px-1.5 py-0.5 font-bold tabular-nums text-ui-ink">{{ $popular['score'] }}</span>
                                    @endforeach
                                </span>
                            @endif
                        </div>
                        <div class="flex h-3 overflow-hidden rounded-full bg-ui-raised">
                            @foreach ($parts as [$name, $count, $color])
                                <div class="{{ $color }} h-full transition-[width] duration-1000 ease-out" style="width: {{ $split['count'] > 0 ? round($count * 100 / $split['count'], 1) : 0 }}%"></div>
                            @endforeach
                        </div>
                        <div class="mt-2 grid grid-cols-3 text-xs">
                            @foreach ($parts as [$name, $count, $color])
                                <span @class(['flex items-center gap-1.5', 'justify-center' => $loop->index === 1, 'justify-end' => $loop->last])>
                                    <span class="{{ $color }} size-2 rounded-full"></span>
                                    <span class="truncate">{{ $name }}</span>
                                    <b class="tabular-nums">{{ $split['count'] > 0 ? round($count * 100 / $split['count']) : 0 }}%</b>
                                </span>
                            @endforeach
                        </div>
                    @else
                        <a href="{{ route('support') }}" wire:navigate class="flex items-center gap-3 rounded-xl bg-ui-raised/60 px-4 py-3 text-sm transition hover:bg-ui-raised">
                            <x-ui.icon name="lock-closed" variant="mini" class="shrink-0 text-amber-500" />
                            <span class="min-w-0 flex-1">{{ __('Who wins according to the players and the most popular scores: in premium.') }}</span>
                            <x-ui.icon name="chevron-right" variant="micro" class="text-ui-muted" />
                        </a>
                    @endif
                </div>
            @endif

            {{-- Ostatnio typujący (bez ich typów) --}}
            @if ($live['recent'] !== [])
                <div class="flex flex-wrap items-center gap-2 border-t border-ui-line px-5 py-3 text-xs">
                    <span class="font-bold uppercase tracking-wider text-ui-muted">{{ __('Just tipped') }}</span>
                    @foreach ($live['recent'] as $team)
                        <span wire:key="recent-{{ md5($team) }}" x-data x-init="$el.animate?.([{ opacity: 0, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' }], { duration: 400, easing: 'ease-out' })"
                            class="max-w-48 truncate rounded-full bg-ui-raised px-2.5 py-1 font-semibold">{{ $team }}</span>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
