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
    /** Częste literówki w domenach => poprawna domena. */
    public const TYPOS = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmal.com' => 'gmail.com', 'gamil.com' => 'gmail.com',
        'gmail.co' => 'gmail.com', 'gmail.pl' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gmail.cm' => 'gmail.com', 'gmail.con' => 'gmail.com',
        'wp.p' => 'wp.pl', 'wp.pll' => 'wp.pl', 'o2.p' => 'o2.pl', 'onet.p' => 'onet.pl', 'interia.p' => 'interia.pl',
        'interia.com' => 'interia.pl', 'yahoo.co' => 'yahoo.com', 'hotmail.co' => 'hotmail.com', 'outlok.com' => 'outlook.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower((string) substr(strrchr((string) $value, '@') ?: '', 1));
        if ($domain === '') {
            return;
        }

        if (isset(self::TYPOS[$domain])) {
            $fail(__('Check the address: did you mean :domain?', ['domain' => self::TYPOS[$domain]]));

            return;
        }

        if (config('auth.email_dns_check', true) && ! checkdnsrr($domain.'.', 'MX') && ! checkdnsrr($domain.'.', 'A')) {
            $fail(__('The domain :domain does not receive email. Check the address.', ['domain' => $domain]));
        }
    }
}
