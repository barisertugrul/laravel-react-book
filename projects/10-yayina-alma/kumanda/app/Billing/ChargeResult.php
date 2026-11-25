<?php

namespace App\Billing;

final readonly class ChargeResult
{
    private function __construct(public bool $paid, public ?string $reference, public ?string $message) {}

    public static function paid(string $reference): self
    {
        return new self(true, $reference, null);
    }

    public static function declined(string $message): self
    {
        return new self(false, null, $message);
    }
}
