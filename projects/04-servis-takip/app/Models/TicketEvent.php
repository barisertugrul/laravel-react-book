<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property TicketStatus|null $from_status
 * @property TicketStatus $to_status
 * @property string|null $note
 * @property bool $is_public
 * @property int|null $reverts_event_id
 * @property int|null $user_id
 */
#[Fillable(['user_id', 'from_status', 'to_status', 'note', 'is_public', 'reverts_event_id', 'created_at'])]
class TicketEvent extends Model
{
    // Olaylar değiştirilmez; yalnızca oluşturulma zamanı tutulur.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => TicketStatus::class,
            'to_status' => TicketStatus::class,
            'is_public' => 'boolean',
        ];
    }

    /** Bu olay bir geri alma mı? */
    public function isRevert(): bool
    {
        return $this->reverts_event_id !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
