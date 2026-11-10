{{--
    Menu „Więcej”: strony jako kafelki z ikonami (siatka bez przewijania), konto, motyw (małe przyciski z ikonami),
    instalacja aplikacji i na samym dole język (przyciski z flagami, zmienia się rzadko).
    Na telefonie pod spodem panel z rywalami i newsami ($extra). „Panel” widzą tylko osoby z dostępem do panelu.
--}}
@props(['extra' => null])

@php
    $links = [
        [route('my-stats'), 'chart-bar', __('My stats')],
        [route('hall-of-fame'), 'trophy', __('Hall of Fame')],
        [route('history'), 'clock', __('History')],
        [route('stars'), 'star', __('Stars')],
        [route('support'), 'sparkles', __('Premium and support')],
        [route('rules'), 'book-open', __('Rules')],
        [route('faq'), 'question-mark-circle', __('FAQ')],
        [route('office'), 'inbox', __('Complaints and requests office')],
    ];
    if (auth()->check()) {
        $links[] = [route('profile.edit'), 'cog', __('Settings')];
    }
    if (auth()->user()?->can(\App\Enums\Permission::DashboardAccess->value)) {
        $links[] = [route('dashboard'), 'squares-2x2', __('Dashboard')];
    }
    $themes = ['system' => ['device-phone-mobile', __('Phone')], 'light' => ['sun', __('Light')], 'dark' => ['moon', __('Dark')]];
@endphp

<x-ui.sheet name="more" :title="__('More')">
    <nav class="grid grid-cols-2 gap-2 sm:grid-cols-3" aria-label="{{ __('More') }}">
        @foreach ($links as [$href, $icon, $label])
            <a href="{{ $href }}" wire:navigate
                class="group flex items-center gap-2.5 rounded-xl border border-ui-line px-3 py-2.5 text-sm font-medium transition hover:border-club/40 hover:bg-ui-raised">
                <x-ui.icon :name="$icon" variant="mini" class="size-5 text-club transition-transform duration-200 motion-safe:group-hover:scale-110" />
                <span class="leading-tight">{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Konto: wylogowanie albo logowanie i rejestracja. --}}
    <div class="flex flex-wrap items-center gap-2">
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.btn type="submit" size="sm" variant="ghost" icon="arrow-right-start-on-rectangle" class="text-bad">{{ __('Log out') }}</x-ui.btn>
            </form>
        @else
            <x-ui.btn size="sm" variant="primary" icon="arrow-right-end-on-rectangle" :href="route('login')">{{ __('Log in') }}</x-ui.btn>
            @if (Route::has('register'))
                <x-ui.btn size="sm" icon="user-plus" :href="route('register')">{{ __('Register') }}</x-ui.btn>
            @endif
        @endauth
    </div>

    {{-- Instalacja na ekranie telefonu: Android/Chrome przez okno przeglądarki, iPhone przez „Udostępnij”. --}}
    <div x-data="uiInstall" x-show="canInstall || ios" x-cloak class="flex items-center gap-3 rounded-xl bg-ui-raised/70 px-3 py-2.5">
        <img src="{{ asset('images/icon-192.png') }}" alt="" class="size-9 rounded-lg">
        <div class="min-w-0 flex-1 text-xs">
            <span class="block text-sm font-semibold">{{ __('Install the app') }}</span>
            <span x-show="ios" class="text-ui-muted">{{ __('Tap Share, then “Add to Home Screen”.') }}</span>
            <span x-show="!ios" class="text-ui-muted">{{ __('One tap from your home screen, works offline.') }}</span>
        </div>
        <x-ui.btn x-show="canInstall" size="sm" variant="primary" @click="install()">{{ __('Install') }}</x-ui.btn>
    </div>

    {{-- Motyw i język w jednym rzędzie na dole: zmienia się je rzadko. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ui-line pt-3">
        <div x-data="uiTheme" class="flex items-center gap-1" role="radiogroup" aria-label="{{ __('Theme') }}">
            @foreach ($themes as $mode => [$icon, $label])
                <button type="button" role="radio" @click="set(@js($mode))" :aria-checked="mode === @js($mode)"
                    title="{{ $label }}" aria-label="{{ $label }}"
                    class="flex size-9 items-center justify-center rounded-lg transition"
                    :class="mode === @js($mode) ? 'bg-ui-raised text-club' : 'text-ui-muted hover:bg-ui-raised hover:text-ui-ink'">
                    <x-ui.icon :name="$icon" variant="mini" class="size-5" />
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-1" aria-label="{{ __('Language') }}">
            @foreach (config('club.locales') as $code => $name)
                <form method="POST" action="{{ route('locale.switch', $code) }}">
                    @csrf
                    <button type="submit" title="{{ $name }}" @if (app()->getLocale() === $code) aria-current="true" @endif
                        @class([
                            'flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition',
                            'bg-ui-raised text-ui-ink' => app()->getLocale() === $code,
                            'text-ui-muted hover:bg-ui-raised hover:text-ui-ink' => app()->getLocale() !== $code,
                        ])>
                        <x-flag :code="$code" />{{ strtoupper($code) }}
                    </button>
                </form>
            @endforeach
        </div>
    </div>

    @if ($extra)
        <div class="xl:hidden">{{ $extra }}</div>
    @endif
</x-ui.sheet>
