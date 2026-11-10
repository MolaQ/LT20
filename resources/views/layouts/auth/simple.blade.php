{{-- Logowanie, rejestracja i hasła w wyglądzie 2.0 (bez Flux): znak klubu nad kartą z formularzem. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @include('partials.club-theme')
    </head>
    <body class="min-h-svh bg-ui-bg font-sans text-ui-ink antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 px-4 py-10">
            <div class="flex w-full max-w-sm flex-col gap-5">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2" wire:navigate>
                    <x-app-logo-icon class="size-14" />
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <x-ui.card class="shadow-sm">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </x-ui.card>
            </div>
        </div>

        <x-ui.toast />
    </body>
</html>
