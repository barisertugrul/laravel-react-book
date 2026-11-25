<?php

namespace Tests;

use App\Support\Signer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Testler derlenmiş ön yüz dosyalarına (public/build) bağlı olmasın
        $this->withoutVite();

        // Her test kendi imza anahtarıyla çalışır: geliştirme ortamının .env anahtarı testlere sızmaz
        [$secret] = Signer::generateKeyPair();
        config(['licensing.signing_key' => base64_encode($secret)]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
