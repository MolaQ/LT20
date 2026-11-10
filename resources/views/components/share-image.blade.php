{{--
    Przycisk „Obrazek na sociale” (resources/js/share-image.js). Zdjęcie obejmuje najbliższy element nadrzędny
    z atrybutem data-share-target. Tylko dla premium (Admin zawsze); zalogowany bez premium widzi kłódkę
    z linkiem do strony wsparcia, gość nie widzi nic. Kolor bierze z otoczenia, więc pasuje też na kolorowych belkach.
    Użycie: <section data-share-target> ... <x-share-image :title="__('Table')" /> ... </section>
--}}
@props(['title'])

@auth
    @php
        $premium = \App\Support\Premium::isActive(auth()->user());
        $brand = parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.name');
        // Sam znaczek w kółku (napis „Udostępnij” w podpowiedzi i dla czytników ekranu).
        $base = 'inline-flex size-8 shrink-0 items-center justify-center gap-1 rounded-full border border-current/30 text-xs transition hover:bg-current/10';
    @endphp

    @if ($premium)
        @php
            $labels = [
                'error' => __('Could not create the image. Try again.'),
                'download' => __('Download'),
                'share' => __('Share'),
                'close' => __('Close'),
                'x' => __('Post on X'),
                'xHint' => __('The image is saved: attach it to the post on X.'),
                'hashtag' => config('club.hashtag'),
                'via' => config('club.x_handle'),
            ];
        @endphp
        <button type="button" data-share-hide x-data="shareImage(@js($title), @js($brand), @js($labels))"
            @mouseenter.once="prepare()" @touchstart.passive.once="prepare()" @click="make()" :disabled="busy" title="{{ __('Image for social media') }}" aria-label="{{ __('Image for social media') }}"
            {{ $attributes->class($base) }}>
            <x-ui.icon name="share" variant="micro" class="size-4" x-show="!busy" />
            <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
        </button>
    @else
        <a href="{{ route('support') }}" wire:navigate data-share-hide title="{{ __('Image for social media – premium option') }}"
            aria-label="{{ __('Image for social media – premium option') }}" {{ $attributes->class($base.' relative opacity-80') }}>
            <x-ui.icon name="share" variant="micro" class="size-4" />
            <x-ui.icon name="lock-closed" variant="micro" class="absolute -bottom-1 -right-1 size-3.5 rounded-full bg-purple-600 p-0.5 text-white" />
        </a>
    @endif
@endauth
