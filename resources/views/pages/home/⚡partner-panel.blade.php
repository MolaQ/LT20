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
    {{-- Ta sama wysokość co baner rozgrywek obok (xl:min-h-[8.5rem] w wynikach). --}}
    @if (($competition = $this->competition) && $competition->sponsor)
        <x-partner-block :competition="$competition" wire:key="partner-{{ $competition->id }}" class="h-[8.5rem]" />
    @endif
</div>
