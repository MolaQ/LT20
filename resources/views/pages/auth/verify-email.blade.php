{{-- Potwierdzenie adresu e-mail kodem (komponent pages::auth.verify-code) w zwykłym układzie strony. --}}
<x-layouts::public :title="__('Email verification')">
    <div class="mx-auto flex w-full max-w-md flex-col gap-6">
        <x-ui.heading size="xl" level="1" class="text-center">{{ __('Email verification') }}</x-ui.heading>
        <livewire:pages::auth.verify-code />

        <form method="POST" action="{{ route('logout') }}" class="flex justify-center">
            @csrf
            <x-ui.btn variant="ghost" size="sm" type="submit" data-test="logout-button">{{ __('Log out') }}</x-ui.btn>
        </form>
    </div>
</x-layouts::public>
