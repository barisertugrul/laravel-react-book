<?php

namespace App\Enums;

/** Kullanıcının abonelik planı ve bu plana bağlı sınırlar. */
enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Ücretsiz',
            self::Pro => 'Pro',
        };
    }

    /** Bu planda oluşturulabilecek en fazla kart sayısı. */
    public function cardLimit(): int
    {
        return match ($this) {
            self::Free => 1,
            self::Pro => 5,
        };
    }

    /** @return list<CardTheme> */
    public function themes(): array
    {
        return match ($this) {
            self::Free => [CardTheme::Klasik],
            self::Pro => CardTheme::cases(),
        };
    }

    /** React tarafına gönderilecek özet. */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'card_limit' => $this->cardLimit(),
            'themes' => array_map(fn (CardTheme $theme) => $theme->value, $this->themes()),
        ];
    }
}
