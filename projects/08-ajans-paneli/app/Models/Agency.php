<?php

namespace App\Models;

use App\Enums\AgencyRole;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Kiracı (tenant). Kendisi BelongsToAgency kullanmaz: ajansın kendisi kapsamın dışındadır.
 *
 * @property int $id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name'])]
class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory;

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<AgencyInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(AgencyInvitation::class);
    }

    /**
     * Ajansın siteleri. Site modelinin global scope'u yine çalışır; ajans bağlamı dışında
     * (ör. komut satırında) kullanılacaksa önce bağlam kurulmalıdır: CurrentAgency::run().
     *
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function addMember(User $user, AgencyRole $role): void
    {
        $this->users()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }

    /** @return list<User> */
    public function owners(): array
    {
        return $this->users()->wherePivot('role', AgencyRole::Owner->value)->get()->all();
    }
}
