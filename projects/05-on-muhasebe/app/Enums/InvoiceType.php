<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Sales = 'sales';
    case Purchase = 'purchase';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Satış',
            self::Purchase => 'Alış',
        };
    }

    /** Fatura numarası öneki: SAT-2026-0001, ALS-2026-0001 */
    public function prefix(): string
    {
        return match ($this) {
            self::Sales => 'SAT',
            self::Purchase => 'ALS',
        };
    }
}
