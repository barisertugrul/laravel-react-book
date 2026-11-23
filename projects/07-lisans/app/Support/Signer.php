<?php

namespace App\Support;

use LogicException;

/**
 * Ed25519 imzaları (PHP'nin yerleşik sodium uzantısı).
 *
 * HMAC'ten (Bölüm 6) farkı: imzalamak için GİZLİ anahtar, doğrulamak için AÇIK anahtar gerekir.
 * Açık anahtarı WordPress eklentisine gömebiliriz; onu ele geçiren biri imza ÜRETEMEZ.
 * HMAC'te doğrulayan da aynı sırrı bilmek zorundadır; sır eklentideyse herkesindir.
 */
final class Signer
{
    /** Ham (64 bayt) imza döndürür */
    public static function sign(string $message): string
    {
        return sodium_crypto_sign_detached($message, self::secretKey());
    }

    public static function verify(string $signature, string $message, ?string $publicKey = null): bool
    {
        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey ?? self::publicKey());
    }

    /** Açık anahtar gizli anahtardan türetilir; ayrıca saklamaya gerek yok */
    public static function publicKey(): string
    {
        return sodium_crypto_sign_publickey_from_secretkey(self::secretKey());
    }

    /** Yeni bir anahtar çifti: [gizli, açık] (ham baytlar) */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [sodium_crypto_sign_secretkey($pair), sodium_crypto_sign_publickey($pair)];
    }

    private static function secretKey(): string
    {
        $key = base64_decode((string) config('licensing.signing_key'), true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new LogicException('LICENSE_SIGNING_KEY tanımlı değil veya geçersiz. Üretmek için: php artisan license:keygen');
        }

        return $key;
    }

    /** Adreslerde ve anahtarlarda sorun çıkarmayan base64 (+ / = yerine - _ ve dolgu yok) */
    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $text): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_-]*$/', $text)) {
            return null;
        }

        $decoded = base64_decode(strtr($text, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
