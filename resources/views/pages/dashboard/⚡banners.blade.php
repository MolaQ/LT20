<?php

use App\Enums\BannerSlot;
use App\Enums\Permission;
use App\Models\BannerCampaign;
use App\Models\BannerStat;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Banery partnerów (rotator): kampanie z grafiką, linkiem i tekstem w miejscach strony (pasek sezonu, prawa kolumna).
 * Banery jednego miejsca zmieniają się same co kilka sekund, te z wyższą wagą częściej. Przy każdej kampanii
 * wyświetlenia i kliknięcia (30 dni i razem) oraz prywatny link ze statystykami dla partnera.
 * Podgląd: season-list, zmiany: season-edit.
 */
new class extends Component {
    use WithFileUploads;

    public ?int $editId = null;
    public string $name = '';
    public string $place = 'season';
    public string $caption = '';
    public string $url = '';
    public int $weight = 5;
    public string $startsOn = '';
    public string $endsOn = '';
    public bool $isActive = true;
    public $image = null;

    public ?int $deleteId = null;

    public function render(): View
    {
        return $this->view()->title(__('Partner banners'));
    }

    #[Computed]
    public function campaigns()
    {
        return BannerCampaign::orderByDesc('is_active')->orderBy('slot')->latest()->get();
    }

    /**
     * Liczniki kampanii: id => [views30, clicks30, views, clicks].
     *
     * @return array<int, array{views30: int, clicks30: int, views: int, clicks: int}>
     */
    #[Computed]
    public function totals(): array
    {
        $since = now()->subDays(29)->toDateString();

        return BannerStat::query()
            ->select('banner_campaign_id')
            ->selectRaw('SUM(views) as views, SUM(clicks) as clicks')
            ->selectRaw('SUM(CASE WHEN day >= ? THEN views ELSE 0 END) as views30', [$since])
            ->selectRaw('SUM(CASE WHEN day >= ? THEN clicks ELSE 0 END) as clicks30', [$since])
            ->groupBy('banner_campaign_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->banner_campaign_id => [
                'views30' => (int) $r->views30, 'clicks30' => (int) $r->clicks30,
                'views' => (int) $r->views, 'clicks' => (int) $r->clicks,
            ]])
            ->all();
    }

    public function slotEnum(): BannerSlot
    {
        return BannerSlot::tryFrom($this->place) ?? BannerSlot::Season;
    }

    /** Zmiana miejsca zmienia proporcje grafiki, więc wybrany plik trzeba przyciąć od nowa. */
    public function updatedPlace(): void
    {
        $this->reset('image');
    }

    public function openForm(?int $id = null): void
    {
        $this->authorizeEdit();
        $this->resetForm();

        if ($id) {
            $campaign = BannerCampaign::findOrFail($id);
            $this->editId = $campaign->id;
            $this->name = $campaign->name;
            $this->place = $campaign->slot->value;
            $this->caption = (string) $campaign->title;
            $this->url = $campaign->url;
            $this->weight = $campaign->weight;
            $this->startsOn = (string) $campaign->starts_on?->toDateString();
            $this->endsOn = (string) $campaign->ends_on?->toDateString();
            $this->isActive = $campaign->is_active;
        }

        $this->dispatch('ui-sheet', name: 'banner-form');
    }

    public function save(): void
    {
        $this->authorizeEdit();
        $campaign = $this->editId ? BannerCampaign::findOrFail($this->editId) : new BannerCampaign();

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'place' => ['required', Rule::enum(BannerSlot::class)],
            'caption' => ['nullable', 'string', 'max:80'],
            'url' => ['required', 'url', 'max:255'],
            'weight' => ['required', 'integer', 'between:1,10'],
            'startsOn' => ['nullable', 'date'],
            'endsOn' => array_filter(['nullable', 'date', $this->startsOn !== '' ? 'after_or_equal:startsOn' : null]),
            'image' => [$campaign->exists && $campaign->slot->value === $this->place ? 'nullable' : 'required',
                'image', 'mimes:png,jpg,jpeg,webp', 'max:4096', $this->placeEnum()->dimensions()],
        ], [], [
            'name' => __('Partner'), 'place' => __('Place'), 'caption' => __('Text'), 'url' => __('Link'),
            'weight' => __('Weight'), 'startsOn' => __('From'), 'endsOn' => __('Until'), 'image' => __('Banner'),
        ]);

        $old = $campaign->exists ? $this->auditData($campaign) : [];
        $campaign->fill([
            'name' => trim($this->name),
            'slot' => $this->place,
            'title' => trim($this->caption) ?: null,
            'url' => $this->url,
            'weight' => $this->weight,
            'starts_on' => $this->startsOn ?: null,
            'ends_on' => $this->endsOn ?: null,
            'is_active' => $this->isActive,
        ]);

        if ($this->image) {
            if ($campaign->image_path) {
                Storage::disk('public')->delete($campaign->image_path);
            }
            $campaign->image_path = $this->image->store('banners', 'public');
        }

        $campaign->save();
        Audit::log($this->editId ? 'banner.updated' : 'banner.created', null, $old, $this->auditData($campaign), $campaign->name);

        $this->dispatch('ui-sheet', name: 'banner-form', open: false);
        $this->resetForm();
        unset($this->campaigns);
        $this->dispatch('ui-toast', variant: 'success', text: __('Banner saved.'));
    }

    public function toggle(int $id): void
    {
        $this->authorizeEdit();
        $campaign = BannerCampaign::findOrFail($id);
        $campaign->update(['is_active' => !$campaign->is_active]);

        Audit::log('banner.updated', null, ['is_active' => !$campaign->is_active], ['is_active' => $campaign->is_active], $campaign->name);
        unset($this->campaigns);
    }

    /** Nowy link statystyk: stary przestaje działać (np. gdy partner go komuś przekazał). */
    public function newToken(int $id): void
    {
        $this->authorizeEdit();
        $campaign = BannerCampaign::findOrFail($id);
        $campaign->forceFill(['stats_token' => \Illuminate\Support\Str::random(40)])->save();

        unset($this->campaigns);
        $this->dispatch('ui-toast', variant: 'success', text: __('New stats link created. The old one no longer works.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeEdit();
        $this->deleteId = BannerCampaign::findOrFail($id)->id;

        $this->dispatch('ui-sheet', name: 'delete-banner');
    }

    public function delete(): void
    {
        $this->authorizeEdit();
        $campaign = BannerCampaign::findOrFail((int) $this->deleteId);

        Storage::disk('public')->delete($campaign->image_path);
        $campaign->delete(); // statystyki znikają razem z kampanią

        Audit::log('banner.deleted', null, ['name' => $campaign->name], [], $campaign->name);

        $this->dispatch('ui-sheet', name: 'delete-banner', open: false);
        $this->reset('deleteId');
        unset($this->campaigns, $this->totals);
    }

    private function resetForm(): void
    {
        $this->reset('editId', 'name', 'place', 'caption', 'url', 'weight', 'startsOn', 'endsOn', 'isActive', 'image');
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    private function auditData(BannerCampaign $campaign): array
    {
        return [
            'slot' => $campaign->slot->value, 'title' => $campaign->title, 'url' => $campaign->url, 'weight' => $campaign->weight,
            'starts_on' => $campaign->starts_on?->toDateString(), 'ends_on' => $campaign->ends_on?->toDateString(), 'is_active' => $campaign->is_active,
        ];
    }

    private function authorizeEdit(): void
    {
        abort_unless(auth()->user()?->can(Permission::SeasonEdit->value), 403);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Partner banners') }}</x-ui.heading>
            <x-ui.subheading>{{ __('Banners of partners change by themselves every few seconds, without reloading the page. A higher weight shows a banner more often. A view counts when at least half of the banner is visible for a second.') }}</x-ui.subheading>
        </div>

        @can(\App\Enums\Permission::SeasonEdit->value)
            <x-ui.btn variant="primary" icon="plus" wire:click="openForm">{{ __('New banner') }}</x-ui.btn>
        @endcan
    </div>

    @forelse ($this->campaigns as $campaign)
        @php
            $t = $this->totals[$campaign->id] ?? ['views30' => 0, 'clicks30' => 0, 'views' => 0, 'clicks' => 0];
            $ctr = fn (int $v, int $c) => $v > 0 ? number_format($c / $v * 100, 1, ',', '').'%' : '–';
            [$statusColor, $statusText] = match (true) {
                ! $campaign->is_active => ['zinc', __('Off')],
                $campaign->isLive() => ['green', __('Showing')],
                $campaign->starts_on && $campaign->starts_on->isFuture() => ['blue', __('Scheduled')],
                default => ['amber', __('Ended')],
            };
            $statsUrl = route('partner.stats', $campaign->stats_token);
        @endphp
        <x-ui.card wire:key="banner-{{ $campaign->id }}">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                <img src="{{ $campaign->imageUrl() }}" alt="" @class(['shrink-0 rounded-lg border border-ui-line object-cover', 'aspect-[24/5] w-full lg:w-80' => $campaign->slot === \App\Enums\BannerSlot::Season, 'aspect-[6/5] w-40' => $campaign->slot === \App\Enums\BannerSlot::Panel])>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $campaign->name }}</span>
                        <x-ui.badge size="sm" :color="$statusColor">{{ $statusText }}</x-ui.badge>
                    </div>
                    @if (filled($campaign->title))
                        <div class="text-sm">{{ $campaign->title }}</div>
                    @endif
                    <div class="text-xs text-ui-muted">
                        {{ $campaign->slot->label() }} · {{ __('Weight') }} {{ $campaign->weight }}
                        @if ($campaign->starts_on || $campaign->ends_on)
                            · {{ $campaign->starts_on?->format('d.m.Y') ?? '…' }} – {{ $campaign->ends_on?->format('d.m.Y') ?? '…' }}
                        @endif
                    </div>
                    <a href="{{ $campaign->url }}" target="_blank" rel="noopener" class="block truncate text-xs text-club hover:underline">{{ $campaign->url }}</a>
                </div>
                <div class="grid shrink-0 grid-cols-3 gap-2 text-center text-xs sm:w-80">
                    <div class="rounded-xl bg-ui-raised p-2"><b class="ui-num block text-lg leading-tight">{{ number_format($t['views30'], 0, ',', ' ') }}</b>{{ __('views (30 days)') }}</div>
                    <div class="rounded-xl bg-ui-raised p-2"><b class="ui-num block text-lg leading-tight">{{ number_format($t['clicks30'], 0, ',', ' ') }}</b>{{ __('clicks (30 days)') }}</div>
                    <div class="rounded-xl bg-ui-raised p-2"><b class="ui-num block text-lg leading-tight">{{ $ctr($t['views30'], $t['clicks30']) }}</b>CTR</div>
                    <div class="col-span-3 text-ui-muted">{{ __('All time: :views views, :clicks clicks', ['views' => number_format($t['views'], 0, ',', ' '), 'clicks' => number_format($t['clicks'], 0, ',', ' ')]) }}</div>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-ui-line pt-3" x-data="{ copied: false, link: @js($statsUrl) }">
                <x-ui.btn size="sm" variant="ghost" icon="link"
                    x-on:click="navigator.clipboard.writeText(link); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-show="! copied">{{ __('Copy stats link for the partner') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </x-ui.btn>
                <a href="{{ $statsUrl }}" target="_blank" class="text-xs text-ui-muted hover:text-club hover:underline">{{ __('Preview') }}</a>
                @can(\App\Enums\Permission::SeasonEdit->value)
                    <span class="flex-1"></span>
                    <x-ui.btn size="sm" variant="ghost" icon="arrow-path" wire:click="newToken({{ $campaign->id }})"
                        wire:confirm="{{ __('Create a new stats link? The old one stops working.') }}">{{ __('New link') }}</x-ui.btn>
                    <x-ui.btn size="sm" variant="ghost" wire:click="toggle({{ $campaign->id }})">{{ $campaign->is_active ? __('Turn off') : __('Turn on') }}</x-ui.btn>
                    <x-ui.btn size="sm" variant="ghost" icon="pencil" wire:click="openForm({{ $campaign->id }})" :aria-label="__('Edit')" />
                    <x-ui.btn size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $campaign->id }})" :aria-label="__('Delete')" />
                @endcan
            </div>
        </x-ui.card>
    @empty
        <x-ui.card>
            <x-ui.text class="text-sm">{{ __('No partner banners yet. Without banners, the season bar shows the season sponsor banner and the right column shows nothing extra.') }}</x-ui.text>
        </x-ui.card>
    @endforelse

    <x-ui.modal name="banner-form" class="w-full md:w-[34rem]">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ $editId ? __('Edit banner') : __('New banner') }}</x-ui.heading>
            <x-ui.input wire:model="name" :label="__('Partner')" maxlength="100" />
            <x-ui.select wire:model.live="place" :label="__('Place')">
                @foreach (\App\Enums\BannerSlot::cases() as $option)
                    <x-ui.select.option :value="$option->value">{{ $option->label() }}</x-ui.select.option>
                @endforeach
            </x-ui.select>
            <div class="space-y-2" wire:key="banner-image-{{ $place }}">
                <x-ui.label>{{ __('Banner') }}</x-ui.label>
                {{-- Proporcje wymuszone przez miejsce: pasek sezonu 24:5, prawa kolumna 6:5 (300×250) --}}
                <x-ui.image-cropper model="image" :aspect="$this->placeEnum()->aspect()" format="jpeg" :width="$this->placeEnum()->width()" :label="__('Choose banner')"
                    :current="$image && ! $errors->has('image') ? $image->temporaryUrl() : ($editId ? \App\Models\BannerCampaign::find($editId)?->imageUrl() : null)" />
                <x-ui.error name="image" />
            </div>
            <x-ui.input wire:model="caption" :label="__('Text')" maxlength="80" :placeholder="__('e.g. Partner news every day')" />
            <x-ui.input wire:model="url" type="url" :label="__('Link')" placeholder="https://" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input wire:model="weight" type="number" min="1" max="10" :label="__('Weight')" />
                <x-ui.input wire:model="startsOn" type="date" :label="__('From')" />
                <x-ui.input wire:model="endsOn" type="date" :label="__('Until')" />
            </div>
            <x-ui.switch wire:model="isActive" :label="__('Showing')" :description="__('Empty dates = shown until turned off.')" />
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,image">{{ __('Save') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal name="delete-banner" class="w-full md:w-[28rem]">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ __('Delete banner') }}</x-ui.heading>
            <x-ui.text>{{ __('Delete this banner together with its statistics?') }}</x-ui.text>
            <div class="flex justify-end gap-2">
                <x-ui.modal.close>
                    <x-ui.btn variant="ghost">{{ __('Cancel') }}</x-ui.btn>
                </x-ui.modal.close>
                <x-ui.btn variant="danger" wire:click="delete">{{ __('Delete') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>
</div>
