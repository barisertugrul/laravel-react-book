<?php

namespace App\Support;

/**
 * Bir sitenin adresini karşılaştırılabilir tek bir biçime getirir.
 * Aksi halde "https://www.Ornek.com/" ve "ornek.com" iki ayrı site sayılır ve bir site iki hak harcar.
 */
final class Domain
{
    /** "https://www.Örnek.com.tr:8080/blog" → "xn--rnek-4qa.com.tr"; geçersizse null */
    public static function normalize(string $value): ?string
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = "http://{$value}";
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = preg_replace('/^www\./', '', rtrim($host, '.'));

        // Türkçe karakterli alan adları (IDN) ASCII biçimine (punycode) çevrilir
        if (preg_match('/[^\x20-\x7e]/', $host)) {
            $host = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;

            if ($host === false) {
                return null;
            }
        }

        // Her etiket: harf/rakam ile başlar ve biter, arada tire olabilir, en fazla 63 karakter
        $label = '[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?';

        return strlen($host) <= 253 && preg_match("/^{$label}(\\.{$label})*$/", $host) ? $host : null;
    }
}
