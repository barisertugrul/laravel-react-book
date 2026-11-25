<?php

namespace App\Support;

use App\Models\License;

/**
 * İmzalı lisans anahtarı: ANH1.<yük>.<imza>
 *
 *   yük  = base64url(JSON) → {"lid":42,"prd":"ajans-asistani","iat":"2026-11-23"}
 *   imza = base64url(Ed25519("ANH1.<yük>"))
 *
 * Anahtar rastgele bir dize değil, sunucunun imzaladığı küçük bir belgedir. Böylece:
 * - Uydurma bir anahtar, veritabanına hiç sorulmadan reddedilir.
 * - WordPress eklentisi, açık anahtarla, internete çıkmadan biçimi ve ürünü doğrulayabilir.
 * Değişebilen bilgiler (site sınırı, güncelleme süresi, iptal) anahtara YAZILMAZ; sunucuda kalır.
 */
final class LicenseKey
{
    public static function issue(License $license): string
    {
        $prefix = config('licensing.key_prefix');

        $payload = Signer::base64UrlEncode(json_encode([
            'lid' => $license->id,
            'prd' => $license->plan->product->slug,
            'iat' => $license->created_at->toDateString(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $signature = Signer::sign("{$prefix}.{$payload}");

        return "{$prefix}.{$payload}.".Signer::base64UrlEncode($signature);
    }

    /**
     * İmza geçerliyse yükü, değilse null döndürür.
     * Dikkat: geçerli bir imza lisansın HÂLÂ GEÇERLİ olduğunu söylemez; yalnızca anahtarı bizim ürettiğimizi söyler.
     *
     * @return array{lid: int, prd: string, iat: string}|null
     */
    public static function parse(string $key): ?array
    {
        $parts = explode('.', trim($key));

        if (count($parts) !== 3 || $parts[0] !== config('licensing.key_prefix')) {
            return null;
        }

        [$prefix, $payload, $encodedSignature] = $parts;
        $signature = Signer::base64UrlDecode($encodedSignature);

        if ($signature === null || ! Signer::verify($signature, "{$prefix}.{$payload}")) {
            return null;
        }

        $data = json_decode(Signer::base64UrlDecode($payload) ?? '', true);

        return is_array($data) && is_int($data['lid'] ?? null) && is_string($data['prd'] ?? null) ? $data : null;
    }
}
