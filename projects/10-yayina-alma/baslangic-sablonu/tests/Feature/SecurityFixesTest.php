<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Güvenlik hatalarının geri gelmesini önleyen testler.
 *
 * Her yeni projede bulunan bir hata düzeltildiğinde buraya bir test eklenir:
 * önce hatayı gösteren test yazılır (kırmızı), sonra düzeltme yapılır (yeşil).
 */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    /** Canlıda (APP_DEBUG=false) hata sayfası yığın izini ve hata mesajını göstermemeli. */
    public function test_error_page_does_not_leak_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::get('/_test/boom', fn () => throw new RuntimeException('gizli-veritabani-sifresi'));

        $this->get('/_test/boom')
            ->assertStatus(500)
            ->assertDontSee('gizli-veritabani-sifresi')
            ->assertDontSee('RuntimeException');
    }
}
