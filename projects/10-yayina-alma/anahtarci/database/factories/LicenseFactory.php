<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use App\Support\LicenseKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Siparişsiz, tek siteli, etkin lisans. Anahtar kayıttan sonra imzalanır (id gerekir).
 * Gerçek akış için IssueLicense kullanın.
 *
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'max_sites' => 1,
            'updates_until' => now()->addYear(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (License $license) {
            if ($license->key === null) {
                $license->update(['key' => LicenseKey::issue($license)]);
            }
        });
    }

    public function sites(?int $max): static
    {
        return $this->state(['max_sites' => $max]);
    }

    public function revoked(): static
    {
        return $this->state(['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => 'İade']);
    }
}
