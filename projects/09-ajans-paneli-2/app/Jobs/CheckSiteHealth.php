<?php

namespace App\Jobs;

use App\Actions\RunHealthCheck;
use App\Models\Site;
use App\Tenancy\CurrentAgency;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kuyruk işinde oturum da middleware da yoktur: ajans bağlamını iş kendisi kurar.
 * (SerializesModels, Site'ı kuyruktan geri yüklerken global scope'ları zaten uygulamaz.)
 *
 * ShouldBeUnique: zamanlayıcı ve "Şimdi kontrol et" düğmesi aynı siteyi aynı anda kuyruğa koyamaz.
 */
class CheckSiteHealth implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function __construct(public Site $site) {}

    public function uniqueId(): string
    {
        return (string) $this->site->id;
    }

    public function handle(CurrentAgency $current, RunHealthCheck $check): void
    {
        $current->run($this->site->agency, fn () => $check->handle($this->site));
    }
}
