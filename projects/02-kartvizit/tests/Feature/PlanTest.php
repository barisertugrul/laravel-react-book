<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upgrade_to_pro(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('plan.update'), ['plan' => 'pro']);

        $this->assertSame(Plan::Pro, $user->fresh()->plan);
    }

    public function test_cannot_downgrade_with_too_many_cards(): void
    {
        $user = User::factory()->create(['plan' => Plan::Pro]);
        Card::factory()->count(2)->for($user)->create();

        $this->actingAs($user)->put(route('plan.update'), ['plan' => 'free'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(Plan::Pro, $user->fresh()->plan);
    }
}
