<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $description
 * @property string $quantity "2.500"
 * @property int $unit_price
 * @property int $discount_rate
 * @property int $vat_rate
 * @property int $net
 * @property int $vat
 * @property int $total
 */
#[Fillable(['description', 'quantity', 'unit_price', 'discount_rate', 'vat_rate', 'net', 'vat', 'total', 'sort_order'])]
class InvoiceLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class,
            'net' => MoneyCast::class,
            'vat' => MoneyCast::class,
            'total' => MoneyCast::class,
            // SQLite ondalık sütunu sayı (2.5), MySQL metin ("2.500") döndürür; cast ikisini eşitler
            'quantity' => 'decimal:3',
            'discount_rate' => 'integer',
            'vat_rate' => 'integer',
        ];
    }
}
