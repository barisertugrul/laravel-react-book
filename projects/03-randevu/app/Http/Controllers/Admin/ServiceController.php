<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('services/index', [
            'services' => Service::withCount('appointments')->orderBy('name')->get(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        Service::create($request->validated());

        return to_route('services.index');
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        return to_route('services.index');
    }

    public function destroy(Service $service): RedirectResponse
    {
        // Geçmiş randevuları olan hizmet silinmez, pasife alınır (migration'daki restrictOnDelete)
        if ($service->appointments()->exists()) {
            $service->update(['is_active' => false]);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Hizmetin randevuları olduğu için silinmedi, pasife alındı.']);
        } else {
            $service->delete();
        }

        return to_route('services.index');
    }
}
