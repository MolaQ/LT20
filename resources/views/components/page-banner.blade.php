{{--
    Baner strony publicznej (wzór ze strony głównej): gradient sidebara, nadtytuł, duży tytuł i podtytuł.
    Slot domyślny trafia pod podtytuł, slot „aside” po prawej stronie (np. sponsor, liczby).
    image = grafika w tle (baner sponsora sezonu), przyciemniona od lewej, żeby tekst był czytelny.
    slides = banery partnerów z rotatora (BannerCampaign, miejsce „season”): zmieniają się same w tle, cała grafika
    jest linkiem do partnera (przez licznik kliknięć), a grafika z „image” dołącza do kolejki jako ostatnia.
    Użycie: <x-page-banner :title="__('Hall of Fame')" :subtitle="..." />
--}}
@props(['title', 'subtitle' => null, 'eyebrow' => '#LechTYPER', 'image' => null, 'slides' => null])

@php
    $items = collect($slides ?? [])->map(fn (\App\Models\BannerCampaign $c) => [
        'id' => $c->id,
        'src' => $c->imageUrl(),
        'href' => route('banner.click', $c),
        'caption' => collect([$c->name, $c->title])->filter()->implode(' · '),
    ]);
    if (filled($image)) {
        $items->push(['id' => null, 'src' => $image, 'href' => null, 'caption' => '']);
    }
    $rotator = $items->contains(fn ($i) => $i['id'] !== null);
@endphp

<section {{ $attributes->class(['lech-banner relative overflow-hidden rounded-2xl px-5 py-6 sm:px-8 sm:py-7', 'flex min-h-48 flex-col justify-end sm:min-h-56' => $items->isNotEmpty()]) }}
    @if ($rotator) x-data="bannerRotator(@js(['slides' => $items->map(fn ($i) => ['id' => $i['id']])->values(), 'viewsUrl' => route('banner.views'), 'token' => csrf_token()]))"
        @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false" @endif>
    @if ($rotator)
        @foreach ($items as $i => $item)
            @if ($item['href'])
                <a href="{{ $item['href'] }}" target="_blank" rel="noopener sponsored" aria-label="{{ $item['caption'] }}"
            @else
                <div
            @endif
                @class(['absolute inset-0 transition-opacity duration-700 motion-reduce:transition-none', 'opacity-0 pointer-events-none' => $i > 0])
                :class="index === {{ $i }} ? 'opacity-100' : 'opacity-0 pointer-events-none'" @if ($i > 0) aria-hidden="true" :aria-hidden="index !== {{ $i }}" @endif>
                <img src="{{ $item['src'] }}" alt="" class="size-full object-cover" @if ($i > 0) loading="lazy" @endif>
            @if ($item['href'])
                </a>
            @else
                </div>
            @endif
        @endforeach
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent md:bg-gradient-to-r md:from-black/75 md:via-black/30 md:to-black/10"></div>

        {{-- Kto jest na banerze i przełączniki banerów --}}
        <div class="absolute end-3 top-3 flex items-center gap-2 rounded-full bg-black/45 px-3 py-1 text-[11px] font-medium text-white/90 backdrop-blur">
            @foreach ($items as $i => $item)
                @if (filled($item['caption']))
                    <span x-show="index === {{ $i }}" @if ($i > 0) x-cloak @endif class="max-w-52 truncate sm:max-w-80">{{ $item['caption'] }}</span>
                @endif
            @endforeach
            @if ($items->count() > 1)
                <span class="flex gap-1">
                    @foreach ($items as $i => $item)
                        <button type="button" @click="go({{ $i }})" class="size-2 rounded-full bg-white/40 transition"
                            :class="index === {{ $i }} && '!bg-white'" aria-label="{{ __('Banner :number', ['number' => $i + 1]) }}"></button>
                    @endforeach
                </span>
            @endif
        </div>
    @elseif ($items->isNotEmpty())
        <img src="{{ $items->first()['src'] }}" alt="" class="absolute inset-0 size-full object-cover" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent md:bg-gradient-to-r md:from-black/75 md:via-black/30 md:to-black/10"></div>
    @endif
    <div @class(['relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between', 'pointer-events-none [&_a]:pointer-events-auto [&_button]:pointer-events-auto' => $rotator])>
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
