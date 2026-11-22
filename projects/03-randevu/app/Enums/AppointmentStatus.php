<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Onaylandı',
            self::Completed => 'Tamamlandı',
            self::Cancelled => 'İptal edildi',
        };
    }

    /** Takvimde yer kaplayan, yani o saati dolu gösteren durumlar. */
    public function blocksTime(): bool
    {
        return $this !== self::Cancelled;
    }
}
