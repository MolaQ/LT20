{{--
    Wybór rozgrywek w kreatorze typowania: kafelek na każde rozgrywki, w których gracz jeszcze gra.
    Klik wybiera rozgrywki dla składu i pytań; na kafelku stan: wystawieni zawodnicy i odpowiedzi na pytania zestawu.
--}}
@php
    $active = $this->activeQuestionSet();
    $lines = collect($this->ticket)->keyBy('set');
@endphp
<div id="tip-competitions" class="flex scroll-mt-24 flex-col gap-2">
    <div>
        <x-ui.heading size="lg">{{ __('Your competitions') }}</x-ui.heading>
        <x-ui.text class="text-sm">{{ __('Pick a competition, then its squad and its questions.') }}</x-ui.text>
    </div>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach ($this->questionSets as $setValue => $set)
            @php
                $line = $lines->get($setValue);
                $isActive = $setValue === $active;
                $players = count($line['players'] ?? []);
                $questions = count($line['questions'] ?? []);
            @endphp
            <button type="button" wire:click="pickSet('{{ $setValue }}')" wire:key="tip-set-{{ $setValue }}" style="{{ $set['style'] }}" @if ($isActive) aria-current="true" @endif
                @class([
                    'comp-chip flex flex-col items-start gap-1 rounded-xl px-3 py-2 text-start transition active:scale-[.98]',
                    'shadow-md ring-2 ring-club/70' => $isActive,
                    'opacity-60 hover:opacity-100' => !$isActive,
                ])>
                <span class="w-full truncate text-[13px] font-semibold">{{ $set['label'] }}</span>
                <span class="flex items-center gap-2.5 text-xs font-semibold tabular-nums">
                    <span class="flex items-center gap-0.5" title="{{ __('Fielded players') }}"><x-ui.icon name="user-group" variant="micro" />{{ $players }}/{{ $line['max'] ?? 0 }}</span>
                    @if ($questions)
                        <span class="flex items-center gap-0.5" title="{{ __('Answered questions') }}"><x-ui.icon name="question-mark-circle" variant="micro" />{{ $line['summary']['answered'] ?? 0 }}/{{ $questions }}</span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>
</div>
