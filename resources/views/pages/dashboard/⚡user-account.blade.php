<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Audit;
use App\Support\TeamRules;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ręczna edycja konta w panelu Użytkownicy (tylko Admin): nazwa wyświetlana, nazwa drużyny, e-mail
 * (z potwierdzeniem albo bez) i nowe hasło. Okno otwiera zdarzenie „user-account” z listy użytkowników,
 * po zapisie lista odświeża się zdarzeniem „account-saved”. Zmiany trafiają do dziennika zmian bez treści hasła.
 */
new class extends Component {
    // Edytowane konto: nazwa, drużyna, e-mail, nowe hasło (puste = bez zmiany).
    public ?int $accountId = null;
    public string $accountName = '';
    public string $accountTeam = '';
    public string $accountEmail = '';
    public string $accountPassword = '';
    public bool $accountVerified = false;

    #[On('user-account')]
    public function open(int $id): void
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($id);

        $this->accountId = $user->id;
        $this->accountName = $user->name;
        $this->accountTeam = (string) $user->team_name;
        $this->accountEmail = $user->email;
        $this->accountPassword = '';
        $this->accountVerified = $user->hasVerifiedEmail();
        $this->resetValidation();

        $this->dispatch('ui-sheet', name: 'user-account');
    }

    public function saveAccount(): void
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($this->accountId);
        $this->accountName = Str::squish($this->accountName);
        $this->accountTeam = TeamRules::normalize(['team_name' => $this->accountTeam])['team_name'];
        $this->accountEmail = Str::lower(trim($this->accountEmail));

        // Konto bez drużyny (np. sam admin) może jej dalej nie mieć; nazwa, która już była, nie może zniknąć.
        $teamRules = TeamRules::name($user->id);
        if (blank($user->team_name)) {
            $teamRules[0] = 'nullable';
        }

        $this->validate([
            'accountName' => ['required', 'string', 'max:255'],
            'accountTeam' => $teamRules,
            'accountEmail' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'accountPassword' => ['nullable', 'string', Password::default()],
        ], [], [
            'accountName' => __('Name'),
            'accountTeam' => __('Team name'),
            'accountEmail' => __('Email'),
            'accountPassword' => __('New password'),
        ]);

        $fields = ['name', 'team_name', 'email'];
        $old = $user->only($fields) + ['email_confirmed' => $user->hasVerifiedEmail()];

        $user->fill([
            'name' => $this->accountName,
            'team_name' => $this->accountTeam !== '' ? $this->accountTeam : null,
            'email' => $this->accountEmail,
        ]);

        // Potwierdzenie adresu: przełącznik w oknie (nowy adres bez potwierdzenia wymaga kodu przy logowaniu).
        if ($this->accountVerified && !$user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now(), 'verification_code' => null, 'verification_code_expires_at' => null]);
        } elseif (!$this->accountVerified) {
            $user->email_verified_at = null;
        }

        $passwordChanged = $this->accountPassword !== '';
        if ($passwordChanged) {
            $user->password = $this->accountPassword; // cast „hashed” szyfruje hasło
            $user->setRememberToken(Str::random(60)); // „zapamiętaj mnie” na innych urządzeniach przestaje działać
        }

        $user->save();

        $new = $user->only($fields) + ['email_confirmed' => $user->hasVerifiedEmail()];
        if ($passwordChanged) {
            $old['password'] = '•••';
            $new['password'] = __('changed');
        }
        Audit::log('user.updated', $user, array_diff_assoc($old, $new), array_diff_assoc($new, $old));

        $this->dispatch('ui-sheet', name: 'user-account', open: false);
        $this->dispatch('ui-toast', text: __('Account of :name saved.', ['name' => $user->name]), variant: 'success');
        $this->resetAccount();
        $this->dispatch('account-saved'); // lista użytkowników odświeża się (pages::dashboard.users)
    }

    public function resetAccount(): void
    {
        $this->reset('accountId', 'accountName', 'accountTeam', 'accountEmail', 'accountPassword', 'accountVerified');
        $this->resetValidation();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole(RoleName::Admin->value), 403);
    }
};
?>

<div>
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
</div>
