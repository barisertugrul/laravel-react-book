<?php

namespace App\Enums;

/** Cari hesap türü: kime satış yapıyoruz, kimden alış yapıyoruz. */
enum ContactType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Müşteri',
            self::Supplier => 'Tedarikçi',
        };
    }
}
