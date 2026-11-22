<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // ?week=2026-11-23 → o haftanın pazartesisi; yoksa bu hafta
        $start = CarbonImmutable::parse($request->query('week', 'now'))->startOfWeek();
        $end = $start->endOfWeek();

        $hours = WorkingHour::where('is_open', true)->get();

        return Inertia::render('calendar', [
            'weekStart' => $start->toDateString(),
            'appointments' => Appointment::with('service:id,name')
                ->whereBetween('starts_at', [$start, $end])
                ->orderBy('starts_at')
                ->get(),
            // Takvimde gösterilecek saat aralığı: en erken açılış - en geç kapanış
            'range' => $this->hourRange($hours),
            'closedDays' => WorkingHour::where('is_open', false)->pluck('weekday'),
        ]);
    }

    /** @return array{from: int, to: int} ör. 09:00-19:30 → 9-20 */
    private function hourRange(Collection $hours): array
    {
        $opens = $hours->min('opens_at') ?? '09:00';
        $closes = $hours->max('closes_at') ?? '18:00';

        return [
            'from' => (int) substr($opens, 0, 2),
            'to' => (int) substr($closes, 0, 2) + (substr($closes, 3, 2) === '00' ? 0 : 1),
        ];
    }
}
