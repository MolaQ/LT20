<?php

use App\Models\BannerCampaign;
use App\Models\BannerStat;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Statystyki partnera pod prywatnym linkiem (bez logowania): wyświetlenia, kliknięcia i CTR jego banera,
 * razem i dzień po dniu z ostatnich 30 dni. Link generuje panel „Banery partnerów”, nowy link unieważnia stary.
 */
new #[Layout('layouts::shell')] class extends Component {
    public BannerCampaign $campaign;

    public function mount(string $token): void
    {
        $this->campaign = BannerCampaign::where('stats_token', $token)->firstOrFail();
    }

    public function render(): View
    {
        return $this->view()->title(__('Partner statistics'));
    }

    /** @return array<string, array{views: int, clicks: int}> dzień Y-m-d => liczniki, od najnowszego */
    #[Computed]
    public function days(): array
    {
        $rows = BannerStat::where('banner_campaign_id', $this->campaign->id)
            ->where('day', '>=', now()->subDays(29)->toDateString())
            ->get()->keyBy('day');

        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $day = now()->subDays($i)->toDateString();
            $days[$day] = ['views' => (int) ($rows[$day]->views ?? 0), 'clicks' => (int) ($rows[$day]->clicks ?? 0)];
        }

        return $days;
    }

    /** @return array{views: int, clicks: int} */
    #[Computed]
    public function total(): array
    {
        $row = BannerStat::where('banner_campaign_id', $this->campaign->id)
            ->selectRaw('COALESCE(SUM(views), 0) as views, COALESCE(SUM(clicks), 0) as clicks')->first();

        return ['views' => (int) $row?->views, 'clicks' => (int) $row?->clicks];
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    @php
        $ctr = fn (int $v, int $c) => $v > 0 ? number_format($c / $v * 100, 1, ',', '').'%' : '–';
        $fmt = fn (int $n) => number_format($n, 0, ',', ' ');
        $views30 = array_sum(array_column($this->days, 'views'));
        $clicks30 = array_sum(array_column($this->days, 'clicks'));
    @endphp

    <x-page-banner :title="$campaign->name" :subtitle="__('Statistics of your banner on LechTYPER.pl. A view counts when at least half of the banner is visible for a second.')" :eyebrow="__('Partner statistics')" />

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-ui.stat :value="$views30" :label="__('views (30 days)')" />
                <x-ui.stat :value="$clicks30" :label="__('clicks (30 days)')" />
                <x-ui.stat :value="$ctr($views30, $clicks30)" label="CTR" />
                <x-ui.stat :value="$this->total['views']" :label="__('views (all time)')" />
            </div>

            <x-ui.card>
                <x-ui.heading>{{ __('Last 30 days') }}</x-ui.heading>
                <x-ui.table class="mt-3">
                    <x-ui.table.columns>
                        <x-ui.table.column>{{ __('Day') }}</x-ui.table.column>
                        <x-ui.table.column align="end">{{ __('Views') }}</x-ui.table.column>
                        <x-ui.table.column align="end">{{ __('Clicks') }}</x-ui.table.column>
                        <x-ui.table.column align="end">CTR</x-ui.table.column>
                    </x-ui.table.columns>
                    <x-ui.table.rows>
                        @foreach ($this->days as $day => $row)
                            <x-ui.table.row wire:key="day-{{ $day }}">
                                <x-ui.table.cell>{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('D, d.m') }}</x-ui.table.cell>
                                <x-ui.table.cell align="end" class="tabular-nums">{{ $fmt($row['views']) }}</x-ui.table.cell>
                                <x-ui.table.cell align="end" class="tabular-nums">{{ $fmt($row['clicks']) }}</x-ui.table.cell>
                                <x-ui.table.cell align="end" class="tabular-nums">{{ $ctr($row['views'], $row['clicks']) }}</x-ui.table.cell>
                            </x-ui.table.row>
                        @endforeach
                    </x-ui.table.rows>
                </x-ui.table>
            </x-ui.card>
        </div>

        <x-ui.card class="space-y-3 self-start">
            <x-ui.heading>{{ __('Your banner') }}</x-ui.heading>
            <img src="{{ $campaign->imageUrl() }}" alt="" class="w-full rounded-lg border border-ui-line object-cover">
            <div class="text-sm">{{ $campaign->slot->label() }}</div>
            @if (filled($campaign->title))
                <div class="text-sm font-medium">{{ $campaign->title }}</div>
            @endif
            <div class="text-xs text-ui-muted">
                {{ $campaign->isLive() ? __('Showing') : __('Not showing now') }}
                @if ($campaign->starts_on || $campaign->ends_on)
                    · {{ $campaign->starts_on?->format('d.m.Y') ?? '…' }} – {{ $campaign->ends_on?->format('d.m.Y') ?? '…' }}
                @endif
            </div>
            <div class="text-xs text-ui-muted">{{ __('All time: :views, :clicks', ['views' => trans_choice(':count view|:count views', $this->total['views'], ['count' => $fmt($this->total['views'])]), 'clicks' => trans_choice(':count click|:count clicks', $this->total['clicks'], ['count' => $fmt($this->total['clicks'])])]) }}</div>
        </x-ui.card>
    </div>
</div>
