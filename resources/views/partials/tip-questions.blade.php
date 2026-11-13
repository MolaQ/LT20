{{--
    Pytania wybranych rozgrywek w kreatorze typowania (Support\Questions): każde rozgrywki mają własny zestaw do 5 pytań.
    Gracz odpowiada Tak albo Nie (ponowny klik cofa odpowiedź, czyli pominięcie) i przy każdej odpowiedzi wybiera OF albo DEF.
    Zwarte wiersze: treść, pod nią przyciski odpowiedzi i strony bonusu.
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
            <div wire:key="q-{{ $active }}-{{ $question->id }}" @class([
                'rounded-xl border px-3 py-2 transition',
                'border-club/40 bg-club/5' => $mine,
                'border-ui-line' => !$mine,
            ])>
                <div class="flex items-start gap-2 text-sm">
                    <span class="w-4 shrink-0 font-semibold tabular-nums text-ui-muted">{{ $loop->iteration }}.</span>
                    <span class="min-w-0 flex-1 font-medium leading-snug">{{ $question->text }}</span>
                    @if ($result)
                        <x-ui.chip :variant="$result[0]">{{ $result[1] }}</x-ui.chip>
                    @elseif ($question->correct !== null)
                        <x-ui.chip>{{ $question->correct ? __('Yes') : __('No') }}</x-ui.chip>
                    @endif
                </div>
                <div class="mt-1.5 flex items-center gap-1.5 ps-6">
                    @foreach (['yes' => __('Yes'), 'no' => __('No')] as $option => $label)
                        <button type="button" wire:click="answerQuestion({{ $question->id }}, '{{ $value === $option ? '' : $option }}')" @disabled(!$open) aria-pressed="{{ $value === $option ? 'true' : 'false' }}"
                            @class([
                                'h-8 min-w-12 rounded-lg px-3 text-xs font-bold transition active:scale-95 disabled:cursor-default',
                                'bg-club text-club-ink shadow-sm' => $value === $option,
                                'bg-ui-raised hover:bg-ui-raised/70' => $value !== $option,
                            ])>{{ $label }}</button>
                    @endforeach
                    {{-- Strona bonusu: OF (atak) albo DEF (obrona), tylko przy pytaniu z odpowiedzią --}}
                    <div @class(['ms-auto flex gap-1', 'pointer-events-none opacity-35' => !$mine])>
                        @foreach (['attack' => ['OF', __('Offensive')], 'defense' => ['DEF', __('Defensive')]] as $side => [$short, $label])
                            @php $on = $mine && $mine->side === $side; @endphp
                            <button type="button" wire:click="questionSide({{ $question->id }}, '{{ $side }}')" @disabled(!$open || !$mine) title="{{ $label }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                @class([
                                    'h-8 rounded-lg border px-2.5 font-display text-xs font-bold tracking-wider transition disabled:cursor-default',
                                    'border-transparent bg-gold-soft text-gold' => $on && $side === 'attack',
                                    'border-transparent bg-good-soft text-good' => $on && $side === 'defense',
                                    'border-dashed border-ui-line text-ui-muted' => !$on,
                                ])>{{ $short }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</section>
