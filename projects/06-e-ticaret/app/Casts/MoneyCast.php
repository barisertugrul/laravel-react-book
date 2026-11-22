<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Özel Eloquent dönüşümü (custom cast).
 * Veritabanında kuruş (int) saklanır; modelde de kuruş (int) okunur.
 * Yazarken "149,90" gibi bir metin verilirse kuruşa çevrilir.
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return match (true) {
            $value === null || $value === '' => null,
            is_int($value) => $value,
            default => Money::parse((string) $value),
        };
    }
}
