<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Zapisuje ostatnią aktywność zalogowanego konta (users.last_seen_at) do kolumny „Ostatnio widziany” w panelu.
 * Najwyżej raz na EVERY sekund, zapytaniem bez zdarzeń modelu i bez zmiany updated_at.
 */
class TrackLastSeen
{
    /** Co ile sekund najwyżej odświeżamy znacznik. */
    public const EVERY = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subSeconds(self::EVERY)))) {
            try {
                $now = now();
                DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $now]);
                $user->setAttribute('last_seen_at', $now);
                $user->syncOriginalAttribute('last_seen_at');
            } catch (Throwable) {
                // Znacznik nie może zepsuć strony (np. przed migracją).
            }
        }

        return $next($request);
    }
}
