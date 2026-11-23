<?php

namespace App\Billing;

use InvalidArgumentException;

/**
 * Bir abonelik planı (config/billing.php). Planlar veritabanında değil yapılandırmada:
 * sayıları az, nadiren değişir ve kod onları anahtarla ("ajans") tanır.
 */
final readonly class Plan
{
    public function __construct(
        public string $key,
        public string $name,
        public int $price,    // kuruş, aylık
        public int $maxSites,
    ) {}

    public static function find(string $key): self
    {
        $plan = config("billing.plans.{$key}") ?? throw new InvalidArgumentException("Bilinmeyen plan: {$key}");

        return new self($key, $plan['name'], $plan['price'], $plan['max_sites']);
    }

    public static function free(): self
    {
        return self::find(config('billing.free_plan'));
    }

    /** @return list<self> */
    public static function all(): array
    {
        return array_map(fn (string $key) => self::find($key), array_keys(config('billing.plans')));
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    /** @return array{key: string, name: string, price: int, max_sites: int} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'name' => $this->name, 'price' => $this->price, 'max_sites' => $this->maxSites];
    }
}
