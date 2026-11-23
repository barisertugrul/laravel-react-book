<?php

namespace App\Enums;

enum AgencyRole: string
{
    case Owner = 'owner';   // siteleri siler, ekibi ve ajans ayarlarını yönetir
    case Member = 'member'; // siteleri ekler, izler, kontrol başlatır

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Sahip',
            self::Member => 'Üye',
        };
    }
}
