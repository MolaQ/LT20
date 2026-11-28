<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Wypisanie z maili przypominających o kolejce (App\Notifications\EveReminder) albo z podsumowań kolejki (MatchdayRecap) podpisanym linkiem, bez logowania.
 * GET z linku w mailu pokazuje potwierdzenie, POST to „Anuluj subskrypcję” w poczcie (List-Unsubscribe-Post).
 */
class EmailRemindersController extends Controller
{
    public function __invoke(Request $request, User $user): View|Response
    {
        // Ten sam link dla dwóch rodzajów maili: przypomnienie przed kolejką i podsumowanie po niej (RecapMail).
        $recap = $request->routeIs('email.recap.off');
        $user->forceFill($recap ? ['email_recap' => false] : ['email_reminder_hours' => 0])->save();

        return $request->isMethod('POST') ? response()->noContent() : view('email-unsubscribed', ['recap' => $recap]);
    }
}
