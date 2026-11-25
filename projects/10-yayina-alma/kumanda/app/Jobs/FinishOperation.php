<?php

namespace App\Jobs;

use App\Events\OperationProgressed;
use App\Models\Operation;
use App\Tenancy\CurrentAgency;
use Illuminate\Bus\Batch;

/**
 * Batch'in "finally" adımı: bütün siteler (başarılı ya da değil) bitince BİR kez çalışır.
 * Closure yerine sınıf: kuyruğa serileştirilir, testte de tek başına çağrılabilir.
 *
 * Kuyruk işçisinde oturum, middleware ve ajans bağlamı YOKTUR. İşlemi acrossAgencies ile
 * buluruz, sonra bağlamı KURARIZ: yayındaki özet (summary) OperationTarget satırlarını sayar;
 * bağlam olmasaydı AgencyScope hiçbir satır döndürmez, ekrana "0/0 site" giderdi.
 */
class FinishOperation
{
    public function __construct(public int $operationId) {}

    public function __invoke(?Batch $batch = null): void
    {
        $operation = Operation::acrossAgencies()->with('agency')->find($this->operationId);

        if ($operation === null || $operation->finished_at !== null) {
            return;
        }

        app(CurrentAgency::class)->run($operation->agency, function () use ($operation): void {
            $operation->forceFill(['finished_at' => now()])->save();
            rescue(fn () => broadcast(new OperationProgressed($operation)), report: true);
        });
    }
}
