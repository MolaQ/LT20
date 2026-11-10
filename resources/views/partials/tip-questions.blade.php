{{--
    Krok „Pytania” w kreatorze typowania (Support\Questions): tak / nie / pomiń i strona bonusu (atak albo obrona).
    Pasek na górze pokazuje, ile można zyskać i że jedno pudło zeruje oba bonusy z pytań.
--}}
@php
    $answers = $this->myAnswers;
    $open = $this->isOpen;
    $attack = $answers->where('side', 'attack')->count();
    $defense = $answers->where('side', 'defense')->count();
    $total = max(1, $this->questions->count());
    $settled = $this->questions->contains(fn ($q) => $q->correct !== null || $q->voided);
    $bonus = $settled ? \App\Support\Questions::bonus($this->questions, $answers) : null;
@endphp

<div class="sticky top-16 z-10 flex flex-col gap-1.5 rounded-2xl border border-ui-line bg-ui-surface p-3 shadow-lg lg:top-20">
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
        <small class="text-xs text-ui-muted">{{ __('One miss wipes both question bonuses. Players still count.') }}</small>
    @endif
</div>

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
    <x-ui.card wire:key="q-{{ $question->id }}" :class="!$mine && !$open ? 'opacity-80' : ''">
        <div class="flex items-start justify-between gap-2">
            <span class="font-bold">{{ $question->text }}</span>
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
        <div @class(['mt-2 flex gap-1.5 text-xs', 'pointer-events-none opacity-35' => !$mine])>
            @foreach (['attack' => '⚔ '.__('Attack'), 'defense' => '🛡 '.__('Defence')] as $side => $label)
                @php $on = $mine && $mine->side === $side; @endphp
                <button type="button" wire:click="questionSide({{ $question->id }}, '{{ $side }}')" @disabled(!$open || !$mine)
                    @class([
                        'flex-1 rounded-lg border py-1.5 font-display font-bold uppercase tracking-wider',
                        'border-transparent bg-gold-soft text-gold' => $on && $side === 'attack',
                        'border-transparent bg-good-soft text-good' => $on && $side === 'defense',
                        'border-dashed border-ui-line text-ui-muted' => !$on,
                    ])>{{ $label }}</button>
            @endforeach
        </div>
    </x-ui.card>
@endforeach
