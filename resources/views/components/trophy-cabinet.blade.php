{{--
    Gablota trofeów (profil zespołu, profil bota, podgląd w panelu Hall of Fame). Puchary stoją na półce (wyrównane do dołu),
    a ich wysokość idzie za hierarchią (HallOfFame::trophyScale): Liga Legend, mistrz Ekstraklasy i Liga Mistrzów największe,
    nagrody indywidualne najmniejsze. $items: lista {key, label, count, seasons}; $icons: klucz => adres ikony.
--}}
@props(['items' => [], 'icons' => []])

<div {{ $attributes->class('grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4') }}>
    @foreach ($items as $item)
        @php
            $scale = \App\Support\HallOfFame::trophyScale($item['key']);
            $height = (int) round(96 * $scale / 100);
        @endphp
        <div class="flex flex-col items-center gap-2 rounded-xl border border-ui-line p-4 text-center" wire:key="cab-{{ $item['key'] }}">
            {{-- Półka: stała wysokość, puchar przy dolnej krawędzi --}}
            <div class="flex h-24 w-full items-end justify-center border-b-2 border-amber-700/30 pb-0.5 dark:border-amber-300/20">
                <div class="relative" style="height: {{ $height }}px">
                    @if (isset($icons[$item['key']]))
                        <img src="{{ $icons[$item['key']] }}" alt="" class="h-full w-auto object-contain drop-shadow-md">
                    @else
                        <x-ui.icon name="trophy" class="h-full w-auto text-amber-500" />
                    @endif
                    @if (($item['count'] ?? 1) > 1)
                        <span class="absolute -right-3 -top-1 rounded-full bg-amber-500 px-1.5 text-xs font-bold text-white">×{{ $item['count'] }}</span>
                    @endif
                </div>
            </div>
            <span class="text-sm font-medium">{{ $item['label'] }}</span>
            @if (!empty($item['seasons']))
                <span class="text-xs text-ui-muted">{{ implode(', ', $item['seasons']) }}</span>
            @endif
        </div>
    @endforeach
</div>
