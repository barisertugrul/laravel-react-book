<?php

namespace App\Jobs;

use App\Actions\IssueLicense;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Ödemesi onaylanan siparişin lisansını kuyrukta üretir (bkz. MarkOrderPaid) */
class IssueLicenseForOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [5, 30, 60];

    public function __construct(public Order $order) {}

    public function handle(IssueLicense $issue): void
    {
        $issue->handle($this->order);
    }
}
