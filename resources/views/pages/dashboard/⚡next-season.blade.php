<?php

use App\Enums\CompetitionType;
use App\Enums\League;
use App\Models\Competition;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Support\Backyard;
use App\Support\Promotion;
use App\Support\Standings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Podgląd listy na nowy sezon (Rozgrywki → Nowa lista): awanse, spadki i wypychanie botów (Promotion::applyTree)
 * liczone na aktualnych tabelach bieżącego sezonu. W trakcie sezonu to prognoza, po ostatniej kolejce wynik.
 * Nic nie zapisuje; listę buduje dopiero „Zbuduj listę z poprzedniego sezonu” w sezonach.
 */
new class extends Component
{
    public function render(): View
    {
        return $this->view()->title(__('Next season list'));
    }

    /**
     * Nowe ligi: nazwa i zespoły z kierunkiem zmiany (up, down, same), poprzednią ligą i znacznikiem pauzy bota.
     *
     * @return array{season: ?Season, leagues: list<array{name: string, rows: list<array{name: string, bot: bool, move: string, from: string, rests: bool}>}>, humans: int, bots: int, size: int}
     */
    #[Computed]
    public function preview(): array
    {
        $season = Season::current();
        $empty = ['season' => $season, 'leagues' => [], 'humans' => 0, 'bots' => 0, 'size' => 0];
        if (! $season) {
            return $empty;
        }

        $competitions = Competition::where('season_id', $season->id)
            ->whereIn('type', [CompetitionType::League->value, CompetitionType::Swiss->value])
            ->get()
            ->all();
        $tree = Standings::projectedTree($competitions);
        if ($tree === []) {
            return $empty;
        }

        $result = Promotion::applyTree($tree);
        $keys = array_map(strval(...), array_keys($tree));
        $rankOfKey = array_flip($keys);
        $fromKey = [];
        $human = [];
        foreach ($tree as $key => $league) {
            foreach ($league['teams'] as $team) {
                $fromKey[$team['id']] = (string) $key;
                $human[$team['id']] = $team['human'];
            }
        }

        $humans = count(array_filter($human));
        $size = SeasonTeam::listSize($humans);
        $teams = SeasonTeam::with(['user:id,name,team_name', 'bot:id,name'])->whereIn('id', array_keys($fromKey))->get()->keyBy('id');

        $leagues = [];
        $position = 0;
        foreach ($result as $key => $ids) {
            $rows = [];
            foreach ($ids as $id) {
                $position++;
                $from = $fromKey[$id] ?? (string) $key;
                $rows[] = [
                    'name' => (string) ($teams->get($id)?->name ?? '?'),
                    'bot' => ! ($human[$id] ?? false),
                    'move' => $rankOfKey[(string) $key] < $rankOfKey[$from] ? 'up' : ($rankOfKey[(string) $key] > $rankOfKey[$from] ? 'down' : 'same'),
                    'from' => $this->leagueName($from),
                    'rests' => $position > $size,
                ];
            }
            $leagues[] = ['name' => $this->leagueName((string) $key), 'rows' => $rows];
        }

        return ['season' => $season, 'leagues' => $leagues, 'humans' => $humans, 'bots' => count($human) - $humans, 'size' => $size];
    }

    /** Nazwa ligi z klucza drzewa: '1'..'10', '11' (stara podwórkowa), 'p1', 'p2' … */
    private function leagueName(string $key): string
    {
        return str_starts_with($key, 'p') ? Backyard::name((int) substr($key, 1)) : League::from((int) $key)->label();
    }
}; ?>

@php
    $preview = $this->preview;
@endphp

<div class="flex w-full flex-col gap-6">
    <div class="space-y-1">
        <x-ui.heading size="xl" level="1">{{ __('Next season list') }}</x-ui.heading>
        <x-ui.text>{{ __('Promotions, relegations and pushing out the bots, counted on the current tables. During the season it is a forecast, after the last matchday the result. Nothing is saved here.') }}</x-ui.text>
    </div>

    @if ($preview['leagues'] === [])
        <x-ui.callout icon="information-circle">{{ __('No league tables in the current season yet.') }}</x-ui.callout>
    @else
        <div class="flex flex-wrap gap-4 text-sm">
            <span class="rounded-lg bg-ui-raised px-3 py-1.5"><span class="font-semibold tabular-nums">{{ $preview['humans'] }}</span> {{ trans_choice('player|players', (int) $preview['humans']) }}</span>
            <span class="rounded-lg bg-ui-raised px-3 py-1.5"><span class="font-semibold tabular-nums">{{ $preview['bots'] }}</span> {{ trans_choice('bot|bots', (int) $preview['bots']) }}</span>
            <span class="rounded-lg bg-ui-raised px-3 py-1.5">{{ __('List size') }}: <span class="font-semibold tabular-nums">{{ $preview['size'] }}</span></span>
            <span class="flex items-center gap-3 text-xs text-ui-muted">
                <span class="inline-flex items-center gap-1"><x-ui.icon name="chevron-up" variant="micro" class="size-3.5 text-green-600" />{{ __('Promotion') }}</span>
                <span class="inline-flex items-center gap-1"><x-ui.icon name="chevron-down" variant="micro" class="size-3.5 text-red-600" />{{ __('Relegation') }}</span>
                <span class="italic">{{ __('bot') }}</span>
                <span class="line-through">{{ __('rests a season') }}</span>
            </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($preview['leagues'] as $league)
                <section class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface" wire:key="next-{{ $loop->index }}">
                    <h2 class="border-b border-ui-line bg-ui-raised px-4 py-2 text-sm font-semibold">{{ $league['name'] }}</h2>
                    <ol class="divide-y divide-ui-line text-sm">
                        @foreach ($league['rows'] as $row)
                            <li class="flex items-center gap-2 px-4 py-1.5">
                                <span class="w-5 shrink-0 text-right text-xs tabular-nums text-ui-muted">{{ $loop->iteration }}.</span>
                                <span @class(['min-w-0 flex-1 truncate', 'italic text-ui-muted' => $row['bot'], 'line-through' => $row['rests']])>{{ $row['name'] }}</span>
                                @if ($row['move'] !== 'same')
                                    <span class="flex shrink-0 items-center gap-1 text-xs text-ui-muted" title="{{ $row['from'] }}">
                                        <x-ui.icon :name="$row['move'] === 'up' ? 'chevron-up' : 'chevron-down'" variant="micro" @class(['size-3.5', 'text-green-600' => $row['move'] === 'up', 'text-red-600' => $row['move'] === 'down']) />
                                        <span class="max-w-28 truncate">{{ $row['from'] }}</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
    @endif
</div>
