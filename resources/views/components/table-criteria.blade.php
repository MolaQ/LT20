{{--
    Okno „Kolejność w tabeli”: kryteria z App\Support\Standings::sort (regulamin, punkt 4) jako ponumerowana drabinka z ikonami.
    Jedno na stronę, otwiera je przycisk x-table-criteria-button (zdarzenie ui-sheet o nazwie table-criteria).
    Te same kryteria mają ligi, liga podwórkowa, Liga Legend i grupy pucharów europejskich.
--}}
@php
    // Ikona (Heroicons albo własna) albo kolor kartki; ostatnie kryterium działa tylko przy pełnym remisie.
    $criteria = [
        ['icon' => 'trophy', 'name' => __('Points'), 'note' => __('3 for a win, 1 for a draw.')],
        ['icon' => 'scale', 'name' => __('Goal balance'), 'note' => __('Goals scored minus goals conceded.')],
        ['icon' => 'fire', 'name' => __('Goals scored'), 'note' => __('More goals = higher.')],
        ['icon' => 'hand-thumb-up', 'name' => __('Wins'), 'note' => __('More wins = higher.')],
        ['icon' => 'arrows-right-left', 'name' => __('Drawn matches'), 'note' => __('More draws = higher.')],
        ['icon' => 'sparkles', 'name' => __('Exact tips'), 'note' => __('Exact Lech scores hit in this competition.')],
        ['icon' => 'calculator', 'name' => __('Goal differences hit'), 'note' => __('Tips with the right goal difference.')],
        ['icon' => 'check-circle', 'name' => __('Outcomes hit'), 'note' => __('Tips with the right winner or a draw.')],
        ['icon' => 'swords', 'name' => __('Bonuses'), 'note' => __('Attack and defence bonuses of the fielded players.')],
        ['card' => 'bg-red-600', 'name' => __('Red cards'), 'note' => __('Of the fielded players. Fewer = higher.')],
        ['card' => 'bg-yellow-400', 'name' => __('Yellow cards'), 'note' => __('Of the fielded players. Fewer = higher.')],
        ['icon' => 'star', 'name' => __('Hall of Fame points'), 'note' => __('As at the start of the season.')],
        ['icon' => 'list-bullet', 'name' => __('Pre-season list'), 'note' => __('The place on the list before the season.')],
    ];
@endphp

<x-ui.modal name="table-criteria" class="w-full md:w-[34rem]">
    <div class="flex items-start gap-3">
        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-sky-500 text-white shadow">
            <x-ui.icon name="information-circle" variant="mini" class="size-6" />
        </span>
        <div class="min-w-0">
            <x-ui.heading size="lg">{{ __('How the table is ordered') }}</x-ui.heading>
            <x-ui.text class="text-sm">{{ __('Teams are compared criterion by criterion. The next one counts only when everything above it is equal.') }}</x-ui.text>
        </div>
    </div>

    {{-- Drabinka: numer, ikona, nazwa i krótkie objaśnienie; linia łączy kolejne stopnie --}}
    <ol class="relative space-y-1.5 before:absolute before:bottom-4 before:left-[1.05rem] before:top-4 before:w-0.5 before:bg-sky-500/30">
        @foreach ($criteria as $i => $item)
            <li class="relative flex items-center gap-3 rounded-xl px-1 py-1">
                <span @class(['relative z-10 grid size-8 shrink-0 place-items-center rounded-full text-sm font-black tabular-nums shadow-sm',
                    'bg-sky-500 text-white' => $i < 5, 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-100' => $i >= 5])>{{ $i + 1 }}</span>
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-ui-raised text-club">
                    @isset($item['card'])
                        <span class="h-4 w-3 rounded-[3px] {{ $item['card'] }} shadow-sm" aria-hidden="true"></span>
                    @else
                        <x-ui.icon :name="$item['icon']" variant="micro" class="size-4" />
                    @endisset
                </span>
                <span class="min-w-0 leading-tight">
                    <span class="block text-sm font-semibold">{{ $item['name'] }}</span>
                    <span class="block text-xs text-ui-muted">{{ $item['note'] }}</span>
                </span>
            </li>
        @endforeach
    </ol>

    <div class="rounded-xl border border-sky-500/30 bg-sky-500/10 px-3 py-2 text-xs leading-relaxed">
        <span class="font-semibold">{{ __('Example') }}:</span>
        {{ __('Two teams with 1 point each after a 0:0 draw are equal up to criterion 5, so exact tips decide first, then goal differences and outcomes hit. Bonuses come only after that.') }}
    </div>
</x-ui.modal>
