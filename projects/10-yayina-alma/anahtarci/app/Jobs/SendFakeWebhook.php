<?php

namespace App\Jobs;

use App\Payments\FakeGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Sahte sağlayıcının "sunucusu": ödeme sonucunu uygulamamızın webhook adresine,
 * tarayıcıdan bağımsız, imzalı bir HTTP isteğiyle bildirir. Gerçek sağlayıcılar da böyle çalışır.
 */
class SendFakeWebhook implements ShouldQueue
{
    use Queueable;

    /** Bildirim ulaşamazsa (uygulama kapalıysa) birkaç kez tekrar dene */
    public int $tries = 5;

    public array $backoff = [5, 30, 60];

    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $body = json_encode($this->payload);
        $timestamp = (string) now()->timestamp;

        Http::withHeaders([
            'X-Fake-Timestamp' => $timestamp,
            'X-Fake-Signature' => FakeGateway::sign($timestamp, $body),
        ])
            ->withBody($body, 'application/json')
            ->post(route('webhooks.fake'))
            ->throw(); // 2xx dışında bir yanıt: iş başarısız sayılır ve tekrar denenir
    }
}
