{{--
    Pytania wybranych rozgrywek w kreatorze typowania (Support\Questions): każde rozgrywki mają własny zestaw do 5 pytań.
    Gracz odpowiada Tak albo Nie (ponowny klik cofa odpowiedź, czyli pominięcie) i przy każdej odpowiedzi wybiera
    miecze (atak) albo tarczę (obrona). Kolory obu stron: Kolory strony → Pytania (--q-attack, --q-defense).
    Zwarte wiersze: na szerszym ekranie treść i przyciski w jednej linii, na telefonie przyciski pod treścią.
--}}
@php
    $active = $this->activeQuestionSet();
    $answers = $this->myAnswers->get($active, collect());
    $questions = $this->questions;
    $open = $this->isOpen;
    $attack = $answers->where('side', 'attack')->count();
    $defense = $answers->where('side', 'defense')->count();
    $settled = $questions->contains(fn ($q) => $q->correct !== null || $q->voided);
    $bonus = $settled ? \App\Support\Questions::bonus($questions, $answers) : null;
@endphp

<section class="flex flex-col gap-2.5 rounded-2xl border border-ui-line bg-ui-surface p-4">
    <div class="flex items-baseline justify-between gap-2">
        <x-ui.heading>{{ __('Questions') }} <span class="font-normal text-ui-muted">· {{ $this->questionSets[$active]['label'] ?? '' }}</span></x-ui.heading>
        @if ($questions->isNotEmpty())
            <span class="shrink-0 text-xs font-semibold tabular-nums text-ui-muted">{{ $answers->count() }}/{{ $questions->count() }}</span>
        @endif
    </div>

    @if ($questions->isEmpty())
        <x-ui.text class="text-sm">{{ __('No questions for this competition in this matchday.') }}</x-ui.text>
    @else
        {{-- Bonus: po rozstrzygnięciu wynik, wcześniej to, co do zgarnięcia, i ryzyko (ile pytań z odpowiedzią) --}}
        <div class="flex flex-col gap-1 rounded-xl bg-ui-raised/60 px-3 py-2">
            @if ($bonus)
                <b class="text-sm">{{ $bonus['missed'] ? __('One miss: question bonuses wiped.') : __('Question bonus: attack +:a, defence +:d', ['a' => $bonus['attack'], 'd' => $bonus['defense']]) }}</b>
            @else
                <b class="text-sm">{{ $answers->isEmpty() ? __('No questions answered: no bonus, no risk.') : __('Up for grabs: attack +:a, defence +:d', ['a' => $attack, 'd' => $defense]) }}</b>
                <div class="h-1.5 overflow-hidden rounded-full bg-ui-line" role="meter" aria-valuemin="0" aria-valuemax="{{ $questions->count() }}" aria-valuenow="{{ $answers->count() }}" aria-label="{{ __('Risk') }}">
                    <i class="block h-full bg-linear-to-r from-good via-gold to-bad transition-[width] duration-500" style="width: {{ round(100 * $answers->count() / max(1, $questions->count())) }}%"></i>
                </div>
                <small class="text-xs text-ui-muted">{{ __('Click again to take an answer back. One miss wipes both question bonuses in this competition.') }}</small>
            @endif
        </div>

        @foreach ($questions as $question)
            @php
                $mine = $answers->get($question->id);
                $value = $mine ? ($mine->answer ? 'yes' : 'no') : '';
                $result = match (true) {
                    $question->voided => ['default', __('Voided')],
                    $question->correct === null || !$mine => null,
                    $mine->answer === $question->correct => ['good', __('Hit')],
                    default => ['bad', __('Miss')],
                };
            @endphp
            {{-- Pytanie z odpowiedzią: pasek z lewej w kolorze strony bonusu (miecze = atak, tarcza = obrona) --}}
            @php $sideColor = $mine ? ($mine->side === 'defense' ? 'var(--q-defense)' : 'var(--q-attack)') : null; @endphp
            <div wire:key="q-{{ $active }}-{{ $question->id }}" @if ($sideColor) style="border-inline-start-color: {{ $sideColor }}; background: color-mix(in srgb, {{ $sideColor }} 6%, transparent)" @endif
                @class([
                    'flex flex-col gap-1.5 rounded-lg border border-s-[3px] px-2.5 py-1 transition duration-200 hover:shadow-sm sm:flex-row sm:items-center sm:gap-2',
                    'border-ui-line' => true,
                ])>
                <div class="flex min-w-0 flex-1 items-start gap-1.5 text-[13px]">
                    <span class="w-4 shrink-0 font-semibold tabular-nums text-ui-muted">{{ $loop->iteration }}.</span>
                    <span class="min-w-0 flex-1 font-medium leading-snug">{{ $question->text }}</span>
                    @if ($result)
                        <x-ui.chip :variant="$result[0]">{{ $result[1] }}</x-ui.chip>
                    @elseif ($question->correct !== null)
                        <x-ui.chip>{{ $question->correct ? __('Yes') : __('No') }}</x-ui.chip>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-1 ps-5 sm:ps-0">
                    @foreach (['yes' => __('Yes'), 'no' => __('No')] as $option => $label)
                        <button type="button" wire:click="answerQuestion({{ $question->id }}, '{{ $value === $option ? '' : $option }}')" @disabled(!$open) aria-pressed="{{ $value === $option ? 'true' : 'false' }}"
                            @class([
                                'h-7 min-w-10 rounded-md px-2 text-xs font-bold transition duration-150 active:scale-95 disabled:cursor-default',
                                'bg-club text-club-ink shadow-sm' => $value === $option,
                                'bg-ui-raised hover:bg-ui-raised/70' => $value !== $option,
                            ])>{{ $label }}</button>
                    @endforeach
                    {{-- Strona bonusu: miecze (atak) albo tarcza (obrona), tylko przy pytaniu z odpowiedzią; kolory z panelu --}}
                    <div @class(['ms-auto flex gap-1 transition-opacity duration-200 sm:ms-1.5', 'pointer-events-none opacity-30' => !$mine])>
                        @foreach (['attack' => ['swords', __('Attack'), 'var(--q-attack)'], 'defense' => ['shield', __('Defence'), 'var(--q-defense)']] as $side => [$icon, $label, $color])
                            @php $on = $mine && $mine->side === $side; @endphp
                            <button type="button" wire:click="questionSide({{ $question->id }}, '{{ $side }}')" @disabled(!$open || !$mine) title="{{ $label }}" aria-label="{{ $label }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                @if ($on) style="color: {{ $color }}; background: color-mix(in srgb, {{ $color }} 16%, transparent); border-color: color-mix(in srgb, {{ $color }} 45%, transparent)" @endif
                                @class([
                                    'grid size-7 place-items-center rounded-md border transition duration-150 active:scale-90 disabled:cursor-default',
                                    'border-dashed border-ui-line text-ui-muted hover:text-ui-ink' => !$on,
                                ])><x-ui.icon :name="$icon" class="size-4" /></button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</section>
