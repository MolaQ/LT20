<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        // Zespoły w trwających sezonach przejmują boty, nierozliczone typy znikają (App\Support\Roster).
        \App\Support\Roster::releaseUser(Auth::user());

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-ui.sheet name="confirm-user-deletion" :title="__('Are you sure you want to delete your account?')">
        <form method="POST" wire:submit="deleteUser" class="space-y-5">
            <x-ui.text>
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </x-ui.text>

            <x-ui.input wire:model="password" :label="__('Password')" type="password" viewable />

            <div class="grid grid-cols-2 gap-2">
                <x-ui.btn variant="filled" x-on:click="$dispatch('ui-sheet', { name: 'confirm-user-deletion', open: false })">{{ __('Cancel') }}</x-ui.btn>
                <x-ui.btn variant="danger" type="submit" data-test="confirm-delete-user-button">{{ __('Delete account') }}</x-ui.btn>
            </div>
        </form>
    </x-ui.sheet>
</div>
