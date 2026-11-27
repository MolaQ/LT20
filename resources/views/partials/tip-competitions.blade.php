{{--
    „Twoje rozgrywki” obok wyniku: kafelek na każde rozgrywki gracza w sezonie, po dwa w wierszu (na wąskim telefonie
    jeden, 19.11.2026: w dwóch kolumnach nazwy i podpisy były ucięte do kilku liter), z etapem albo miejscem.
    Rozgrywki, w których gracz jeszcze gra: klik wybiera je dla składu i pytań, na kafelku wystawieni zawodnicy i odpowiedzi.
    Rozgrywki, z których odpadł: wyszarzone, bez kliknięcia.
    16.11.2026: wyraźniej, że to przełącznik (ludzie nie wiedzieli, że poza Ekstraklasą trzeba ustawić też inne rozgrywki):
    nagłówek z liczbą rozgrywek, na wybranym kafelku „Teraz ustawiasz”, na pozostałych „Do ustawienia” albo „Ustawione”.
    18.11.2026: kafelki na całą wysokość obok okna wyniku, w każdym skład, suma doświadczenia, średnia forma
    i ikona na każde pytanie (miecze atak, tarcza obrona, znak zapytania bez odpowiedzi); braki jako bursztynowa plakietka
    zamiast żółtej ramki. Blok można udostępnić obrazkiem.
--}}
@php
    $active = $this->activeQuestionSet();
    $lines = collect($this->setStatus)->keyBy('set');
@endphp
<div id="tip-competitions" data-share-target class="flex flex-1 scroll-mt-24 flex-col gap-3">
    @php
        // Sparing (27.11.2026) zajmuje wolny typ rozgrywek: kafelek rozgrywek, z których gracz odpadł, zostaje wyszarzony.
        $friendlySet = $this->friendlySet();
        $isPlaying = fn ($e) => isset($this->questionSets[$e['type']->scoreSet()->value]) && (($e['friendly'] ?? false) || $e['type']->scoreSet()->value !== $friendlySet);
        $playingCount = collect($this->competitionTiles)->filter($isPlaying)->count();
    @endphp
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <x-ui.heading size="lg">{{ __('Your competitions') }}</x-ui.heading>
            <x-ui.text class="text-sm">
                {{ $playingCount > 1 ? __('Click a tile to set its squad and questions.') : __('Pick a competition, then its squad and its questions.') }}
                {{ __('Everything saves automatically.') }}
            </x-ui.text>
        </div>
        <x-share-image :title="__('Your competitions').' · '.__('Matchday :number', ['number' => $this->matchday?->number])" class="text-ui-muted" />
    </div>
    <div class="grid flex-1 auto-rows-fr grid-cols-1 gap-2.5 min-[480px]:grid-cols-2">
        @foreach ($this->competitionTiles as $entry)
            @php
                $setValue = $entry['type']->scoreSet()->value;
                $playing = $isPlaying($entry);
                $isActive = $playing && $setValue === $active;
                $line = $lines->get($setValue);
                $questions = count($line['questions'] ?? []);
            @endphp
            <button type="button" @if ($playing) wire:click="pickSet('{{ $setValue }}')" @else disabled @endif wire:key="tip-comp-{{ $entry['competition_id'] }}"
                style="{{ \App\Support\CompetitionColors::style($entry['trophy']) }}" @if ($isActive) aria-current="true" @endif
                @class([
                    'comp-chip flex min-w-0 flex-col items-start justify-between gap-2 rounded-xl px-3 py-3 text-start transition duration-200',
                    'shadow-md ring-2 ring-club/70' => $isActive,
                    'cursor-pointer opacity-90 ring-1 ring-black/10 hover:-translate-y-0.5 hover:opacity-100 hover:shadow-md active:scale-[.98] dark:ring-white/15' => $playing && !$isActive,
                    'cursor-default opacity-40 grayscale' => !$playing,
                ])>
                @php
                    $ready = count($line['players'] ?? []) > 0;
                    $answered = $line['summary']['answered'] ?? 0;
                    // Brak składu albo nie wszystkie pytania: bursztynowa plakietka z tym, czego brakuje.
                    $missing = !$ready ? __('No squad') : ($questions > 0 && $answered < $questions ? __('Questions :done/:all', ['done' => $answered, 'all' => $questions]) : null);
                @endphp
                <span class="flex w-full items-center justify-between gap-2">
                    <span class="min-w-0 truncate text-sm font-semibold" title="{{ $entry['name'] }}">{{ $entry['name'] }}</span>
                    @if ($playing)
                        <span @class([
                            'flex shrink-0 items-center gap-1 rounded-full px-2 py-px text-[10px] font-bold uppercase tracking-wide',
                            'bg-white text-zinc-900' => $isActive,
                            'bg-amber-400 text-amber-950' => !$isActive && $missing,
                            'bg-black/25' => !$isActive && !$missing,
                        ])>
                            @if ($isActive)
                                <x-ui.icon name="pencil" variant="micro" class="size-3" />{{ __('Now setting') }}
                            @elseif ($missing)
                                <x-ui.icon name="exclamation-circle" variant="micro" class="size-3" />{{ $missing }}
                            @else
                                <x-ui.icon name="check" variant="micro" class="size-3" />{{ __('Set') }}
                            @endif
                        </span>
                    @endif
                </span>
                <span class="w-full truncate text-xs opacity-90">{{ $entry['status'] ?: '—' }}</span>
                @if ($playing)
                    {{-- Skład, suma doświadczenia w klubie gracza i średnia forma wystawionych --}}
                    <span class="grid w-full grid-cols-3 gap-1.5 tabular-nums">
                        @foreach ([
                            [__('Squad'), 'user-group', count($line['players'] ?? []).'/'.($line['max'] ?? 0)],
                            [__('Exp.'), 'star', \App\Support\ClubStats::experience($line['experience'] ?? 0)],
                            [__('Form'), 'fire', isset($line['form']) ? \App\Support\ClubStats::experience($line['form']) : '–'],
                        ] as [$statLabel, $statIcon, $statValue])
                            <span class="min-w-0 rounded-lg bg-black/20 px-2 py-1.5">
                                <span class="block truncate text-[10px] font-bold uppercase tracking-wide opacity-75">{{ $statLabel }}</span>
                                <span class="flex items-center gap-1 text-sm font-bold"><x-ui.icon :name="$statIcon" variant="micro" class="size-3.5 shrink-0" />{{ $statValue }}</span>
                            </span>
                        @endforeach
                    </span>
                    @if ($questions)
                        {{-- Pytania: ikona na każde (miecze atak, tarcza obrona, znak zapytania bez odpowiedzi) --}}
                        <span class="flex w-full items-center justify-between gap-2">
                            <span class="text-[9px] font-bold uppercase tracking-wider opacity-75">{{ __('Questions') }}</span>
                            <span class="flex flex-wrap justify-end gap-1">
                                @foreach ($line['questions'] as $side)
                                    @if ($side === 'attack')
                                        <span class="grid size-6 place-items-center rounded-md text-white shadow-sm" style="background: var(--q-attack)" title="{{ __('Attack') }}"><x-ui.icon name="swords" variant="micro" class="size-3.5" /></span>
                                    @elseif ($side === 'defense')
                                        <span class="grid size-6 place-items-center rounded-md text-white shadow-sm" style="background: var(--q-defense)" title="{{ __('Defence') }}"><x-ui.icon name="shield" variant="micro" class="size-3.5" /></span>
                                    @else
                                        <span class="grid size-6 place-items-center rounded-md border-[1.5px] border-dashed border-current/60 text-[11px] font-bold opacity-80" title="{{ __('No answer') }}">?</span>
                                    @endif
                                @endforeach
                            </span>
                        </span>
                    @endif
                @else
                    <span class="rounded-full bg-black/25 px-2 py-px text-[11px] font-semibold">{{ __('You are out') }}</span>
                @endif
            </button>
        @endforeach
    </div>
</div>
