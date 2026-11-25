<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Domena adresu e-mail musi istnieć (rekord MX albo A w DNS), a popularne literówki (gmial.com, wp.p …)
 * dostają podpowiedź poprawnej domeny. Zły adres odpada przy rejestracji, zanim kod pójdzie w próżnię.
 * Sprawdzanie DNS wyłącza config('auth.email_dns_check') (testy działają bez sieci).
 */
class RealEmailDomain implements ValidationRule
{
    /** @param  string|null  $current  dotychczasowy adres (zmiana profilu): bez zmiany adresu nie sprawdzamy DNS */
    public function __construct(private ?string $current = null) {}

    /** Częste literówki w domenach => poprawna domena. */
    public const TYPOS = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmal.com' => 'gmail.com', 'gamil.com' => 'gmail.com',
        'gmail.co' => 'gmail.com', 'gmail.pl' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gmail.cm' => 'gmail.com', 'gmail.con' => 'gmail.com',
        'wp.p' => 'wp.pl', 'wp.pll' => 'wp.pl', 'o2.p' => 'o2.pl', 'onet.p' => 'onet.pl', 'interia.p' => 'interia.pl',
        'yahoo.co' => 'yahoo.com', 'hotmail.co' => 'hotmail.com', 'outlok.com' => 'outlook.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Tablice i inne typy odrzucają już reguły string/email; tu tylko je pomijamy.
        if (! is_string($value) || ($this->current !== null && strcasecmp($value, $this->current) === 0)) {
            return;
        }

        $domain = strtolower(substr(strrchr($value, '@') ?: '', 1));
        if ($domain === '') {
            return;
        }

        if (isset(self::TYPOS[$domain])) {
            $fail(__('Check the address: did you mean :domain?', ['domain' => self::TYPOS[$domain]]));

            return;
        }

        if (config('auth.email_dns_check', true) && ! self::receivesMail($domain)) {
            $fail(__('The domain :domain does not receive email. Check the address.', ['domain' => $domain]));
        }
    }

    /**
     * Czy domena przyjmuje pocztę: rekord MX (ale nie „null MX” z celem „.”, RFC 7505, który wprost mówi „nie przyjmuję”),
     * a bez MX rekord A albo AAAA (poczta idzie wtedy na adres domeny).
     */
    private static function receivesMail(string $domain): bool
    {
        $mx = @dns_get_record($domain.'.', DNS_MX) ?: [];
        if ($mx !== []) {
            foreach ($mx as $record) {
                if (rtrim((string) ($record['target'] ?? ''), '.') !== '') {
                    return true;
                }
            }

            return false;
        }

        return checkdnsrr($domain.'.', 'A') || checkdnsrr($domain.'.', 'AAAA');
    }
}
