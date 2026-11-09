{{-- Kafelek liczby z podpisem. Liczba całkowita „nabija się” od zera (count), inne wartości pokazujemy wprost. --}}
@props(['value', 'label', 'prefix' => '', 'tone' => null])

<div {{ $attributes->class('flex flex-col gap-0.5 rounded-2xl border border-ui-line bg-ui-surface p-2.5') }}>
    @if (is_int($value))
        <b x-data="uiCountUp({{ $value }}, @js($prefix))" x-text="shown" @class(['ui-num text-[28px] leading-none', 'text-good' => $tone === 'good', 'text-bad' => $tone === 'bad'])>{{ $prefix }}{{ $value }}</b>
    @else
        <b @class(['ui-num text-[28px] leading-none', 'text-good' => $tone === 'good', 'text-bad' => $tone === 'bad'])>{{ $value }}</b>
    @endif
    <span class="text-xs text-ui-muted">{{ $label }}</span>
</div>
