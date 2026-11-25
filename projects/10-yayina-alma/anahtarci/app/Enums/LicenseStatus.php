<?php

namespace App\Enums;

/**
 * Süresi dolmak bir durum DEĞİLDİR: ömür boyu lisansta güncelleme süresi biter, lisans çalışmaya devam eder.
 * Yalnızca iade veya kötüye kullanım lisansı iptal eder.
 */
enum LicenseStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
