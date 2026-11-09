{{-- Potwierdzenie adresu e-mail kodem (komponent pages::auth.verify-code). --}}
<x-layouts::auth :title="__('Email verification')">
    <div class="flex flex-col gap-6">
        <livewire:pages::auth.verify-code />

        <form method="POST" action="{{ route('logout') }}" class="flex justify-center">
            @csrf
            <x-ui.btn variant="ghost" size="sm" type="submit" data-test="logout-button">{{ __('Log out') }}</x-ui.btn>
        </form>
    </div>
</x-layouts::auth>
