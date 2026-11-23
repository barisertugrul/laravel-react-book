<?php

namespace App\Actions;

use App\Enums\LicenseEventType;
use App\Exceptions\LicenseApiException;
use App\Models\Activation;
use App\Models\License;
use App\Support\Domain;
use App\Support\LicenseKey;
use Illuminate\Support\Facades\DB;

class ActivateLicense
{
    /**
     * Bir siteyi lisansa bağlar ve o siteye özel bir API token'ı üretir.
     * Aynı site tekrar etkinleştirilirse (eklenti yeniden kuruldu) yeni yer harcanmaz, token yenilenir.
     *
     * @return array{activation: Activation, token: string, created: bool}
     */
    public function handle(string $key, string $domain, ?string $ip = null): array
    {
        $domain = Domain::normalize($domain) ?? throw LicenseApiException::invalidDomain();

        // 1. Anahtarı biz mi ürettik? (Veritabanına gitmeden, yalnızca imzayla)
        $payload = LicenseKey::parse($key) ?? throw LicenseApiException::invalidKey();

        // 2. Lisans hâlâ var mı ve anahtar birebir aynı mı?
        $license = License::find($payload['lid']);

        if (! $license || ! hash_equals((string) $license->key, trim($key))) {
            throw LicenseApiException::notFound();
        }

        // 3. Lisans geçerli mi? İmza yalnızca "bizim anahtarımız" der; iade edilmiş olabilir.
        if (! $license->isActive()) {
            $license->log(LicenseEventType::Rejected, $domain, $ip, ['reason' => 'revoked']);

            throw LicenseApiException::revoked();
        }

        $result = DB::transaction(function () use ($license, $domain, $ip) {
            // 4. Lisans satırını kilitle: aynı lisansla aynı anda gelen aktivasyonlar SIRAYLA sayılır.
            // Kilitsiz "say, sonra ekle", eşzamanlı isteklerde site sınırını aşar (bkz. Güvenlik incelemesi).
            $license = License::lockForUpdate()->findOrFail($license->id);

            $activation = $license->activations()->where('domain', $domain)->first();
            $created = $activation === null;

            if ($created) {
                if (! $license->isUnlimited() && $license->activations()->count() >= $license->max_sites) {
                    return null; // sınır dolu; istisnayı transaction dışında atıyoruz (aşağıda)
                }

                $activation = $license->activations()->create(['domain' => $domain]);
            }

            // Eski kurulumun token'ı geçersiz olur; her sitenin tek bir geçerli token'ı vardır
            $activation->tokens()->delete();
            $activation->update(['ip' => $ip, 'last_seen_at' => now()]);
            $token = $activation->createToken("site:{$domain}", ['license:check'])->plainTextToken;

            $license->log($created ? LicenseEventType::Activated : LicenseEventType::Reactivated, $domain, $ip);

            return ['activation' => $activation, 'token' => $token, 'created' => $created];
        });

        if ($result === null) {
            // Transaction içinde atılan istisna, içindeki her yazmayı geri alırdı; bu kaydı da.
            $license->log(LicenseEventType::Rejected, $domain, $ip, ['reason' => 'site_limit']);

            throw LicenseApiException::siteLimitReached($license->max_sites);
        }

        return $result;
    }
}
