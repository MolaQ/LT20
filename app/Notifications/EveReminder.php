<?php

namespace App\Notifications;

use App\Models\Matchday;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * Mail „wkrótce mecz, nie masz typu” (App\Support\EveMail). Na dole link wypisujący z takich maili jednym kliknięciem
 * (także nagłówek List-Unsubscribe, który poczta pokazuje jako „Anuluj subskrypcję”).
 */
class EveReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Matchday $matchday) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $matchday = $this->matchday;
        $unsubscribe = URL::signedRoute('email.reminders.off', ['user' => $notifiable instanceof User ? $notifiable->getKey() : 0]);

        return (new MailMessage)
            ->subject(__('Matchday :number of LechTYPER: you have not tipped yet', ['number' => $matchday->number]))
            ->greeting(__('Hello!'))
            ->line(__(':fixture, :date.', ['fixture' => $matchday->fixture, 'date' => $matchday->kickoff_at?->translatedFormat('l j F, H:i') ?? '']))
            ->line(__('You have not tipped this matchday yet. It takes a minute: the score, your squad and the questions. Tipping closes at kick-off.'))
            ->action(__('Tip now'), route('tips'))
            ->line(__('Tip: turn on push notifications in the settings and we will remind you on your phone instead.'))
            ->line(__('Do not want these emails? [Unsubscribe](:url).', ['url' => $unsubscribe]))
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
