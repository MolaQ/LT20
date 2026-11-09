{{-- Pole wielowierszowe (zamiennik flux:textarea). label; wire:model i reszta atrybutów trafiają do <textarea>. --}}
@props(['label' => null])

@php
    $model = $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
@endphp

<label {{ $attributes->only('class')->class('flex flex-col gap-1') }}>
    @if ($label)<span class="text-sm font-semibold">{{ $label }}</span>@endif
    <textarea {{ $attributes->except('class')->class('w-full rounded-xl border border-ui-line bg-ui-surface px-3 py-2 text-base text-ui-ink placeholder:text-ui-muted focus:border-club focus:outline-none focus:ring-2 focus:ring-club/30') }}>{{ $slot }}</textarea>
    @if ($model)
        @error($model)<span class="text-xs text-bad">{{ $message }}</span>@enderror
    @endif
</label>
