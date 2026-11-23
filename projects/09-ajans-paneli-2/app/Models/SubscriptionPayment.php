<?php

namespace App\Models;

use App\Tenancy\BelongsToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bir tahsilat denemesi. Kayıtlar değişmez: her deneme yeni satırdır.
 *
 * @property int $id
 * @property string $plan
 * @property int $amount
 * @property string $status
 * @property string|null $reference
 * @property string|null $failure_message
 * @property Carbon $created_at
 */
#[Fillable(['subscription_id', 'plan', 'amount', 'status', 'reference', 'failure_message'])]
class SubscriptionPayment extends Model
{
    use BelongsToAgency;

    public const UPDATED_AT = null;
}
