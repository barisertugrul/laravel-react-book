<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        // Ana sayfa müşteri sorgulama sayfasına yönlendirir
        $this->get(route('home'))->assertRedirect('/takip');
        $this->get(route('tracking.lookup'))->assertOk();
    }
}
