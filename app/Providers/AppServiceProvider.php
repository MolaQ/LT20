<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Players;
use App\Support\Seo;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

/**
 * Ustawienia aplikacji przy starcie: daty niezmienne, blokada niszczących poleceń i reguły haseł na produkcji.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Opis i obrazek strony (App\Support\Seo) nie przechodzą na następne żądanie (kolejka, testy).
        $this->app->terminating(fn () => Seo::reset());

        // Nowe konto po potwierdzeniu adresu e-mail dostaje rolę User i od razu trafia na listę sezonu.
        Event::listen(Verified::class, function (Verified $event): void {
            if ($event->user instanceof User) {
                Players::approveVerified($event->user);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
