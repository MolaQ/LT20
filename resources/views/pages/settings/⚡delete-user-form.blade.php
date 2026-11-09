<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-8 space-y-4 border-t border-ui-line pt-6">
    <div>
        <x-ui.heading>{{ __('Delete account') }}</x-ui.heading>
        <x-ui.text class="mt-1">{{ __('Delete your account and all of its resources') }}</x-ui.text>
    </div>

    <x-ui.btn variant="danger" data-test="delete-user-button" x-on:click="$dispatch('ui-sheet', { name: 'confirm-user-deletion' })">
        {{ __('Delete account') }}
    </x-ui.btn>

    <livewire:pages::settings.delete-user-modal />
</section>
