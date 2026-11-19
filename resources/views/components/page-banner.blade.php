{{--
    Baner strony publicznej (wzór ze strony głównej): gradient sidebara, nadtytuł, duży tytuł i podtytuł.
    Slot domyślny trafia pod podtytuł, slot „aside” po prawej stronie (np. sponsor, liczby).
    image = grafika w tle (baner sponsora sezonu), przyciemniona od lewej, żeby tekst był czytelny.
    Użycie: <x-page-banner :title="__('Hall of Fame')" :subtitle="..." />
--}}
@props(['title', 'subtitle' => null, 'eyebrow' => '#LechTYPER', 'image' => null])

<section {{ $attributes->class(['lech-banner relative overflow-hidden rounded-2xl px-5 py-6 sm:px-8 sm:py-7', 'flex min-h-48 flex-col justify-end sm:min-h-56' => filled($image)]) }}>
    @if (filled($image))
        <img src="{{ $image }}" alt="" class="absolute inset-0 size-full object-cover" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent md:bg-gradient-to-r md:from-black/75 md:via-black/30 md:to-black/10"></div>
    @endif
    <div class="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0 space-y-2">
            @if (filled($eyebrow))
                <p class="text-xs font-semibold uppercase tracking-wider text-lech-200">{{ $eyebrow }}</p>
            @endif
            <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">{{ $title }}</h1>
            @if (filled($subtitle))
                <p class="max-w-3xl text-sm text-lech-100 sm:text-[15px]">{{ $subtitle }}</p>
            @endif
            {{ $slot }}
        </div>

        @if (isset($aside) && $aside->isNotEmpty())
            <div class="shrink-0">{{ $aside }}</div>
        @endif
    </div>
</section>
