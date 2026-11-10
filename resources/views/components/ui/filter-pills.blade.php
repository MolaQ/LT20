{{--
    Filtr jako klikalne przyciski (zamiast listy rozwijanej). Klik ustawia własność Livewire $model na wartość opcji.
    options: [wartość => etykieta], counts: [wartość => liczba] (opcjonalnie, liczba obok etykiety), label: podpis rzędu.
    Użycie: <x-ui.filter-pills model="status" :value="$status" :options="[...]" :counts="$this->stats" :label="__('Status')" />
--}}
@props(['model', 'value', 'options' => [], 'counts' => [], 'label' => null])

<div {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    @if ($label)
        <span class="w-20 shrink-0 text-xs font-semibold uppercase tracking-wide text-ui-muted">{{ $label }}</span>
    @endif
    @foreach ($options as $optionValue => $optionLabel)
        @php
            $selected = (string) $value === (string) $optionValue;
        @endphp
        <button type="button" wire:click="$set('{{ $model }}', @js($optionValue))" aria-pressed="{{ $selected ? 'true' : 'false' }}"
            @class([
                'flex items-center gap-2 rounded-full border px-3 py-1 text-sm transition',
                'border-lech-600 bg-lech-50 font-semibold text-club dark:bg-lech-950/50' => $selected,
                'border-ui-line hover:border-zinc-300' => ! $selected,
            ])>
            <span>{{ $optionLabel }}</span>
            @if (array_key_exists($optionValue, $counts))
                <span class="tabular-nums text-ui-muted">{{ $counts[$optionValue] }}</span>
            @endif
        </button>
    @endforeach
</div>
