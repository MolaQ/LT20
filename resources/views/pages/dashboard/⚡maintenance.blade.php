<?php

use App\Support\Maintenance;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Tryb konserwacji (App\Support\Maintenance): włączenie zamyka stronę dla wszystkich poza adminami
 * (przerwa techniczna z komunikatem i planowanym końcem), admini sprawdzają zmiany i otwierają stronę ponownie.
 */
new class extends Component {
    public string $message = '';

    public string $until = '';

    public function mount(): void
    {
        $state = Maintenance::state();
        $this->message = $state['message'];
        $this->until = $state['until'] ? substr(str_replace(' ', 'T', $state['until']), 0, 16) : '';
    }

    public function render(): View
    {
        return $this->view()->title(__('Maintenance mode'));
    }

    public function enable(): void
    {
        $this->authorizeAdmin();
        $this->validateForm();

        Maintenance::enable($this->message, $this->untilValue(), auth()->user());
        $this->dispatch('ui-toast', variant: 'warning', text: __('Maintenance mode is on. Only admins can see the site.'));
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $this->validateForm();

        Maintenance::update($this->message, $this->untilValue());
        $this->dispatch('ui-toast', variant: 'success', text: __('Saved.'));
    }

    public function disable(): void
    {
        $this->authorizeAdmin();

        Maintenance::disable();
        $this->dispatch('ui-toast', variant: 'success', text: __('The site is open again.'));
    }

    private function validateForm(): void
    {
        $this->validate([
            'message' => ['nullable', 'string', 'max:500'],
            'until' => ['nullable', 'date'],
        ]);
    }

    private function untilValue(): ?string
    {
        return $this->until !== '' ? \Illuminate\Support\Carbon::parse($this->until)->toDateTimeString() : null;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Maintenance::canBypass(auth()->user()), 403);
    }
}; ?>

@php
    $state = \App\Support\Maintenance::state();
    $until = \App\Support\Maintenance::until();
@endphp

<div class="max-w-3xl space-y-6">
    <div>
        <x-ui.heading size="xl">{{ __('Maintenance mode') }}</x-ui.heading>
        <x-ui.text>{{ __('Close the site for players while you update it or test new features. Admins still see the whole site and the panel.') }}</x-ui.text>
    </div>

    <x-ui.card class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="{{ $state['enabled'] ? 'bg-amber-500' : 'bg-emerald-500' }} inline-block size-3 rounded-full"></span>
                <div>
                    <div class="font-semibold">{{ $state['enabled'] ? __('The site is closed (technical break)') : __('The site is open') }}</div>
                    @if ($state['enabled'] && $state['since'])
                        <div class="text-xs text-ui-muted">{{ __('Since :date, by :name', ['date' => \Illuminate\Support\Carbon::parse($state['since'])->format('d.m.Y H:i'), 'name' => $state['by'] ?? '—']) }}</div>
                    @endif
                </div>
            </div>
            @if ($state['enabled'])
                <x-ui.btn variant="primary" icon="lock-open" wire:click="disable">{{ __('Open the site') }}</x-ui.btn>
            @else
                <x-ui.btn variant="danger" icon="wrench-screwdriver" wire:click="enable">{{ __('Close the site') }}</x-ui.btn>
            @endif
        </div>

        <x-ui.textarea wire:model="message" :label="__('Message for players (optional)')" rows="3" maxlength="500"
            :placeholder="__('e.g. We are adding player designation. Back after the update.')" />
        <x-ui.input type="datetime-local" wire:model="until" :label="__('Planned end (optional)')" />
        @if ($state['enabled'])
            <div class="flex justify-end">
                <x-ui.btn size="sm" wire:click="save">{{ __('Save message') }}</x-ui.btn>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card class="space-y-2">
        <x-ui.heading>{{ __('How to update the site safely') }}</x-ui.heading>
        <ol class="list-decimal space-y-1 pl-5 text-sm text-ui-muted">
            <li>{{ __('Close the site here, so nobody saves tips while the update runs.') }}</li>
            <li>{{ __('Upload the package in Site update and approve it with your password. The server cron installs it within a minute, migrations included.') }}</li>
            <li>{{ __('Check the site as admin, then open it again.') }}</li>
        </ol>
        <x-ui.text class="text-xs">{{ __('While the installer replaces the files, the site shows a short technical break for everybody and opens again by itself.') }}</x-ui.text>
        <div class="flex justify-end">
            <x-ui.btn size="sm" icon="arrow-up-tray" :href="route('dashboard.site-update')" wire:navigate>{{ __('Site update') }}</x-ui.btn>
        </div>
    </x-ui.card>
</div>
