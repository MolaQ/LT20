{{-- Podtytuł (zamiennik flux:subheading). --}}
@props(['size' => null])

<div {{ $attributes->class([$size === 'lg' ? 'text-base' : 'text-sm', 'text-ui-muted']) }}>{{ $slot }}</div>
