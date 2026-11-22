<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $invoice_id
 * @property CarbonImmutable $paid_at
 * @property int $amount
 * @property PaymentMethod $method
 * @property string|null $note
 */
#[Fillable(['invoice_id', 'user_id', 'paid_at', 'amount', 'method', 'note'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'paid_at' => 'immutable_date:Y-m-d',
            'amount' => MoneyCast::class,
            'method' => PaymentMethod::class,
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
