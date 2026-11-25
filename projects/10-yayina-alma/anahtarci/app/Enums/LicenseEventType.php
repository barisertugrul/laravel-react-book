<?php

namespace App\Enums;

enum LicenseEventType: string
{
    case Issued = 'issued';             // ödeme sonrası üretildi
    case Activated = 'activated';       // yeni sitede etkinleştirildi
    case Reactivated = 'reactivated';   // aynı sitede yeniden etkinleştirildi (eklenti yeniden kuruldu)
    case Deactivated = 'deactivated';   // site, eklentiden lisansı kaldırdı
    case Removed = 'removed';           // müşteri, siteyi portaldan kaldırdı
    case Rejected = 'rejected';         // reddedilen aktivasyon (iptal edilmiş lisans, site sınırı)
    case Revoked = 'revoked';           // yönetici iptal etti
    case Extended = 'extended';         // yönetici güncelleme süresini uzattı
}
