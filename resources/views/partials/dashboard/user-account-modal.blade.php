{{-- Okno ręcznej edycji konta w panelu Użytkownicy (pages::dashboard.users): nazwa, drużyna, e-mail, hasło. --}}
<x-ui.modal name="user-account" class="w-full md:w-[30rem]" @close="$wire.resetAccount()">
    <form wire:submit="saveAccount" class="space-y-4">
        <div>
            <x-ui.heading size="lg">{{ __('Edit account') }}</x-ui.heading>
            <x-ui.text class="mt-1">{{ __('Changes are saved in the change log. The player is not notified.') }}</x-ui.text>
        </div>

        <x-ui.input wire:model="accountName" :label="__('Name')" maxlength="255" />
        <x-ui.input wire:model="accountTeam" :label="__('Team name')" maxlength="40" />
        <x-ui.input wire:model="accountEmail" type="email" :label="__('Email')" maxlength="255" autocomplete="off" />
        <x-ui.switch wire:model="accountVerified" :label="__('Email confirmed')"
            :description="__('Without confirmation the player must enter a code from the email after logging in.')" />
        <div class="space-y-1">
            <x-ui.input wire:model="accountPassword" type="password" viewable :label="__('New password')" autocomplete="new-password" />
            <x-ui.text class="text-xs">{{ __('Leave empty to keep the current password. Give the new password to the player yourself.') }}</x-ui.text>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.modal.close>
                <x-ui.btn variant="ghost" type="button">{{ __('Cancel') }}</x-ui.btn>
            </x-ui.modal.close>
            <x-ui.btn variant="primary" type="submit">
                <span wire:loading.remove wire:target="saveAccount">{{ __('Save changes') }}</span>
                <span wire:loading wire:target="saveAccount">{{ __('Saving...') }}</span>
            </x-ui.btn>
        </div>
    </form>
</x-ui.modal>
