<?php

use App\Support\Audit;
use App\Support\SiteColors;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Kolory strony (App\Support\SiteColors): barwy klubu, belki i banery, jasny motyw, stany i tablica odliczania.
 * Podgląd na żywo obok pól; zapis zmienia kolory na całej stronie i w panelu. Tylko Admin.
 */
new class extends Component {
    /** klucz => #rrggbb */
    public array $colors = [];

    public function mount(): void
    {
        $this->colors = SiteColors::all();
    }

    public function render(): View
    {
        return $this->view()->title(__('Site colors'));
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $rules = [];
        foreach (array_keys(SiteColors::DEFAULTS) as $key) {
            $rules["colors.$key"] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        $this->validate($rules);

        $old = SiteColors::saved();
        SiteColors::save($this->colors);
        Audit::log('site_colors.saved', null, $old, SiteColors::saved(), __('Site colors'));

        // Pełne przeładowanie: nowe kolory są w <head> całej strony.
        $this->redirect(route('dashboard.site-colors'));
        session()->flash('status', 'site-colors-saved');
    }

    /** Kolor domyślny jednego pola (zapis dopiero po „Zapisz”). */
    public function restore(string $key): void
    {
        abort_unless(isset(SiteColors::DEFAULTS[$key]), 422);
        $this->colors[$key] = SiteColors::defaults()[$key];
    }

    public function restoreAll(): void
    {
        $this->colors = SiteColors::defaults();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole(\App\Enums\RoleName::Admin->value), 403);
    }
}; ?>

@php
    $preview = \App\Support\SiteColors::css($colors);
    $defaults = \App\Support\SiteColors::defaults();
    $labels = \App\Support\SiteColors::labels();
    $byGroup = collect(\App\Support\SiteColors::DEFAULTS)->map(fn ($row) => $row[2])->groupBy(fn ($group) => $group, preserveKeys: true);
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <x-ui.heading size="xl" level="1">{{ __('Site colors') }}</x-ui.heading>
            <x-ui.subheading>{{ __('Colors of the whole site and the panel. Changes show in the preview right away and on the site after saving.') }}</x-ui.subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.btn variant="ghost" icon="arrow-uturn-left" wire:click="restoreAll">{{ __('Restore all defaults') }}</x-ui.btn>
            <x-ui.btn variant="primary" icon="check" wire:click="save">{{ __('Save') }}</x-ui.btn>
        </div>
    </div>

    @if (session('status') === 'site-colors-saved')
        <x-ui.callout variant="success" icon="check-circle">{{ __('Colors saved.') }}</x-ui.callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- Pola kolorów w grupach --}}
        <div class="space-y-4">
            @foreach (\App\Support\SiteColors::groups() as $group => $groupLabel)
                <x-ui.card class="space-y-3" wire:key="group-{{ $group }}">
                    <x-ui.heading>{{ $groupLabel }}</x-ui.heading>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($byGroup[$group] ?? [] as $key => $_)
                            <div class="flex items-center gap-3 rounded-xl border border-ui-line px-3 py-2" wire:key="color-{{ $key }}">
                                <input type="color" wire:model.live="colors.{{ $key }}" id="sc-{{ $key }}"
                                    class="h-9 w-11 shrink-0 cursor-pointer rounded-lg border border-ui-line bg-transparent p-0.5">
                                <label for="sc-{{ $key }}" class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $labels[$key] }}</span>
                                    <code class="text-xs text-ui-muted">{{ $colors[$key] }}</code>
                                </label>
                                @if (strtolower($colors[$key]) !== $defaults[$key])
                                    <x-ui.btn size="xs" variant="ghost" icon="arrow-uturn-left" wire:click="restore('{{ $key }}')"
                                        :title="__('Restore default') . ' ' . $defaults[$key]" :aria-label="__('Restore default')" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        {{-- Podgląd na żywo (jasny motyw): zmienne z pól ustawione na tym bloku --}}
        <div class="lg:sticky lg:top-6 lg:self-start">
            <div class="space-y-3 rounded-2xl border border-ui-line bg-ui-bg p-4 text-ui-ink" style="{{ $preview[':root'] }} {{ $preview[':root:not(.dark)'] }} --ui-club: {{ $colors['primary'] }};">
                <p class="text-xs font-medium text-ui-muted">{{ __('Preview') }}</p>
                <section class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface">
                    <div class="lech-bar flex items-center justify-between px-4 py-2.5 text-sm font-semibold">
                        {{ __('Next matchday') }}
                        <span class="rounded-full bg-ui-good-soft px-2 py-0.5 text-xs text-ui-good">{{ __('Tipping open') }}</span>
                    </div>
                    <div class="space-y-3 p-4">
                        <p class="text-sm">{{ __('Text') }} · <span class="text-ui-muted">{{ __('Secondary text') }}</span></p>
                        <div class="flex items-center justify-center gap-1 text-3xl">
                            <span class="flap"><span class="flap-half flap-top"><span>4</span></span><span class="flap-half flap-bottom"><span>4</span></span></span>
                            <span class="flap"><span class="flap-half flap-top"><span>2</span></span><span class="flap-half flap-bottom"><span>2</span></span></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 border-t border-ui-line pt-3">
                            <span class="rounded-xl bg-club px-3 py-1.5 text-sm font-semibold text-club-ink">{{ __('Change tip') }}</span>
                            <span class="rounded-full bg-ui-raised px-2.5 py-1 text-xs">{{ __('Raised elements') }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" style="background: color-mix(in srgb, var(--club-accent) 25%, transparent); color: color-mix(in srgb, var(--club-accent) 60%, #000)">+3</span>
                            <span class="text-xs font-semibold text-ui-bad">{{ __('Bad (losses, errors)') }}</span>
                        </div>
                    </div>
                </section>
                <div class="lech-banner rounded-2xl px-4 py-5">
                    <p class="text-xs opacity-80">#LechTYPER</p>
                    <p class="text-xl font-bold">{{ __('Bars and banners') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
