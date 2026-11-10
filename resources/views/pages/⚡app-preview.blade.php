<?php

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Podgląd wyglądu 2.0: skorupa aplikacji i komponenty x-ui.* na przykładowych danych.
 * Strona do akceptacji przebudowy, niczego nie zapisuje.
 */
new #[Layout('layouts::shell')] class extends Component {
    public int $home = 2;

    public int $away = 1;

    public string $tab = 'score';

    /** Udawany zapis typu: dymek i konfetti, jak po prawdziwym zapisie. */
    public function save(): void
    {
        $this->dispatch('ui-toast', text: __('Tip :score saved! You can change it until kick-off.', ['score' => $this->home.':'.$this->away]));
        $this->js('window.ui.confetti(90)');
    }

    public function render(): View
    {
        return $this->view()->title(__('New look preview'));
    }
}; ?>

<div class="flex flex-col gap-3.5">
    <x-ui.chip variant="gold" class="self-start">{{ __('Preview: sample data') }}</x-ui.chip>

    <x-ui.card hero>
        <div class="flex justify-between text-xs uppercase tracking-wider opacity-85">
            <span>{{ __('Matchweek :n', ['n' => 16]) }} · Ekstraklasa</span><span>{{ __('Sat') }} 20:15</span>
        </div>
        <div class="my-3 flex items-center justify-around">
            <x-ui.counter wire:model.live="home" :label="mb_strtoupper(config('club.short_name'))" />
            <span class="font-display text-5xl font-extrabold opacity-60">:</span>
            <x-ui.counter wire:model.live="away" label="LEGIA" />
        </div>
        <x-ui.button variant="light" wire:click="save">{{ __('Save tip :score', ['score' => $home.':'.$away]) }}</x-ui.button>
    </x-ui.card>

    <x-ui.segmented wire:model.live="tab" :options="['score' => __('Score'), 'questions' => __('Questions'), 'players' => __('Squad picks')]" />
    <p class="text-center text-sm text-ui-muted">{{ __('Selected tab: :tab', ['tab' => $tab]) }}</p>

    <x-ui.section-title>{{ __('Your duel, matchweek :n', ['n' => 15]) }}</x-ui.section-title>
    <div class="grid grid-cols-3 gap-2.5">
        <x-ui.stat :value="14" prefix="+" tone="good" :label="__('points this week')" />
        <x-ui.stat value="2." :label="__('league position')" />
        <x-ui.stat :value="5" :label="__('hit streak')" />
    </div>

    <x-ui.section-title>{{ __('Your form') }}</x-ui.section-title>
    <x-ui.card>
        <div class="grid grid-cols-3 gap-2">
            <x-ui.ring :value="58" :label="__('correct outcomes')" />
            <x-ui.ring :value="14" color="gold" :label="__('exact scores')" />
            <x-ui.ring :value="71" color="good" :label="__('correct questions')" />
        </div>
    </x-ui.card>

    <div class="flex flex-wrap gap-2">
        <x-ui.chip>{{ __('Default') }}</x-ui.chip>
        <x-ui.chip variant="club">{{ config('club.short_name') }}</x-ui.chip>
        <x-ui.chip variant="gold">★ {{ __('Premium') }}</x-ui.chip>
        <x-ui.chip variant="good">{{ __('Promotion') }}</x-ui.chip>
        <x-ui.chip variant="bad">{{ __('Relegation') }}</x-ui.chip>
    </div>

    <x-ui.button variant="ghost" @click="$dispatch('ui-sheet', { name: 'more' })">{{ __('Open the More menu') }}</x-ui.button>
</div>
