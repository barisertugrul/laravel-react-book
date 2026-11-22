<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_manage_categories(): void
    {
        $this->get(route('categories.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_category(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('categories.store'), ['name' => 'Çorbalar', 'sort_order' => 1, 'is_active' => '1'])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Çorbalar', 'is_active' => true]);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('categories.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Kategori adı zorunludur.']);
    }

    public function test_user_can_update_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('categories.update', $category), ['name' => 'Yeni Ad']);

        $this->assertSame('Yeni Ad', $category->fresh()->name);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_deleting_category_deletes_its_products(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('categories.destroy', $product->category));

        $this->assertModelMissing($product);
    }
}
