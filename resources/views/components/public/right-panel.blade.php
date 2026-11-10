<div class="space-y-4">
    {{-- Strona wyników: mecz Lecha wybranej kolejki na samej górze (okno ze szczegółami typów). --}}
    @if (request()->routeIs('results'))
        <livewire:pages::home.lech-match-panel />
    @endif
    {{-- Rywale gracza w kolejce: nad informacją o premium (tylko zalogowani gracze z zespołem). --}}
    @auth
        <livewire:pages::home.rivals-panel />
    @endauth
    {{-- 3 najnowsze newsy: miniatura (pierwszy obrazek z treści albo data), tytuł, kiedy i początek treści. --}}
    @php
        $latestNews = \Illuminate\Support\Facades\Schema::hasTable('news')
            ? \App\Models\News::published()->latest('published_at')->limit(3)->get(['id', 'title', 'body', 'published_at'])
            : collect();
    @endphp
    <section class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
        <header class="flex items-center justify-between gap-2 px-4 pb-2 pt-3">
            <h2 class="text-sm font-semibold text-ui-ink">{{ __('News') }}</h2>
            <a href="{{ route('home') }}#news" wire:navigate class="text-xs font-medium text-club hover:underline">{{ __('All news') }}</a>
        </header>
        @forelse ($latestNews as $item)
            @php
                $thumb = \App\Support\NewsHtml::firstImage($item->body);
            @endphp
            <a href="{{ route('news.show', $item) }}" wire:navigate
                class="group flex gap-3 border-t border-ui-line px-4 py-3 transition hover:bg-ui-raised/60">
                @if ($thumb)
                    <img src="{{ $thumb }}" alt="" loading="lazy" class="size-12 shrink-0 rounded-lg object-cover">
                @else
                    <span class="flex size-12 shrink-0 flex-col items-center justify-center rounded-lg bg-lech-50 text-club dark:bg-lech-950/60">
                        <span class="text-base font-semibold leading-none tabular-nums">{{ $item->published_at->format('d') }}</span>
                        <span class="mt-0.5 text-[10px] uppercase leading-none">{{ $item->published_at->translatedFormat('M') }}</span>
                    </span>
                @endif
                <span class="min-w-0 flex-1">
                    <span class="line-clamp-2 text-sm font-medium leading-snug text-ui-ink group-hover:text-club">{{ $item->title }}</span>
                    <span class="mt-0.5 block text-xs text-ui-muted">{{ $item->published_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <p class="border-t border-ui-line px-4 py-3 text-sm text-ui-muted">{{ __('Latest announcements will appear here.') }}</p>
        @endforelse
    </section>
    <hr class="border-ui-line">
    <x-public.premium-card />
</div>
