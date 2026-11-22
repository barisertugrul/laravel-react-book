<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkingHoursRequest;
use App\Models\WorkingHour;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkingHoursController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('working-hours', [
            'hours' => WorkingHour::orderBy('weekday')->get()->map(fn (WorkingHour $h) => [
                'weekday' => $h->weekday,
                'name' => WorkingHour::DAY_NAMES[$h->weekday],
                'is_open' => $h->is_open,
                'opens_at' => substr($h->opens_at, 0, 5),
                'closes_at' => substr($h->closes_at, 0, 5),
            ]),
        ]);
    }

    public function update(WorkingHoursRequest $request): RedirectResponse
    {
        foreach ($request->validated('hours') as $day) {
            // Gün varsa güncelle, yoksa oluştur
            WorkingHour::updateOrCreate(['weekday' => $day['weekday']], $day);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Çalışma saatleri kaydedildi.']);

        return to_route('working-hours.edit');
    }
}
