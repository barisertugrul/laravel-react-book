<?php

namespace App\Agent;

/**
 * Panel ile Ajans Asistanı eklentisi arasındaki protokolün (AA1) ortak tanımları.
 * Eklentideki class-protocol.php AYNI kuralları uygular; biri değişirse öteki de değişmeli.
 */
final class AgentProtocol
{
    public const VERSION = 'AA1';

    /** İki saat arasında kabul edilen en büyük fark (saniye) */
    public const MAX_CLOCK_SKEW = 300;

    public const HEADER_SITE = 'X-Ajans-Site';

    public const HEADER_TIMESTAMP = 'X-Ajans-Timestamp';

    public const HEADER_NONCE = 'X-Ajans-Nonce';

    public const HEADER_SIGNATURE = 'X-Ajans-Signature';

    public const ROUTE_PAIR = 'ajans-asistani/v1/pair';

    public const ROUTE_HEALTH = 'ajans-asistani/v1/health';

    /**
     * İsteğin imzalanan metni. Gövdenin kendisi değil SHA-256 özeti girer: metin kısa kalır,
     * gövdedeki tek bir bayt değişse bile özet (dolayısıyla imza) tutmaz.
     */
    public static function requestMessage(string $method, string $route, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [
            self::VERSION.'-REQ',
            strtoupper($method),
            '/'.ltrim($route, '/'),
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
    }

    /**
     * Yanıtın imzalanan metni. İSTEĞİN nonce'unu içerir: yanıt yalnızca o isteğe aittir,
     * başka bir isteğin (eski, "her şey yolunda" diyen) yanıtı yerine kullanılamaz.
     */
    public static function responseMessage(string $requestNonce, int $status, string $timestamp, string $body): string
    {
        return implode("\n", [
            self::VERSION.'-RES',
            $requestNonce,
            (string) $status,
            $timestamp,
            hash('sha256', $body),
        ]);
    }
}
