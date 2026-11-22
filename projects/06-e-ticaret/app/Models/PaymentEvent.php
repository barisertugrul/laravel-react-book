<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ödeme sağlayıcısından gelen her bildirimin kaydı (denetim ve tekrar koruması için).
 *
 * @property string $provider
 * @property string $event_id
 * @property string $type
 * @property array $payload
 */
#[Fillable(['provider', 'event_id', 'order_id', 'type', 'payload'])]
class PaymentEvent extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
