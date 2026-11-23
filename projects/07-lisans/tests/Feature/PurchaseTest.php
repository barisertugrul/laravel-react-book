<?php

namespace Tests\Feature;

use App\Actions\IssueLicense;
use App\Enums\OrderStatus;
use App\Models\License;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\LicenseIssued;
use App\Payments\FakeGateway;
use App\Support\LicenseKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** Sahte sağlayıcının göndereceği gibi imzalı bir webhook (Bölüm 6) */
    private function webhook(Order $order, string $type = 'payment.succeeded', ?string $eventId = null): TestResponse
    {
        $body = json_encode([
            'event_id' => $eventId ?? (string) Str::uuid(),
            'type' => $type,
            'order' => $order->number,
            'amount' => $order->total,
            'reference' => 'FAKE-123',
        ]);
        $timestamp = (string) now()->timestamp;

        return $this->call('POST', route('webhooks.fake'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_TIMESTAMP' => $timestamp,
            'HTTP_X_FAKE_SIGNATURE' => FakeGateway::sign($timestamp, $body),
        ], $body);
    }

    public function test_guests_must_log_in_to_buy(): void
    {
        $plan = Plan::factory()->create();

        $this->post(route('purchase.store', $plan))->assertRedirect(route('login'));
    }

    public function test_buying_creates_a_pending_order_and_sends_the_customer_to_the_provider(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->sites(5)->create(['price' => 399000]);

        $response = $this->actingAs($user)->post(route('purchase.store', $plan));

        $order = Order::sole();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(399000, $order->total);
        $this->assertStringContainsString('/sahte-odeme/'.$order->number, $response->headers->get('Location'));
        $this->assertDatabaseCount('licenses', 0); // ödeme gelmeden lisans yok
    }

    public function test_plan_of_an_inactive_product_cannot_be_bought(): void
    {
        $plan = Plan::factory()->create();
        $plan->product->update(['is_active' => false]);

        $this->actingAs(User::factory()->create())->post(route('purchase.store', $plan))->assertNotFound();
    }

    public function test_paid_webhook_issues_a_signed_license_and_emails_it(): void
    {
        $plan = Plan::factory()->sites(5)->create();
        $order = Order::factory()->for($plan)->create();

        $this->webhook($order)->assertOk();

        $license = License::sole();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame($order->user_id, $license->user_id);
        $this->assertSame(5, $license->max_sites);
        $this->assertSame(now()->addMonths(12)->toDateString(), $license->updates_until->toDateString());
        $this->assertSame($license->id, LicenseKey::parse($license->key)['lid']);
        Notification::assertSentTo($order->user, LicenseIssued::class);
    }

    public function test_license_keeps_the_site_limit_it_was_sold_with(): void
    {
        $plan = Plan::factory()->sites(5)->create();
        $order = Order::factory()->for($plan)->create();
        $this->webhook($order);

        // Plan sonradan değişse de satılmış lisansın hakkı değişmez
        $plan->update(['max_sites' => 3]);

        $this->assertSame(5, License::sole()->max_sites);
    }

    public function test_same_webhook_twice_issues_one_license(): void
    {
        $order = Order::factory()->create();

        $this->webhook($order, eventId: 'evt-1')->assertOk();
        $this->webhook($order, eventId: 'evt-1')->assertOk()->assertSee('daha önce işlendi');
        $this->webhook($order, eventId: 'evt-2')->assertOk(); // farklı olay, aynı sipariş

        $this->assertDatabaseCount('licenses', 1);
        Notification::assertSentToTimes($order->user, LicenseIssued::class, 1);
    }

    public function test_issuing_twice_returns_the_same_license(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $issue = new IssueLicense;

        // Kuyruk bir işi iki kez çalıştırabilir
        $first = $issue->handle($order);
        $second = $issue->handle($order);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('licenses', 1);
    }

    public function test_unpaid_order_cannot_get_a_license(): void
    {
        $this->expectException(\LogicException::class);

        (new IssueLicense)->handle(Order::factory()->create());
    }

    public function test_failed_payment_cancels_the_order_without_a_license(): void
    {
        $order = Order::factory()->create();

        $this->webhook($order, 'payment.failed')->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertDatabaseCount('licenses', 0);
    }

    public function test_result_page_shows_the_key_to_its_owner_only(): void
    {
        $order = Order::factory()->create();
        $this->webhook($order);
        $license = License::sole();

        $this->actingAs($order->user)->get(route('purchase.result', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/purchase-result')
                ->where('order.status', 'paid')
                ->where('license.key', $license->key));

        $this->actingAs(User::factory()->create())->get(route('purchase.result', $order))->assertNotFound();
    }
}
