<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';    // ödendi, dönem sürüyor
    case PastDue = 'past_due'; // yenileme ödemesi alınamadı; ek süre içinde plan sürer, her gün yeniden denenir
    case Ended = 'ended';      // iptal edildi ya da ek süre bitti: ajans ücretsiz planda

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Etkin',
            self::PastDue => 'Ödeme bekleniyor',
            self::Ended => 'Sona erdi',
        };
    }
}
