<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire;
use Livewire\Livewire as LivewireTest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Canlıda bulunan hata (Bölüm 10, Kumanda'nın cPanel kurulumu): Pulse paneli açıldıktan birkaç saniye sonra 500.
 * Kartlar sonuçlarını 5 sn önbelleğe koyar; Laravel 13'ün 'serializable_classes' => false
 * ayarı önbellekten hiçbir nesneyi geri açmaz → Collection "incomplete object" olur.
 * Testler phpunit.xml'deki array deposunu değil, canlıdaki gibi VERİTABANI deposunu kullanır.
 */
class PulseCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database', 'pulse.cache' => null]);
        Cache::forgetDriver('database');

        $this->seedPulseData();
    }

    /** Her kartın göstereceği gerçek biçimde veri (Pulse'un kaydedicileri böyle yazar) */
    private function seedPulseData(): void
    {
        $user = User::factory()->create();

        Pulse::startRecording();
        Pulse::record('slow_query', json_encode(['select * from users', 'app/Models/User.php:10']), 1500)->max()->count();
        Pulse::record('slow_request', json_encode(['GET', '/dashboard', 'Closure']), 1500)->max()->count();
        Pulse::record('slow_job', 'App\Jobs\ExampleJob', 1500)->max()->count();
        Pulse::record('slow_outgoing_request', json_encode(['GET', 'https://ornek.test/*']), 1500)->max()->count();
        Pulse::record('exception', json_encode(['RuntimeException', 'app/Foo.php:1']), now()->timestamp)->max()->count();
        Pulse::record('user_request', (string) $user->id)->count();
        Pulse::record('queued', 'database:default')->count();
        Pulse::record('processed', 'database:default')->count();
        Pulse::record('cache_hit', 'user:*')->count();
        Pulse::record('cpu', 'web-1', 30)->avg()->onlyBuckets();
        Pulse::record('memory', 'web-1', 512)->avg()->onlyBuckets();
        Pulse::set('system', 'web-1', json_encode([
            'name' => 'web-1', 'cpu' => 30, 'memory_used' => 512, 'memory_total' => 2048,
            'storage' => [['directory' => '/', 'total' => 10000, 'used' => 4000]],
        ]));
        Pulse::ingest();
    }

    /** @return array<string, array{class-string}> */
    public static function cards(): array
    {
        return [
            'servers' => [Livewire\Servers::class],
            'usage' => [Livewire\Usage::class],
            'queues' => [Livewire\Queues::class],
            'cache' => [Livewire\Cache::class],
            'slow queries' => [Livewire\SlowQueries::class],
            'exceptions' => [Livewire\Exceptions::class],
            'slow requests' => [Livewire\SlowRequests::class],
            'slow jobs' => [Livewire\SlowJobs::class],
            'slow outgoing requests' => [Livewire\SlowOutgoingRequests::class],
        ];
    }

    #[DataProvider('cards')]
    public function test_a_card_renders_again_from_the_cache(string $card): void
    {
        // Kartlar #[Lazy]: tembel yükleme kapatılmazsa testte yalnızca yer tutucu çizilir, sorgu hiç çalışmaz
        LivewireTest::withoutLazyLoading()->test($card)->assertOk(); // ilk çizim: sonucu önbelleğe yazar
        LivewireTest::withoutLazyLoading()->test($card)->assertOk(); // 5 sn içindeki yoklama: ÖNBELLEKTEN okur
    }

    public function test_the_cache_still_refuses_other_classes(): void
    {
        // İzin listesi dar kalmalı: listede olmayan bir nesne yine açılmaz (gadget chain koruması)
        Cache::put('deneme', new \ArrayObject([1]), 60);

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, Cache::get('deneme'));
    }
}
