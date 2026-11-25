<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Yayın öncesi kontrol: başlangıç kitinin varsayılan sayfası ürünün kendi sayfasıyla değişti (Bölüm 10) */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_shows_the_product_and_its_plans(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->has('plans', count(config('billing.plans')))
                ->where('plans.0.key', config('billing.free_plan'))
                ->where('plans.1.price', config('billing.plans.ajans.price')));
    }

    public function test_no_starter_kit_leftovers_remain_on_the_page(): void
    {
        $source = file_get_contents(resource_path('js/pages/welcome.tsx'));

        foreach (['Deploy now', 'cloud.laravel.com', "Let's get started", 'Laracasts'] as $leftover) {
            $this->assertStringNotContainsString($leftover, $source);
        }
    }
}
