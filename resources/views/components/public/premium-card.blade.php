{{-- Prawy panel: korzyści z premium (etap 15) i stan premium zalogowanego gracza. --}}
@php
    $user = auth()->user();
    $active = \App\Support\Premium::isActive($user);
    // Premium dla wszystkich: karta mówi, że dodatki są za darmo, a przycisk prowadzi do kawki.
    $free = \App\Support\Premium::free();
    $benefits = [
        ['eye', __('Rival preview: their outcome while tipping is open, the exact tip after it closes')],
        ['chart-bar', __('While you tip: how the other players tip the score')],
        ['chart-bar', __('Your exact place among the players')],
        ['pencil-square', __('Team name change without approval')],
        ['star', __('Every payment supports the development of LechTYPER')],
    ];
@endphp

<div class="space-y-3 rounded-2xl border border-gold/30 bg-ui-surface p-4">
    <div class="flex items-center gap-2">
        <x-ui.icon name="sparkles" class="size-5 text-gold" />
        <x-ui.heading>{{ __('Premium') }}</x-ui.heading>
    </div>

    @if ($free)
        <x-ui.text class="text-sm">{{ __('Free for everyone') }}</x-ui.text>
    @elseif ($active)
        <x-ui.text class="text-sm">
            {{ $user->premium_until ? __('Active until :date', ['date' => $user->premium_until->format('d.m.Y')]) : __('Active without an end date') }}
        </x-ui.text>
    @endif

    <ul class="space-y-2 text-sm">
        @foreach ($benefits as [$icon, $text])
            <li class="flex gap-2">
                <x-ui.icon :name="$icon" variant="micro" class="mt-0.5 shrink-0 text-gold" />
                <span>{{ $text }}</span>
            </li>
        @endforeach
    </ul>

    <x-ui.text class="text-xs">{{ __('Tipping is always free.') }}</x-ui.text>

    <x-ui.btn size="sm" class="w-full" variant="primary" :href="route('support')" wire:navigate>
        {{ $free ? __('Buy us a coffee') : ($active ? __('Extend premium') : __('From 5 zł a week')) }}
    </x-ui.btn>
</div>
