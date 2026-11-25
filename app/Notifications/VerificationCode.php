<?php

namespace App\Notifications;

use App\Support\Unverified;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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

        return $mail
            ->line(__('Enter this code to confirm your email address:'))
            ->line('**'.$this->code.'**')
            ->line(__('The code is valid for :minutes minutes.', ['minutes' => $this->minutes]))
            ->action(__('Enter the code'), route('verification.notice'))
            ->line(__('If you did not create an account, ignore this message.'));
    }
}
