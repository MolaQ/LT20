<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Wypisanie z maili przypominających o kolejce (App\Notifications\EveReminder) podpisanym linkiem, bez logowania.
 * GET z linku w mailu pokazuje potwierdzenie, POST to „Anuluj subskrypcję” w poczcie (List-Unsubscribe-Post).
 */
class EmailRemindersController extends Controller
{
    public function __invoke(Request $request, User $user): View|Response
    {
        $user->forceFill(['email_reminder_hours' => 0])->save();

        return $request->isMethod('POST') ? response()->noContent() : view('email-unsubscribed');
    }
}
