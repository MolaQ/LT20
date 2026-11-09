<?php

use App\Models\LechMatch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Raport meczu Lecha z kalendarza, który nie jest kolejką gry (mecz archiwalny): wynik, skład i zdarzenia
 * (pages::dashboard.match-report). Doświadczenie z tych meczów ustawia kolejność zawodników w panelu wyniku,
 * do klubów graczy się nie liczy. Mecz przypisany do kolejki ma raport w panelu wyników.
 */
new class extends Component {
    public LechMatch $lechMatch;

    public function render(): View
    {
        return $this->view()->title(__('Match report'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ $lechMatch->fixture() }}</x-ui.heading>
            <x-ui.subheading>
                {{ $lechMatch->kickoff_at ? $lechMatch->kickoff_at->translatedFormat('j F Y, H:i') : __('not set yet') }}
                @if ($lechMatch->competitionLabel()) &middot; {{ $lechMatch->competitionLabel() }} @endif
            </x-ui.subheading>
        </div>
        <x-ui.btn icon="arrow-left" :href="route('dashboard.lech-calendar')" wire:navigate>{{ __('Lech calendar') }}</x-ui.btn>
    </div>

    @if ($lechMatch->matchday)
        <x-ui.callout icon="information-circle" :heading="__('This match is a matchday of the game. Enter its result in the results panel.')">
            <x-slot name="actions">
                <x-ui.btn size="sm" :href="route('dashboard.results', ['season' => $lechMatch->matchday->season_id, 'matchday' => $lechMatch->matchday->number])" wire:navigate>{{ __('Results') }}</x-ui.btn>
            </x-slot>
        </x-ui.callout>
    @else
        <x-ui.text class="text-sm">{{ __('A match outside the game: its report counts only to the order of players in the result panel.') }}</x-ui.text>
        <livewire:pages::dashboard.match-report :lech-match-id="$lechMatch->id" :key="'archive-report-' . $lechMatch->id" />
    @endif
</div>
