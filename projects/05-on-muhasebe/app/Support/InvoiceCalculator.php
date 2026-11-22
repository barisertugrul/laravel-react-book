<?php

namespace App\Support;

/**
 * Fatura satırlarının ve toplamların hesabı. Hepsi kuruş cinsinden tam sayı.
 *
 * Her satır için:
 *   brüt      = birim fiyat × miktar
 *   iskonto   = brüt × iskonto oranı
 *   matrah    = brüt − iskonto
 *   KDV       = matrah × KDV oranı
 *   toplam    = matrah + KDV
 *
 * Yuvarlama SATIR bazında yapılır; fatura toplamları satırların toplamıdır.
 * Böylece faturadaki her satır kendi başına doğru ve satırların toplamı faturanın toplamına eşittir.
 */
final class InvoiceCalculator
{
    /**
     * @param  array{quantity: string|int, unit_price: int, discount_rate: int, vat_rate: int}  $line  unit_price kuruş cinsinden
     * @return array{gross: int, discount: int, net: int, vat: int, total: int}
     */
    public static function line(array $line): array
    {
        $gross = Money::multiply($line['unit_price'], Money::parseQuantity($line['quantity']));
        $discount = Money::percentOf($gross, (int) $line['discount_rate']);
        $net = $gross - $discount;
        $vat = Money::percentOf($net, (int) $line['vat_rate']);

        return [
            'gross' => $gross,
            'discount' => $discount,
            'net' => $net,
            'vat' => $vat,
            'total' => $net + $vat,
        ];
    }

    /**
     * @param  list<array>  $lines
     * @return array{subtotal: int, discount_total: int, vat_total: int, total: int, vat_breakdown: array<int, int>}
     */
    public static function totals(array $lines): array
    {
        $totals = ['subtotal' => 0, 'discount_total' => 0, 'vat_total' => 0, 'total' => 0, 'vat_breakdown' => []];

        foreach ($lines as $line) {
            $l = self::line($line);
            $totals['subtotal'] += $l['gross'];
            $totals['discount_total'] += $l['discount'];
            $totals['vat_total'] += $l['vat'];
            $totals['total'] += $l['total'];
            // Faturalarda KDV, oranına göre ayrı ayrı gösterilir (%10: ..., %20: ...)
            $rate = (int) $line['vat_rate'];
            $totals['vat_breakdown'][$rate] = ($totals['vat_breakdown'][$rate] ?? 0) + $l['vat'];
        }

        ksort($totals['vat_breakdown']);

        return $totals;
    }
}
