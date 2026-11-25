<?php

namespace App\Jobs;

use App\Billing\FakeBillingGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Sahte sağlayıcının "sunucusu": sonucu uygulamanın webhook adresine, tarayıcıdan bağımsız,
 * imzalı bir HTTP isteğiyle bildirir (Bölüm 6'daki SendFakeWebhook ile aynı).
 */
class SendFakeBillingWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [5, 30, 60];

    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $body = json_encode($this->payload);
        $timestamp = (string) now()->timestamp;

        Http::withHeaders([
            'X-Fake-Timestamp' => $timestamp,
            'X-Fake-Signature' => FakeBillingGateway::sign($timestamp, $body),
        ])
            ->withBody($body, 'application/json')
            ->post(route('webhooks.billing'))
            ->throw();
    }
}
