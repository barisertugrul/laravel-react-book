<?php

namespace App\Health;

use Carbon\CarbonImmutable;

/**
 * Sitenin TLS sertifikasının bitiş tarihini okur.
 * Zincirin geçerliliğini burada denetlemeyiz (onu sayfa isteği zaten yapar); amacımız
 * süresi dolmak üzere olan sertifikayı, DOLMADAN haber vermek.
 */
class SslInspector
{
    public function expiresAt(string $url): ?CarbonImmutable
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: 443;

        if (! is_string($host)) {
            return null;
        }

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'peer_name' => $host,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]]);

        $client = @stream_socket_client("ssl://{$host}:{$port}", $errno, $error, 10, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            return null;
        }

        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $info = $certificate ? openssl_x509_parse($certificate) : false;

        return isset($info['validTo_time_t']) ? CarbonImmutable::createFromTimestamp($info['validTo_time_t']) : null;
    }
}
