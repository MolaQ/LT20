<?php

use App\Enums\Permission;
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

// Te same ustawienia w panelu admina (link „Ustawienia” w menu bocznym panelu): układ panelu zamiast strony.
Route::middleware(['auth', 'verified', 'permission:'.Permission::DashboardAccess->value])
    ->prefix('panel/settings')->name('dashboard.settings.')->group(function () {
        Route::livewire('profile', 'pages::settings.profile')->name('profile');
        Route::livewire('security', 'pages::settings.security')->name('security');
        Route::livewire('notifications', 'pages::settings.notifications')->name('notifications');
        Route::livewire('appearance', 'pages::settings.appearance')->name('appearance');
    });
