<?php

namespace App\Actions;

use App\Enums\OperationType;
use App\Jobs\FinishOperation;
use App\Jobs\RunOperationTarget;
use App\Models\Operation;
use App\Models\OperationTarget;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Toplu işlemi başlatır: her site için bir satır + bir kuyruk işi, hepsi tek bir "batch" içinde.
 * Ajans bağlamında çağrılır (controller); işler bağlamı kendileri kurar.
 */
class StartOperation
{
    /**
     * @param  array<string, mixed>  $params
     * @param  Collection<int, Site>  $sites
     */
    public function handle(User $user, OperationType $type, string $label, array $params, Collection $sites): Operation
    {
        // Satırlar ve işlem birlikte yazılır: yarım kalmış (sitesiz) bir işlem olmaz
        $operation = DB::transaction(function () use ($user, $type, $label, $params, $sites) {
            $operation = Operation::create([
                'user_id' => $user->id,
                'type' => $type,
                'label' => $label,
                'params' => $params,
            ]);

            foreach ($sites as $site) {
                $operation->targets()->create(['site_id' => $site->id]);
            }

            return $operation;
        });

        $operationId = $operation->id;

        $batch = Bus::batch(
            $operation->targets()->get()->map(fn (OperationTarget $target) => new RunOperationTarget($target))->all()
        )
            ->name("operation:{$operationId}")
            // Bir sitedeki hata ötekileri durdurmasın: 12 siteden biri kapalıysa 11'i yine güncellenir
            ->allowFailures()
            // Bütün siteler bitince bir kez: işlemi kapat, son durumu yayınla
            ->finally(new FinishOperation($operationId))
            ->dispatch();

        $operation->forceFill(['batch_id' => $batch->id])->save();

        return $operation;
    }
}
