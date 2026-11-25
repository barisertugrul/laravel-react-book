<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

/**
 * Bölüm 10: sahte ödeme sağlayıcısı canlıda açık kalmamalı (Kumanda'daki güvenlik incelemesinin karşılığı).
 * Açık kalsaydı "Ödemeyi onayla" diyen herkes lisansı bedavaya alırdı.
 */
class ProductionPaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function inProduction(bool $allowFake = false): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        // Laravel CSRF denetimini yalnızca "testing" ortamında atlar; burada sınanan o değil
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['licensing.payment_provider' => 'fake', 'licensing.allow_fake' => $allowFake]);
    }

    private function providerUrl(Order $order): string
    {
        return URL::temporarySignedRoute('fake-provider.show', now()->addMinutes(30), ['order' => $order->number]);
    }

    public function test_the_fake_gateway_cannot_be_resolved_in_production(): void
    {
        $this->inProduction();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('sahte ödeme sağlayıcısı kullanılamaz');

        app(PaymentGateway::class);
    }

    public function test_buying_in_production_does_not_reach_the_fake_provider(): void
    {
        $this->inProduction();
        $plan = Plan::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('purchase.store', $plan))->assertServerError();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_fake_provider_pages_and_webhook_do_not_exist_in_production(): void
    {
        $order = Order::factory()->create();
        $url = $this->providerUrl($order); // imza geçerli: tek engel ortam olmalı
        $this->inProduction();

        $this->actingAs($order->user)->get($url)->assertNotFound();
        $this->actingAs($order->user)->post($url, ['result' => 'approve'])->assertNotFound();
        $this->postJson(route('webhooks.fake'), ['type' => 'payment.succeeded'])->assertNotFound();

        $this->assertDatabaseCount('licenses', 0);
    }

    public function test_an_unset_provider_fails_loudly_instead_of_falling_back_to_fake(): void
    {
        config(['licensing.payment_provider' => null]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('PAYMENT_PROVIDER');

        app(PaymentGateway::class);
    }

    public function test_a_demo_server_can_opt_in_and_shows_a_demo_notice(): void
    {
        $this->inProduction(allowFake: true);
        $order = Order::factory()->create();

        $this->assertInstanceOf(FakeGateway::class, app(PaymentGateway::class));
        $this->actingAs($order->user)->get($this->providerUrl($order))->assertOk();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demo', true));
    }

    public function test_locally_the_fake_provider_works_without_a_notice(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($order->user)->get($this->providerUrl($order))->assertOk();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demo', false));
    }
}
