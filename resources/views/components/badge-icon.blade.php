{{--
    Ikona odznaki (App\Support\Badges): koło w kolorze stopnia z symbolem; niezdobyta jest szara i przygaszona.
    Użycie: <x-badge-icon badge="prophet" :tier="2" icon="eye" class="size-12" />
--}}
@props(['badge', 'tier' => 0, 'icon'])

@php
    $color = $tier > 0 ? \App\Support\Badges::color($badge, $tier) : null;
@endphp

<span {{ $attributes->class(['grid shrink-0 place-items-center rounded-full ring-2', 'bg-ui-raised text-ui-muted opacity-60 ring-ui-line' => !$color, 'text-white shadow-md ring-white/60' => (bool) $color]) }}
    @if ($color) style="background: radial-gradient(circle at 30% 25%, rgba(255,255,255,.45), transparent 55%), {{ $color }}" @endif>
    <x-ui.icon :name="$icon" variant="solid" class="size-1/2" />
</span>
