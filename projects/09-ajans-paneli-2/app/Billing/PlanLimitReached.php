<?php

namespace App\Billing;

use RuntimeException;

class PlanLimitReached extends RuntimeException
{
    public static function sites(Plan $plan): self
    {
        return new self("{$plan->name} planı en fazla {$plan->maxSites} site içerir. Yeni site eklemek için planınızı yükseltin (Abonelik sayfası).");
    }
}
