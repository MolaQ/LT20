{{--
    „Twoje rozgrywki” obok wyniku: kafelek na każde rozgrywki gracza w sezonie, po dwa w wierszu, z etapem albo miejscem.
    Rozgrywki, w których gracz jeszcze gra: klik wybiera je dla składu i pytań, na kafelku wystawieni zawodnicy i odpowiedzi.
    Rozgrywki, z których odpadł: wyszarzone, bez kliknięcia.
    16.11.2026: wyraźniej, że to przełącznik (ludzie nie wiedzieli, że poza Ekstraklasą trzeba ustawić też inne rozgrywki):
    nagłówek z liczbą rozgrywek, na wybranym kafelku „Teraz ustawiasz”, na pozostałych „Do ustawienia” albo „Ustawione”.
--}}
@php
    $active = $this->activeQuestionSet();
    $lines = collect($this->setStatus)->keyBy('set');
@endphp
<div id="tip-competitions" class="flex scroll-mt-24 flex-col gap-2">
    @php
        $playingCount = collect($this->myEntries)->filter(fn ($e) => isset($this->questionSets[$e['type']->scoreSet()->value]))->count();
    @endphp
    <div>
        <x-ui.heading size="lg">{{ __('Your competitions') }}</x-ui.heading>
        <x-ui.text class="text-sm">{{ __('Pick a competition, then its squad and its questions.') }} {{ __('Everything saves automatically.') }}</x-ui.text>
        @if ($playingCount > 1)
            <div class="mt-2 flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                <x-ui.icon name="arrows-right-left" variant="mini" class="mt-0.5 shrink-0" />
                <span>{{ __('You play in :count competitions. Each has its own squad and questions: click every tile below and set them all.', ['count' => $playingCount]) }}</span>
            </div>
        @endif
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
                    'cursor-pointer opacity-90 ring-1 ring-black/10 hover:-translate-y-0.5 hover:opacity-100 hover:shadow-md active:scale-[.98] dark:ring-white/15' => $playing && !$isActive,
                    'cursor-default opacity-40 grayscale' => !$playing,
                ])>
                @php
                    $ready = count($line['players'] ?? []) > 0;
                @endphp
                <span class="flex w-full items-center justify-between gap-2">
                    <span class="min-w-0 truncate text-sm font-semibold">{{ $entry['name'] }}</span>
                    @if ($playing)
                        <span @class([
                            'flex shrink-0 items-center gap-1 rounded-full px-2 py-px text-[10px] font-bold uppercase tracking-wide',
                            'bg-white text-zinc-900' => $isActive,
                            'bg-black/25' => !$isActive,
                        ])>
                            @if ($isActive)
                                <x-ui.icon name="pencil" variant="micro" class="size-3" />{{ __('Now setting') }}
                            @elseif ($ready)
                                <x-ui.icon name="check" variant="micro" class="size-3" />{{ __('Set') }}
                            @else
                                <span class="size-1.5 animate-pulse rounded-full bg-amber-300"></span>{{ __('To set') }}
                            @endif
                        </span>
                    @endif
                </span>
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
