<?php

use App\Models\PageView;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Statystyki odwiedzin (wzór z jokerpila.pl) z odsłon zapisanych przez TrackPageView: liczby okresu z porównaniem
 * do poprzedniego, wizyty dzień po dniu, działy i strony, źródła wejść, urządzenia, godziny i dni tygodnia.
 * Tylko Admin. Wizyta = odwiedzający liczony raz dziennie (visitor_hash zmienia się codziennie).
 */
new class extends Component {
    /** Okresy: dni => etykieta. */
    public const PERIODS = [7 => '7 days', 30 => '30 days', 90 => '3 months', 365 => 'Year'];

    /** Działy strony rozpoznawane po nazwie trasy. */
    private const SECTIONS = [
        'home' => 'Home',
        'tips' => 'Tipping',
        'results' => 'Results',
        'my-stats' => 'My stats',
        'hall-of-fame' => 'Hall of Fame',
        'history' => 'History',
        'stars' => 'Stars',
        'news.' => 'News',
        'team.' => 'Club profiles',
        'bot.' => 'Club profiles',
        'support' => 'Premium and support',
        'rules' => 'Rules',
        'faq' => 'FAQ',
        'office' => 'Complaints and requests office',
        'system' => 'System information',
        'profile.' => 'Settings',
        'security.' => 'Settings',
        'notifications.' => 'Settings',
        'appearance.' => 'Settings',
        'login' => 'Log in and registration',
        'register' => 'Log in and registration',
    ];

    /** Źródła wejść rozpoznawane po adresie strony, z której ktoś przyszedł. */
    private const SOURCES = [
        'Facebook' => ['facebook.', 'fb.', 'messenger.'],
        'Instagram' => ['instagram.'],
        'X' => ['twitter.', 'x.com', 't.co'],
        'WhatsApp' => ['whatsapp.'],
        'Google' => ['google.'],
        'YouTube' => ['youtube.', 'youtu.be'],
        'TikTok' => ['tiktok.'],
    ];

    #[Url(as: 'period', except: 30)]
    public int $days = 30;

    public function render(): View
    {
        return $this->view()->title(__('Visit statistics'));
    }

    public function updatedDays(): void
    {
        if (! array_key_exists($this->days, self::PERIODS)) {
            $this->days = 30;
        }
    }

    private function since(): Carbon
    {
        return now()->subDays($this->days - 1)->startOfDay();
    }

    /** @return Builder<PageView> */
    private function views(?Carbon $from = null, ?Carbon $to = null): Builder
    {
        return PageView::query()
            ->where('created_at', '>=', $from ?? $this->since())
            ->when($to, fn (Builder $q) => $q->where('created_at', '<', $to));
    }

    /** @return array{views: int, visits: int, pages_per_visit: float, bounce: float} */
    private function summary(Carbon $from, ?Carbon $to = null): array
    {
        $totals = $this->views($from, $to)->selectRaw('COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visits')->toBase()->first();
        $perVisit = $this->views($from, $to)->select('visitor_hash', DB::raw('COUNT(*) as pages'))->groupBy('visitor_hash')->toBase();
        $bounces = DB::query()->fromSub($perVisit, 'visits')->where('pages', 1)->count();
        $views = (int) ($totals->views ?? 0);
        $visits = (int) ($totals->visits ?? 0);

        return [
            'views' => $views,
            'visits' => $visits,
            'pages_per_visit' => $visits ? $views / $visits : 0,
            'bounce' => $visits ? $bounces / $visits * 100 : 0,
        ];
    }

    /** Liczby okresu i zmiana względem poprzedniego okresu tej samej długości (w procentach, null bez porównania). */
    #[Computed]
    public function totals(): array
    {
        $now = $this->summary($this->since());
        $before = $this->summary($this->since()->subDays($this->days), $this->since());
        $change = fn (float $a, float $b) => $b > 0 ? (int) round(($a - $b) / $b * 100) : null;

        return [
            'now' => $now,
            'change' => [
                'views' => $change($now['views'], $before['views']),
                'visits' => $change($now['visits'], $before['visits']),
                'pages_per_visit' => $change($now['pages_per_visit'], $before['pages_per_visit']),
                'bounce' => $change($now['bounce'], $before['bounce']),
            ],
        ];
    }

    /** Wizyty i odsłony dzień po dniu (dni bez wejść też, z zerami). @return array<int, array{date: Carbon, visits: int, views: int}> */
    #[Computed]
    public function daily(): array
    {
        $rows = $this->views()->selectRaw('DATE(created_at) as day, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visits')
            ->groupBy('day')->toBase()->get()->keyBy('day');

        $out = [];
        for ($date = $this->since()->copy(); $date->lte(today()); $date->addDay()) {
            $row = $rows[$date->toDateString()] ?? null;
            $out[] = ['date' => $date->copy(), 'visits' => (int) ($row->visits ?? 0), 'views' => (int) ($row->views ?? 0)];
        }

        return $out;
    }

    /** Odsłony działów strony. @return array<string, int> */
    #[Computed]
    public function sections(): array
    {
        $out = [];
        foreach ($this->views()->select('route_name', DB::raw('COUNT(*) as views'))->groupBy('route_name')->toBase()->get() as $row) {
            $name = __($this->sectionName($row->route_name));
            $out[$name] = ($out[$name] ?? 0) + (int) $row->views;
        }
        arsort($out);

        return $out;
    }

    /** Najczęściej oglądane adresy. @return array<string, int> */
    #[Computed]
    public function pages(): array
    {
        return $this->views()->select('path', DB::raw('COUNT(*) as views'))->groupBy('path')->orderByDesc('views')->limit(10)
            ->toBase()->get()->pluck('views', 'path')->map(fn ($v) => (int) $v)->all();
    }

    /** Wizyty według źródła wejścia (pierwsza odsłona wizyty). @return array<string, int> */
    #[Computed]
    public function sources(): array
    {
        $firstIds = $this->views()->selectRaw('MIN(id)')->groupBy('visitor_hash')->toBase();
        $out = [];
        foreach (PageView::whereIn('id', $firstIds)->select('referrer_host', DB::raw('COUNT(*) as visits'))->groupBy('referrer_host')->toBase()->get() as $row) {
            $name = $this->sourceName($row->referrer_host);
            $out[$name] = ($out[$name] ?? 0) + (int) $row->visits;
        }
        arsort($out);

        return $out;
    }

    /** Wizyty według urządzenia. @return array<string, int> */
    #[Computed]
    public function devices(): array
    {
        $labels = ['mobile' => __('Phone'), 'desktop' => __('Computer'), 'tablet' => __('Tablet')];
        $out = [];
        foreach ($this->views()->select('device', DB::raw('COUNT(DISTINCT visitor_hash) as visits'))->groupBy('device')->toBase()->get() as $row) {
            $out[$labels[$row->device] ?? __('Other')] = (int) $row->visits;
        }
        arsort($out);

        return $out;
    }

    /** Odsłony według godziny (0–23). @return array<int, int> */
    #[Computed]
    public function hours(): array
    {
        $sql = DB::getDriverName() === 'sqlite' ? "CAST(strftime('%H', created_at) AS INTEGER)" : 'HOUR(created_at)';
        $rows = $this->views()->selectRaw("$sql as h, COUNT(*) as views")->groupBy('h')->toBase()->get()->pluck('views', 'h');

        return array_map(fn (int $h) => (int) ($rows[$h] ?? 0), range(0, 23));
    }

    /** Odsłony według dnia tygodnia (0 = poniedziałek). @return array<int, int> */
    #[Computed]
    public function weekdays(): array
    {
        $sql = DB::getDriverName() === 'sqlite' ? "(CAST(strftime('%w', created_at) AS INTEGER) + 6) % 7" : 'WEEKDAY(created_at)';
        $rows = $this->views()->selectRaw("$sql as d, COUNT(*) as views")->groupBy('d')->toBase()->get()->pluck('views', 'd');

        return array_map(fn (int $d) => (int) ($rows[$d] ?? 0), range(0, 6));
    }

    private function sectionName(?string $route): string
    {
        foreach (self::SECTIONS as $prefix => $label) {
            if ($route !== null && ($route === $prefix || (str_ends_with($prefix, '.') && str_starts_with($route, $prefix)))) {
                return $label;
            }
        }

        return 'Other';
    }

    private function sourceName(?string $host): string
    {
        if ($host === null) {
            return __('Direct visits');
        }
        foreach (self::SOURCES as $name => $needles) {
            if (Str::contains(mb_strtolower($host), $needles)) {
                return $name;
            }
        }

        return __('Other websites');
    }
}; ?>

@php
    $t = $this->totals;
    $daily = $this->daily;
    $maxDaily = max(1, ...array_column($daily, 'visits'));
    $hours = $this->hours;
    $maxHour = max(1, ...$hours);
    $weekdays = $this->weekdays;
    $maxWeekday = max(1, ...$weekdays);
    $weekdayNames = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];
    $periods = collect(array_keys($this::PERIODS))->mapWithKeys(fn ($d) => [$d => __($this::PERIODS[$d])])->all();
    $tiles = [
        ['visits', __('Visits'), number_format($t['now']['visits'], 0, ',', ' '), false],
        ['views', __('Page views'), number_format($t['now']['views'], 0, ',', ' '), false],
        ['pages_per_visit', __('Pages per visit'), number_format($t['now']['pages_per_visit'], 1, ',', ' '), false],
        ['bounce', __('Single-page visits'), number_format($t['now']['bounce'], 0, ',', ' ').'%', true],
    ];
    // Ranking jako poziome słupki w jednym kolorze (wartość obok, tekst w kolorach tekstu).
    $ranking = function (array $rows, string $title, string $unit) {
        return ['rows' => $rows, 'title' => $title, 'unit' => $unit, 'max' => max(1, ...array_values($rows ?: [0]))];
    };
    $rankings = [
        $ranking($this->sections, __('Sections'), __('page views')),
        $ranking($this->sources, __('Where visitors come from'), __('visits')),
        $ranking($this->devices, __('Devices'), __('visits')),
        $ranking($this->pages, __('Most viewed pages'), __('page views')),
    ];
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Visit statistics') }}</x-ui.heading>
            <x-ui.subheading>{{ __('Public pages only, without robots, the panel and admins. No IP addresses or cookies: a visitor counts once a day.') }}</x-ui.subheading>
        </div>
    </div>

    <x-ui.filter-pills model="days" :value="$days" :label="__('Period')" :options="$periods" />

    {{-- Liczby okresu z porównaniem do poprzedniego okresu tej samej długości --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($tiles as [$key, $label, $value, $lowerIsBetter])
            @php
                $change = $t['change'][$key];
                $good = $change !== null && ($lowerIsBetter ? $change < 0 : $change > 0);
            @endphp
            <x-ui.card class="space-y-1">
                <p class="text-xs font-medium text-ui-muted">{{ $label }}</p>
                <p class="text-2xl font-semibold tabular-nums">{{ $value }}</p>
                <p class="text-xs text-ui-muted">
                    @if ($change === null)
                        {{ __('No previous period to compare') }}
                    @else
                        <span @class(['font-semibold', 'text-good' => $good, 'text-bad' => ! $good && $change !== 0])>{{ $change > 0 ? '+' : '' }}{{ $change }}%</span>
                        {{ __('vs previous period') }}
                    @endif
                </p>
            </x-ui.card>
        @endforeach
    </div>

    {{-- Wizyty dzień po dniu: słupki w barwie klubu, wartość i data po najechaniu --}}
    <x-ui.card class="space-y-3">
        <div class="flex items-baseline justify-between gap-2">
            <x-ui.heading>{{ __('Visits per day') }}</x-ui.heading>
            <span class="text-xs text-ui-muted">{{ __('Highest: :count', ['count' => number_format($maxDaily, 0, ',', ' ')]) }}</span>
        </div>
        <div class="flex h-48 items-end gap-[2px]" role="img" aria-label="{{ __('Visits per day') }}">
            @foreach ($daily as $day)
                <div class="group relative flex h-full flex-1 items-end">
                    <div class="w-full rounded-t-[4px] bg-club/80 transition group-hover:bg-club" style="height: {{ max($day['visits'] ? 2 : 0, round(100 * $day['visits'] / $maxDaily)) }}%"></div>
                    <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-ui-ink px-2 py-1 text-xs text-ui-bg shadow group-hover:block">
                        {{ $day['date']->translatedFormat('D j M') }}: {{ trans_choice(':count visit|:count visits', $day['visits'], ['count' => $day['visits']]) }}, {{ trans_choice(':count page view|:count page views', $day['views'], ['count' => $day['views']]) }}
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-between text-[11px] text-ui-muted">
            <span>{{ $daily[0]['date']->translatedFormat('j M') }}</span>
            <span>{{ end($daily)['date']->translatedFormat('j M') }}</span>
        </div>
    </x-ui.card>

    {{-- Rankingi: działy, źródła, urządzenia, strony --}}
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($rankings as $block)
            <x-ui.card class="space-y-3">
                <x-ui.heading>{{ $block['title'] }}</x-ui.heading>
                @forelse ($block['rows'] as $name => $value)
                    <div class="space-y-1" title="{{ $name }}: {{ number_format($value, 0, ',', ' ') }} {{ $block['unit'] }}">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="truncate">{{ $name }}</span>
                            <span class="shrink-0 tabular-nums text-ui-muted">{{ number_format($value, 0, ',', ' ') }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-ui-raised">
                            <div class="h-full rounded-full bg-club/80" style="width: {{ round(100 * $value / $block['max']) }}%"></div>
                        </div>
                    </div>
                @empty
                    <x-ui.text>{{ __('No visits in this period yet.') }}</x-ui.text>
                @endforelse
            </x-ui.card>
        @endforeach
    </div>

    {{-- Kiedy ludzie wchodzą: godziny i dni tygodnia --}}
    <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <x-ui.card class="space-y-3">
            <x-ui.heading>{{ __('Hour of the day') }}</x-ui.heading>
            <div class="flex h-32 items-end gap-[2px]" role="img" aria-label="{{ __('Hour of the day') }}">
                @foreach ($hours as $h => $value)
                    <div class="group relative flex h-full flex-1 items-end" title="{{ sprintf('%02d:00', $h) }}: {{ number_format($value, 0, ',', ' ') }}">
                        <div class="w-full rounded-t-[4px] bg-club/80 group-hover:bg-club" style="height: {{ max($value ? 2 : 0, round(100 * $value / $maxHour)) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[11px] text-ui-muted"><span>0:00</span><span>6:00</span><span>12:00</span><span>18:00</span><span>23:00</span></div>
        </x-ui.card>
        <x-ui.card class="space-y-3">
            <x-ui.heading>{{ __('Day of the week') }}</x-ui.heading>
            <div class="space-y-1.5">
                @foreach ($weekdays as $d => $value)
                    <div class="flex items-center gap-2 text-sm" title="{{ $weekdayNames[$d] }}: {{ number_format($value, 0, ',', ' ') }}">
                        <span class="w-8 shrink-0 text-xs text-ui-muted">{{ $weekdayNames[$d] }}</span>
                        <div class="h-2 flex-1 rounded-full bg-ui-raised">
                            <div class="h-full rounded-full bg-club/80" style="width: {{ round(100 * $value / $maxWeekday) }}%"></div>
                        </div>
                        <span class="w-12 shrink-0 text-right tabular-nums text-ui-muted">{{ number_format($value, 0, ',', ' ') }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </div>
</div>
