<?php

namespace App\Http\Requests;

use App\Enums\OperationType;
use App\Models\Package;
use App\Models\Site;
use App\Tenancy\CurrentAgency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOperationRequest extends FormRequest
{
    /** @var Collection<int, Site> */
    private Collection $sites;

    private ?Package $package = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(OperationType::class)],
            'site_ids' => ['required', 'array', 'min:1', 'max:200'],
            'site_ids.*' => ['integer', 'distinct'],

            // Kur: wordpress.org'daki kısa ad ya da paket kütüphanesinden bir paket
            'source' => ['exclude_unless:type,install', 'required', 'in:wporg,package'],
            // İki koşul birden: form "source" alanını her türde gönderir (varsayılanı wporg). Yalnızca
            // source'a bakan kural, lisans ya da güncelleme işleminde boş slug için "zorunlu" hatası verirdi.
            'slug' => ['exclude_unless:type,install', 'exclude_unless:source,wporg', 'required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'package_id' => ['exclude_unless:type,install', 'exclude_unless:source,package', 'required', 'integer'],
            'activate' => ['exclude_unless:type,install', 'boolean'],

            // Güncelle: WordPress'in eklenti dosyası adı ("contact-form-7/wp-contact-form-7.php").
            // Parçalar nokta ile başlayamaz: "../wp-config.php" gibi bir yol klasör adı sayılmasın.
            // Lisans: Anahtarcı'nın ürettiği imzalı anahtar (ANH1.<yük>.<imza>)
            'license_key' => ['exclude_unless:type,license', 'required', 'string', 'max:1000', 'regex:/^ANH1\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/'],

            'file' => ['exclude_unless:type,update', 'required', 'string', 'max:200', 'regex:#^[A-Za-z0-9_-][A-Za-z0-9._-]*/[A-Za-z0-9_-][A-Za-z0-9._-]*\.php$#'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Plan sınırını aşan ajans (ör. ek süresi bitip ücretsiz plana düşen) toplu işlem yapamaz;
            // siteleri silinmez, izlenmeye devam eder.
            $agency = app(CurrentAgency::class)->get();
            if ($agency->isOverLimit()) {
                $plan = $agency->plan();
                $validator->errors()->add('site_ids', "Ajansınızda {$agency->siteCount()} site var, {$plan->name} planı {$plan->maxSites} site içeriyor. Toplu işlemler için planı yükseltin ya da fazla siteleri kaldırın.");

                return;
            }

            // Site modelinin global scope'u burada ÇALIŞIR (Eloquent sorgusu): başka ajansın
            // sitesinin numarası gönderilse bile bulunmaz, sayılar tutmaz, istek reddedilir.
            $ids = array_map('intval', (array) $this->input('site_ids'));
            $this->sites = Site::whereKey($ids)->orderBy('name')->get();

            if ($this->sites->count() !== count($ids)) {
                $validator->errors()->add('site_ids', 'Seçilen sitelerden biri bulunamadı.');
            }

            if ($this->input('source') === 'package') {
                $this->package = Package::find((int) $this->input('package_id'));

                if ($this->package === null) {
                    $validator->errors()->add('package_id', 'Paket bulunamadı.');
                }
            }
        }];
    }

    public function type(): OperationType
    {
        return OperationType::from($this->validated('type'));
    }

    /** @return Collection<int, Site> */
    public function sites(): Collection
    {
        return $this->sites;
    }

    /** İşlemin kalıcı ayrıntıları; adres ve özet gibi değerler iş çalışırken üretilir @return array<string, mixed> */
    public function params(): array
    {
        return match ($this->type()) {
            OperationType::Update => ['file' => $this->validated('file')],
            OperationType::License => ['key' => trim($this->validated('license_key'))],
            OperationType::Install => array_filter([
                'source' => $this->validated('source'),
                'slug' => $this->validated('slug'),
                'package_id' => $this->package?->id,
                'activate' => $this->boolean('activate'),
            ], fn ($value) => $value !== null),
        };
    }

    public function label(): string
    {
        if ($this->type() === OperationType::Update) {
            return $this->pluginName((string) $this->validated('file')).' güncelle';
        }

        if ($this->type() === OperationType::License) {
            $key = trim($this->validated('license_key'));

            // Anahtarın tamamı listelerde görünmez: başı ve sonu yeter
            return 'Ajans Asistanı lisansı ('.substr($key, 0, 9).'…'.substr($key, -6).')';
        }

        $what = $this->package !== null
            ? "{$this->package->name} {$this->package->version} kur (paket)"
            : "{$this->validated('slug')} kur (wordpress.org)";

        return $this->boolean('activate') ? "{$what} ve etkinleştir" : $what;
    }

    /** Eklentinin adını sitelerin son raporlarından bulur; bulamazsa klasör adı */
    private function pluginName(string $file): string
    {
        foreach ($this->sites->load('latestCheck') as $site) {
            foreach ((array) ($site->latestCheck?->report['plugins'] ?? []) as $plugin) {
                if (($plugin['file'] ?? null) === $file) {
                    return (string) $plugin['name'];
                }
            }
        }

        return (string) strtok($file, '/');
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'site_ids' => 'siteler',
            'slug' => 'eklenti adı',
            'package_id' => 'paket',
            'file' => 'eklenti',
            'license_key' => 'lisans anahtarı',
        ];
    }
}
