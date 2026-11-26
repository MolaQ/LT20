{{-- Liczba w banerze panelu (x-admin-hero): wartość, podpis i opcjonalny pasek postępu (0–100). --}}
@props(['value', 'label', 'percent' => null, 'tone' => null])

<div {{ $attributes->class('flex min-w-0 flex-col gap-1 rounded-xl bg-white/[0.07] px-4 py-3 ring-1 ring-white/10') }}>
    <span class="text-[11px] font-semibold uppercase tracking-wider text-white/60">{{ $label }}</span>
    <b @class(['font-display text-2xl leading-none tabular-nums sm:text-3xl', 'text-amber-300' => $tone === 'warn', 'text-emerald-300' => $tone === 'good'])>{{ $value }}</b>
    @if ($percent !== null)
        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/10">
            <i class="block h-full rounded-full bg-linear-to-r from-emerald-400 to-emerald-300 transition-[width] duration-500" style="width: {{ max(0, min(100, (int) $percent)) }}%"></i>
        </div>
    @endif
</div>
