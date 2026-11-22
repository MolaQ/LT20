{{--
    Pytania kolejki rywala (App\Support\Rivals::questions): ikona na każde pytanie z odpowiedzią.
    Bez premium skrzyżowane miecze na tarczy (ile pytań, bez strony bonusu), premium najpierw miecze (ataki),
    potem tarcze (obrony), bez kolejności pytań. Pytania bez odpowiedzi to blade kółka.
    Po wynikach (items) każda ikona pokazuje rozstrzygnięcie: zielony znaczek = weszło, krzyżyk w kolorze strony (atak albo obrona) = pudło,
    a pozostałe trafienia strony, którą pudło wyzerowało, są przygaszone i przekreślone.
    Użycie: <x-question-sides :info="$rival['questions']" />
--}}
@props(['info'])

@php
    $items = $info['items'] ?? null;
    if ($items !== null) {
        // Najpierw ataki, potem obrony, w kolejności pytań.
        $answered = array_values(array_filter($items, fn ($item) => $item['side'] !== null));
        usort($answered, fn ($a, $b) => ($a['side'] === 'defense') <=> ($b['side'] === 'defense'));
        $icons = array_map(fn ($item) => ['side' => $item['side'], 'result' => $item['result'],
            'zeroed' => $item['result'] === 'hit' && ($info[$item['side'] . '_missed'] ?? false)], $answered);
    } else {
        $plain = fn (?string $side, int $count) => array_fill(0, $count, ['side' => $side, 'result' => null, 'zeroed' => false]);
        $icons = $info['attack'] !== null
            ? array_merge($plain('attack', $info['attack']), $plain('defense', $info['defense']))
            : $plain(null, $info['answered']);
    }
    $missing = max(0, $info['total'] - count($icons));
    $title = $info['attack'] !== null
        ? __('Questions: :answered of :total (attack :attack, defence :defence)', ['answered' => $info['answered'], 'total' => $info['total'], 'attack' => $info['attack'], 'defence' => $info['defense']])
        : __('Questions: :answered of :total', ['answered' => $info['answered'], 'total' => $info['total']]);
@endphp

<span {{ $attributes->class('inline-flex flex-wrap items-center gap-0.5') }} title="{{ $title }}" aria-label="{{ $title }}">
    @foreach ($icons as $icon)
        @php
            $miss = $icon['result'] === 'miss';
            $faded = $icon['zeroed'] || in_array($icon['result'], ['void', 'pending'], true);
        @endphp
        <span @class(['relative grid size-5 place-items-center rounded text-white', 'bg-club text-club-ink' => $icon['side'] === null, 'opacity-40' => $faded])
            @if ($icon['side'] !== null) style="background: var(--q-{{ $icon['side'] }})" @endif>
            @if ($miss)
                <x-ui.icon name="x-mark" variant="micro" class="size-4" />
            @elseif ($icon['side'] === 'attack')
                <x-ui.icon name="swords" variant="micro" class="size-3" />
            @elseif ($icon['side'] === 'defense')
                <x-ui.icon name="shield" variant="micro" class="size-3" />
            @else
                <x-ui.icon name="battle" variant="micro" class="size-3.5" />
            @endif
            @if ($icon['result'] === 'hit' && !$icon['zeroed'])
                <span class="absolute -right-1 -top-1 grid size-2.5 place-items-center rounded-full bg-emerald-500 ring-1 ring-ui-surface"><x-ui.icon name="check" variant="micro" class="size-2 text-white" /></span>
            @endif
            @if ($icon['zeroed'])
                <span class="absolute inset-x-0.5 top-1/2 h-0.5 -rotate-45 rounded bg-red-600" aria-hidden="true"></span>
            @endif
        </span>
    @endforeach
    @for ($i = 0; $i < $missing; $i++)
        <span class="size-5 rounded border border-dashed border-ui-line" aria-hidden="true"></span>
    @endfor
</span>
