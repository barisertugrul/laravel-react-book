<?php

namespace Tests\Feature;

use App\Actions\PairSite;
use App\Agent\AgentProtocol;
use App\Agent\ConnectionKey;
use App\Enums\TargetStatus;
use App\Events\OperationProgressed;
use App\Jobs\CheckSiteHealth;
use App\Jobs\FinishOperation;
use App\Models\Agency;
use App\Models\Operation;
use App\Models\Site;
use App\Models\User;
use App\Tenancy\CurrentAgency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeAgent;
use Tests\TestCase;

class OperationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Agency $agency;

    private FakeAgent $kafe;

    private FakeAgent $klinik;

    private Site $kafeSite;

    private Site $klinikSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->owner();
        $this->agency = $this->user->currentAgency;
        $this->actAsAgency($this->agency);

        $this->kafe = (new FakeAgent('https://lale-kafe.test'))->fake();
        $this->klinik = (new FakeAgent('https://gul-klinik.test'))->fake();
        $this->kafeSite = app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse($this->kafe->connectionKey()));
        $this->klinikSite = app(PairSite::class)->handle('Gül Klinik', ConnectionKey::parse($this->klinik->connectionKey()));
    }

    /** @param array<string, mixed> $data */
    private function start(array $data): Operation
    {
        $this->actingAs($this->user)
            ->post(route('operations.store'), $data + ['site_ids' => [$this->kafeSite->id, $this->klinikSite->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Operation::latest('id')->firstOrFail();
    }

    /** @return list<array<string, mixed>> Sitenin aldığı komutlar (sağlık raporu istekleri hariç) */
    private function commandsTo(FakeAgent $agent, string $route): array
    {
        return array_values(array_map(fn ($c) => $c[1], array_filter($agent->commands, fn ($c) => $c[0] === $route)));
    }

    public function test_installs_from_wordpress_org_on_every_selected_site(): void
    {
        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7', 'activate' => true]);

        foreach ([$this->kafe, $this->klinik] as $agent) {
            $this->assertSame(
                [['source' => 'wporg', 'slug' => 'contact-form-7', 'activate' => true]],
                $this->commandsTo($agent, AgentProtocol::ROUTE_PLUGIN_INSTALL),
            );
        }

        $this->assertSame('contact-form-7 kur (wordpress.org) ve etkinleştir', $operation->label);
        $this->assertNotNull($operation->fresh()->finished_at, 'Batch bitince işlem kapanmalı');
        $this->assertSame(['total' => 2, 'pending' => 0, 'running' => 0, 'succeeded' => 2, 'failed' => 0, 'cancelled' => 0], $operation->summary());
        $this->assertSame('Contact Form 7 2.0.0 (etkin)', $operation->targets()->first()->message);
    }

    public function test_a_failing_site_does_not_stop_the_others(): void
    {
        $this->klinik->onCommand = fn () => [422, ['error' => 'install_failed', 'message' => 'Hedef klasör zaten var.']];

        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);

        $targets = $operation->targets()->get()->keyBy('site_id');
        $this->assertSame(TargetStatus::Succeeded, $targets[$this->kafeSite->id]->status);
        $this->assertSame(TargetStatus::Failed, $targets[$this->klinikSite->id]->status);
        $this->assertSame('Hedef klasör zaten var.', $targets[$this->klinikSite->id]->message);
        $this->assertNotNull($operation->fresh()->finished_at);
    }

    public function test_a_site_without_the_agent_fails_with_a_clear_message(): void
    {
        $this->klinik->uninstalled = true;

        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);

        $this->assertStringContainsString('Ajans Asistanı eklentisi bulunamadı', $operation->targets()->where('site_id', $this->klinikSite->id)->value('message'));
    }

    public function test_update_sends_the_plugin_file(): void
    {
        CheckSiteHealth::dispatchSync($this->kafeSite); // eklenti adları son rapordan okunur

        $operation = $this->start(['type' => 'update', 'file' => 'contact-form-7/wp-contact-form-7.php']);

        $this->assertSame([['file' => 'contact-form-7/wp-contact-form-7.php']], $this->commandsTo($this->kafe, AgentProtocol::ROUTE_PLUGIN_UPDATE));
        $this->assertSame('Contact Form 7 güncelle', $operation->label);
    }

    public function test_a_successful_command_refreshes_the_site_report(): void
    {
        $before = $this->kafeSite->checks()->count();

        $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);

        $this->assertSame($before + 1, $this->kafeSite->checks()->count());
    }

    public function test_license_activation_sends_the_key_and_keeps_it_encrypted(): void
    {
        $key = 'ANH1.eyJsaWQiOjJ9.c2lnbmF0dXJlLW9ybmVr';
        $licensed = fn () => [200, ['state' => 'active', 'plan' => '5 site', 'max_sites' => 5, 'sites_used' => 4]];
        $this->kafe->onCommand = $licensed;
        // Anahtarcı'nın hatası eklentiden panele olduğu gibi gelir
        $this->klinik->onCommand = fn () => [422, ['error' => 'site_limit_reached', 'message' => 'Bu lisans en fazla 5 sitede kullanılabilir.']];

        $operation = $this->start(['type' => 'license', 'license_key' => $key]);

        $this->assertSame([['key' => $key]], $this->commandsTo($this->kafe, AgentProtocol::ROUTE_LICENSE_ACTIVATE));
        $this->assertSame('Ajans Asistanı lisansı (ANH1.eyJs…9ybmVr)', $operation->label);

        $targets = $operation->targets()->get()->keyBy('site_id');
        $this->assertSame('Lisans etkin · 5 site (4/5 kullanımda)', $targets[$this->kafeSite->id]->message);
        $this->assertSame('Bu lisans en fazla 5 sitede kullanılabilir.', $targets[$this->klinikSite->id]->message);

        // Veritabanında anahtar düz metin olarak durmaz
        $raw = DB::table('operations')->value('params');
        $this->assertStringNotContainsString($key, $raw);
        $this->assertSame($key, $operation->fresh()->params['key']);
    }

    public function test_the_form_sends_every_field_and_only_the_chosen_type_counts(): void
    {
        // Ön yüzdeki form, seçili türden bağımsız olarak bütün alanları gönderir (useForm)
        $form = ['source' => 'wporg', 'slug' => '', 'package_id' => '', 'activate' => true, 'file' => '', 'license_key' => '', 'site_ids' => [$this->kafeSite->id]];

        $this->actingAs($this->user)
            ->post(route('operations.store'), ['type' => 'license', 'license_key' => 'ANH1.eyJsaWQiOjJ9.c2lnbmF0dXJl'] + $form)
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post(route('operations.store'), ['type' => 'update', 'file' => 'contact-form-7/wp-contact-form-7.php'] + $form)
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Operation::count());
    }

    public function test_a_malformed_license_key_is_rejected_before_any_site_is_contacted(): void
    {
        $this->actingAs($this->user)
            ->post(route('operations.store'), ['type' => 'license', 'license_key' => 'XYZ-1234-ABCD', 'site_ids' => [$this->kafeSite->id]])
            ->assertSessionHasErrors('license_key');

        $this->assertSame([], $this->kafe->commands);
    }

    public function test_sites_of_another_agency_cannot_be_targeted(): void
    {
        $theirs = Site::factory()->forAgency($this->owner('Kuzey Web')->currentAgency)->create();

        $this->actingAs($this->user)
            ->post(route('operations.store'), ['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7', 'site_ids' => [$this->kafeSite->id, $theirs->id]])
            ->assertSessionHasErrors(['site_ids' => 'Seçilen sitelerden biri bulunamadı.']);

        $this->assertSame(0, Operation::acrossAgencies()->count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('operations.store'), ['type' => 'install', 'source' => 'wporg', 'slug' => '../../wp-config', 'site_ids' => [$this->kafeSite->id]])
            ->assertSessionHasErrors('slug');

        $this->actingAs($this->user)
            ->post(route('operations.store'), ['type' => 'update', 'file' => '../wp-config.php', 'site_ids' => [$this->kafeSite->id]])
            ->assertSessionHasErrors('file');
    }

    public function test_progress_is_broadcast_on_the_agency_channel(): void
    {
        Event::fake([OperationProgressed::class]);

        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);

        // Her site için "çalışıyor" + "bitti", sonunda bir kez "işlem bitti"
        Event::assertDispatchedTimes(OperationProgressed::class, 5);
        Event::assertDispatched(OperationProgressed::class, function (OperationProgressed $event) use ($operation) {
            return $event->broadcastOn()->name === "private-agencies.{$this->agency->id}"
                && $event->target === null
                && $event->broadcastWith()['operation_id'] === $operation->id;
        });
    }

    public function test_the_final_broadcast_counts_the_targets_without_a_request_context(): void
    {
        Queue::fake(); // siteler kuyrukta beklesin; finally'yi kuyruk işçisi gibi biz çağıracağız
        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);
        $operation->targets()->get()->each->markSucceeded(['name' => 'Contact Form 7']);

        $payloads = [];
        Event::listen(OperationProgressed::class, function (OperationProgressed $event) use (&$payloads) {
            $payloads[] = $event->broadcastWith(); // olay anında hesaplanan özet
        });

        app(CurrentAgency::class)->set(null); // kuyruk işçisi: oturum yok, ajans bağlamı yok
        (new FinishOperation($operation->id))();

        $this->assertNotNull($operation->fresh()->finished_at);
        $this->assertSame(2, $payloads[0]['summary']['total'], 'Bağlamsız özet 0/0 gönderiyordu');
        $this->assertSame(2, $payloads[0]['summary']['succeeded']);
        $this->assertNull(app(CurrentAgency::class)->get(), 'Bağlam iş bitince geri bırakılmalı');
    }

    public function test_the_broadcast_payload_contains_only_what_the_screen_shows(): void
    {
        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);
        $target = $operation->targets()->first();

        $payload = (new OperationProgressed($operation, $target))->broadcastWith();

        $this->assertSame(['id', 'site_id', 'site_name', 'site_url', 'status', 'message', 'started_at', 'finished_at'], array_keys($payload['target']));
        $this->assertStringNotContainsString('panel_secret_key', json_encode($payload));
    }

    public function test_pending_sites_can_be_cancelled(): void
    {
        Queue::fake(); // işler kuyrukta beklesin

        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);

        $this->actingAs($this->user)->post(route('operations.cancel', $operation))->assertRedirect();

        $this->assertSame(2, $operation->summary()['cancelled']);
        $this->assertTrue(Bus::findBatch($operation->batch_id)->cancelled());
    }

    public function test_the_progress_page(): void
    {
        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);

        $this->actingAs($this->user)->get(route('operations.show', $operation))->assertInertia(fn (Assert $page) => $page
            ->component('operations/show')
            ->has('targets', 2)
            ->where('targets.0.site_name', 'Gül Klinik') // siteler ada göre sırayla eklenir
            ->where('summary.succeeded', 2)
            ->where('realtime', false) // testlerde BROADCAST_CONNECTION=null
            ->where('agencyId', $this->agency->id));

        $this->actingAs($this->user)->get(route('operations.index'))->assertInertia(fn (Assert $page) => $page
            ->has('operations', 1)
            ->where('operations.0.finished', 2));
    }

    public function test_another_agencys_operation_is_not_found(): void
    {
        $operation = $this->start(['type' => 'install', 'source' => 'wporg', 'slug' => 'contact-form-7']);
        $stranger = $this->owner('Kuzey Web');

        $this->actingAs($stranger)->get(route('operations.show', $operation))->assertNotFound();
        $this->actingAs($stranger)->post(route('operations.cancel', $operation))->assertNotFound();
    }
}
