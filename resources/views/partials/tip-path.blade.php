{{--
    „Twoje mecze” pod paskiem kolejek na /tips: mecze zespołu gracza w wybranych rozgrywkach (domyślnie liga),
    kolumna na kolejkę, wybrana kolejka zaznaczona (Liga Legend: dwie pary). Nad paskiem wybór rozgrywek,
    gdy gracz gra w kilku. Klik w etykietę kolejki otwiera ją do typowania, klik w mecz pokazuje pojedynek.
--}}
@if ($myPath = $this->myPath)
    @php
        $pathEntry = $myPath['entry'];
        $matchdaysByNumber = $this->matchdays->keyBy('number');
    @endphp
    <div class="flex flex-col gap-2">
        @if (count($this->myEntries) > 1)
            <div class="-mx-3.5 flex gap-1.5 overflow-x-auto px-3.5 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0" data-no-swipe>
                @foreach ($this->myEntries as $entryOption)
                    @php $selected = $entryOption['competition_id'] === $pathEntry['competition_id']; @endphp
                    <button type="button" wire:click="$set('pathCompetition', {{ $entryOption['competition_id'] }})" wire:key="path-comp-{{ $entryOption['competition_id'] }}"
                        @if ($selected) aria-current="true" @endif
                        @class([
                            'flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition',
                            'bg-club text-club-ink shadow-sm' => $selected,
                            'bg-ui-raised text-ui-muted hover:text-ui-ink' => !$selected,
                        ])>
                        <span class="size-2.5 shrink-0 rounded-full" style="{{ \App\Support\CompetitionColors::dotStyle($entryOption['color']) }}"></span>
                        {{ $entryOption['name'] }}
                    </button>
                @endforeach
            </div>
        @endif

        @include('partials.my-matches-path', [
            'title' => __('Your matches') . ' · ' . $pathEntry['name'],
            'status' => $pathEntry['status'],
            'rounds' => \App\Models\Matchday::PER_SEASON,
            'perRound' => $pathEntry['type'] === \App\Enums\CompetitionType::Legends ? 2 : 1,
            'path' => $myPath['path'],
            'entryId' => $pathEntry['entry_id'],
            'eliminated' => $pathEntry['eliminated_round'],
            'stageLabel' => fn (int $stage) => __('Matchday :number', ['number' => $stage]) . (($matchdaysByNumber[$stage] ?? null)?->isOpenForTips() ? ' •' : ''),
            'stageAction' => 'openMatchday',
            'stageExtra' => fn (int $stage) => match (true) {
                !isset($matchdaysByNumber[$stage]) || blank($matchdaysByNumber[$stage]->opponent) => __('Match not assigned yet'),
                $matchdaysByNumber[$stage]->lech_goals !== null => 'Lech ' . $matchdaysByNumber[$stage]->lech_goals . ':' . $matchdaysByNumber[$stage]->opponent_goals . ' ' . $matchdaysByNumber[$stage]->opponent,
                default => $matchdaysByNumber[$stage]->opponent . ($matchdaysByNumber[$stage]->kickoff_at ? ', ' . $matchdaysByNumber[$stage]->kickoff_at->translatedFormat('j M H:i') : ''),
            },
            'current' => $this->number,
        ])
    </div>
@endif
