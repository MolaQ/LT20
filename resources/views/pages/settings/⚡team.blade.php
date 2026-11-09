<?php

use App\Models\TeamNameChangeRequest;
use App\Support\Audit;
use App\Support\TeamRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $teamName = '';

    private const FIELDS = [
        'teamName' => 'team_name',
    ];

    public function mount(): void
    {
        $this->fillFromUser();
    }

    /** Konto bez żadnej roli i gracz premium zmieniają dane od razu, pozostali wysyłają prośbę (regulamin, punkt 11). */
    #[Computed]
    public function canRenameDirectly(): bool
    {
        $user = auth()->user();

        return !$user->roles()->exists() || \App\Support\Premium::isActive($user);
    }

    #[Computed]
    public function pendingRequest(): ?TeamNameChangeRequest
    {
        return TeamNameChangeRequest::query()
            ->where('user_id', auth()->id())
            ->pending()
            ->latest()
            ->first();
    }

    protected function rules(): array
    {
        $id = auth()->id();

        return [
            'teamName' => TeamRules::name($id),
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'teamName' => __('Team name'),
        ];
    }

    /* ------------------------------------------------------------------
     | Walidacja na żywo
     * ----------------------------------------------------------------*/

    public function updated(string $property, mixed $value = null): void
    {
        if (!array_key_exists($property, self::FIELDS)) {
            return;
        }

        // Lekka normalizacja w trakcie pisania. Pełna jest przy zapisie.
        $this->{$property} = preg_replace('/\s{2,}/u', ' ', ltrim((string) $this->{$property}));

        $this->validateOnly($property);
    }

    /** Czy pole różni się od aktualnej wartości zapisanej w koncie. */
    public function changed(string $property): bool
    {
        return trim($this->{$property}) !== (string) auth()->user()->{self::FIELDS[$property]};
    }

    private function hasChanges(): bool
    {
        foreach (array_keys(self::FIELDS) as $property) {
            if ($this->changed($property)) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------
     | Zapis
     * ----------------------------------------------------------------*/

    public function save(): void
    {
        $this->normalizeFields();
        $this->validate();

        if (!$this->hasChanges()) {
            $this->addError('form', __('Change at least one of the values.'));

            return;
        }

        $user = auth()->user();
        $before = $this->currentValues();
        $after = $this->formValues();

        if ($this->canRenameDirectly) {
            try {
                $user->update($after);
            } catch (UniqueConstraintViolationException) {
                $this->addError('form', __('One of these values has just been taken. Try different ones.'));

                return;
            }

            Audit::log('team_name.changed', $user, $before, $after, __('Changed by the user.'));
            $this->dispatch('ui-toast', text: __('Team details saved.'), variant: 'success');

            return;
        }

        if ($this->pendingRequest) {
            $this->dispatch('ui-toast', text: __('You already have a request awaiting approval.'), variant: 'warning');

            return;
        }

        $request = TeamNameChangeRequest::create([
            'user_id' => $user->id,
            'current_team_name' => $before['team_name'],
            'requested_team_name' => $after['team_name'],
        ]);

        Audit::log('team_name.requested', $user, $before, $after, __('Request #:id', ['id' => $request->id]));

        unset($this->pendingRequest);
        $this->fillFromUser();

        $this->dispatch('ui-toast', text: __('Request sent. An administrator will review it.'), variant: 'success');
    }

    public function cancelRequest(): void
    {
        $request = $this->pendingRequest;

        if (!$request) {
            return;
        }

        Audit::log(
            'team_name.cancelled',
            auth()->user(),
            [
                'team_name' => $request->requested_team_name,
            ],
            [],
            __('Request #:id cancelled by the user.', ['id' => $request->id]),
        );

        $request->delete();

        unset($this->pendingRequest);
        $this->dispatch('ui-toast', text: __('Request cancelled.'));
    }

    /* ------------------------------------------------------------------
     | Pomocnicze
     * ----------------------------------------------------------------*/

    private function currentValues(): array
    {
        $user = auth()->user();

        return [
            'team_name' => $user->team_name,
        ];
    }

    private function formValues(): array
    {
        return [
            'team_name' => $this->teamName,
        ];
    }

    private function normalizeFields(): void
    {
        $normalized = TeamRules::normalize($this->formValues());

        $this->teamName = $normalized['team_name'];
    }

    private function fillFromUser(): void
    {
        $values = $this->currentValues();

        $this->teamName = (string) $values['team_name'];
        $this->resetValidation();
    }
};
?>

<section class="w-full space-y-5">
    <x-ui.text>
        @if ($this->canRenameDirectly)
            {{ __('You can change your team details at any time.') }}
        @else
            {{ __('Changes to your team details must be approved by an administrator.') }}
        @endif
    </x-ui.text>

    {{-- Oczekująca prośba --}}
    @if ($this->pendingRequest)
        @php
            $pending = $this->pendingRequest;
        @endphp

        <div class="space-y-3 rounded-2xl border border-ui-line bg-ui-raised p-4">
            <div class="flex items-center justify-between gap-4">
                <x-ui.heading>{{ __('Request awaiting approval') }}</x-ui.heading>
                <x-ui.chip variant="gold">{{ __('Pending') }}</x-ui.chip>
            </div>

            <dl class="text-sm">
                <dt class="text-ui-muted">{{ __('Team name') }}</dt>
                <dd class="font-semibold">{{ $pending->requested_team_name }}</dd>
            </dl>

            <x-ui.text>{{ __('Sent') }}: {{ $pending->created_at->format('Y-m-d H:i') }}</x-ui.text>

            <x-ui.btn size="sm" variant="ghost" x-on:click="$dispatch('confirm-action', { message: @js(__('Cancel this request?')), run: () => $wire.cancelRequest() })">
                {{ __('Cancel request') }}
            </x-ui.btn>
        </div>
    @endif

    {{-- Formularz --}}
    <form wire:submit="save" class="space-y-5">
        <div class="space-y-1">
            <x-ui.input wire:model.live.blur="teamName" :label="__('Team name')" type="text" maxlength="40" :disabled="$this->pendingRequest !== null" />
            @if ($this->changed('teamName') && !$errors->has('teamName'))
                <p class="text-sm font-medium text-good">{{ __('Available') }}</p>
            @endif
        </div>

        <x-ui.error name="form" />

        <x-ui.btn variant="primary" type="submit" :disabled="$this->pendingRequest !== null">
            <span wire:loading.remove wire:target="save">
                {{ $this->canRenameDirectly ? __('Save changes') : __('Send change request') }}
            </span>
            <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
        </x-ui.btn>
    </form>
</section>
