<?php

use NotificationChannels\WebPush\PushSubscription;

// Powiadomienia push (laravel-notification-channels/webpush). Klucze VAPID: php artisan webpush:vapid (zapisuje je w .env).
// Bez kluczy powiadomienia są wyłączone (App\Support\Push::enabled), reszta serwisu działa normalnie.

return [

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    'model' => PushSubscription::class,

    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),

    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    'client_options' => [],

    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
