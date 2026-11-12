{{-- Dolny pasek aplikacji: Start, Wyniki, duże środkowe Typuj, Mój klub, Więcej (arkusz). --}}
@props(['items'])

<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-ui-line bg-[var(--ui-nav)] backdrop-blur-md" aria-label="{{ __('Main menu') }}">
    <div class="mx-auto flex max-w-xl items-end justify-around px-1.5 pb-[calc(.75rem+env(safe-area-inset-bottom))] pt-2">
        @foreach ($items as $item)
            @php($tag = $item['href'] ? 'a' : 'button')
            <{{ $tag }} @if ($item['href']) href="{{ $item['href'] }}" wire:navigate @click="window.ui.vibrate()" @else type="button" @click="window.ui.vibrate(); $dispatch('ui-sheet', { name: 'more' })" @endif
                @if ($item['active']) aria-current="page" @endif
                @class([
                    'relative flex w-16 flex-col items-center gap-0.5 text-[11px] font-semibold',
                    'text-club' => $item['active'],
                    'text-ui-muted' => ! $item['active'],
                ])>
                @if ($item['key'] === 'tip')
                    <span class="-mt-7 grid size-[52px] place-items-center rounded-full bg-club text-club-ink shadow-[0_0_0_5px_var(--ui-bg)]">
                        <x-ui.icon :name="$item['key']" class="size-7" />
                    </span>
                    @if ($item['badge'] ?? false)
                        <span class="absolute -top-6 end-2 size-3.5 rounded-full border-2 border-ui-bg bg-bad" aria-label="{{ __('Tip missing') }}"></span>
                    @endif
                @else
                    <x-ui.icon :name="$item['key']" class="size-6" />
                @endif
                {{ $item['label'] }}
            </{{ $tag }}>
        @endforeach
    </div>
</nav>
