<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Zapisuje odsłony stron publicznych (wzór z jokerpila.pl): wejścia GET na strony HTML (także przejścia
 * wire:navigate), bez panelu, żądań Livewire, robotów i adminów. Bez IP i bez ciasteczek (visitor_hash zmienia się codziennie).
 */
class TrackPageView
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|monitor|curl|wget|python|http-client/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            try {
                PageView::create([
                    'path' => Str::limit('/'.ltrim($request->path(), '/'), 250, ''),
                    'route_name' => $request->route()?->getName(),
                    'visitor_hash' => self::visitorHash($request),
                    'referrer_host' => $this->referrerHost($request),
                    'device' => $this->device((string) $request->userAgent()),
                    'locale' => app()->getLocale(),
                ]);
            } catch (Throwable) {
                // Statystyka nie może zepsuć strony (np. przed migracją).
            }
        }

        return $response;
    }

    /**
     * Skrót odwiedzającego zmieniający się codziennie: pozwala policzyć unikalne osoby w ciągu dnia,
     * ale nie śledzi nikogo dłużej i nie zapisuje adresu IP.
     */
    public static function visitorHash(Request $request): string
    {
        return hash('sha256', implode('|', [$request->ip(), $request->userAgent(), now()->toDateString(), config('app.key')]));
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->is('panel', 'panel/*', 'dashboard', 'dashboard/*', 'livewire/*', 'storage/*', 'up', 'manifest*', 'offline*')
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! preg_match(self::BOTS, (string) $request->userAgent())
            && ! $request->user()?->hasRole(RoleName::Admin->value);
    }

    private function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        return is_string($host) && $host !== $request->getHost() ? Str::limit($host, 120, '') : null;
    }

    private function device(string $userAgent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet/i', $userAgent) => 'tablet',
            (bool) preg_match('/mobi|android|iphone/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }
}
