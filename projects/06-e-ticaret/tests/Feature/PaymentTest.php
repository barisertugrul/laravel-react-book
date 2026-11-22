<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\SendFakeWebhook;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\OrderPaid;
use App\Payments\FakeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** Sahte sağlayıcının göndereceği gibi imzalı bir webhook isteği */
    private function webhook(array $payload, ?int $timestamp = null, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload);
        $timestamp = (string) ($timestamp ?? now()->timestamp);

        return $this->call('POST', route('webhooks.fake'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_TIMESTAMP' => $timestamp,
            'HTTP_X_FAKE_SIGNATURE' => $signature ?? FakeGateway::sign($timestamp, $body),
        ], $body);
    }

    private function succeeded(Order $order, array $overrides = []): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'type' => 'payment.succeeded',
            'order' => $order->number,
            'amount' => $order->total,
            'reference' => 'FAKE-123',
            ...$overrides,
        ];
    }

    public function test_signed_webhook_marks_the_order_paid_and_notifies_the_customer(): void
    {
        $order = Order::factory()->create();

        $this->webhook($this->succeeded($order))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('FAKE-123', $order->payment_reference);
        Notification::assertSentTo($order->user, OrderPaid::class);
    }

    public function test_webhook_with_wrong_signature_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->webhook($this->succeeded($order), signature: 'uydurma-imza')->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_tampered_body_breaks_the_signature(): void
    {
        $order = Order::factory()->create();
        $payload = $this->succeeded($order);
        $timestamp = now()->timestamp;
        $signature = FakeGateway::sign((string) $timestamp, json_encode($payload));

        // Saldırgan imzalı bir bildirimi yakalayıp tutarı değiştiriyor
        $this->webhook([...$payload, 'amount' => 1], $timestamp, $signature)->assertForbidden();
    }

    public function test_old_webhooks_cannot_be_replayed(): void
    {
        $order = Order::factory()->create();

        $this->webhook($this->succeeded($order), now()->subMinutes(10)->timestamp)->assertForbidden();
    }

    public function test_same_event_is_processed_only_once(): void
    {
        $order = Order::factory()->create();
        $payload = $this->succeeded($order);

        $this->webhook($payload)->assertOk();
        $this->webhook($payload)->assertOk()->assertSee('daha önce işlendi');

        $this->assertDatabaseCount('payment_events', 1);
        Notification::assertSentToTimes($order->user, OrderPaid::class, 1);
    }

    public function test_amount_mismatch_is_not_accepted(): void
    {
        $order = Order::factory()->create(['total' => 100000]);

        $this->webhook($this->succeeded($order, ['amount' => 100]))->assertServerError();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0); // sağlayıcı tekrar gönderebilsin
    }

    public function test_failed_payment_cancels_the_order_and_releases_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->create();
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 1000, 'quantity' => 2, 'total' => 2000]);

        $this->webhook($this->succeeded($order, ['type' => 'payment.failed']))->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_fake_provider_page_requires_a_valid_signature(): void
    {
        $order = Order::factory()->create();

        $this->get("/sahte-odeme/{$order->number}")->assertForbidden();
        $this->get((new FakeGateway)->start($order))->assertOk();
    }

    public function test_fake_provider_reports_the_result_by_webhook_not_by_redirect(): void
    {
        Queue::fake();
        $order = Order::factory()->create();
        $url = (new FakeGateway)->start($order);

        $this->actingAs($order->user)->post($url, ['result' => 'approve'])
            ->assertRedirect(route('checkout.result', $order));

        // Yönlendirme siparişi değiştirmedi; sonuç kuyruktaki webhook ile gelecek
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        Queue::assertPushed(SendFakeWebhook::class, fn ($job) => $job->payload['type'] === 'payment.succeeded');
    }

    public function test_webhook_job_signs_its_request(): void
    {
        Http::fake();

        (new SendFakeWebhook(['event_id' => 'e1', 'type' => 'payment.succeeded', 'order' => 'SIP-000001', 'amount' => 100, 'reference' => 'R']))->handle();

        Http::assertSent(fn (HttpRequest $request) => $request->url() === route('webhooks.fake')
            && hash_equals(FakeGateway::sign($request->header('X-Fake-Timestamp')[0], $request->body()), $request->header('X-Fake-Signature')[0]));
    }
}
