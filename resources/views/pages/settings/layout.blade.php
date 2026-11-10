{{-- Układ ustawień konta w skorupie 2.0: zakładki u góry (profil, bezpieczeństwo, wygląd) i karta z treścią. --}}
@php
    $tabs = [
        ['profile.edit', __('Profile')],
        ['security.edit', __('Security')],
        ['appearance.edit', __('Appearance')],
    ];
@endphp

<div class="flex flex-col gap-4">
    <nav aria-label="{{ __('Settings') }}" class="flex gap-1 overflow-x-auto rounded-2xl bg-ui-raised p-1">
        @foreach ($tabs as [$route, $label])
            <a href="{{ route($route) }}" wire:navigate @class([
                'flex-1 whitespace-nowrap rounded-xl px-3 py-2.5 text-center text-sm font-bold transition',
                'bg-ui-surface shadow' => request()->routeIs($route),
                'text-ui-muted' => ! request()->routeIs($route),
            ])>{{ $label }}</a>
        @endforeach
    </nav>

    <x-ui.card>
        <x-ui.heading size="lg">{{ $heading ?? '' }}</x-ui.heading>
        <x-ui.text class="mt-1">{{ $subheading ?? '' }}</x-ui.text>

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </x-ui.card>
</div>
