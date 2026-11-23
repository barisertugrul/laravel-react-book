<?php

namespace App\Support;

/**
 * Ed25519 imzaları (sodium; uzantı yoksa paragonie/sodium_compat).
 *
 * Bölüm 7'deki Signer'ın genelleştirilmiş hali: orada TEK bir anahtar çifti vardı ve
 * gizli anahtar .env'den okunuyordu. Burada her sitenin kendi anahtar çifti var;
 * anahtar artık parametre olarak verilir.
 */
final class Signer
{
    /** Ham (64 bayt) imza döndürür */
    public static function sign(string $message, string $secretKey): string
    {
        return sodium_crypto_sign_detached($message, $secretKey);
    }

    public static function verify(string $signature, string $message, string $publicKey): bool
    {
        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    /** Yeni bir anahtar çifti: [gizli, açık] (ham baytlar) */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [sodium_crypto_sign_secretkey($pair), sodium_crypto_sign_publickey($pair)];
    }

    /** Adreslerde ve başlıklarda sorun çıkarmayan base64 (+ / = yerine - _ ve dolgu yok) */
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
