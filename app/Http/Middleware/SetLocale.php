<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawia język strony: najpierw wybór z konta, potem z sesji (goście), potem język przeglądarki,
 * a na końcu APP_LOCALE. Obsługiwane języki są w config/club.php (locales).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::resolve($request));

        return $next($request);
    }

    public static function resolve(Request $request): string
    {
        $supported = array_map(strval(...), array_keys(config('club.locales', [])));

        $candidates = [
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->getPreferredLanguage($supported),
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && in_array($locale, $supported, true)) {
                return $locale;
            }
        }

        return config('app.locale');
    }
}
