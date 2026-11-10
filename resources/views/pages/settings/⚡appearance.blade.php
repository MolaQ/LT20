<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::public'), Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div x-data="uiTheme">
            <x-ui.segmented x-model="mode" x-init="$watch('mode', (m) => set(m))" :options="['light' => __('Light'), 'dark' => __('Dark'), 'system' => __('System')]" />
        </div>
    </x-pages::settings.layout>
</section>
