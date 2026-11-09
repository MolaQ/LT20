{{-- Menu „Więcej”: motyw, język i pozostałe strony. --}}
<x-ui.sheet name="more" :title="__('More')">
    <div x-data="uiTheme" class="flex flex-col gap-2">
        <b class="text-sm">{{ __('Theme') }}</b>
        <x-ui.segmented x-model="mode" x-init="$watch('mode', (m) => set(m))" :options="['system' => __('Phone'), 'light' => __('Light'), 'dark' => __('Dark')]" />
    </div>

    <div class="flex flex-col gap-2">
        <b class="text-sm">{{ __('Language') }}</b>
        <div class="flex gap-1 rounded-2xl bg-ui-raised p-1">
            @foreach (config('club.locales') as $code => $name)
                <form method="POST" action="{{ route('locale.switch', $code) }}" class="flex-1">
                    @csrf
                    <button type="submit" @class([
                        'w-full rounded-xl py-2.5 text-sm font-bold',
                        'bg-ui-surface shadow' => app()->getLocale() === $code,
                        'text-ui-muted' => app()->getLocale() !== $code,
                    ]) @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $name }}</button>
                </form>
            @endforeach
        </div>
    </div>

    @php
        $links = [
            [route('hall-of-fame'), __('Hall of Fame')],
            [route('history'), __('History')],
            [route('stars'), __('Stars')],
            [route('support'), __('Premium and support')],
            [route('rules'), __('Rules')],
            [route('faq'), __('FAQ')],
        ];
        if (auth()->user()?->can(\App\Enums\Permission::DashboardAccess->value)) {
            $links[] = [route('dashboard'), __('Dashboard')];
        }
    @endphp
    <div class="flex flex-col">
        @foreach ($links as [$href, $label])
            <a href="{{ $href }}" wire:navigate class="flex items-center justify-between border-b border-ui-line px-1 py-3.5 font-semibold">
                {{ $label }} <x-ui.icon name="chevron" class="size-4 text-ui-muted" />
            </a>
        @endforeach
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center justify-between px-1 py-3.5 font-semibold text-bad">{{ __('Log out') }}</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="flex items-center justify-between px-1 py-3.5 font-semibold text-club">{{ __('Log in') }}</a>
        @endauth
    </div>
</x-ui.sheet>
