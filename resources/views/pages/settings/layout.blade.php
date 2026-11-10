{{--
    Układ ustawień konta w skorupie 2.0: zakładki u góry (profil, bezpieczeństwo, powiadomienia, wygląd) i karta z treścią.
    panel: ustawienia otwarte z panelu admina (trasy dashboard.settings.*), wtedy zakładki zostają w panelu.
--}}
@props(['heading' => '', 'subheading' => '', 'panel' => false])

@php
    $tabs = $panel
        ? [
            ['dashboard.settings.profile', __('Profile')],
            ['dashboard.settings.security', __('Security')],
            ['dashboard.settings.notifications', __('Notifications')],
            ['dashboard.settings.appearance', __('Appearance')],
        ]
        : [
            ['profile.edit', __('Profile')],
            ['security.edit', __('Security')],
            ['notifications.edit', __('Notifications')],
            ['appearance.edit', __('Appearance')],
        ];
    // Aktywna zakładka: adres strony (przy żądaniach Livewire to adres, z którego przyszło żądanie).
    $current = strtok(request()->routeIs('livewire.*') ? url()->previous() : url()->current(), '?');
@endphp

<div class="flex flex-col gap-4">
    <nav aria-label="{{ __('Settings') }}" class="flex gap-1 overflow-x-auto rounded-2xl bg-ui-raised p-1">
        @foreach ($tabs as [$route, $label])
            <a href="{{ route($route) }}" wire:navigate @class([
                'flex-1 whitespace-nowrap rounded-xl px-3 py-2.5 text-center text-sm font-bold transition',
                'bg-ui-surface shadow' => $current === route($route),
                'text-ui-muted' => $current !== route($route),
            ])>{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Niepotwierdzony e-mail: kod wpisuje się tutaj, bez wychodzenia ze strony. --}}
    @if (auth()->user() && ! auth()->user()->hasVerifiedEmail())
        <x-ui.card class="border border-amber-300 bg-amber-50/60 dark:border-amber-500/40 dark:bg-amber-500/10">
            <livewire:pages::auth.verify-code compact :back="request()->url()" />
        </x-ui.card>
    @elseif (session('status') === 'email-verified')
        <x-ui.card class="border border-green-300 bg-green-50/60 dark:border-green-500/40 dark:bg-green-500/10">
            <x-ui.text class="font-semibold text-good">{{ __('Your email address is confirmed. You can tip now.') }}</x-ui.text>
        </x-ui.card>
    @endif

    <x-ui.card>
        <x-ui.heading size="lg">{{ $heading ?? '' }}</x-ui.heading>
        <x-ui.text class="mt-1">{{ $subheading ?? '' }}</x-ui.text>

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </x-ui.card>
</div>
