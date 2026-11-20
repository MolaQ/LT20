{{--
    Grafika kolejki w panelu (Promocja → Grafiki kolejki): obrazek w klubowym stylu do mediów społecznościowych.
    Zdjęcie robi przycisk x-share-image z data-share-size (1080 px szerokości, bez stopki). Format (kwadrat albo story)
    pochodzi z Alpine `fmt` na stronie. „Tekst” kopiuje gotowy opis posta.
    Użycie: <x-graphic-card :title="…" :when="…" :text="…" :badge="…"> treść grafiki </x-graphic-card>
--}}
@props(['title', 'when', 'text' => '', 'badge' => '', 'background' => null])

<div class="flex flex-col gap-2 rounded-xl border border-ui-line bg-ui-surface p-3">
    <div class="flex items-center justify-between gap-2 text-sm font-semibold">
        <span>{{ $title }}</span>
        <span class="text-xs font-normal text-ui-muted">{{ $when }}</span>
    </div>

    <div data-share-target data-share-size="1080"
        class="relative overflow-hidden rounded-lg text-white"
        :class="fmt === 'story' ? 'aspect-[9/16]' : 'aspect-square'"
        style="background: {{ $background ?? 'linear-gradient(135deg, #061331 0%, #0b1f4d 45%, #1740b0 100%)' }}">
        {{ $slot }}
        <div class="absolute right-2 bottom-2" data-share-hide>
            <x-share-image :title="$title" class="bg-black/40 text-white" />
        </div>
    </div>

    @if (filled($text))
        <button type="button" class="rounded-md border border-ui-line px-2 py-1.5 text-xs hover:bg-ui-raised"
            x-data="{ done: false }"
            @click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($text) }}).then(() => { done = true; setTimeout(() => done = false, 2000) })">
            <span x-show="!done">{{ __('Copy the post text') }}</span>
            <span x-show="done" x-cloak>{{ __('Copied') }}</span>
        </button>
    @endif
    @if (filled($badge))
        <div class="text-xs text-ui-muted">{{ $badge }}</div>
    @endif
</div>
