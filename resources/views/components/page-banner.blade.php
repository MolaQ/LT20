{{--
    Baner strony publicznej (wzór ze strony głównej): gradient sidebara, nadtytuł, duży tytuł i podtytuł.
    Slot domyślny trafia pod podtytuł, slot „aside” po prawej stronie (np. sponsor, liczby).
    Użycie: <x-page-banner :title="__('Hall of Fame')" :subtitle="..." />
--}}
@props(['title', 'subtitle' => null, 'eyebrow' => '#LechTYPER'])

<section {{ $attributes->class('lech-banner relative overflow-hidden rounded-2xl px-5 py-6 sm:px-8 sm:py-7') }}>
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
