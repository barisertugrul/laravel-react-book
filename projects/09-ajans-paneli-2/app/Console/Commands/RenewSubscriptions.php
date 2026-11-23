<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Jobs\RenewSubscription;
use App\Models\Subscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:renew')]
#[Description('Dönemi biten abonelikleri yeniler; ek süresi dolanları sonlandırır')]
class RenewSubscriptions extends Command
{
    public function handle(): int
    {
        // Bütün ajansları gezen sistem kodu: kapsam bilerek kaldırılır (acrossAgencies)
        $due = Subscription::acrossAgencies()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->get()
            ->filter(fn (Subscription $subscription) => $subscription->isDue());

        $due->each(fn (Subscription $subscription) => RenewSubscription::dispatch($subscription));

        $this->components->info("{$due->count()} abonelik kuyruğa kondu.");

        return self::SUCCESS;
    }
}
