<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Lisans API'sinin hata yanıtları. Laravel, bir istisnada render() metodu varsa yanıtı ondan alır.
 * WordPress eklentisi "error" koduna göre davranır; "message" kullanıcıya gösterilecek Türkçe metindir.
 */
class LicenseApiException extends Exception
{
    public function __construct(public readonly string $error, string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function invalidKey(): self
    {
        return new self('invalid_key', 'Lisans anahtarı geçersiz. Anahtarı eksiksiz kopyaladığınızdan emin olun.', 422);
    }

    public static function notFound(): self
    {
        return new self('license_not_found', 'Bu lisans bulunamadı.', 404);
    }

    public static function invalidDomain(): self
    {
        return new self('invalid_domain', 'Site adresi geçersiz.', 422);
    }

    public static function revoked(): self
    {
        return new self('license_revoked', 'Bu lisans iptal edilmiş. Ayrıntılar için bizimle iletişime geçin.', 403);
    }

    public static function siteLimitReached(int $max): self
    {
        return new self('site_limit_reached', "Bu lisans en fazla {$max} sitede kullanılabilir. Başka bir siteden kaldırıp tekrar deneyin.", 409);
    }

    /** Bunlar beklenen müşteri hatalarıdır; günlüğe (log) yazılmaz */
    public function report(): void {}

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->error,
            'message' => $this->getMessage(),
            'server_time' => now()->toIso8601String(),
        ], $this->status);
    }
}
