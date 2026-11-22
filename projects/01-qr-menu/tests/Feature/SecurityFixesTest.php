<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bölüm 1'in "Güvenlik incelemesi" kısmındaki düzeltmeler */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Davetsiz', 'email' => 'x@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_user_can_be_created_from_the_command_line(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Ad soyad', 'Kafe Sahibi')
            ->expectsQuestion('E-posta', 'sahip@example.com')
            ->expectsQuestion('Şifre (en az 8 karakter)', 'gizli-sifre-123')
            ->assertSuccessful();

        $this->assertNotNull(User::where('email', 'sahip@example.com')->sole()->email_verified_at);
    }

    public function test_deleting_a_category_also_deletes_product_images(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->createWithContent('a.png', 'x')->store('products', 'public');
        $product = Product::factory()->create(['image_path' => $path]);

        $this->actingAs(User::factory()->create())
            ->delete(route('categories.destroy', $product->category));

        Storage::disk('public')->assertMissing($path);
    }
}
