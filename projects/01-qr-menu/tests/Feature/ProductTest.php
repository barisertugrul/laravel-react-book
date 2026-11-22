<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1x1 piksellik gerçek bir PNG. UploadedFile::fake()->image() GD eklentisi
     * ister; bu yöntem her PHP kurulumunda çalışır.
     */
    private function fakePng(string $name = 'gorsel.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        ));
    }

    public function test_user_can_create_product_with_image(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), [
                'category_id' => $category->id,
                'name' => 'Mercimek Çorbası',
                'price' => '95.50',
                'is_available' => '1',
                'image' => $this->fakePng('corba.png'),
            ])
            ->assertRedirect(route('products.index'));

        $product = Product::firstOrFail();
        $this->assertSame('95.50', $product->price);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_price_must_be_numeric(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), [
                'category_id' => Category::factory()->create()->id,
                'name' => 'Ayran',
                'price' => 'bedava',
            ])
            ->assertSessionHasErrors('price');
    }

    public function test_replacing_image_deletes_old_file(): void
    {
        Storage::fake('public');
        $old = $this->fakePng('eski.png')->store('products', 'public');
        $product = Product::factory()->create(['image_path' => $old]);

        $this->actingAs(User::factory()->create())
            ->put(route('products.update', $product), [
                'category_id' => $product->category_id,
                'name' => $product->name,
                'price' => '10',
                'image' => $this->fakePng('yeni.png'),
            ]);

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($product->fresh()->image_path);
    }

    public function test_qr_code_page_contains_menu_svg(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('qr-code'))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('qr-code.download'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }
}
