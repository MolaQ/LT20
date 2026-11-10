{{--
    Stopka każdej strony (layouts::shell): statystyki (teraz na stronie, gracze, typy, kolejki sezonu)
    i linki do stron informacyjnych, w tym Biuro skarg i wniosków (z liczbą trwających głosowań).
--}}
@php
    $online = \App\Support\SiteStats::online();
    $game = \App\Support\SiteStats::game();
    $voting = \Illuminate\Support\Facades\Cache::remember('site-stats:office-voting', 300,
        fn () => \App\Models\OfficeRequest::where('status', \App\Models\OfficeRequest::VOTING)->count());
    $stats = array_filter([
        $online ? [__('Online now'), $online['online'], __(':members logged in, :guests guests', ['members' => $online['members'], 'guests' => $online['guests']])] : null,
        [__('Players'), $game['players'], null],
        [__('Tips placed'), $game['tips'], null],
        $game['season'] ? [__('Matchdays played'), $game['played'].' / '.$game['matchdays'], $game['season']] : null,
    ]);
    $links = [
        [route('rules'), __('Rules')],
        [route('faq'), __('FAQ')],
        [route('support'), __('Premium and support')],
        [route('hall-of-fame'), __('Hall of Fame')],
        [route('system'), __('System information')],
    ];
@endphp

<footer class="mt-8 border-t border-ui-line pt-6 text-sm">
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($stats as [$label, $value, $hint])
            <div class="rounded-xl bg-ui-raised/60 px-3 py-2.5">
                <dt class="text-xs text-ui-muted">{{ $label }}</dt>
                <dd class="text-lg font-semibold tabular-nums text-ui-ink">{{ is_int($value) ? number_format($value, 0, ',', ' ') : $value }}</dd>
                @if ($hint)
                    <dd class="truncate text-xs text-ui-muted">{{ $hint }}</dd>
                @endif
            </div>
        @endforeach
    </dl>

    <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <nav aria-label="{{ __('Footer') }}" class="flex flex-wrap gap-x-4 gap-y-2">
            @foreach ($links as [$href, $label])
                <a href="{{ $href }}" wire:navigate class="text-ui-muted hover:text-ui-ink hover:underline">{{ $label }}</a>
            @endforeach
        </nav>

        <a href="{{ route('office') }}" wire:navigate
            class="inline-flex shrink-0 items-center gap-2 self-start rounded-full border border-ui-line px-3 py-1.5 font-semibold text-ui-ink hover:bg-ui-raised">
            <x-ui.icon name="inbox" variant="micro" class="size-4 text-club" />
            {{ __('Complaints and requests office') }}
            @if ($voting > 0)
                <span class="rounded-full bg-club px-1.5 text-xs text-club-ink" title="{{ trans_choice(':count bot name is being voted on|:count bot names are being voted on', $voting, ['count' => $voting]) }}">{{ $voting }}</span>
            @endif
        </a>
    </div>

    <p class="mt-5 text-xs text-ui-muted">© {{ now()->year }} {{ config('app.name') }} · {{ __('An unofficial tipping game for Lech Poznań fans.') }}</p>
</footer>
