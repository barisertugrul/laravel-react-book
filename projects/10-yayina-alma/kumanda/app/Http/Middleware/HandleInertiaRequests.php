<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Tenancy\CurrentAgency;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Kenar çubuğundaki ajans değiştirici: etkin ajans, rolüm ve üyesi olduğum ajanslar
                'agency' => fn () => $this->agency($request),
                'agencies' => fn () => $request->user()?->agencies()->orderBy('name')->get(['agencies.id', 'agencies.name'])
                    ->map(fn (Agency $agency) => ['id' => $agency->id, 'name' => $agency->name]),
            ],
            // Abonelik özeti: üst şerit (ödeme bekleniyor, sınır aşıldı) ve kenar çubuğu için
            'billing' => fn () => $this->billing(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array<string, mixed>|null */
    private function billing(): ?array
    {
        $agency = app(CurrentAgency::class)->get();

        if ($agency === null) {
            return null;
        }

        $plan = $agency->plan();
        $subscription = $agency->subscription;

        return [
            'plan' => $plan->name,
            'sites' => $agency->siteCount(),
            'max_sites' => $plan->maxSites,
            'past_due' => $subscription?->status === SubscriptionStatus::PastDue,
            'grace_ends_at' => $subscription?->grace_ends_at?->toIso8601String(),
        ];
    }

    /** @return array{id: int, name: string, role: string}|null */
    private function agency(Request $request): ?array
    {
        $agency = app(CurrentAgency::class)->get();

        if ($agency === null || $request->user() === null) {
            return null;
        }

        return ['id' => $agency->id, 'name' => $agency->name, 'role' => $request->user()->roleIn($agency)?->value ?? 'member'];
    }
}
