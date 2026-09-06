<?php

namespace Tests\Feature;

use App\Models\Brands;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_the_home_catalog_and_product_detail(): void
    {
        $product = $this->product('Kamera mirrorless', 'kamera-mirrorless', 2);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Front/Products/Index')
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.stock.available', 2));

        $this->get(route('front.products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Front/Products/Show')
                ->where('product.id', $product->id)
                ->where('product.stock.available', 2));
    }

    public function test_guests_can_filter_the_public_catalog_without_exposing_transaction_routes(): void
    {
        $camera = $this->product('Kamera mirrorless', 'kamera-mirrorless');
        $this->product('Tenda camping', 'tenda-camping');

        $this->get(route('home', ['search' => 'kamera', 'category' => $camera->category->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $camera->id)
                ->where('filters.search', 'kamera')
                ->where('filters.category', $camera->category->slug));

        $this->get(route('front.loans.index'))->assertRedirect(route('login'));
    }

    public function test_guests_can_browse_category_pages(): void
    {
        $product = $this->product('Kamera mirrorless', 'kamera-mirrorless');
        $brand = $product->brand;
        $otherCategory = Category::create(['name' => 'Camping', 'slug' => 'camping']);
        $otherBrand = Brands::create(['name' => 'Outdoor', 'slug' => 'outdoor']);
        Brands::create(['name' => 'Belum ada produk', 'slug' => 'empty-brand']);
        $this->product('Tenda outdoor', 'tenda-outdoor')->update([
            'category_id' => $otherCategory->id, 'brand_id' => $otherBrand->id,
        ]);
        $this->product('Tenda HMW', 'tenda-hmw')->update(['category_id' => $otherCategory->id]);

        $this->get(route('front.categories.index'))->assertOk();
        $this->get(route('front.categories.show', $product->category->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Front/Brands/Index')
                ->where('category.id', $product->category_id)
                ->has('brands.data', 1)
                ->where('brands.data.0.id', $brand->id)
                ->where('brands.data.0.products_count', 1));

        $this->get(route('front.brands.show', [$product->category->slug, $brand->slug]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Front/Brands/Show')
                ->where('brand.id', $brand->id)
                ->has('products.data', 1)
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.stock.available', 1));
        $this->get(route('front.brands.show', [$product->category->slug, $otherBrand->slug]))->assertNotFound();

        $emptyCategory = Category::create(['name' => 'Kategori kosong', 'slug' => 'empty-category']);
        $this->get(route('front.categories.show', $emptyCategory->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('brands.data', 0));
    }

    public function test_login_returns_to_the_selected_product_and_rental_dates(): void
    {
        $product = $this->product('Kamera mirrorless', 'kamera-mirrorless');
        $user = User::factory()->create();
        $parameters = [
            'product' => $product->slug,
            'rent_duration' => 3,
            'rent_start_date' => Carbon::today('Asia/Jakarta')->addDay()->toDateString(),
        ];

        $this->get(route('login', $parameters))->assertOk();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('front.products.show', $parameters));
    }

    public function test_registration_preserves_product_choice_and_only_assigns_member_role(): void
    {
        Notification::fake();
        $product = $this->product('Kamera mirrorless', 'kamera-mirrorless');
        $parameters = ['product' => $product->slug, 'rent_duration' => 2];
        $this->get(route('register', $parameters))->assertOk();

        $this->post(route('register'), [
            'name' => 'Customer Baru',
            'email' => 'baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ])->assertRedirect(route('front.products.show', $parameters));

        $user = User::where('email', 'baru@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('member'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_login_does_not_accept_an_external_product_redirect(): void
    {
        $user = User::factory()->create();
        $this->get(route('login', ['product' => 'https://example.org/redirect']))->assertOk();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('front.products.index'));
    }

    private function product(string $title, string $slug, int $available = 1): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'fotografi'],
            ['name' => 'Fotografi'],
        );
        $brand = Brands::firstOrCreate(
            ['slug' => 'hmw'],
            ['name' => 'HMW'],
        );

        $product = Product::create([
            'prod_code' => 'PRD-'.strtoupper(str()->random(6)),
            'title' => $title,
            'slug' => $slug,
            'description' => 'Barang untuk pengujian katalog publik.',
            'release_year' => '2026',
            'price' => 100000,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);
        $product->stock()->create(['total' => $available, 'available' => $available]);

        return $product->load('category');
    }
}
