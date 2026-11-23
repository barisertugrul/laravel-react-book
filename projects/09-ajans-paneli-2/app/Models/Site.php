<?php

namespace App\Models;

use App\Enums\HealthStatus;
use App\Tenancy\BelongsToAgency;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $api_url
 * @property string $agent_public_key
 * @property string $panel_secret_key
 * @property HealthStatus $health_status
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $last_checked_at
 * @property string|null $wp_version
 * @property string|null $php_version
 * @property string|null $agent_version
 * @property int $pending_updates
 * @property int|null $response_ms
 * @property Carbon|null $ssl_expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SiteCheck|null $latestCheck
 */
#[Fillable(['name', 'url', 'api_url', 'agent_public_key', 'panel_secret_key'])]
#[Hidden(['panel_secret_key'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use BelongsToAgency, HasFactory;

    protected $attributes = ['health_status' => 'unknown'];

    protected function casts(): array
    {
        return [
            'health_status' => HealthStatus::class,
            // Veritabanında APP_KEY ile şifreli durur; veritabanı yedeği sızsa bile anahtar okunamaz
            'panel_secret_key' => 'encrypted',
            'status_changed_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
        ];
    }

    /** @return HasMany<SiteCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(SiteCheck::class);
    }

    /** @return HasOne<SiteCheck, $this> */
    public function latestCheck(): HasOne
    {
        return $this->hasOne(SiteCheck::class)->latestOfMany('checked_at');
    }

    /** "https://ornek.com/wp-json/" + "ajans-asistani/v1/health" */
    public function endpoint(string $route): string
    {
        return $this->api_url.ltrim($route, '/');
    }
}
