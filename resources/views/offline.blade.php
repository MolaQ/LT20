{{-- Strona bez internetu (service worker pokazuje ją, gdy strony nie ma w pamięci telefonu). Bez bazy i bez Livewire. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['title' => __('Offline')])
    @include('partials.club-theme')
</head>

<body class="grid min-h-dvh place-items-center bg-ui-bg p-6 text-center font-ui text-ui-ink">
    <div class="flex max-w-sm flex-col items-center gap-4">
        <img src="{{ asset(config('club.logo')) }}" alt="" class="size-20 object-contain">
        <h1 class="font-display text-3xl font-extrabold">{{ __('No connection') }}</h1>
        <p class="text-ui-muted">{{ __('Pages you opened recently are still available. Your tips are saved when you are back online.') }}</p>
        <button type="button" onclick="location.reload()" class="rounded-2xl bg-club px-6 py-3 font-display text-lg font-semibold uppercase text-club-ink">{{ __('Try again') }}</button>
    </div>
</body>

</html>
