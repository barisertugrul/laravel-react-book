<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /** Teknisyenin kendi değişikliğini geri alabileceği süre (dakika) */
    public const UNDO_WINDOW_MINUTES = 15;

    /**
     * Diğer bütün kontrollerden önce çalışır.
     * true döndürürse izin verilir; null döndürürse ilgili metoda geçilir.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->assigned_to === null || $ticket->assigned_to === $user->id;
    }

    /** Durum değiştirmek için kayıt teknisyene atanmış olmalı. */
    public function update(User $user, Ticket $ticket): bool
    {
        return $ticket->assigned_to === $user->id;
    }

    /** Atanmamış bir kaydı teknisyen üzerine alabilir. */
    public function claim(User $user, Ticket $ticket): bool
    {
        return $ticket->assigned_to === null;
    }

    /**
     * Son durum değişikliğini geri alma. Yönetici her zaman (before());
     * teknisyen yalnızca kendi yaptığı son değişikliği, belirli bir süre içinde.
     */
    public function undo(User $user, Ticket $ticket): bool
    {
        $event = $ticket->latestEvent();

        return $event !== null
            && $event->user_id === $user->id
            && $event->created_at->gt(now()->subMinutes(self::UNDO_WINDOW_MINUTES));
    }

    /** Başkasına atama yalnızca yöneticinin işi (before() yöneticiye zaten izin verir). */
    public function assign(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
