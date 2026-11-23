<?php

namespace App\Health;

use App\Enums\HealthStatus;

final readonly class HealthResult
{
    /** @param list<array{level: string, code: string, message: string}> $issues */
    public function __construct(public HealthStatus $status, public array $issues) {}
}
