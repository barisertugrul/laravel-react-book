<?php

namespace App\Models;

use App\Enums\HealthStatus;
use App\Tenancy\BelongsToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bir sağlık kontrolünün sonucu. Kayıtlar değişmez: her kontrol yeni satırdır.
 *
 * @property int $id
 * @property int $site_id
 * @property HealthStatus $status
 * @property int|null $http_status
 * @property int|null $response_ms
 * @property Carbon|null $ssl_expires_at
 * @property list<array{level: string, code: string, message: string}> $issues
 * @property array<string, mixed>|null $report
 * @property string|null $error
 * @property Carbon $checked_at
 */
#[Fillable(['site_id', 'status', 'http_status', 'response_ms', 'ssl_expires_at', 'issues', 'report', 'error', 'checked_at'])]
class SiteCheck extends Model
{
    use BelongsToAgency;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => HealthStatus::class,
            'ssl_expires_at' => 'datetime',
            'issues' => 'array',
            'report' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
