<?php

namespace Tests\Feature;

use App\Actions\PairSite;
use App\Agent\AgentProtocol;
use App\Agent\ConnectionKey;
use App\Enums\AgencyRole;
use App\Models\Agency;
use App\Models\Operation;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAgent;
use Tests\TestCase;
use ZipArchive;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP zip uzantısı kapalı.');
        }

        Storage::fake('local');
        $this->owner = $this->owner();
        $this->agency = $this->owner->currentAgency;
        $this->actAsAgency($this->agency);
    }

    /** @param array<string, string> $files zip içindeki yol => içerik */
    private function zip(array $files, string $name = 'paket.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    private function plugin(string $version = '1.2.0'): UploadedFile
    {
        return $this->zip([
            'ajans-asistani/ajans-asistani.php' => "<?php\n/**\n * Plugin Name:       Ajans Asistanı\n * Version:           {$version}\n */\n",
            'ajans-asistani/includes/class-rest.php' => '<?php // ...',
        ]);
    }

    private function upload(UploadedFile $file): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->post(route('packages.store'), ['file' => $file]);
    }

    public function test_an_uploaded_zip_is_read_and_stored_privately(): void
    {
        $file = $this->plugin();
        $sha = hash_file('sha256', $file->getRealPath());

        $this->upload($file)->assertSessionHasNoErrors();

        $package = Package::sole();
        $this->assertSame(['ajans-asistani', 'Ajans Asistanı', '1.2.0', $sha], [$package->slug, $package->name, $package->version, $package->sha256]);
        $this->assertStringStartsWith("packages/{$this->agency->id}/ajans-asistani-", $package->path);
        Storage::disk('local')->assertExists($package->path);
    }

    public function test_a_zip_that_is_not_a_plugin_is_rejected(): void
    {
        $this->upload($this->zip(['readme.txt' => 'merhaba']))->assertSessionHasErrors('file');
        $this->upload($this->zip(['eklenti/eklenti.php' => '<?php // başlık yok']))->assertSessionHasErrors(['file' => 'Zip içinde "Plugin Name:" başlığı olan bir PHP dosyası bulunamadı.']);
        $this->upload($this->zip(['a/a.php' => "<?php\n/* Plugin Name: A\nVersion: 1 */", 'b/b.php' => '<?php']))->assertSessionHasErrors('file');
        $this->upload($this->zip(['eklenti/../../evil.php' => "<?php\n/* Plugin Name: Kötü\nVersion: 1 */"]))->assertSessionHasErrors(['file' => 'Zip içinde geçersiz bir dosya yolu var.']);

        $this->assertSame(0, Package::count());
    }

    public function test_the_same_version_cannot_be_uploaded_twice(): void
    {
        $this->upload($this->plugin('1.2.0'));
        $this->upload($this->plugin('1.2.0'))->assertSessionHasErrors(['file' => 'Ajans Asistanı 1.2.0 kütüphanede zaten var.']);
        $this->upload($this->plugin('1.3.0'))->assertSessionHasNoErrors();

        $this->assertSame(2, Package::count());
    }

    public function test_only_the_owner_manages_packages(): void
    {
        $member = User::factory()->create();
        $this->agency->addMember($member, AgencyRole::Member);
        $member->switchAgency($this->agency);

        $this->actingAs($member)->post(route('packages.store'), ['file' => $this->plugin()])->assertForbidden();
        $this->actingAs($member)->get(route('packages.index'))->assertOk();
    }

    public function test_installing_a_package_sends_a_signed_url_and_the_checksum(): void
    {
        $this->upload($this->plugin());
        $package = Package::sole();

        $agent = (new FakeAgent('https://lale-kafe.test'))->fake();
        $site = app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse($agent->connectionKey()));

        $this->actingAs($this->owner)->post(route('operations.store'), [
            'type' => 'install', 'source' => 'package', 'package_id' => $package->id, 'activate' => true, 'site_ids' => [$site->id],
        ])->assertSessionHasNoErrors();

        $command = collect($agent->commands)->firstWhere(0, AgentProtocol::ROUTE_PLUGIN_INSTALL)[1];
        $this->assertSame('package', $command['source']);
        $this->assertSame($package->sha256, $command['sha256']);
        $this->assertSame('Ajans Asistanı 1.2.0 kur (paket) ve etkinleştir', Operation::sole()->label);

        // Sitenin yapacağı istek: oturum yok, ajans bağlamı yok; yetki yalnızca imzadan
        app(\App\Tenancy\CurrentAgency::class)->set(null);
        auth()->logout();

        $download = $this->get($command['url']);
        $download->assertOk()->assertDownload('ajans-asistani.zip');
        $this->assertSame($package->sha256, hash('sha256', $download->streamedContent()));
    }

    public function test_a_tampered_download_url_is_refused(): void
    {
        $this->upload($this->plugin());
        $package = Package::sole();
        $site = \App\Models\Site::factory()->forAgency($this->agency)->create();

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('packages.download', now()->addMinutes(10), ['package' => $package->id, 'site' => $site->id]);

        $this->get(str_replace("/indir/{$site->id}", '/indir/999', $url))->assertForbidden();
        $this->get(route('packages.download', ['package' => $package->id, 'site' => $site->id]))->assertForbidden(); // imzasız
    }
}
