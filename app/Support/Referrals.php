<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Źródło nowych kont i zaproszenia (kampania, 18.11.2026).
 *
 * Pierwsze wejście gościa zapisuje w sesji, skąd przyszedł (Facebook, Google, link zapraszający…), a rejestracja
 * przepisuje to do users.signup_source (statystyki w panelu). Link zapraszający to strona główna z ?z=<id gracza>:
 * za każdych PER_REWARD zaproszonych, którzy potwierdzą adres e-mail, zapraszający dostaje REWARD_DAYS dni premium.
 */
final class Referrals
{
    public const PER_REWARD = 3;

    public const REWARD_DAYS = 14;

    /** Źródło zaproszeń w users.signup_source. */
    public const INVITE = 'invite';

    private const SESSION_SOURCE = 'visit.source';

    private const SESSION_REFERRER = 'visit.referrer';

    /** Serwisy rozpoznawane po domenie strony, z której ktoś przyszedł (także subdomeny, np. m.facebook.com). */
    public const PLATFORMS = [
        'Facebook' => ['facebook.com', 'fb.com', 'fb.me', 'messenger.com'],
        'Instagram' => ['instagram.com'],
        'X' => ['twitter.com', 'x.com', 't.co'],
        'WhatsApp' => ['whatsapp.com', 'whatsapp.net'],
        'Google' => ['google.com', 'google.pl'],
        'YouTube' => ['youtube.com', 'youtu.be'],
        'TikTok' => ['tiktok.com'],
    ];

    /** Wartości utm_source oznaczające serwis. */
    private const UTM = [
        'facebook' => 'Facebook', 'fb' => 'Facebook', 'instagram' => 'Instagram', 'ig' => 'Instagram',
        'twitter' => 'X', 'x' => 'X', 'whatsapp' => 'WhatsApp', 'google' => 'Google', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
    ];

    /** Link zapraszający gracza. */
    public static function link(User $user): string
    {
        return route('home', ['z' => $user->id]);
    }

    /** Zapamiętuje w sesji gościa źródło pierwszego wejścia i zaproszenie (middleware RememberVisitSource). */
    public static function remember(Request $request): void
    {
        if (! $request->isMethod('GET') || ! $request->hasSession() || $request->user()) {
            return;
        }

        $session = $request->session();
        $referrer = (int) $request->query('z', '0');

        if ($referrer > 0 && ! $session->has(self::SESSION_REFERRER)) {
            $session->put(self::SESSION_REFERRER, $referrer);
            $session->put(self::SESSION_SOURCE, self::INVITE);

            return;
        }

        if (! $session->has(self::SESSION_SOURCE)) {
            $session->put(self::SESSION_SOURCE, self::sourceOf($request));
        }
    }

    /** Nazwa serwisu, host innej strony albo null (wejście bezpośrednie). */
    public static function sourceOf(Request $request): ?string
    {
        if ($request->query->has('fbclid')) {
            return 'Facebook';
        }

        $utm = mb_strtolower(trim((string) $request->query('utm_source', '')));
        if ($utm !== '') {
            return self::UTM[$utm] ?? Str::limit($utm, 60, '');
        }

        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if (! is_string($host) || $host === '' || $host === $request->getHost()) {
            return null;
        }

        return self::platform($host) ?? Str::limit((string) preg_replace('/^www\./', '', mb_strtolower($host)), 60, '');
    }

    /** Serwis o tej domenie (albo jej subdomenie) albo null. */
    public static function platform(string $host): ?string
    {
        $host = mb_strtolower($host);
        foreach (self::PLATFORMS as $name => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return $name;
                }
            }
        }

        return null;
    }

    /** Przy rejestracji: źródło i zapraszający z sesji (zapraszający musi istnieć). */
    public static function attach(User $user): void
    {
        $referrer = (int) session(self::SESSION_REFERRER, 0);
        $referrer = $referrer > 0 && $referrer !== $user->id && User::whereKey($referrer)->exists() ? $referrer : null;

        $source = session(self::SESSION_SOURCE);
        if ($source === self::INVITE && $referrer === null) {
            $source = null;
        }

        $user->forceFill(['signup_source' => $source, 'referred_by' => $referrer])->save();
        session()->forget([self::SESSION_SOURCE, self::SESSION_REFERRER]);
    }

    /**
     * Po potwierdzeniu adresu zaproszonego: zapraszający dostaje premium za każdą pełną trójkę potwierdzonych kont,
     * za którą jeszcze nie dostał. Zwraca nową datę wygaśnięcia premium albo null.
     */
    public static function reward(User $invited): ?CarbonInterface
    {
        $referrer = $invited->referred_by ? User::find($invited->referred_by) : null;
        if (! $referrer || ! Role::where('name', RoleName::Premium->value)->where('guard_name', 'web')->exists()) {
            return null;
        }

        $due = intdiv(self::confirmed($referrer), self::PER_REWARD) - (int) $referrer->referral_rewards;
        if ($due <= 0) {
            return null;
        }

        $referrer->forceFill(['referral_rewards' => (int) $referrer->referral_rewards + $due])->save();
        $until = Premium::extend($referrer, $due * self::REWARD_DAYS);
        Audit::log('premium.referral', $referrer, [], ['premium_until' => $until->toDateTimeString()],
            __('Premium for :days days for inviting :count players', ['days' => $due * self::REWARD_DAYS, 'count' => $due * self::PER_REWARD]));

        return $until;
    }

    /** Liczba zaproszonych z potwierdzonym adresem e-mail. */
    public static function confirmed(User $referrer): int
    {
        return User::where('referred_by', $referrer->id)->whereNotNull('email_verified_at')->count();
    }

    /** @return array{invited: int, confirmed: int, progress: int, days: int} */
    public static function stats(User $referrer): array
    {
        $confirmed = self::confirmed($referrer);

        return [
            'invited' => User::where('referred_by', $referrer->id)->count(),
            'confirmed' => $confirmed,
            'progress' => $confirmed % self::PER_REWARD,
            'days' => (int) $referrer->referral_rewards * self::REWARD_DAYS,
        ];
    }
}
