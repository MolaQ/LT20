<?php

namespace App\Notifications;

use App\Models\Matchday;
use App\Models\User;
use App\Support\RecapMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * Mail z podsumowaniem kolejki (App\Support\RecapMail): wynik, typ i punkty, mecz ligowy, miejsce w tabeli,
 * sparing i następna kolejka. Na dole link wypisujący (także nagłówek List-Unsubscribe).
 */
class MatchdayRecap extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{tip: array{0: int, 1: int}|null, points: int, bonus: int, duel: array{mine: int, theirs: int, rival: string}|null, place: int|null, teams: int, league: string|null, friendly: array{mine: int, theirs: int, rival: string}|null}  $recap
     */
    public function __construct(public Matchday $matchday, public array $recap, public ?Matchday $next = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recap = $this->recap;
        $unsubscribe = URL::signedRoute('email.recap.off', ['user' => $notifiable instanceof User ? $notifiable->getKey() : 0]);

        $mail = (new MailMessage)
            ->subject(__('Matchday :number of LechTYPER: :score', ['number' => $this->matchday->number, 'score' => RecapMail::score($this->matchday)]))
            ->greeting(__('Matchday :number is settled!', ['number' => $this->matchday->number]))
            ->line('**'.RecapMail::score($this->matchday).'**');

        $mail->line($recap['tip']
            ? __('Your tip :tip: :points pts for the tip and :bonus bonus.', ['tip' => $recap['tip'][0].':'.$recap['tip'][1], 'points' => $recap['points'], 'bonus' => $recap['bonus']])
            : __('You did not tip this matchday.'));

        if ($recap['duel']) {
            $duel = $recap['duel'];
            $mail->line(__($duel['mine'] > $duel['theirs'] ? 'League: you beat :team :score.' : ($duel['mine'] < $duel['theirs'] ? 'League: you lost to :team :score.' : 'League: draw with :team :score.'),
                ['team' => $duel['rival'], 'score' => $duel['mine'].':'.$duel['theirs']]));
        }
        if ($recap['place']) {
            $mail->line(__(':league: :place. place of :teams.', ['place' => $recap['place'], 'teams' => $recap['teams'], 'league' => $recap['league']]));
        }
        if ($recap['friendly']) {
            $friendly = $recap['friendly'];
            $mail->line(__('Friendly with :team: :score.', ['team' => $friendly['rival'], 'score' => $friendly['mine'].':'.$friendly['theirs']]));
        }

        if ($this->next) {
            $mail->line(__('Next: matchday :number, :fixture, :date.', [
                'number' => $this->next->number,
                'fixture' => $this->next->fixture,
                'date' => $this->next->kickoff_at?->translatedFormat('l j F, H:i') ?? '',
            ]))->action(__('Tip the next matchday'), route('tips'));
        } else {
            $mail->action(__('See the results'), route('results'));
        }

        return $mail
            ->line(__('Do not want these emails? [Unsubscribe](:url).', ['url' => $unsubscribe]))
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
