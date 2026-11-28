{{-- Potwierdzenie wypisania z maili przypominających (App\Http\Controllers\EmailRemindersController). Bez Livewire. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['title' => __('Email reminders are off')])
    @include('partials.club-theme')
</head>

<body class="grid min-h-dvh place-items-center bg-ui-bg p-6 text-center font-ui text-ui-ink">
    <div class="flex max-w-sm flex-col items-center gap-4">
        <img src="{{ asset(config('club.logo')) }}" alt="" class="size-20 object-contain">
        <h1 class="font-display text-3xl font-extrabold">{{ __('Email reminders are off') }}</h1>
        <p class="text-ui-muted">{{ __('We will not email you before matchdays any more. You can turn the reminders back on in the notification settings.') }}</p>
        <a href="{{ route('home') }}" class="rounded-2xl bg-club px-6 py-3 font-display text-lg font-semibold uppercase text-club-ink">{{ __('Back to the game') }}</a>
    </div>
</body>

</html>
