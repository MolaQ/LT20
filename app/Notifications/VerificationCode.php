<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Unverified;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Mail z 6-cyfrowym kodem potwierdzającym adres e-mail (App\Models\User::sendEmailVerificationNotification).
 * Przypomnienie (App\Support\Unverified::remind) ma inny temat i mówi, kiedy konto zniknie bez potwierdzenia.
 */
class VerificationCode extends Notification
{
    public function __construct(public string $code, public int $minutes, public bool $reminder = false) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->reminder
                ? __('Reminder: confirm your LechTYPER account (code :code)', ['code' => $this->code])
                : __('Your LechTYPER code: :code', ['code' => $this->code]))
            ->greeting(__('Hello!'));

        if ($this->reminder) {
            $mail->line(__('Your account is still waiting for the email confirmation. Without it you cannot tip, and after :days days from the sign-up the account is deleted.', ['days' => Unverified::DELETE_AFTER_DAYS]));
        }

        // Przycisk z podpisanym linkiem (App\Http\Controllers\VerifyLinkController) albo kod wpisany ręcznie.
        $link = URL::temporarySignedRoute('verification.link', now()->addDays(Unverified::DELETE_AFTER_DAYS), [
            'id' => $notifiable instanceof User ? $notifiable->getKey() : 0,
            'hash' => sha1($notifiable instanceof User ? $notifiable->getEmailForVerification() : ''),
        ]);

        return $mail
            ->line(__('Click the button to confirm your email address:'))
            ->action(__('Confirm my email'), $link)
            ->line(__('Or enter this code on the confirmation page (:url):', ['url' => route('verification.notice')]))
            ->line('**'.$this->code.'**')
            ->line(__('The code is valid for :minutes minutes.', ['minutes' => $this->minutes]))
            ->line(__('If you did not create an account, ignore this message.'));
    }
}
