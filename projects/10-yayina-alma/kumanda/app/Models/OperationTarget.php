<?php

namespace App\Models;

use App\Enums\TargetStatus;
use App\Tenancy\BelongsToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $operation_id
 * @property int $site_id
 * @property TargetStatus $status
 * @property string|null $message
 * @property array{file?: string, name?: string, version?: string|null, active?: bool}|null $result
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read Operation $operation
 * @property-read Site $site
 */
#[Fillable(['operation_id', 'site_id'])]
class OperationTarget extends Model
{
    use BelongsToAgency;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => TargetStatus::class,
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Operation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function markRunning(): void
    {
        $this->forceFill(['status' => TargetStatus::Running, 'started_at' => now()])->save();
    }

    /** @param array<string, mixed> $result */
    public function markSucceeded(array $result): void
    {
        $this->finish(TargetStatus::Succeeded, self::describe($result), $result);
    }

    /**
     * Eklentinin yanıtından tek satırlık sonuç:
     * "Contact Form 7 6.2.6 (etkin)" ya da "Lisans etkin · 5 site (4/5 kullanımda)"
     *
     * @param  array<string, mixed>  $result
     */
    private static function describe(array $result): string
    {
        if (isset($result['state'])) {
            $usage = isset($result['max_sites']) ? " ({$result['sites_used']}/{$result['max_sites']} kullanımda)" : ' (sınırsız)';

            return ($result['state'] === 'active' ? 'Lisans etkin' : 'Lisans geçersiz').' · '.($result['plan'] ?? '').$usage;
        }

        $version = isset($result['version']) ? " {$result['version']}" : '';
        $active = ($result['active'] ?? false) ? ' (etkin)' : '';

        return ($result['name'] ?? 'Eklenti').$version.$active;
    }

    public function markFailed(string $message): void
    {
        $this->finish(TargetStatus::Failed, $message);
    }

    public function markCancelled(): void
    {
        $this->finish(TargetStatus::Cancelled, 'İşlem iptal edildi.');
    }

    /** @param array<string, mixed>|null $result */
    private function finish(TargetStatus $status, string $message, ?array $result = null): void
    {
        $this->forceFill([
            'status' => $status,
            'message' => Str::limit($message, 490),
            'result' => $result,
            'finished_at' => now(),
        ])->save();
    }

    /** Yayın ve sayfa için tek biçim @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'site_name' => $this->site->name,
            'site_url' => $this->site->url,
            'status' => $this->status->value,
            'message' => $this->message,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
