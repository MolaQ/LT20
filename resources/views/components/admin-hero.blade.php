{{--
    Nagłówek strony panelu w stylu strony głównej: granatowy baner z poświatą w barwie klubu, tytuł, opis
    i miejsce na liczby (slot) oraz przełączniki (slot „controls”, pod banerem na jasnym tle).
    Użycie: <x-admin-hero :title="…" :subtitle="…" icon="check-badge"> liczby </x-admin-hero>
--}}
@props(['title', 'subtitle' => null, 'icon' => null, 'eyebrow' => null])

<section {{ $attributes->class('lech-banner relative overflow-hidden rounded-2xl px-5 py-5 sm:px-7 sm:py-6') }}>
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_90%_at_100%_0%,rgb(255_255_255/0.10),transparent_60%)]"></div>
    <div class="relative flex flex-col gap-5">
        <div class="flex items-start gap-4">
            @if ($icon)
                <span class="hidden size-12 shrink-0 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/15 sm:grid">
                    <x-ui.icon :name="$icon" class="size-6" />
                </span>
            @endif
            <div class="min-w-0">
                @if ($eyebrow)
                    <div class="text-[11px] font-bold uppercase tracking-widest text-lech-200">{{ $eyebrow }}</div>
                @endif
                <h1 class="font-display text-2xl font-bold leading-tight sm:text-3xl">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-1 max-w-2xl text-sm text-white/70">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @if (trim((string) $slot) !== '')
            {{ $slot }}
        @endif
    </div>
</section>
