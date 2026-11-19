<?php

use App\Models\Competition;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Prawy panel strony wyników, na samej górze: partner wybranych rozgrywek w barwach tych rozgrywek
 * (duże logo, nazwa, link). Rozgrywki podaje strona wyników zdarzeniem „results-competition”
 * (także zaraz po wejściu na stronę). Bez partnera panel jest pusty.
 */
new class extends Component {
    public int $competitionId = 0;

    #[On('results-competition')]
    public function select(int $id): void
    {
        $this->competitionId = $id;
        unset($this->competition);
    }

    #[Computed]
    public function competition(): ?Competition
    {
        return $this->competitionId ? Competition::with('sponsor')->find($this->competitionId) : null;
    }
}; ?>

<div>
    @if (($competition = $this->competition) && ($sponsor = $competition->sponsor))
        @php
            $colorKey = $competition->trophyKey();
            $dashed = \App\Support\CompetitionColors::dashed($colorKey) ? 'comp-dashed' : '';
        @endphp
        <a @if ($sponsor->url) href="{{ $sponsor->url }}" target="_blank" rel="noopener sponsored" @endif
            wire:key="partner-{{ $competition->id }}"
            class="comp-banner {{ $dashed }} group block rounded-2xl px-4 py-4 transition {{ $sponsor->url ? 'hover:brightness-110' : '' }}"
            style="{{ \App\Support\CompetitionColors::style($colorKey) }}">
            <span class="relative z-10 flex items-center justify-between gap-2 text-[11px] font-semibold uppercase tracking-widest">
                <span class="comp-accent">{{ $sponsor->label ?: __('Competition partner') }}</span>
                <span class="truncate opacity-80">{{ $competition->name ?: $competition->type->label() }}</span>
            </span>
            <span class="relative z-10 mt-3 flex items-center gap-4">
                <span class="comp-partner-logo flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl">
                    @if ($sponsor->logoUrl())
                        <img src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}" class="size-full object-contain p-1.5">
                    @else
                        <span class="text-2xl font-black uppercase">{{ mb_substr($sponsor->name, 0, 1) }}</span>
                    @endif
                </span>
                <span class="min-w-0">
                    <span class="block text-lg font-bold leading-tight">{{ $sponsor->name }}</span>
                    @if ($sponsor->url)
                        <span class="mt-1 inline-flex items-center gap-1 text-xs font-semibold opacity-80 transition group-hover:opacity-100">
                            {{ __('Visit the partner') }}
                            <x-ui.icon name="chevron-right" variant="micro" class="transition group-hover:translate-x-0.5" />
                        </span>
                    @endif
                </span>
            </span>
        </a>
    @endif
</div>
