<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Para hesaplarının tek kuralı: tutarlar KURUŞ cinsinden TAM SAYI (int) olarak tutulur.
 * 149,90 TL → 14990. Kayan noktalı sayılar (float) para için asla kullanılmaz
 * (bkz. bölüm sonundaki "Güvenlik incelemesi").
 */
final class Money
{
    /** "149.90", "149,90", "1.250,50" → 14990, 14990, 125050 */
    public static function parse(string|int|float $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        $value = trim((string) $value);

        // Türkçe biçim: binlik ayıracı nokta, ondalık ayıracı virgül
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Geçersiz tutar: {$value}");
        }

        // Metni ondalık noktadan bölerek çeviriyoruz; float'a hiç dönüştürmüyoruz.
        [$lira, $kurus] = array_pad(explode('.', ltrim($value, '-')), 2, '0');
        $cents = (int) $lira * 100 + (int) str_pad($kurus, 2, '0');

        return str_starts_with($value, '-') ? -$cents : $cents;
    }

    /** 125050 → "1.250,50 ₺" */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.number_format(intdiv($cents, 100), 0, ',', '.').','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT).' ₺';
    }

    /** 125050 → "1250.50": ödeme sağlayıcılarının API'lerinin beklediği biçim */
    public static function toDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Tam sayı aritmetiğiyle yüzde hesabı; yarım kuruşlar yukarı yuvarlanır.
     * Oranlar (KDV %20, iskonto %5) tam sayıdır.
     *   percentOf(14990, 20) → 2998    (14990 × 20 / 100)
     *   percentOf(333, 18)   → 60      (59,94 → 60)
     */
    public static function percentOf(int $cents, int $percent): int
    {
        return self::divideRounded($cents * $percent, 100);
    }

    /** Miktarı binde bir hassasiyetle tam sayıya çevirir: "2,5" → 2500, "1" → 1000 */
    public static function parseQuantity(string|int $value): int
    {
        $value = str_replace(',', '.', trim((string) $value));
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '0');

        return (int) $whole * 1000 + (int) str_pad(substr($fraction, 0, 3), 3, '0');
    }

    /** Birim fiyat × miktar (miktar binde bir cinsinden) */
    public static function multiply(int $cents, int $quantityMilli): int
    {
        return self::divideRounded($cents * $quantityMilli, 1000);
    }

    /** Tam sayı bölmesi, yarım değerler sıfırdan uzağa yuvarlanır (bankacı değil, "okul" yuvarlaması). */
    public static function divideRounded(int $dividend, int $divisor): int
    {
        $quotient = intdiv($dividend, $divisor);
        $remainder = $dividend % $divisor;

        if (abs($remainder) * 2 >= abs($divisor)) {
            $quotient += ($dividend < 0) !== ($divisor < 0) ? -1 : 1;
        }

        return $quotient;
    }
}
