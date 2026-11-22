<?php

namespace App\Enums;

enum CardTheme: string
{
    case Klasik = 'klasik';
    case Gece = 'gece';
    case Okyanus = 'okyanus';

    public function label(): string
    {
        return match ($this) {
            self::Klasik => 'Klasik',
            self::Gece => 'Gece',
            self::Okyanus => 'Okyanus',
        };
    }
}
