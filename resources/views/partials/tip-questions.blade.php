{{--
    Krok „Pytania” w kreatorze typowania (Support\Questions). Każdy typ rozgrywek gracza ma osobny zestaw odpowiedzi:
    w każdym gracz sam wybiera, na które z pytań kolejki odpowiada (0–5), i które są ofensywne (OF), a które defensywne (DEF).
    Na górze karty typów rozgrywek ze stanem, pod nimi pasek bonusu wybranego typu i pytania.
--}}
@php
    $sets = $this->questionSets;
    $active = $this->activeQuestionSet();
    $allAnswers = $this->myAnswers;
    $answers = $allAnswers->get($active, collect());
    $open = $this->isOpen;
    $attack = $answers->where('side', 'attack')->count();
    $defense = $answers->where('side', 'defense')->count();
    $total = max(1, $this->questions->count());
    $settled = $this->questions->contains(fn ($q) => $q->correct !== null || $q->voided);
    $bonus = $settled ? \App\Support\Questions::bonus($this->questions, $answers) : null;
@endphp

<div class="flex flex-col gap-3">
    <div>
        <x-ui.heading size="lg">{{ __('Questions') }}</x-ui.heading>
        <x-ui.text class="text-sm">{{ __('Each competition has its own answers. Pick the questions you want (none is fine) and mark each one OF (attack) or DEF (defence).') }}</x-ui.text>
    </div>

    {{-- Typy rozgrywek gracza: klik wybiera, na karcie stan odpowiedzi --}}
    @if (count($sets) > 1)
        <div class="grid grid-cols-2 gap-2">
            @foreach ($sets as $setValue => $set)
                @php
                    $summary = \App\Support\Questions::summary($allAnswers->get($setValue, collect()));
                    $isActive = $setValue === $active;
                @endphp
                <button type="button" wire:click="$set('questionSet', '{{ $setValue }}')" wire:key="q-set-{{ $setValue }}" style="{{ $set['style'] }}"
                    @class([
                        'comp-chip flex flex-col items-start gap-0.5 rounded-xl px-3 py-2 text-left transition',
                        'shadow-sm ring-2 ring-club/60' => $isActive,
                        'opacity-60 hover:opacity-100' => !$isActive,
                    ])>
                    <span class="w-full truncate text-[13px] font-semibold">{{ $set['label'] }}</span>
                    <span class="text-xs tabular-nums opacity-90">
                        @if ($summary['answered'])
                            {{ $summary['answered'] }}/{{ $this->questions->count() }} · OF {{ $summary['attack'] }} · DEF {{ $summary['defense'] }}
                        @else
                            {{ __('No questions') }}
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    @endif
</div>

{{-- Pasek bonusu wybranego typu rozgrywek --}}
<div class="sticky top-16 z-10 flex flex-col gap-1.5 rounded-2xl border border-ui-line bg-ui-surface p-4 shadow-lg lg:top-20">
    <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold uppercase tracking-wider text-ui-muted">{{ $sets[$active]['label'] ?? '' }}</span>
        @if ($open && count($sets) > 1 && $answers->isNotEmpty())
            <button type="button" wire:click="copyQuestions" wire:confirm="{{ __('Copy these answers to all your competitions? Their current answers will be replaced.') }}"
                class="rounded-lg bg-ui-raised px-2.5 py-1 text-xs font-semibold text-ui-ink transition hover:bg-ui-raised/70">{{ __('Copy to all') }}</button>
        @endif
    </div>
    @if ($bonus)
        <b class="font-display text-lg">
            {{ $bonus['missed'] ? __('One miss: question bonuses wiped.') : __('Question bonus: attack +:a, defence +:d', ['a' => $bonus['attack'], 'd' => $bonus['defense']]) }}
        </b>
    @else
        <b class="font-display text-lg">
            {{ $answers->isEmpty() ? __('No questions answered: no bonus, no risk.') : __('Up for grabs: attack +:a, defence +:d', ['a' => $attack, 'd' => $defense]) }}
        </b>
        <div class="h-2 overflow-hidden rounded-full bg-ui-raised" role="meter" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $answers->count() }}" aria-label="{{ __('Risk') }}">
            <i class="block h-full bg-linear-to-r from-good via-gold to-bad transition-[width] duration-500" style="width: {{ round(100 * $answers->count() / $total) }}%"></i>
        </div>
        <small class="text-xs text-ui-muted">{{ __('One miss wipes both question bonuses in this competition. Players still count.') }}</small>
    @endif
</div>

{{-- Na szerokim ekranie pytania w dwóch kolumnach, żeby nie były rozciągnięte na całą szerokość --}}
<div class="grid gap-3 lg:grid-cols-2">
@foreach ($this->questions as $question)
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
    <x-ui.card wire:key="q-{{ $active }}-{{ $question->id }}" :class="!$mine ? 'opacity-80' : ''">
        <div class="flex items-start justify-between gap-2">
            <span class="font-bold"><span class="me-1 text-ui-muted tabular-nums">{{ $loop->iteration }}.</span>{{ $question->text }}</span>
            @if ($result)
                <x-ui.chip :variant="$result[0]">{{ $result[1] }}</x-ui.chip>
            @elseif ($question->correct !== null)
                <x-ui.chip>{{ $question->correct ? __('Yes') : __('No') }}</x-ui.chip>
            @endif
        </div>
        <div class="mt-2.5 flex gap-2">
            @foreach (['yes' => __('Yes'), 'no' => __('No'), '' => __('Skip')] as $option => $label)
                <button type="button" wire:click="answerQuestion({{ $question->id }}, '{{ $option }}')" @disabled(!$open)
                    @class([
                        'flex-1 rounded-xl py-2.5 font-bold transition active:scale-95 disabled:cursor-default',
                        'bg-club text-club-ink' => $value === $option && $option !== '',
                        'bg-ui-muted text-ui-surface' => $value === '' && $option === '',
                        'bg-ui-raised' => $value !== $option,
                    ])>{{ $label }}</button>
            @endforeach
        </div>
        {{-- Strona bonusu: ofensywne (atak) albo defensywne (obrona), tylko dla pytania z odpowiedzią --}}
        <div @class(['mt-2 flex gap-1.5 text-xs', 'pointer-events-none opacity-35' => !$mine])>
            @foreach (['attack' => ['OF', __('Offensive')], 'defense' => ['DEF', __('Defensive')]] as $side => [$short, $label])
                @php $on = $mine && $mine->side === $side; @endphp
                <button type="button" wire:click="questionSide({{ $question->id }}, '{{ $side }}')" @disabled(!$open || !$mine)
                    @class([
                        'flex flex-1 items-center justify-center gap-1.5 rounded-lg border py-1.5 font-semibold',
                        'border-transparent bg-gold-soft text-gold' => $on && $side === 'attack',
                        'border-transparent bg-good-soft text-good' => $on && $side === 'defense',
                        'border-dashed border-ui-line text-ui-muted' => !$on,
                    ])><b class="font-display tracking-wider">{{ $short }}</b> {{ $label }}</button>
            @endforeach
        </div>
    </x-ui.card>
@endforeach
</div>
