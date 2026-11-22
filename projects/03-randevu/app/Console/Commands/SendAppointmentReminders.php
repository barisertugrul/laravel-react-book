<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:send-reminders')]
#[Description('Yarınki randevular için hatırlatma e-postası gönderir')]
class SendAppointmentReminders extends Command
{
    public function handle(): int
    {
        $tomorrow = CarbonImmutable::tomorrow();

        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed)
            ->whereBetween('starts_at', [$tomorrow, $tomorrow->endOfDay()])
            ->whereNull('reminder_sent_at') // komut iki kez çalışsa da tekrar göndermez
            ->get();

        foreach ($appointments as $appointment) {
            $appointment->notify(new AppointmentReminder($appointment));
            $appointment->update(['reminder_sent_at' => now()]);
        }

        $this->info("{$appointments->count()} hatırlatma kuyruğa eklendi.");

        return self::SUCCESS;
    }
}
