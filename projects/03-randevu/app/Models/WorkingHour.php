<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $weekday 1 = Pazartesi ... 7 = Pazar (ISO 8601)
 * @property bool $is_open
 * @property string $opens_at "09:00:00"
 * @property string $closes_at "18:00:00"
 */
#[Fillable(['weekday', 'is_open', 'opens_at', 'closes_at'])]
class WorkingHour extends Model
{
    public $timestamps = false;

    public const DAY_NAMES = [1 => 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_open' => 'boolean',
        ];
    }
}
