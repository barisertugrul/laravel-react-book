<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Son durum değişikliğini geri alır.
 * Geçmiş silinmez: geri alma da yeni bir olay olarak kaydedilir (kim, ne zaman, neyi geri aldı).
 */
class UndoLastStatusChange
{
    public function handle(Ticket $ticket, User $by): Ticket
    {
        $event = $ticket->latestEvent();

        if (! $event || $event->from_status === null) {
            throw ValidationException::withMessages(['undo' => 'Geri alınacak bir durum değişikliği yok.']);
        }

        // Zincirleme geri alma ("geri almayı geri al") karışıklık yaratır; izin vermiyoruz.
        if ($event->isRevert()) {
            throw ValidationException::withMessages(['undo' => 'Son işlem zaten bir geri alma. Durumu normal şekilde değiştirin.']);
        }

        DB::transaction(function () use ($ticket, $event, $by) {
            $ticket->update(['status' => $event->from_status]);

            $ticket->events()->create([
                'user_id' => $by->id,
                'from_status' => $event->to_status,
                'to_status' => $event->from_status,
                'note' => "Geri alındı: {$event->to_status->label()} → {$event->from_status->label()}",
                // Müşterinin görmediği bir değişikliğin geri alınması da müşteriye gösterilmez
                'is_public' => $event->is_public,
                'reverts_event_id' => $event->id,
            ]);
        });

        return $ticket;
    }
}
