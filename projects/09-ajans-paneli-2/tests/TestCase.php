<?php

namespace Tests;

use App\Health\SslInspector;
use App\Models\Agency;
use App\Models\User;
use App\Tenancy\CurrentAgency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Testler derlenmiş ön yüz dosyalarına (public/build) bağlı olmasın
        $this->withoutVite();

        // Sahtesi tanımlanmamış bir adrese gerçek HTTP isteği gitmesin
        Http::preventStrayRequests();

        // composer run dev açıkken Inertia, sayfayı sunucuda oluşturmak (SSR) için Vite'a istek atar.
        // Testler sayfanın verisine bakıyor, HTML'ine değil: SSR kapalı.
        config(['inertia.ssr.enabled' => false]);

        // Sertifika okuma doğrudan soket açar (Http::fake kapsamaz): testte 90 gün kalmış say
        $this->app->instance(SslInspector::class, new class extends SslInspector
        {
            public function expiresAt(string $url): ?CarbonImmutable
            {
                return CarbonImmutable::now()->addDays(90);
            }
        });
    }

    /** Kendi ajansının sahibi bir kullanıcı */
    protected function owner(string $agency = 'Mavi Pixel'): User
    {
        return User::factory()->withAgency($agency)->create();
    }

    /** Komut satırı ve test kodu için ajans bağlamı (HTTP isteğinde bunu middleware yapar) */
    protected function actAsAgency(Agency $agency): void
    {
        app(CurrentAgency::class)->set($agency);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
