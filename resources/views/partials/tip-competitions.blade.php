{{--
    „Twoje rozgrywki” obok wyniku: kafelek na każde rozgrywki gracza w sezonie, po dwa w wierszu, z etapem albo miejscem.
    Rozgrywki, w których gracz jeszcze gra: klik wybiera je dla składu i pytań, na kafelku wystawieni zawodnicy i odpowiedzi.
    Rozgrywki, z których odpadł: wyszarzone, bez kliknięcia.
--}}
@php
    $active = $this->activeQuestionSet();
    $lines = collect($this->setStatus)->keyBy('set');
@endphp
<div id="tip-competitions" class="flex scroll-mt-24 flex-col gap-2">
    <div>
        <x-ui.heading size="lg">{{ __('Your competitions') }}</x-ui.heading>
        <x-ui.text class="text-sm">{{ __('Pick a competition, then its squad and its questions.') }} {{ __('Everything saves automatically.') }}</x-ui.text>
    </div>
    <div class="grid grid-cols-2 gap-2">
        @foreach ($this->myEntries as $entry)
            @php
                $setValue = $entry['type']->scoreSet()->value;
                $playing = isset($this->questionSets[$setValue]);
                $isActive = $playing && $setValue === $active;
                $line = $lines->get($setValue);
                $questions = count($line['questions'] ?? []);
            @endphp
            <button type="button" @if ($playing) wire:click="pickSet('{{ $setValue }}')" @else disabled @endif wire:key="tip-comp-{{ $entry['competition_id'] }}"
                style="{{ \App\Support\CompetitionColors::style($entry['trophy']) }}" @if ($isActive) aria-current="true" @endif
                @class([
                    'comp-chip flex min-w-0 flex-col items-start gap-1 rounded-xl px-3 py-2.5 text-start transition duration-200',
                    'shadow-md ring-2 ring-club/70' => $isActive,
                    'opacity-70 hover:-translate-y-0.5 hover:opacity-100 hover:shadow-md active:scale-[.98]' => $playing && !$isActive,
                    'cursor-default opacity-40 grayscale' => !$playing,
                ])>
                <span class="w-full truncate text-sm font-semibold">{{ $entry['name'] }}</span>
                <span class="w-full truncate text-xs opacity-90">{{ $entry['status'] ?: '—' }}</span>
                @if ($playing)
                    <span class="flex items-center gap-2.5 text-xs font-semibold tabular-nums">
                        <span class="flex items-center gap-0.5" title="{{ __('Fielded players') }}"><x-ui.icon name="user-group" variant="micro" />{{ count($line['players'] ?? []) }}/{{ $line['max'] ?? 0 }}</span>
                        @if ($questions)
                            <span class="flex items-center gap-0.5" title="{{ __('Answered questions') }}"><x-ui.icon name="question-mark-circle" variant="micro" />{{ $line['summary']['answered'] ?? 0 }}/{{ $questions }}</span>
                        @endif
                    </span>
                @else
                    <span class="rounded-full bg-black/25 px-2 py-px text-[11px] font-semibold">{{ __('You are out') }}</span>
                @endif
            </button>
        @endforeach
    </div>
</div>
