<?php

use Illuminate\Support\Facades\Route;

// Ustawienia konta po samym zalogowaniu: niepotwierdzony e-mail widzi w nich pasek z kodem (settings.layout)
// i nie wypada poza stronę. Typowanie i panel nadal wymagają potwierdzonego adresu (middleware verified).
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');

    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->name('security.edit');

    Route::livewire('settings/notifications', 'pages::settings.notifications')->name('notifications.edit');
});
