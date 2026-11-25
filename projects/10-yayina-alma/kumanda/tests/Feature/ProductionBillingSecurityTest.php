<?php

namespace Tests\Feature;

use App\Billing\BillingGateway;
use App\Billing\FakeBillingGateway;
use App\Models\Agency;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

/**
 * Bölüm 10 "Güvenlik incelemesi" (b): sahte ödeme sağlayıcısı canlıda açık kalmamalı.
 * Açık kalırsa 4242 yazan her kullanıcı ücretli planı bedavaya alır.
 */
class ProductionBillingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->owner();
        $this->agency = $this->owner->currentAgency;
        $this->actAsAgency($this->agency);
    }

    private function inProduction(bool $allowFake = false): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        // Laravel CSRF denetimini yalnızca "testing" ortamında atlar; burada sınanan o değil
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['billing.provider' => 'fake', 'billing.allow_fake' => $allowFake]);
    }

    private function providerUrl(): string
    {
        return URL::temporarySignedRoute('fake-billing.show', now()->addMinutes(30), [
            'agency' => $this->agency->id, 'plan' => 'ajans', 'purpose' => 'subscribe',
        ]);
    }

    public function test_the_fake_gateway_cannot_be_resolved_in_production(): void
    {
        $this->inProduction();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Canlıda sahte ödeme sağlayıcısı kullanılamaz');

        app(BillingGateway::class);
    }

    public function test_choosing_a_paid_plan_in_production_does_not_reach_the_fake_provider(): void
    {
        $this->inProduction();

        $this->actingAs($this->owner)->post(route('billing.subscribe', 'ajans'))->assertServerError();

        $this->assertNull($this->agency->fresh()->subscription);
        $this->assertSame('baslangic', $this->agency->fresh()->plan()->key);
    }

    public function test_fake_provider_pages_and_webhook_do_not_exist_in_production(): void
    {
        $url = $this->providerUrl(); // imza geçerli: tek engel ortam olmalı
        $this->inProduction();

        $this->actingAs($this->owner)->get($url)->assertNotFound();
        $this->actingAs($this->owner)->post($url, ['card' => '4242'])->assertNotFound();
        $this->postJson(route('webhooks.billing'), ['type' => 'checkout.completed'])->assertNotFound();

        $this->assertSame(0, Subscription::count());
    }

    public function test_an_unset_provider_fails_loudly_instead_of_falling_back_to_fake(): void
    {
        config(['billing.provider' => null]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('BILLING_PROVIDER');

        app(BillingGateway::class);
    }

    public function test_a_demo_server_can_opt_in_and_shows_a_demo_notice(): void
    {
        $this->inProduction(allowFake: true);

        $this->assertInstanceOf(FakeBillingGateway::class, app(BillingGateway::class));
        $this->actingAs($this->owner)->get($this->providerUrl())->assertOk();
        $this->actingAs($this->owner)->get(route('billing.index'))
            ->assertInertia(fn (Assert $page) => $page->where('demo', true));
    }

    public function test_locally_the_fake_provider_works_without_a_notice(): void
    {
        config(['billing.provider' => 'fake', 'billing.allow_fake' => false]);

        $this->actingAs($this->owner)->get($this->providerUrl())->assertOk();
        $this->actingAs($this->owner)->get(route('billing.index'))
            ->assertInertia(fn (Assert $page) => $page->where('demo', false));
    }
}
