<?php

namespace Tests\Feature;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use ZipArchive;

class PluginPackagingTest extends TestCase
{
    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        $this->out = sys_get_temp_dir().'/eklenti-paketle-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->out);

        parent::tearDown();
    }

    public function test_packs_the_plugin_with_the_public_key_and_server_written_in(): void
    {
        $key = base64_encode(random_bytes(32));
        $source = file_get_contents(base_path('wp-plugin/ajans-asistani/includes/class-license.php'));

        $result = $this->pack($key, 'https://lisans.example.com/');

        $this->assertTrue($result->successful(), $result->errorOutput());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->out.'/ajans-asistani-1.2.0.zip'));

        $license = $zip->getFromName('ajans-asistani/includes/class-license.php');
        $this->assertStringContainsString("const DEFAULT_PUBLIC_KEY = '$key';", $license);
        $this->assertStringContainsString("const DEFAULT_SERVER = 'https://lisans.example.com';", $license);
        $this->assertNotFalse($zip->getFromName('ajans-asistani/ajans-asistani.php'));
        $this->assertNotFalse($zip->getFromName('ajans-asistani/uninstall.php'));
        $zip->close();

        // Depodaki kaynak değişmez: her okur kendi anahtarıyla paketler
        $this->assertSame($source, file_get_contents(base_path('wp-plugin/ajans-asistani/includes/class-license.php')));
        $this->assertStringContainsString("const DEFAULT_PUBLIC_KEY = '';", $source);
    }

    public function test_rejects_a_key_that_is_not_32_bytes(): void
    {
        foreach (['', 'abc', base64_encode(random_bytes(31)), base64_encode(random_bytes(64)), 'boşluk var=='] as $key) {
            $result = $this->pack($key, 'https://lisans.example.com');

            $this->assertFalse($result->successful(), "Kabul edilmemeliydi: '$key'");
            $this->assertStringContainsString('Ed25519', $result->errorOutput());
        }

        $this->assertDirectoryDoesNotExist($this->out);
    }

    public function test_rejects_an_invalid_server_address(): void
    {
        foreach (['', 'lisans.example.com', 'ftp://lisans.example.com', "https://lisans.example.com/'.phpinfo().'"] as $server) {
            $result = $this->pack(base64_encode(random_bytes(32)), $server);

            $this->assertFalse($result->successful(), "Kabul edilmemeliydi: $server");
            $this->assertStringContainsString('--sunucu', $result->errorOutput());
        }
    }

    public function test_warns_when_the_server_is_plain_http(): void
    {
        $result = $this->pack(base64_encode(random_bytes(32)), 'http://lisans.example.com');

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertStringContainsString('canlıda https', $result->errorOutput());
    }

    private function pack(string $key, string $server): ProcessResult
    {
        return Process::run([PHP_BINARY, base_path('deploy/eklenti-paketle.php'), "--acik-anahtar=$key", "--sunucu=$server", "--cikti={$this->out}"]);
    }
}
