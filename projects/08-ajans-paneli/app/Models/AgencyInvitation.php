<?php

namespace App\Models;

use App\Enums\AgencyRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Davet, davet edilen kişinin DAHA ÜYE OLMADIĞI ajansa aittir; bu yüzden ajans kapsamı
 * (BelongsToAgency) burada kullanılmaz. Erişimi imzalı bağlantı + e-posta eşleşmesi korur.
 *
 * @property int $id
 * @property int $agency_id
 * @property string $email
 * @property AgencyRole $role
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read Agency $agency
 * @property-read User|null $inviter
 */
#[Fillable(['email', 'role', 'invited_by', 'expires_at', 'accepted_at'])]
class AgencyInvitation extends Model
{
    protected function casts(): array
    {
        return [
            'role' => AgencyRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
