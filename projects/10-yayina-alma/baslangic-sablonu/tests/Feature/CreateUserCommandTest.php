<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** SSH olmayan sunucuda yönetici hesabı cron'dan, soru sormadan açılır (Bölüm 10, cPanel komut kutusu) */
class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $passwordFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordFile = tempnam(sys_get_temp_dir(), 'sifre');
        file_put_contents($this->passwordFile, "uzun-bir-sifre-123\n"); // sondaki satır sonu şifreye dahil değil
    }

    protected function tearDown(): void
    {
        @unlink($this->passwordFile);
        parent::tearDown();
    }

    public function test_a_user_can_be_created_without_prompts(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Ayşe Yılmaz', '--email' => 'ayse@ornek.test',
            '--password-file' => $this->passwordFile, '--no-interaction' => true,
        ])->assertSuccessful();

        $user = User::sole();
        $this->assertTrue(Hash::check('uzun-bir-sifre-123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_options_are_validated_like_the_prompts(): void
    {
        User::factory()->create(['email' => 'ayse@ornek.test']);

        $this->artisan('user:create', [
            '--name' => 'X', '--email' => 'ayse@ornek.test',
            '--password-file' => $this->passwordFile, '--no-interaction' => true,
        ])->assertFailed();

        file_put_contents($this->passwordFile, 'kisa');
        $this->artisan('user:create', [
            '--name' => 'X', '--email' => 'yeni@ornek.test',
            '--password-file' => $this->passwordFile, '--no-interaction' => true,
        ])->expectsOutputToContain('en az 8 karakter')->assertFailed();

        $this->assertSame(1, User::count());
    }
}
