<?php

namespace App\Http\Controllers;

use App\Actions\StartOperation;
use App\Enums\TargetStatus;
use App\Events\OperationProgressed;
use App\Http\Requests\StoreOperationRequest;
use App\Models\Operation;
use App\Models\OperationTarget;
use App\Models\Package;
use App\Models\Site;
use App\Tenancy\CurrentAgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    public function index(): Response
    {
        $finished = array_map(fn (TargetStatus $s) => $s->value, [TargetStatus::Succeeded, TargetStatus::Failed, TargetStatus::Cancelled]);

        $operations = Operation::with('user:id,name')
            ->withCount([
                'targets',
                'targets as finished_count' => fn (Builder $q) => $q->whereIn('status', $finished),
                'targets as failed_count' => fn (Builder $q) => $q->where('status', TargetStatus::Failed),
            ])
            ->latest()->limit(30)->get();

        return Inertia::render('operations/index', [
            'operations' => $operations->map(fn (Operation $o) => [
                'id' => $o->id,
                'label' => $o->label,
                'type' => $o->type->value,
                'user' => $o->user?->name,
                'total' => $o->targets_count,
                'finished' => $o->finished_count,
                'failed' => $o->failed_count,
                'created_at' => $o->created_at->toIso8601String(),
                'finished_at' => $o->finished_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $sites = Site::with('latestCheck')->orderBy('name')->get();

        return Inertia::render('operations/create', [
            // Güncelleme seçimi için her sitenin son raporundaki eklentiler
            'sites' => $sites->map(fn (Site $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'health_status' => $site->health_status->value,
                'plugins' => collect($site->latestCheck?->report['plugins'] ?? [])
                    ->map(fn (array $p) => ['file' => $p['file'], 'name' => $p['name'], 'version' => $p['version'], 'update' => $p['update'] ?? null])
                    ->values(),
            ]),
            'packages' => Package::orderBy('name')->orderByDesc('created_at')->get(['id', 'slug', 'name', 'version']),
            // Site sayfasındaki "bu siteye kur" bağlantısı: ?site=3
            'preselected' => array_map('intval', (array) $request->query('site', [])),
        ]);
    }

    public function store(StoreOperationRequest $request, StartOperation $start): RedirectResponse
    {
        $operation = $start->handle($request->user(), $request->type(), $request->label(), $request->params(), $request->sites());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$request->sites()->count()} sitede işlem başladı."]);

        return to_route('operations.show', $operation);
    }

    public function show(Operation $operation, CurrentAgency $current): Response
    {
        return Inertia::render('operations/show', [
            'operation' => [
                'id' => $operation->id,
                'label' => $operation->label,
                'type' => $operation->type->value,
                'user' => $operation->user?->name,
                'created_at' => $operation->created_at->toIso8601String(),
                'finished_at' => $operation->finished_at?->toIso8601String(),
            ],
            'summary' => $operation->summary(),
            'targets' => $operation->targets()->with('site:id,name,url')->orderBy('id')->get()
                ->map(fn (OperationTarget $t) => $t->toRow()),
            // Sayfa canlı yayını mı dinlesin, yoklama mı yapsın? Bilinen sürücüler listesi (izin listesi):
            // .env'deki "null" yazısını env() PHP'nin null değerine çevirir; "null değilse canlı" demek yanılır.
            'realtime' => in_array(config('broadcasting.default'), ['reverb', 'pusher', 'ably'], true),
            'agencyId' => $current->id(),
        ]);
    }

    /** Henüz başlamamış siteler iptal edilir; çalışmakta olan istek yarıda kesilmez */
    public function cancel(Operation $operation): RedirectResponse
    {
        if ($operation->batch_id !== null) {
            Bus::findBatch($operation->batch_id)?->cancel();
        }

        $operation->targets()->where('status', TargetStatus::Pending)->get()
            ->each(fn (OperationTarget $target) => $target->markCancelled());

        rescue(fn () => broadcast(new OperationProgressed($operation)), report: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bekleyen siteler iptal edildi.']);

        return back();
    }
}
