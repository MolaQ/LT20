{{-- Błąd walidacji pola (zamiennik flux:error). --}}
@props(['name'])

@error($name)<p {{ $attributes->class('text-xs font-medium text-bad') }}>{{ $message }}</p>@enderror
