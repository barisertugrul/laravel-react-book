<?php

namespace App\Enums;

use App\Agent\AgentProtocol;

enum OperationType: string
{
    case Install = 'install'; // wordpress.org'dan ya da paket kütüphanesinden kur (kuruluysa üzerine yaz)
    case Update = 'update';   // wordpress.org'daki yeni sürüme güncelle
    case License = 'license'; // Ajans Asistanı lisansını etkinleştir (Anahtarcı, Bölüm 7)

    public function label(): string
    {
        return match ($this) {
            self::Install => 'Kur',
            self::Update => 'Güncelle',
            self::License => 'Lisans',
        };
    }

    /** Eklentideki karşılığı */
    public function route(): string
    {
        return match ($this) {
            self::Install => AgentProtocol::ROUTE_PLUGIN_INSTALL,
            self::Update => AgentProtocol::ROUTE_PLUGIN_UPDATE,
            self::License => AgentProtocol::ROUTE_LICENSE_ACTIVATE,
        };
    }
}
