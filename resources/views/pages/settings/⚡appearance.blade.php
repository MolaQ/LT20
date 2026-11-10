<?php

use Livewire\Component;

new class extends Component {
    use \App\Concerns\SettingsPage;

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->settingsView('Appearance settings');
    }

    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :panel="$inPanel" :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div x-data="uiTheme">
            <x-ui.segmented x-model="mode" x-init="$watch('mode', (m) => set(m))" :options="['light' => __('Light'), 'dark' => __('Dark'), 'system' => __('System')]" />
        </div>
    </x-pages::settings.layout>
</section>
