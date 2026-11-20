{{--
    Zakładka „Odznaki” na stronie drużyny: wszystkie odznaki (App\Support\Badges) w grupach, zdobyty stopień
    (brąz, srebro, złoto) i postęp do następnego progu. Niezdobyte są szare.
    Zmienne: $badges (Badges::forUser), $teamName.
--}}
@php
    $earned = collect($badges)->where('tier', '>', 0)->count();
    $tierNames = [1 => __('Bronze'), 2 => __('Silver'), 3 => __('Gold')];
@endphp

<section data-share-target class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <x-ui.text class="text-sm">{{ __(':team has :n of :total badges.', ['team' => $teamName, 'n' => $earned, 'total' => count($badges)]) }}</x-ui.text>
        <x-share-image :title="__('Badges')" class="text-ui-muted" />
    </div>

    @foreach (\App\Support\Badges::groups() as $group => $groupLabel)
        <div class="space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-ui-muted">{{ $groupLabel }}</h3>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach (collect($badges)->where('group', $group) as $key => $badge)
                    @php
                        $single = count($badge['tiers']) === 1;
                        $goal = $badge['next'] ?? end($badge['tiers']);
                        $percent = $goal > 0 ? min(100, (int) round($badge['progress'] * 100 / $goal)) : 0;
                    @endphp
                    <div wire:key="badge-{{ $key }}" @class(['flex items-center gap-3 rounded-2xl border border-ui-line bg-ui-surface p-3 shadow-sm', 'opacity-80' => $badge['tier'] === 0])>
                        <x-badge-icon :badge="$key" :tier="$badge['tier']" :icon="$badge['icon']" class="size-12" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-bold">{{ $badge['label'] }}</span>
                                @if ($badge['tier'] > 0)
                                    <span class="shrink-0 text-[11px] font-bold" style="color: {{ \App\Support\Badges::color($key, $badge['tier']) }}">{{ $single ? __('Earned') : $tierNames[$badge['tier']] }}</span>
                                @endif
                            </div>
                            <div class="text-xs leading-snug text-ui-muted">{{ $badge['rule'] }}</div>
                            @if (!$single)
                                <div class="mt-1.5 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ui-raised"><div class="h-full rounded-full bg-club" style="width: {{ $percent }}%"></div></div>
                                    <span class="shrink-0 text-[11px] tabular-nums text-ui-muted">{{ $badge['next'] === null ? $badge['progress'] : $badge['progress'] . ' / ' . $badge['next'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</section>
