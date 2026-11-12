{{--
    Stopka każdej strony (layouts::shell), w stylu jokerpila.pl: ciemny granat na całą szerokość. U góry logo z hasłem,
    linki „Na skróty” i Biuro skarg i wniosków; na dole prawa autorskie, licznik odwiedzin (pages::home.visit-counter,
    odświeżany co 15 s) i podpis autora strony (config/club.php: footer_author).
--}}
@php
    $voting = \Illuminate\Support\Facades\Cache::remember('site-stats:office-voting', 300,
        fn () => \App\Models\OfficeRequest::where('status', \App\Models\OfficeRequest::VOTING)->count());
    $brand = config('club.brand.prefix').config('club.brand.suffix');
    $links = [
        [route('rules'), __('Rules')],
        [route('faq'), __('FAQ')],
        [route('support'), __('Premium and support')],
        [route('hall-of-fame'), __('Hall of Fame')],
        [route('history'), __('History')],
        [route('stars'), __('Stars')],
        [route('system'), __('System information')],
    ];
@endphp

<footer class="relative mt-10 overflow-hidden text-sm text-white/70"
    style="background-image: linear-gradient(160deg, var(--theme-bar-from, #0b1f4d), var(--theme-bar-to, #061331) 60%, color-mix(in srgb, var(--theme-bar-to, #061331) 35%, #000))">
    <div class="mx-auto max-w-6xl 2xl:max-w-[1680px] min-[2400px]:max-w-[2200px] px-3.5 pb-28 pt-10 lg:px-6 lg:pb-8">
        <div class="grid gap-10 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <div class="flex items-center gap-4">
                <img src="{{ asset(config('club.logo')) }}" alt="" width="56" height="56" loading="lazy" class="size-14 object-contain">
                <div>
                    <p class="text-2xl font-semibold text-white">{{ $brand }}</p>
                    <p class="text-sm">{{ __('An unofficial tipping game for Lech Poznań fans.') }}</p>
                </div>
            </div>

            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-white/50">{{ __('Shortcuts') }}</p>
                <ul class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                    @foreach ($links as [$href, $label])
                        <li><a href="{{ $href }}" wire:navigate class="inline-block py-0.5 transition hover:text-white">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-white/50">{{ __('Contact') }}</p>
                <a href="{{ route('office') }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-2 font-medium text-white ring-1 ring-white/15 transition hover:bg-white/15">
                    <x-ui.icon name="inbox" variant="micro" class="size-4" />
                    {{ __('Complaints and requests office') }}
                    @if ($voting > 0)
                        <span class="rounded-full bg-white px-1.5 text-xs font-semibold text-[#0b1f4d]"
                            title="{{ trans_choice(':count bot name is being voted on|:count bot names are being voted on', $voting, ['count' => $voting]) }}">{{ $voting }}</span>
                    @endif
                </a>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-4 border-t border-white/10 pt-6 text-xs sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <p><span class="text-white/90">&copy; {{ now()->year }} {{ $brand }}</span></p>
                @if (filled(config('club.footer_author')))
                    <p>{{ __('Design and development') }}:
                        @if (filled(config('club.footer_author_url')))
                            <a href="{{ config('club.footer_author_url') }}" target="_blank" rel="noopener" class="underline hover:text-white">{{ config('club.footer_author') }}</a>
                        @else
                            {{ config('club.footer_author') }}
                        @endif
                    </p>
                @endif
            </div>
            <livewire:pages::home.visit-counter />
        </div>
    </div>
</footer>
