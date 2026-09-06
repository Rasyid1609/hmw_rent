<?php

namespace Tests\Feature;

use App\Enums\ReturnProductCondition;
use App\Enums\ReturnProductStatus;
use App\Models\Brands;
use App\Models\Category;
use App\Models\FineSetting;
use App\Models\Loan;
use App\Models\Product;
use App\Models\ReturnProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_access_privilege_management_but_can_manage_products(): void
    {
        $operator = $this->userWithRole('operator');

        $this->actingAs($operator);

        foreach ([
            'admin.roles.index',
            'admin.permissions.index',
            'admin.assign-permissions.index',
            'admin.assign-users.index',
            'admin.route-accesses.index',
            'admin.users.index',
        ] as $route) {
            $this->get(route($route))->assertForbidden();
        }

        $this->get(route('admin.products.index'))->assertOk();
    }

    public function test_operator_cannot_change_an_admin_password(): void
    {
        $admin = $this->userWithRole('admin');
        $passwordHash = $admin->password;
        $this->actingAs($this->userWithRole('operator'));

        $this->get(route('admin.users.edit', $admin))->assertForbidden();
        $this->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => 'replaced-password',
            'password_confirmation' => 'replaced-password',
        ])->assertForbidden();

        $this->assertSame($passwordHash, $admin->fresh()->password);
    }

    public function test_admin_can_edit_user_details_without_replacing_login_credentials(): void
    {
        $member = $this->userWithRole('member');
        $passwordHash = $member->password;
        $username = $member->username;

        $this->actingAs($this->userWithRole('admin'))->put(route('admin.users.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'phone' => '081234567890',
            'password' => '',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame('081234567890', $member->fresh()->phone);
        $this->assertSame($passwordHash, $member->fresh()->password);
        $this->assertSame($username, $member->fresh()->username);
    }

    public function test_admin_can_load_and_sync_user_roles_by_id(): void
    {
        $member = $this->userWithRole('member');
        $role = Role::findOrCreate('operator', 'web');
        $this->actingAs($this->userWithRole('admin'));

        $this->get(route('admin.assign-users.edit', $member))->assertOk();
        $this->put(route('admin.assign-users.update', $member), ['roles' => [(string) $role->id]])
            ->assertSessionHasNoErrors();

        $this->assertTrue($member->fresh()->hasRole('operator'));
        $this->assertFalse($member->fresh()->hasRole('member'));
    }

    public function test_seeded_admin_can_open_dashboard_without_route_access_seed_data(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->actingAs(User::where('email', 'owner@example.com')->firstOrFail())
            ->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
    }

    public function test_product_edits_recalculate_available_stock_and_reject_impossible_totals(): void
    {
        $product = $this->product(3, 1, 2);
        $payload = [
            'title' => $product->title,
            'description' => $product->description,
            'release_year' => $product->release_year,
            'price' => $product->price,
            'total' => 4,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
        ];
        $this->actingAs($this->userWithRole('admin'));

        $this->put(route('admin.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'total' => 4, 'available' => 2, 'loan' => 2]);

        $this->put(route('admin.products.update', $product), [...$payload, 'total' => 1])
            ->assertSessionHasErrors('total');
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'total' => 4, 'available' => 2, 'loan' => 2]);
    }

    public function test_stock_report_ignores_forged_reserved_counts(): void
    {
        $product = $this->product(3, 1, 2);
        $this->actingAs($this->userWithRole('operator'));
        $route = route('admin.product-stock-reports.update', $product->stock);

        $this->put($route, ['total' => 4, 'available' => 900, 'loan' => 0, 'lost' => 0, 'damaged' => 0])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'total' => 4, 'available' => 2, 'loan' => 2]);

        $this->put($route, ['total' => 1, 'available' => 1, 'loan' => 0])
            ->assertSessionHasErrors('total');
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'total' => 4, 'available' => 2, 'loan' => 2]);
    }

    public function test_product_creation_creates_its_stock_in_the_same_request(): void
    {
        $admin = $this->userWithRole('admin');
        [$category, $brand] = $this->catalogueData();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'title' => 'Tripod profesional',
            'description' => 'Tripod untuk kebutuhan dokumentasi.',
            'release_year' => 2026,
            'price' => 50000,
            'total' => 3,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::sole();

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'total' => 3,
            'available' => 3,
            'loan' => 0,
            'lost' => 0,
            'damaged' => 0,
        ]);
    }

    public function test_legacy_admin_loan_dates_are_normalized_and_stock_is_reserved_once(): void
    {
        $admin = $this->userWithRole('admin');
        $member = $this->userWithRole('member');
        $product = $this->product(2);

        $this->actingAs($admin)->post(route('admin.loans.store'), [
            'user_id' => $member->id,
            'product_id' => $product->id,
            'loan_date' => '2026-09-10',
            'due_date' => '2026-09-13',
            'rent_price' => 1,
            'payment_status' => 'paid',
        ])->assertRedirect(route('admin.loans.index'));

        $loan = Loan::sole();

        $this->assertSame('2026-09-10', $loan->rent_start_date->toDateString());
        $this->assertSame('2026-09-13', $loan->rent_end_date->toDateString());
        $this->assertSame(3, $loan->rent_duration);
        $this->assertSame('pending', $loan->payment_status);
        $this->assertSame(150000, (int) $loan->rent_price);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 1, 'loan' => 1]);

        $this->post(route('admin.loans.store'), [
            'user_id' => $member->id,
            'product_id' => $product->id,
            'rent_start_date' => '2026-09-14',
            'rent_end_date' => '2026-09-16',
            'rent_duration' => 2,
        ])->assertRedirect(route('admin.loans.index'));

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 1, 'loan' => 1]);
    }

    public function test_return_approval_uses_rent_end_date_and_is_idempotent(): void
    {
        $admin = $this->userWithRole('admin');
        $member = $this->userWithRole('member');
        $product = $this->product(2, 1, 1);
        $rentStart = Carbon::parse('2026-09-01');
        $rentEnd = Carbon::parse('2026-09-03');
        $loan = Loan::create([
            'loan_code' => Str::lower(Str::random(10)),
            'user_id' => $member->id,
            'product_id' => $product->id,
            'rent_start_date' => $rentStart,
            'rent_end_date' => $rentEnd,
            'rent_duration' => 2,
            'rent_price' => 100000,
            'payment_status' => 'pending',
        ]);
        $returnProduct = $loan->returnProduct()->create([
            'return_product_code' => Str::lower(Str::random(10)),
            'product_id' => $product->id,
            'user_id' => $member->id,
            'return_date' => $rentEnd->copy()->addDays(2),
        ]);
        FineSetting::create([
            'late_fee_per_day' => 7000,
            'damage_fee_percentage' => 50,
            'lost_fee_percentage' => 100,
        ]);

        $this->assertFalse($returnProduct->isOnTime());
        $this->assertSame(2, $returnProduct->getDaysLate());

        $this->actingAs($admin)->put(route('admin.return-products.approve', [
            'returnProduct' => $returnProduct->return_product_code,
        ]), [
            'condition' => ReturnProductCondition::GOOD->value,
        ])->assertRedirect(route('admin.return-products.index'));

        $returnProduct->refresh();
        $this->assertSame(ReturnProductStatus::FINE, $returnProduct->status);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 2, 'loan' => 0]);

        $this->put(route('admin.return-products.approve', [
            'returnProduct' => $returnProduct->return_product_code,
        ]), [
            'condition' => ReturnProductCondition::GOOD->value,
        ])->assertRedirect(route('admin.return-products.index'));

        $this->assertDatabaseCount('return_product_checks', 1);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 2, 'loan' => 0]);
    }

    public function test_uploaded_product_cover_is_public_even_with_a_private_default_disk(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('public');
        [$category, $brand] = $this->catalogueData();

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.products.store'), [
            'title' => 'Kamera uji',
            'description' => 'Kamera untuk pengujian foto katalog.',
            'release_year' => 2026,
            'price' => 50000,
            'total' => 1,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'cover' => UploadedFile::fake()->create('kamera.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $product = Product::sole();
        Storage::disk('public')->assertExists($product->cover);
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.cover', Storage::disk('public')->url($product->cover)));
    }

    public function test_catalog_and_user_deletion_cannot_erase_rental_history(): void
    {
        $member = $this->userWithRole('member');
        $product = $this->product(2);
        $this->actingAs($this->userWithRole('admin'))->post(route('admin.loans.store'), [
            'user_id' => $member->id,
            'product_id' => $product->id,
            'rent_start_date' => '2026-09-10',
            'rent_end_date' => '2026-09-13',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('loans', 1);

        foreach ([
            route('admin.products.destroy', $product),
            route('admin.categories.destroy', $product->category_id),
            route('admin.brands.destroy', $product->brand_id),
            route('admin.users.destroy', $member),
        ] as $route) {
            $this->delete($route)->assertRedirect()->assertSessionHas('type', 'error');
        }

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }

    public function test_loan_creation_selects_exact_ids_when_display_names_are_duplicated(): void
    {
        $firstMember = $this->userWithRole('member');
        $member = $this->userWithRole('member');
        $member->update(['name' => $firstMember->name]);
        $firstProduct = $this->product(2);
        $product = $this->product(2);
        $product->update(['title' => $firstProduct->title]);

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.loans.store'), [
            'user_id' => $member->id,
            'product_id' => $product->id,
            'rent_start_date' => Carbon::today()->toDateString(),
            'rent_end_date' => Carbon::today()->addDays(2)->toDateString(),
        ])->assertSessionHasNoErrors();

        $loan = Loan::sole();
        $this->assertSame($member->id, $loan->user_id);
        $this->assertSame($product->id, $loan->product_id);
        $this->assertDatabaseHas('stocks', ['product_id' => $firstProduct->id, 'available' => 2]);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('page_data.total_loans', 1)
            ->where('page_data.transactionChart', fn ($chart) => collect($chart)->sum('loan') === 1));
    }

    public function test_brand_edit_and_update_bind_the_requested_brand(): void
    {
        [, $brand] = $this->catalogueData();
        $this->actingAs($this->userWithRole('admin'));
        $this->get(route('admin.brands.edit', $brand))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('brands.id', $brand->id));
        $this->put(route('admin.brands.update', $brand), ['name' => 'Brand diperbarui'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Brand diperbarui', $brand->fresh()->name);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['username' => $role.'_'.Str::random(12)]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    /** @return array{Category, Brands} */
    private function catalogueData(): array
    {
        $suffix = Str::random(12);

        return [
            Category::create(['name' => 'Equipment '.$suffix, 'slug' => 'equipment-'.$suffix]),
            Brands::create(['name' => 'Rental brand '.$suffix, 'slug' => 'rental-brand-'.$suffix]),
        ];
    }

    private function product(int $total, ?int $available = null, int $loan = 0): Product
    {
        [$category, $brand] = $this->catalogueData();
        $suffix = Str::random(12);
        $product = Product::create([
            'prod_code' => 'PRD-'.$suffix,
            'title' => 'Rental equipment '.$suffix,
            'slug' => 'rental-equipment-'.$suffix,
            'description' => 'Equipment for admin integrity tests.',
            'release_year' => '2026',
            'price' => 50000,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        $product->stock()->create([
            'total' => $total,
            'available' => $available ?? $total,
            'loan' => $loan,
            'lost' => 0,
            'damaged' => 0,
        ]);

        return $product;
    }
}
