<?php

namespace App\Models;

use App\Enums\OperationType;
use App\Enums\TargetStatus;
use App\Tenancy\BelongsToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Birden çok sitede çalışan tek komut. Her sitenin durumu OperationTarget satırındadır;
 * sayılar (kaç site bitti, kaçı hatalı) satırlardan hesaplanır, ayrıca tutulmaz:
 * aynı anda biten iki iş bir sayacı birlikte artırırken yanlış sayamaz.
 *
 * @property int $id
 * @property int|null $user_id
 * @property OperationType $type
 * @property string $label
 * @property array{source?: string, slug?: string, package_id?: int, file?: string, activate?: bool, key?: string} $params
 * @property string|null $batch_id
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'type', 'label', 'params', 'batch_id'])]
class Operation extends Model
{
    use BelongsToAgency;

    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            // Şifreli: lisans anahtarı da burada durur; veritabanı yedeği sızsa bile okunamaz (APP_KEY)
            'params' => 'encrypted:array',
            'finished_at' => 'datetime',
        ];
    }

    /** @return HasMany<OperationTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(OperationTarget::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Durumlara göre site sayıları: ['pending' => 2, 'succeeded' => 9, ...] + 'total'
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        $counts = $this->targets()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')
            ->pluck('aggregate', 'status')->map(fn ($n) => (int) $n);

        $summary = ['total' => $counts->sum()];
        foreach (TargetStatus::cases() as $status) {
            $summary[$status->value] = $counts[$status->value] ?? 0;
        }

        return $summary;
    }
}
