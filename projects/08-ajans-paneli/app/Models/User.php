<?php

namespace App\Models;

use App\Enums\AgencyRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $current_agency_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agency|null $currentAgency
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Agency, $this> */
    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class)->withPivot('role')->withTimestamps();
    }

    /** @return BelongsTo<Agency, $this> */
    public function currentAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'current_agency_id');
    }

    public function belongsToAgency(Agency $agency): bool
    {
        return $this->agencies()->whereKey($agency->id)->exists();
    }

    /** Üye değilse null */
    public function roleIn(Agency $agency): ?AgencyRole
    {
        $role = $this->agencies()->whereKey($agency->id)->first()?->pivot?->getAttribute('role');

        return $role ? AgencyRole::from($role) : null;
    }

    public function switchAgency(Agency $agency): void
    {
        $this->forceFill(['current_agency_id' => $agency->id])->save();
        $this->setRelation('currentAgency', $agency);
    }
}
