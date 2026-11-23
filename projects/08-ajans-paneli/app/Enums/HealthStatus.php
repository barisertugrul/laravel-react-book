<?php

namespace App\Enums;

enum HealthStatus: string
{
    case Unknown = 'unknown';   // henüz kontrol edilmedi
    case Ok = 'ok';
    case Warning = 'warning';   // ilgilenmek gerek ama site çalışıyor
    case Critical = 'critical'; // site erişilemiyor ya da ciddi bir sorun var

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Bilinmiyor',
            self::Ok => 'Sağlıklı',
            self::Warning => 'Uyarı',
            self::Critical => 'Kritik',
        };
    }

    /** Karşılaştırma için ağırlık: en kötü bulgu sitenin durumunu belirler */
    public function severity(): int
    {
        return match ($this) {
            self::Unknown => 0,
            self::Ok => 1,
            self::Warning => 2,
            self::Critical => 3,
        };
    }
}
