<?php

namespace App\Notifications;

use App\Models\Matchday;
use App\Models\User;
use App\Support\Unfinished;
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

    /**
     * @param  list<array{set: string, label: string, squad: bool, questions: int}>|null  $missing  null: brak typu; lista: typ jest, ale brakuje składu lub odpowiedzi
     */
    public function __construct(public Matchday $matchday, public ?array $missing = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $matchday = $this->matchday;
        $unsubscribe = URL::signedRoute('email.reminders.off', ['user' => $notifiable instanceof User ? $notifiable->getKey() : 0]);

        $mail = (new MailMessage)
            ->subject($this->missing === null
                ? __('Matchday :number of LechTYPER: you have not tipped yet', ['number' => $matchday->number])
                : __('Matchday :number of LechTYPER: your squad or questions are missing', ['number' => $matchday->number]))
            ->greeting(__('Hello!'))
            ->line(__(':fixture, :date.', ['fixture' => $matchday->fixture, 'date' => $matchday->kickoff_at?->translatedFormat('l j F, H:i') ?? '']));

        if ($this->missing === null) {
            $mail->line(__('You have not tipped this matchday yet. It takes a minute: the score, your squad and the questions. Tipping closes at kick-off.'));
        } else {
            // Typ jest, ale bez składu i pytań traci się bramki ataku i obrony: lista braków po rozgrywkach.
            $mail->line(__('Your tip is saved, but without players and answers you lose attack and defence goals:'));
            foreach ($this->missing as $row) {
                $mail->line('- '.Unfinished::sentence([$row]));
            }
        }

        return $mail
            ->action($this->missing === null ? __('Tip now') : __('Finish my matchday'), route('tips'))
            ->line(__('Tip: turn on push notifications in the settings and we will remind you on your phone instead.'))
            ->line(__('Do not want these emails? [Unsubscribe](:url).', ['url' => $unsubscribe]))
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
