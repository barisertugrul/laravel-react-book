<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\License;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Ürün ve plan yönetimi (Bölüm 10: canlıda vitrin boş kalınca eksik olduğu fark edildi) */
class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => Role::Admin]);
    }

    /** @return array<string, mixed> */
    private function productData(array $overrides = []): array
    {
        return [
            'name' => 'Yedekçi', 'slug' => 'yedekci', 'summary' => 'WordPress yedekleri',
            'description' => null, 'is_active' => true, ...$overrides,
        ];
    }

    public function test_customers_cannot_manage_products(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($customer)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.products.store'), $this->productData())->assertForbidden();
        $this->actingAs($customer)->post(route('admin.plans.store', $product), ['name' => 'X', 'price' => '1,00'])->assertForbidden();

        $this->assertSame(1, Product::count());
    }

    public function test_admin_creates_a_product_and_its_plans_and_it_appears_in_the_store(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productData())
            ->assertRedirect(route('admin.products.edit', Product::sole()));

        $product = Product::sole();
        $this->post(route('admin.plans.store', $product), ['name' => '5 site', 'price' => '1490,90', 'max_sites' => 5])->assertRedirect();
        $this->post(route('admin.plans.store', $product), ['name' => 'Sınırsız', 'price' => '2990', 'max_sites' => '', 'sort' => 2])->assertRedirect();

        [$five, $unlimited] = $product->plans()->get()->all();
        $this->assertSame(149090, $five->price);     // "1490,90" → kuruş
        $this->assertSame(5, $five->max_sites);
        $this->assertSame(299000, $unlimited->price);
        $this->assertNull($unlimited->max_sites);    // boş: sınırsız

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('products.0.slug', 'yedekci')
            ->has('products.0.plans', 2));
    }

    public function test_product_fields_are_validated(): void
    {
        Product::factory()->create(['slug' => 'alinmis']);

        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productData(['slug' => 'Büyük Harf!']))
            ->assertSessionHasErrors('slug');
        $this->post(route('admin.products.store'), $this->productData(['slug' => 'alinmis']))->assertSessionHasErrors('slug');
        $this->post(route('admin.products.store'), $this->productData(['name' => '', 'summary' => '']))->assertSessionHasErrors(['name', 'summary']);

        $this->assertSame(1, Product::count());
    }

    public function test_plan_price_is_validated_and_ambiguous_thousands_are_refused(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.plans.store', $product), ['name' => 'X', 'price' => '1.250'])
            ->assertSessionHasErrors('price'); // 1250 TL mi 1,25 TL mi? Kabul edilmez
        $this->post(route('admin.plans.store', $product), ['name' => 'X', 'price' => 'bedava'])->assertSessionHasErrors('price');
        $this->post(route('admin.plans.store', $product), ['name' => 'X', 'price' => '10', 'max_sites' => 0])->assertSessionHasErrors('max_sites');

        $this->assertSame(0, Plan::count());
    }

    public function test_the_slug_is_locked_once_a_license_is_sold(): void
    {
        $license = License::factory()->create();
        $product = $license->plan->product;
        $oldSlug = $product->slug;

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), $this->productData(['slug' => 'yeni-adres']))
            ->assertSessionHasErrors('slug');
        $this->assertSame($oldSlug, $product->fresh()->slug);

        // Diğer alanlar değişebilir; slug aynı kaldıkça kayıt geçer
        $this->put(route('admin.products.update', $product), $this->productData(['slug' => $oldSlug, 'name' => 'Yeni ad']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Yeni ad', $product->fresh()->name);
    }

    public function test_changing_a_plan_does_not_touch_sold_licenses_or_orders(): void
    {
        $plan = Plan::factory()->sites(5)->create(['price' => 149000]);
        $order = Order::factory()->create(['plan_id' => $plan->id, 'total' => 149000]);
        $license = License::factory()->create(['plan_id' => $plan->id, 'max_sites' => 5]);

        $this->actingAs($this->admin)->put(route('admin.plans.update', $plan), ['name' => '3 site', 'price' => '999,00', 'max_sites' => 3])
            ->assertSessionHasNoErrors();

        $this->assertSame(99900, $plan->fresh()->price);
        $this->assertSame(149000, $order->fresh()->total);     // satın alma anındaki tutar
        $this->assertSame(5, $license->fresh()->max_sites);   // satın alma anındaki site sınırı
    }

    public function test_a_plan_with_orders_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $sold = Plan::factory()->create();
        Order::factory()->create(['plan_id' => $sold->id]);
        $unused = Plan::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $sold))->assertSessionHasErrors('plan');
        $this->delete(route('admin.plans.destroy', $unused))->assertSessionHasNoErrors();

        $this->assertModelExists($sold);
        $this->assertModelMissing($unused);
    }

    public function test_a_product_taken_off_sale_disappears_from_the_store_but_its_licenses_still_work(): void
    {
        $license = License::factory()->create();
        $product = $license->plan->product;

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), $this->productData(['slug' => $product->slug, 'is_active' => false]))
            ->assertSessionHasNoErrors();

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('products', 0));
        $this->assertTrue($license->fresh()->isActive());
    }

    public function test_the_admin_decides_which_plan_is_highlighted_and_with_what_label(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.plans.store', $product), ['name' => 'Tek site', 'price' => '490', 'max_sites' => 1, 'sort' => 0]);
        $this->post(route('admin.plans.store', $product), ['name' => 'Sınırsız', 'price' => '1990', 'badge' => 'Ajanslar için', 'sort' => 1]);
        $this->post(route('admin.plans.store', $product), ['name' => 'X', 'price' => '10', 'badge' => str_repeat('a', 41)])
            ->assertSessionHasErrors('badge');

        // Bölüm 7'deki gibi "ikinci plan" değil: etiketi olan plan vurgulanır, olmayan vurgusuz
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('products.0.plans.0.badge', null)
            ->where('products.0.plans.1.badge', 'Ajanslar için'));

        // Etiketi kaldırmak vurguyu kaldırır
        $plan = $product->plans()->where('name', 'Sınırsız')->sole();
        $this->put(route('admin.plans.update', $plan), ['name' => 'Sınırsız', 'price' => '1990', 'badge' => ''])->assertSessionHasNoErrors();
        $this->assertNull($plan->fresh()->badge);
    }

    public function test_the_edit_page_shows_plans_with_their_sales(): void
    {
        $plan = Plan::factory()->create();
        License::factory()->count(2)->create(['plan_id' => $plan->id]);

        $this->actingAs($this->admin)->get(route('admin.products.edit', $plan->product))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/form')
                ->where('product.licenses_count', 2)
                ->where('plans.0.licenses_count', 2));
    }
}
