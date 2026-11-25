<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Potwierdzenie adresu e-mail przyciskiem z maila (obok 6-cyfrowego kodu): podpisany link ważny tyle,
 * ile żyje niepotwierdzone konto (App\Support\Unverified::DELETE_AFTER_DAYS). Działa bez logowania i na innym
 * urządzeniu. Hash adresu w linku sprawia, że link do starego adresu nie potwierdzi nowego po zmianie maila.
 */
class VerifyLinkController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);
        abort_unless($user && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->confirmEmail();
            event(new Verified($user));
        }

        if ($request->user()?->is($user)) {
            session()->flash('status', 'email-verified');

            return redirect()->intended(route('home'));
        }

        return redirect()->route('login')->with('status', __('Your email is confirmed. Log in to play.'));
    }
}
