<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';       // ödeme bekleniyor; stok ayrıldı
    case Paid = 'paid';             // ödeme onaylandı; hazırlanacak
    case Shipped = 'shipped';       // kargoya verildi
    case Cancelled = 'cancelled';   // ödeme başarısız ya da süresi doldu; stok iade edildi

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ödeme bekleniyor',
            self::Paid => 'Hazırlanıyor',
            self::Shipped => 'Kargoda',
            self::Cancelled => 'İptal edildi',
        };
    }
}
