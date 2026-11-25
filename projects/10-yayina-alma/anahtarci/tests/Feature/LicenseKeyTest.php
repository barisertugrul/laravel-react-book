<?php

namespace Tests\Feature;

use App\Models\License;
use App\Support\Domain;
use App\Support\LicenseKey;
use App\Support\Signer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LicenseKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_key_carries_the_license_and_product(): void
    {
        $license = License::factory()->create();

        $payload = LicenseKey::parse($license->key);

        $this->assertStringStartsWith('ANH1.', $license->key);
        $this->assertSame($license->id, $payload['lid']);
        $this->assertSame($license->plan->product->slug, $payload['prd']);
    }

    public function test_changing_the_payload_breaks_the_signature(): void
    {
        $license = License::factory()->create();
        [$prefix, , $signature] = explode('.', $license->key);

        // Saldırgan yükteki lisans numarasını değiştirip imzayı olduğu gibi bırakıyor
        $forged = Signer::base64UrlEncode(json_encode(['lid' => 1, 'prd' => 'ajans-asistani', 'iat' => '2026-01-01']));

        $this->assertNull(LicenseKey::parse("{$prefix}.{$forged}.{$signature}"));
    }

    public function test_key_signed_with_another_secret_is_rejected(): void
    {
        $license = License::factory()->create();

        // Başka bir sunucu (veya sızdırılmış eski bir anahtar) ile imzalanmış gibi
        [$otherSecret] = Signer::generateKeyPair();
        config(['licensing.signing_key' => base64_encode($otherSecret)]);

        $this->assertNull(LicenseKey::parse($license->key));
    }

    public function test_public_key_alone_verifies_but_cannot_sign(): void
    {
        $license = License::factory()->create();
        [$prefix, $payload, $signature] = explode('.', $license->key);

        // WordPress eklentisinin elinde yalnızca açık anahtar vardır; bu kadarı doğrulamaya yeter
        $this->assertTrue(Signer::verify(Signer::base64UrlDecode($signature), "{$prefix}.{$payload}", Signer::publicKey()));
    }

    #[DataProvider('garbage')]
    public function test_malformed_keys_are_rejected(string $key): void
    {
        $this->assertNull(LicenseKey::parse($key));
    }

    public static function garbage(): array
    {
        return [
            'boş' => [''],
            'rastgele' => ['XXXX-YYYY-ZZZZ'],
            'eksik parça' => ['ANH1.abc'],
            'yanlış önek' => ['ABC9.e30.AAAA'],
            'bozuk base64' => ['ANH1.@@@.!!!'],
            'kısa imza' => ['ANH1.e30.AAAA'],
        ];
    }

    #[DataProvider('domains')]
    public function test_domains_are_normalized(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Domain::normalize($input));
    }

    public static function domains(): array
    {
        return [
            ['ornek.com', 'ornek.com'],
            ['https://www.Ornek.com/', 'ornek.com'],
            ['http://ornek.com:8080/blog?x=1', 'ornek.com'],
            ['  ORNEK.com.tr. ', 'ornek.com.tr'],
            ['magaza.ornek.com', 'magaza.ornek.com'],
            ['localhost', 'localhost'],
            ['', null],
            ['-ornek.com', null],
            ['ornek..com', null],
            ['http://', null],
        ];
    }
}
