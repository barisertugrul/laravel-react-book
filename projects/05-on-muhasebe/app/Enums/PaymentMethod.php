<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case CreditCard = 'credit_card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::BankTransfer => 'Havale / EFT',
            self::CreditCard => 'Kredi kartı',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
