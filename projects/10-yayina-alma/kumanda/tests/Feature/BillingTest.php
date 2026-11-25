<?php

namespace Tests\Feature;

use App\Actions\PairSite;
use App\Agent\ConnectionKey;
use App\Billing\FakeBillingGateway;
use App\Billing\PlanLimitReached;
use App\Enums\AgencyRole;
use App\Enums\SubscriptionStatus;
use App\Jobs\RenewSubscription;
use App\Jobs\SendFakeBillingWebhook;
use App\Models\Agency;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionEnded;
use App\Notifications\SubscriptionPaymentFailed;
use App\Tenancy\CurrentAgency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeAgent;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->owner = $this->owner();
        $this->agency = $this->owner->currentAgency;
        $this->actAsAgency($this->agency);
    }

    /** Dönemi $endsIn sonra biten, $last4 kartlı bir abonelik */
    private function subscribe(string $plan = 'ajans', string $last4 = '4242', ?\DateTimeInterface $endsAt = null): Subscription
    {
        return Subscription::create([
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'provider' => 'fake',
            'card_token' => FakeBillingGateway::token($last4),
            'card_last4' => $last4,
            'current_period_ends_at' => $endsAt ?? now()->addMonth(),
        ]);
    }

    /** Sağlayıcının imzalı bildirimi, sağlayıcının imzaladığı gibi */
    private function webhook(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload + ['event_id' => (string) Str::uuid()]);
        $timestamp = (string) now()->timestamp;

        $response = $this->call('POST', route('webhooks.billing'), [], [], [], [
            'HTTP_X-Fake-Timestamp' => $timestamp,
            'HTTP_X-Fake-Signature' => $signature ?? FakeBillingGateway::sign($timestamp, $body),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        // Oturumsuz istek: SetCurrentAgency bağlamı boşalttı. Testin kendi sorguları için geri kuruyoruz
        // (bağlamsız Subscription::sole() fail-closed kapsama takılır, hiçbir satır bulamazdı).
        $this->actAsAgency($this->agency);

        return $response;
    }

    private function completed(string $purpose = 'subscribe', string $last4 = '4242', string $plan = 'ajans'): array
    {
        return [
            'type' => 'checkout.completed', 'purpose' => $purpose, 'agency_id' => $this->agency->id, 'plan' => $plan,
            'amount' => $purpose === 'subscribe' ? 79900 : 0, 'card_token' => FakeBillingGateway::token($last4), 'card_last4' => $last4,
            'reference' => $purpose === 'subscribe' ? 'FAKE-'.Str::random(8) : null,
        ];
    }

    private function renew(): Subscription
    {
        RenewSubscription::dispatchSync(Subscription::sole());

        return Subscription::sole();
    }

    public function test_a_new_agency_is_on_the_free_plan(): void
    {
        $this->assertSame('baslangic', $this->agency->plan()->key);
        $this->assertSame(3, $this->agency->plan()->maxSites);
    }

    public function test_choosing_a_paid_plan_sends_the_owner_to_the_provider(): void
    {
        $response = $this->actingAs($this->owner)->post(route('billing.subscribe', 'ajans'));

        $response->assertRedirect();
        $this->assertStringContainsString('/sahte-odeme/abonelik', $response->headers->get('Location'));
        $this->assertNull($this->agency->fresh()->subscription, 'Yönlendirme aboneliği açmaz; webhook açar');
    }

    public function test_the_provider_page_reports_the_result_by_webhook(): void
    {
        Queue::fake();
        $url = URL::temporarySignedRoute('fake-billing.show', now()->addMinutes(30), ['agency' => $this->agency->id, 'plan' => 'ajans', 'purpose' => 'subscribe']);

        $this->post($url, ['card' => '4242'])->assertRedirect(route('billing.index', ['donus' => 'tamam']));

        Queue::assertPushed(SendFakeBillingWebhook::class, fn ($job) => $job->payload['type'] === 'checkout.completed' && $job->payload['card_last4'] === '4242');
    }

    public function test_the_webhook_starts_the_subscription_once(): void
    {
        $payload = $this->completed() + ['event_id' => 'evt_1'];

        $this->webhook($payload)->assertOk();
        $this->webhook($payload)->assertOk()->assertSee('daha önce işlendi'); // sağlayıcı aynı bildirimi tekrar gönderdi

        $subscription = Subscription::sole();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('ajans', $this->agency->fresh()->plan()->key);
        $this->assertSame(1, $subscription->payments()->count());
        $this->assertTrue($subscription->current_period_ends_at->isSameDay(now()->addMonth()));
    }

    public function test_a_webhook_with_a_bad_signature_is_refused(): void
    {
        $this->webhook($this->completed(), signature: str_repeat('0', 64))->assertForbidden();

        $this->assertSame(0, Subscription::count());
    }

    public function test_a_declined_card_does_not_start_a_subscription(): void
    {
        $this->webhook(['type' => 'checkout.failed', 'purpose' => 'subscribe', 'agency_id' => $this->agency->id, 'plan' => 'ajans'])->assertOk();

        $this->assertSame(0, Subscription::count());
    }

    public function test_a_due_subscription_is_renewed_for_another_month(): void
    {
        $this->subscribe(endsAt: now()->subMinute());

        $subscription = $this->renew();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_ends_at->isFuture());
        $this->assertSame(['paid'], $subscription->payments()->pluck('status')->all());
    }

    public function test_a_failed_renewal_gives_seven_days_then_falls_back_to_the_free_plan(): void
    {
        $this->subscribe(last4: '0341', endsAt: now()->subMinute());

        // 1. gün: ödeme alınamadı, ek süre başladı, sahip haberdar edildi; plan sürüyor
        $subscription = $this->renew();
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertTrue($subscription->grace_ends_at->isSameDay(now()->addDays(7)));
        $this->assertSame('ajans', $this->agency->fresh()->plan()->key);
        Notification::assertSentTo($this->owner, SubscriptionPaymentFailed::class);

        // Aynı gün ikinci kez denenmez
        $this->assertFalse($subscription->isDue());

        // 2. gün: yeniden denendi, yine reddedildi; ek süre uzamadı, ikinci e-posta yok
        $this->travel(1)->days();
        $subscription = $this->renew();
        $this->assertTrue($subscription->grace_ends_at->isSameDay(now()->addDays(6)));
        Notification::assertSentToTimes($this->owner, SubscriptionPaymentFailed::class, 1);

        // Ek süre bitti: abonelik sona erer, ajans ücretsiz plana düşer
        $this->travel(7)->days();
        $subscription = $this->renew();
        $this->assertSame(SubscriptionStatus::Ended, $subscription->status);
        $this->assertSame('baslangic', $this->agency->fresh()->plan()->key);
        Notification::assertSentTo($this->owner, SubscriptionEnded::class);
        $this->assertSame(['failed', 'failed'], $subscription->payments()->pluck('status')->all());
    }

    public function test_a_new_card_settles_a_past_due_subscription_right_away(): void
    {
        $this->subscribe(last4: '0341', endsAt: now()->subMinute());
        $this->renew();

        $this->webhook($this->completed(purpose: 'card', last4: '4242'))->assertOk();

        $subscription = Subscription::sole();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('4242', $subscription->card_last4);
        $this->assertNull($subscription->grace_ends_at);
        $this->assertSame(['failed', 'paid'], $subscription->payments()->orderBy('id')->pluck('status')->all());
    }

    public function test_a_canceled_subscription_ends_at_the_end_of_the_period_without_a_charge(): void
    {
        $this->subscribe();

        $this->actingAs($this->owner)->post(route('billing.cancel'))->assertRedirect();
        $this->assertSame('ajans', $this->agency->fresh()->plan()->key, 'Ödenen dönem sonuna kadar sürer');

        $this->travel(32)->days();
        $subscription = $this->renew();

        $this->assertSame(SubscriptionStatus::Ended, $subscription->status);
        $this->assertSame(0, $subscription->payments()->count());
    }

    public function test_an_active_subscriber_switches_between_paid_plans_without_checkout(): void
    {
        $this->subscribe('ajans');

        $this->actingAs($this->owner)->post(route('billing.subscribe', 'kurumsal'))->assertRedirect();

        $this->assertSame('kurumsal', Subscription::sole()->plan);
    }

    public function test_the_site_limit_of_the_plan_is_enforced_when_adding_a_site(): void
    {
        Site::factory()->count(3)->forAgency($this->agency)->create();
        $agent = (new FakeAgent)->fake();

        $this->actingAs($this->owner)->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $agent->connectionKey()])
            ->assertSessionHasErrors(['connection_key' => 'Başlangıç planı en fazla 3 site içerir. Yeni site eklemek için planınızı yükseltin (Abonelik sayfası).']);
        $this->assertSame([], $agent->requests, 'Sınır doluysa siteye hiç gidilmez');

        $this->subscribe('ajans');
        app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse($agent->connectionKey()));
        $this->assertSame(4, $this->agency->siteCount());
    }

    public function test_pair_site_refuses_beyond_the_limit_even_when_called_directly(): void
    {
        Site::factory()->count(3)->forAgency($this->agency)->create();

        $this->expectException(PlanLimitReached::class);

        app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse((new FakeAgent)->fake()->connectionKey()));
    }

    public function test_an_agency_over_its_limit_keeps_its_sites_but_cannot_run_operations(): void
    {
        $sites = Site::factory()->count(5)->forAgency($this->agency)->create(); // ör. ek süresi bitip ücretsiz plana düştü

        $this->actingAs($this->owner)
            ->post(route('operations.store'), ['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7', 'site_ids' => [$sites[0]->id]])
            ->assertSessionHasErrors(['site_ids' => 'Ajansınızda 5 site var, Başlangıç planı 3 site içeriyor. Toplu işlemler için planı yükseltin ya da fazla siteleri kaldırın.']);

        $this->actingAs($this->owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('sites', 5)
            ->where('billing.sites', 5)
            ->where('billing.max_sites', 3));
    }

    public function test_only_the_owner_manages_the_subscription(): void
    {
        $member = User::factory()->create();
        $this->agency->addMember($member, AgencyRole::Member);
        $member->switchAgency($this->agency);

        $this->actingAs($member)->get(route('billing.index'))->assertOk();
        $this->actingAs($member)->post(route('billing.subscribe', 'ajans'))->assertForbidden();
        $this->actingAs($member)->post(route('billing.cancel'))->assertForbidden();
    }

    public function test_the_scheduler_queues_due_subscriptions_of_every_agency(): void
    {
        Queue::fake();
        $this->subscribe(endsAt: now()->subMinute());
        app(CurrentAgency::class)->run($this->owner('Kuzey Web')->currentAgency, fn () => $this->subscribe(endsAt: now()->addDays(3)));

        $this->artisan('billing:renew')->assertSuccessful();

        Queue::assertPushed(RenewSubscription::class, 1);
    }

    public function test_the_billing_page(): void
    {
        $this->subscribe(last4: '0341', endsAt: now()->subMinute());
        $this->renew();

        $this->actingAs($this->owner)->get(route('billing.index'))->assertInertia(fn (Assert $page) => $page
            ->component('billing/index')
            ->where('current.key', 'ajans')
            ->where('subscription.status', 'past_due')
            ->where('subscription.card_last4', '0341')
            ->where('billing.past_due', true)
            ->has('payments', 1)
            ->has('plans', 3));
    }
}
