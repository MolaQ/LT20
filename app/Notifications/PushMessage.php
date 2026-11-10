<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Powiadomienie push na telefon (App\Support\Push). Tytuł i treść to klucze tłumaczeń z parametrami,
 * tłumaczone przy wysyłce w języku gracza. tag: kolejne powiadomienie z tym samym tagiem zastępuje poprzednie.
 */
class PushMessage extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|int>  $titleParams
     * @param  array<string, string|int>  $bodyParams
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $tag,
        public array $titleParams = [],
        public array $bodyParams = [],
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__($this->title, $this->titleParams))
            ->body(__($this->body, $this->bodyParams))
            ->icon('/images/lechtyper-logo.png')
            ->badge('/images/lechtyper-logo.png')
            ->tag($this->tag)
            ->data(['url' => $this->url]);
    }
}
