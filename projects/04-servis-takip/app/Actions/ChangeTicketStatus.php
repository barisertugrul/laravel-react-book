<?php

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeTicketStatus
{
    /**
     * Müşteri e-postası bu kadar dakika gecikmeyle gönderilir.
     * Bu sürede değişiklik geri alınırsa e-posta hiç gitmez (bkz. TicketStatusChanged::shouldSend).
     */
    public const NOTIFY_DELAY_MINUTES = 5;

    public function handle(Ticket $ticket, TicketStatus $to, User $by, ?string $note = null, bool $isPublic = true, ?string $finalCost = null): Ticket
    {
        $from = $ticket->status;

        // Durum makinesinin kuralı: yalnızca izin verilen geçişler
        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "\"{$from->label()}\" durumundan \"{$to->label()}\" durumuna geçilemez.",
            ]);
        }

        if ($to === TicketStatus::Ready && blank($finalCost ?? $ticket->final_cost)) {
            throw ValidationException::withMessages([
                'final_cost' => 'Cihazı teslime hazır işaretlemeden önce ücreti girin.',
            ]);
        }

        DB::transaction(function () use ($ticket, $from, $to, $by, $note, $isPublic, $finalCost) {
            $ticket->update(array_filter([
                'status' => $to,
                'final_cost' => $finalCost,
                // Atanmamış kaydı ilk ilerleten teknisyen sahiplenir
                'assigned_to' => $ticket->assigned_to ?? $by->id,
            ], fn ($value) => $value !== null));

            $ticket->events()->create([
                'user_id' => $by->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'is_public' => $isPublic,
            ]);
        });

        if ($to->notifiesCustomer() && $ticket->customer->email) {
            $ticket->customer->notify(
                (new TicketStatusChanged($ticket, $to, $isPublic ? $note : null))
                    ->delay(now()->addMinutes(self::NOTIFY_DELAY_MINUTES)),
            );
        }

        return $ticket;
    }
}
