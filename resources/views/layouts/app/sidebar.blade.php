{{--
    Panel admina w wyglądzie 2.0 (bez Flux): boczne menu (na telefonie wysuwane), treść, dymki i okno potwierdzenia skorupy.
    Menu według kolejności pracy: sezon i jego kolejki (mecz, typy, wynik), rozgrywki, gracze, treści,
    a na końcu rzadko zmieniane ustawienia. Kalendarz Lecha to zakładka „Kolejek”, lista kontrolna jest na pulpicie.
--}}
@php
    $user = auth()->user();

    // Co widać w grupach menu (pusta grupa się nie pokazuje).
    $canSeasons = $user->can(\App\Enums\Permission::SeasonList->value);
    $canRoles = $user->hasRole('Admin');
    $canRequests = $user->can(\App\Enums\Permission::TeamChangeName->value);
    $canLogs = $user->can(\App\Enums\Permission::LogView->value);
    $canNews = $user->can(\App\Enums\Permission::NewsCreate->value);
    $office = $canRoles ? \App\Models\OfficeRequest::pending()->count() : 0;
    $requests = $canRequests ? \App\Models\TeamNameChangeRequest::pending()->count() : 0;

    // Powiadomienia (pilne i ostrzeżenia) jako plakietka przy Pulpicie.
    $alerts = $canSeasons ? \App\Support\AdminAlerts::count() : 0;

    // Pozycja: [ikona, trasa, etykieta, aktywna dla tras, plakietka, kolor plakietki, widoczna].
    $item = fn (string $icon, string $route, string $label, array $active = [], int $badge = 0, string $tone = 'amber', bool $show = true) => compact('icon', 'route', 'label', 'active', 'badge', 'tone', 'show');

    $groups = [
        [__('Season'), $canSeasons, [
            $item('sparkles', 'dashboard.matchday-wizard', __('Matchday wizard')),
            $item('calendar-days', 'dashboard.seasons', __('Seasons')),
            $item('calendar', 'dashboard.matchdays', __('Matchdays'), ['dashboard.matchdays', 'dashboard.lech-calendar']),
            $item('users', 'dashboard.lech-squad', __('Lech squad')),
            $item('check-badge', 'dashboard.tips', __('Tips overview')),
            $item('question-mark-circle', 'dashboard.questions', __('Questions')),
            $item('flag', 'dashboard.results', __('Results')),
        ]],
        [__('Competitions'), $canSeasons, [
            $item('list-bullet', 'dashboard.season-teams', __('Team list')),
            $item('trophy', 'dashboard.competitions', __('Competitions')),
            $item('table-cells', 'dashboard.fixtures', __('Fixtures')),
            $item('globe-europe-africa', 'dashboard.european-qualification', __('European qualification')),
        ]],
        [__('Players'), $canRoles || $canRequests, [
            $item('users', 'dashboard.users', __('Users'), show: $canRoles),
            $item('identification', 'dashboard.team-requests', __('Team requests'), badge: $requests, show: $canRequests),
            $item('banknotes', 'dashboard.payments', __('Payments'), show: $canRoles),
        ]],
        [__('Site content'), $canNews || $canRoles, [
            $item('newspaper', 'dashboard.news', __('News'), show: $canNews),
            $item('book-open', 'dashboard.guide', __('Rules and FAQ'), show: $canNews),
            $item('chart-bar', 'dashboard.site-statistics', __('Visit statistics'), show: $canRoles),
            $item('inbox', 'dashboard.office', __('Complaints and requests office'), badge: $office, show: $canRoles),
        ]],
        [__('Game settings'), $canSeasons || $canRoles || $canLogs, [
            $item('star', 'dashboard.hall-of-fame', __('Hall of Fame'), show: $canSeasons),
            $item('user-group', 'dashboard.designation-settings', __('Line-ups'), show: $canSeasons),
            $item('cpu-chip', 'dashboard.bots', __('Bots'), show: $canSeasons),
            $item('megaphone', 'dashboard.sponsors', __('Sponsors'), show: $canSeasons),
            $item('swatch', 'dashboard.competition-colors', __('Competition colors'), show: $canSeasons),
            $item('paint-brush', 'dashboard.site-colors', __('Site colors'), show: $canRoles),
            $item('wrench-screwdriver', 'dashboard.maintenance', __('Maintenance mode'), show: $canRoles),
            $item('arrow-up-tray', 'dashboard.site-update', __('Site update'), show: $canRoles),
            $item('shield-check', 'dashboard.roles', __('Roles and permissions'), show: $canRoles),
            $item('clipboard-document-list', 'dashboard.logs', __('Change log'), show: $canLogs),
        ]],
    ];

    $isActive = fn (array $i) => request()->routeIs(...($i['active'] ?: [$i['route']]));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
    @include('partials.club-theme')
</head>

<body class="min-h-screen bg-ui-bg font-sans text-ui-ink antialiased" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <x-maintenance-notice />

    @php
        // Menu boczne w granacie „Bułgarska nocą” (te same kolory co belki strony, z panelu „Kolory strony”).
        $link = fn (bool $active) => [
            'flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-sm font-medium transition',
            $active ? 'bg-white/15 font-semibold text-white shadow-sm ring-1 ring-white/10' : 'text-white/70 hover:bg-white/10 hover:text-white',
        ];
        // Nazwa bieżącego działu do górnego paska (pozycja menu z aktywną trasą).
        $current = collect($groups)->flatMap(fn ($g) => $g[2])->first(fn ($i) => $i['show'] && $isActive($i));
        $currentLabel = $current['label'] ?? (request()->routeIs('dashboard.settings.*') ? __('Settings') : __('Dashboard'));
        $currentIcon = $current['icon'] ?? (request()->routeIs('dashboard.settings.*') ? 'cog' : 'home');
    @endphp

    {{-- Tło pod wysuniętym menu (telefon) --}}
    <div x-show="nav" x-cloak x-transition.opacity @click="nav = false" class="fixed inset-0 z-40 bg-black/45 lg:hidden"></div>

    <div class="lg:flex">
        <aside :class="nav ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'"
            style="background-image: linear-gradient(180deg, var(--theme-bar-from, #0b1f4d), var(--theme-bar-to, #061331) 70%, color-mix(in srgb, var(--theme-bar-to, #061331) 40%, #000)); color: #fff;"
            class="fixed inset-y-0 start-0 z-50 flex w-72 -translate-x-full flex-col gap-3 overflow-y-auto p-3 transition-transform duration-200 [scrollbar-width:thin] lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-64 lg:translate-x-0">
            <div class="flex items-center justify-between px-1 pt-1">
                <x-lechtyper-logo :href="route('dashboard')" class="[&_.text-blue-500]:text-blue-300" />
                <button type="button" class="grid size-9 place-items-center rounded-xl text-white/70 hover:bg-white/10 lg:hidden" @click="nav = false" aria-label="{{ __('Close') }}">
                    <x-ui.icon name="x-mark" class="size-5" />
                </button>
            </div>

            <nav class="flex flex-col gap-1" aria-label="{{ __('Dashboard') }}">
                <a href="{{ route('dashboard') }}" wire:navigate @class($link(request()->routeIs('dashboard', 'dashboard.checklist')))>
                    <x-ui.icon name="home" class="size-5" />
                    <span class="flex-1">{{ __('Dashboard') }}</span>
                    @if ($alerts)<span class="rounded-full bg-bad px-1.5 text-xs font-bold text-white">{{ $alerts }}</span>@endif
                </a>

                @foreach ($groups as [$heading, $visible, $items])
                    @continue(! $visible)
                    @php
                        $items = array_filter($items, fn ($i) => $i['show']);
                        $open = $loop->first || collect($items)->contains(fn ($i) => $isActive($i));
                    @endphp
                    <details class="group mt-1" @if ($open) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-white/45 hover:text-white/80 [&::-webkit-details-marker]:hidden">
                            {{ $heading }}
                            <x-ui.icon name="chevron-down" variant="micro" class="size-3.5 transition group-open:rotate-180" />
                        </summary>
                        <div class="mt-0.5 flex flex-col gap-0.5">
                            @foreach ($items as $i)
                                <a href="{{ route($i['route']) }}" wire:navigate @class($link($isActive($i)))>
                                    <x-ui.icon :name="$i['icon']" class="size-5" />
                                    <span class="flex-1">{{ $i['label'] }}</span>
                                    @if ($i['badge'])<span class="rounded-full bg-gold-soft px-1.5 text-xs font-bold text-gold">{{ $i['badge'] }}</span>@endif
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </nav>

            <div class="mt-auto flex flex-col gap-1 border-t border-white/10 pt-3">
                <a href="{{ route('home') }}" @class($link(false))>
                    <x-ui.icon name="globe-alt" class="size-5" />{{ __('Public site') }}
                </a>
                <a href="{{ route('dashboard.settings.profile') }}" wire:navigate @class($link(request()->routeIs('dashboard.settings.*')))>
                    <x-ui.icon name="cog" class="size-5" />{{ __('Settings') }}
                </a>
                <div class="flex items-center gap-2.5 rounded-xl px-2.5 py-2">
                    <x-ui.avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                    <div class="grid min-w-0 flex-1 text-sm leading-tight">
                        <span class="truncate font-semibold">{{ $user->name }}</span>
                        <span class="truncate text-xs text-white/55">{{ $user->email }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="grid size-8 place-items-center rounded-lg text-white/60 hover:bg-white/10 hover:text-white" data-test="logout-button"
                            title="{{ __('Log out') }}" aria-label="{{ __('Log out') }}">
                            <x-ui.icon name="arrow-right-start-on-rectangle" variant="mini" class="size-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            {{-- Pasek na telefonie: menu i znak --}}
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-ui-line bg-ui-surface/95 px-4 py-2.5 backdrop-blur lg:hidden">
                <button type="button" class="grid size-9 place-items-center rounded-xl hover:bg-ui-raised" @click="nav = true" aria-label="{{ __('Menu') }}">
                    <x-ui.icon name="bars-2" class="size-5" />
                </button>
                <x-lechtyper-logo :href="route('dashboard')" />
            </header>

            {{-- Górny pasek na komputerze: bieżący dział, strona publiczna, konto. --}}
            <div class="sticky top-0 z-30 hidden items-center gap-3 border-b border-ui-line bg-ui-bg/90 px-8 py-3 backdrop-blur lg:flex">
                <span class="grid size-8 place-items-center rounded-lg bg-club/10 text-club"><x-ui.icon :name="$currentIcon" variant="mini" class="size-4" /></span>
                <div class="min-w-0 flex-1 text-sm">
                    <span class="text-ui-muted">{{ __('Dashboard') }}</span>
                    <span class="mx-1 text-ui-muted" aria-hidden="true">/</span>
                    <span class="font-semibold">{{ $currentLabel }}</span>
                </div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 rounded-full border border-ui-line px-3 py-1.5 text-xs font-medium text-ui-muted transition hover:bg-ui-raised hover:text-ui-ink">
                    <x-ui.icon name="globe-alt" variant="micro" class="size-4" />{{ __('Public site') }}
                </a>
                <a href="{{ route('dashboard.settings.profile') }}" wire:navigate title="{{ $user->name }}">
                    <x-ui.avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                </a>
            </div>

            <main class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-ui.toast />
    <x-ui.confirm />
</body>

</html>
