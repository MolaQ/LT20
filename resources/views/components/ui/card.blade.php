{{-- Karta 2.0. hero = karta w barwach klubu z poświatą (mecz, najważniejsza informacja ekranu). --}}
@props(['hero' => false])

<section {{ $attributes->class([
    'relative overflow-hidden rounded-[20px] p-4',
    'ui-hero' => $hero,
    'border border-ui-line bg-ui-surface' => ! $hero,
]) }}>
    @if ($hero)
        <span aria-hidden="true" class="pointer-events-none absolute -end-16 -top-20 size-60 rounded-full bg-[radial-gradient(circle,rgba(160,200,255,.35),transparent_70%)]"></span>
    @endif
    <div class="relative">{{ $slot }}</div>
</section>
