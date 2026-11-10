<?php

use App\Notifications\PushMessage;
use App\Support\Push;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Powiadomienia push na telefon: włączenie w tej przeglądarce (subskrypcja z service workera) i wybór rodzajów
 * (users.push_prefs). Wysyłka: App\Support\Push.
 */
new #[Layout('layouts::public'), Title('Notification settings')] class extends Component {
    /** @var array<string, bool> */
    public array $prefs = [];

    public function mount(): void
    {
        foreach (Push::KINDS as $kind) {
            $this->prefs[$kind] = auth()->user()->wantsPush($kind);
        }
    }

    public function updatedPrefs(): void
    {
        $prefs = [];
        foreach (Push::KINDS as $kind) {
            $prefs[$kind] = (bool) ($this->prefs[$kind] ?? true);
        }

        auth()->user()->forceFill(['push_prefs' => $prefs])->save();
        $this->dispatch('ui-toast', text: __('Saved.'));
    }

    /** Subskrypcja z przeglądarki (PushSubscription.toJSON): endpoint i klucze. */
    public function save(array $subscription): void
    {
        $endpoint = (string) ($subscription['endpoint'] ?? '');
        abort_unless(str_starts_with($endpoint, 'https://') && strlen($endpoint) <= 500, 422);

        auth()->user()->updatePushSubscription(
            $endpoint,
            $subscription['keys']['p256dh'] ?? null,
            $subscription['keys']['auth'] ?? null,
            'aes128gcm',
        );

        $this->dispatch('ui-toast', text: __('Notifications are on in this browser.'), variant: 'success');
    }

    public function remove(string $endpoint): void
    {
        auth()->user()->deletePushSubscription($endpoint);
        $this->dispatch('ui-toast', text: __('Notifications are off in this browser.'));
    }

    /** Próbne powiadomienie na wszystkie przeglądarki gracza. */
    public function test(): void
    {
        auth()->user()->notify(new PushMessage('#LechTYPER', 'Test notification. It works!', route('home'), 'test'));
        $this->dispatch('ui-toast', text: __('Test notification sent.'));
    }

    /** Rodzaje powiadomień: [nazwa, opis]. */
    #[Computed]
    public function labels(): array
    {
        return [
            'open' => [__('Tipping is open'), __('A new matchday is ready to tip (up to a week before the match).')],
            'reminder' => [__('Last call'), __(':minutes minutes before kick-off, only if you have not tipped yet.', ['minutes' => Push::REMINDER_MINUTES])],
            'results' => [__('Results and points'), __('The score and your points after the matchday is settled.')],
            'rival' => [__('Your rival has tipped'), __('Your league rival has tipped and you have not yet.')],
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Notifications')" :subheading="__('Push notifications on your phone or computer.')">
        @if (! \App\Support\Push::enabled())
            <x-ui.callout icon="information-circle">{{ __('Notifications are not configured on this server yet.') }}</x-ui.callout>
        @else
            <div x-data="uiPush(@js((string) config('webpush.vapid.public_key')))" class="space-y-5">
                <template x-if="!supported">
                    <x-ui.callout icon="information-circle">
                        {{ __('This browser does not support notifications. On iPhone add the site to the home screen first (Share, Add to Home Screen) and open it from there.') }}
                    </x-ui.callout>
                </template>

                <template x-if="supported && permission === 'denied'">
                    <x-ui.callout variant="warning" icon="exclamation-triangle">
                        {{ __('Notifications are blocked for this site. Allow them in the browser settings and reload the page.') }}
                    </x-ui.callout>
                </template>

                <div x-show="supported && permission !== 'denied'" class="flex flex-wrap items-center gap-2">
                    <x-ui.btn variant="primary" icon="bell" x-show="!subscribed" x-on:click="enable()" x-bind:disabled="busy">{{ __('Turn on notifications') }}</x-ui.btn>
                    <x-ui.chip variant="good" x-show="subscribed" x-cloak>{{ __('On in this browser') }}</x-ui.chip>
                    <x-ui.btn size="sm" variant="ghost" x-show="subscribed" x-cloak x-on:click="test()">{{ __('Send a test') }}</x-ui.btn>
                    <x-ui.btn size="sm" variant="subtle" x-show="subscribed" x-cloak x-on:click="disable()" x-bind:disabled="busy">{{ __('Turn off') }}</x-ui.btn>
                </div>

                <div class="space-y-4 border-t border-ui-line pt-5">
                    @foreach ($this->labels as $kind => [$label, $description])
                        <x-ui.switch wire:model.live="prefs.{{ $kind }}" :label="$label" :description="$description" wire:key="push-{{ $kind }}" />
                    @endforeach
                </div>
            </div>
        @endif
    </x-pages::settings.layout>
</section>
