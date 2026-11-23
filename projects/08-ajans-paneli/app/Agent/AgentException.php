<?php

namespace App\Agent;

use RuntimeException;

/** Ajanla konuşurken çıkan her sorun: ağ, imza, eklentinin döndürdüğü hata */
class AgentException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function unreachable(string $detail): self
    {
        return new self('unreachable', "Siteye bağlanılamadı: {$detail}");
    }

    public static function notInstalled(): self
    {
        return new self('not_installed', 'Ajans Asistanı eklentisi bulunamadı (devre dışı ya da silinmiş olabilir).');
    }

    public static function unsigned(int $status): self
    {
        return new self('unsigned', "Yanıt imzasız geldi (HTTP {$status}); araya giren bir sunucu ya da eklenti hatası olabilir.");
    }

    public static function badSignature(): self
    {
        return new self('bad_signature', 'Yanıtın imzası doğrulanamadı.');
    }

    public static function stale(): self
    {
        return new self('stale', 'Yanıtın zaman damgası geçersiz (saat farkı ya da eski bir yanıtın tekrarı).');
    }

    /** Eklenti imzalı bir hata döndürdü: {"error": "...", "message": "..."} */
    public static function rejected(int $status, ?string $code, ?string $message): self
    {
        return new self($code ?? 'rejected', $message ?? "Eklenti isteği reddetti (HTTP {$status}).");
    }
}
