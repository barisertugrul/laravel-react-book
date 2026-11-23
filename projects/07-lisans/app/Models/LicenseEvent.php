<?php

namespace App\Models;

use App\Enums\LicenseEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property LicenseEventType $type
 * @property string|null $domain
 * @property string|null $ip
 * @property array|null $meta
 * @property CarbonImmutable $created_at
 */
#[Fillable(['type', 'domain', 'ip', 'meta', 'created_at'])]
class LicenseEvent extends Model
{
    // Olay kaydı yalnızca eklenir, değiştirilmez: updated_at yok
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => LicenseEventType::class,
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
