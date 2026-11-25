<?php

namespace App\Jobs;

use App\Agent\AgentClient;
use App\Agent\AgentException;
use App\Enums\OperationType;
use App\Enums\TargetStatus;
use App\Events\OperationProgressed;
use App\Models\OperationTarget;
use App\Models\Package;
use App\Models\Site;
use App\Tenancy\CurrentAgency;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Toplu işlemin TEK bir sitedeki adımı.
 *
 * tries = 1: kurma/güncelleme kendiliğinden tekrar denenmez. Zaman aşımına uğrayan bir istek
 * sitede yine de tamamlanmış olabilir; körü körüne ikinci kez göndermek yerine sonucu
 * kullanıcıya gösteririz, gerekirse o siteyi kendisi yeniden seçer.
 */
class RunOperationTarget implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    /** Eklentinin iç süresinden (150 sn) biraz uzun: istek zaman aşımına uğrarsa iş yine düzgün kapanır */
    public int $timeout = 180;

    public function __construct(public OperationTarget $target) {}

    public function handle(CurrentAgency $current, AgentClient $agent): void
    {
        $current->run($this->target->agency, function () use ($agent): void {
            $target = $this->target->fresh(['operation', 'site']);

            if ($target === null || $target->status !== TargetStatus::Pending) {
                return; // satır silinmiş ya da zaten işlenmiş
            }

            if ($this->batch()?->cancelled()) {
                $target->markCancelled();
                $this->progress($target);

                return;
            }

            $target->markRunning();
            $this->progress($target);

            try {
                $result = $agent->send($target->site, 'POST', $target->operation->type->route(), $this->payload($target), timeout: 150);
                $target->markSucceeded($result);

                // Sitenin eklenti listesi ve bekleyen güncelleme sayısı yenilensin
                CheckSiteHealth::dispatch($target->site);
            } catch (AgentException $e) {
                $target->markFailed($e->getMessage());
            } catch (ModelNotFoundException) {
                $target->markFailed('Paket, işlem başladıktan sonra kütüphaneden silinmiş.');
            }

            $this->progress($target);
        });
    }

    /**
     * Beklenmeyen hata ya da iş zaman aşımı: satır "çalışıyor"da takılı kalmasın.
     * Laravel bu metodu işin dışında çağırır; ajans bağlamı yoktur, sorgu bu yüzden acrossAgencies.
     */
    public function failed(?Throwable $e): void
    {
        $target = OperationTarget::acrossAgencies()->with('operation')->find($this->target->id);

        if ($target !== null && ! $target->status->isFinished()) {
            $target->markFailed('Beklenmeyen hata: '.($e?->getMessage() ?? 'iş zaman aşımına uğradı'));
        }
    }

    /** Yayın sunucusu kapalıysa kurulum başarısız sayılmaz: hata günlüğe yazılır, iş devam eder */
    private function progress(OperationTarget $target): void
    {
        rescue(fn () => broadcast(new OperationProgressed($target->operation, $target)), report: true);
    }

    /** @return array<string, mixed> */
    private function payload(OperationTarget $target): array
    {
        $params = $target->operation->params;

        if ($target->operation->type === OperationType::Update) {
            return ['file' => $params['file']];
        }

        if ($target->operation->type === OperationType::License) {
            return ['key' => $params['key']];
        }

        if (($params['source'] ?? null) === 'package') {
            $package = Package::findOrFail($params['package_id']);

            return [
                'source' => 'package',
                'slug' => $package->slug, // eklenti, kendi güncellemesinde lisansını denetler
                'url' => $this->downloadUrl($package, $target->site),
                'sha256' => $package->sha256,
                'activate' => (bool) ($params['activate'] ?? false),
            ];
        }

        return ['source' => 'wporg', 'slug' => $params['slug'], 'activate' => (bool) ($params['activate'] ?? false)];
    }

    /**
     * Süreli ve siteye bağlı imzalı adres. 10 dakika sonra geçersizdir; adres sitenin günlüklerine
     * düşse ya da müşteri tarafından paylaşılsa bile ücretli paket dağıtılamaz.
     */
    private function downloadUrl(Package $package, Site $site): string
    {
        return URL::temporarySignedRoute('packages.download', now()->addMinutes(10), [
            'package' => $package->id,
            'site' => $site->id,
        ]);
    }
}
