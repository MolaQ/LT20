<?php

use App\Support\Premium;
use App\Support\Push;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Premium (etap 15): po dacie wygaśnięcia rola Premium znika. Harmonogram wymaga uruchamiania
// „php artisan schedule:run” co minutę (na Windowsie: Harmonogram zadań).
Artisan::command('premium:expire', function () {
    $this->info('Premium expired: '.Premium::expire());
})->purpose('Remove the Premium role after its expiry date');

Schedule::command('premium:expire')->hourly();

// Kolejka zadań (etap F: przeliczenie kolejki w tle z paskiem postępu). Na hostingu współdzielonym bez procesów w tle
// zadania wykonuje ten sam cron co harmonogram: co minutę worker kończy pracę, gdy kolejka jest pusta.
// Na VPS z Supervisorem ustaw QUEUE_VIA_SCHEDULER=false i uruchom stały „php artisan queue:work”.
if (config('queue.via_scheduler') && config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

// Powiadomienia push (App\Support\Push): „typowanie otwarte” i przypomnienie 2 godziny przed pierwszym gwizdkiem.
Artisan::command('push:matchdays', function () {
    $this->info('Push notifications sent: '.Push::tick());
})->purpose('Send matchday push notifications (tipping open, last call)');

Schedule::command('push:matchdays')->everyFiveMinutes()->withoutOverlapping();

// Stare odsłony stron (App\Models\PageView, ok. 13 miesięcy) i inne modele z Prunable.
Schedule::command('model:prune')->daily();
