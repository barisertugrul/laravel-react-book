<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

/**
 * Lisansın etkinleştirildiği bir site.
 *
 * Sanctum token'ları genellikle kullanıcılara verilir; burada SİTEYE veriyoruz.
 * HasApiTokens herhangi bir modele eklenebilir: auth:sanctum korumalı bir istekte
 * $request->user() bu durumda User değil, Activation döndürür.
 *
 * @property int $id
 * @property int $license_id
 * @property string $domain
 * @property string|null $ip
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable $created_at
 * @property-read License $license
 */
#[Fillable(['license_id', 'domain', 'ip', 'last_seen_at'])]
class Activation extends Model
{
    use HasApiTokens;

    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        // Site kaldırılınca token'ı da gider: eski kurulum artık API'yi kullanamaz.
        // (personal_access_tokens polimorfik olduğu için yabancı anahtarla silinemez.)
        static::deleting(fn (Activation $activation) => $activation->tokens()->delete());
    }

    /** @return BelongsTo<License, $this> */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
