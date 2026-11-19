{{--
    Pytania kolejki rywala (App\Support\Rivals::questions): ikona na każde pytanie z odpowiedzią.
    Bez premium skrzyżowane miecze na tarczy (ile pytań, bez strony bonusu), premium najpierw miecze (ataki),
    potem tarcze (obrony), bez kolejności pytań. Pytania bez odpowiedzi to blade kółka.
    Użycie: <x-question-sides :info="$rival['questions']" />
--}}
@props(['info'])

@php
    $icons = $info['attack'] !== null
        ? array_merge(array_fill(0, $info['attack'], 'attack'), array_fill(0, $info['defense'], 'defense'))
        : array_fill(0, $info['answered'], 'answered');
    $missing = max(0, $info['total'] - count($icons));
    $title = $info['attack'] !== null
        ? __('Questions: :answered of :total (attack :attack, defence :defence)', ['answered' => $info['answered'], 'total' => $info['total'], 'attack' => $info['attack'], 'defence' => $info['defense']])
        : __('Questions: :answered of :total', ['answered' => $info['answered'], 'total' => $info['total']]);
@endphp

<span {{ $attributes->class('inline-flex flex-wrap items-center gap-0.5') }} title="{{ $title }}" aria-label="{{ $title }}">
    @foreach ($icons as $icon)
        @if ($icon === 'attack')
            <span class="grid size-5 place-items-center rounded text-white" style="background: var(--q-attack)"><x-ui.icon name="swords" variant="micro" class="size-3" /></span>
        @elseif ($icon === 'defense')
            <span class="grid size-5 place-items-center rounded text-white" style="background: var(--q-defense)"><x-ui.icon name="shield" variant="micro" class="size-3" /></span>
        @else
            <span class="grid size-5 place-items-center rounded bg-club text-club-ink"><x-ui.icon name="battle" variant="micro" class="size-3.5" /></span>
        @endif
    @endforeach
    @for ($i = 0; $i < $missing; $i++)
        <span class="size-5 rounded border border-dashed border-ui-line" aria-hidden="true"></span>
    @endfor
</span>
