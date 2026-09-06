<?php

namespace Tests\Feature;

use App\Models\Brands;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LoanPaymentMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_payment_status_migration_preserves_existing_rentals(): void
    {
        $migrationPath = 'database/migrations/2026_09_06_000001_allow_failed_loan_payment_status.php';
        $this->artisan('migrate:rollback', ['--path' => $migrationPath, '--step' => 1])->assertSuccessful();

        $member = User::factory()->create();
        $category = Category::create(['name' => 'Kamera', 'slug' => 'kamera']);
        $brand = Brands::create(['name' => 'HMW', 'slug' => 'hmw']);
        $product = Product::withoutEvents(fn () => Product::create([
            'prod_code' => 'MIGRATION-1', 'title' => 'Kamera', 'slug' => 'kamera-uji',
            'description' => 'Uji migrasi pembayaran.', 'release_year' => '2026', 'price' => 50000,
            'category_id' => $category->id, 'brand_id' => $brand->id,
        ]));
        $product->stock()->create(['total' => 2, 'available' => 1, 'loan' => 1]);
        $attributes = [
            'user_id' => $member->id, 'product_id' => $product->id,
            'rent_start_date' => '2026-09-06', 'rent_end_date' => '2026-09-09',
            'rent_duration' => 3, 'rent_price' => 150000,
        ];
        $pending = Loan::create($attributes + ['loan_code' => 'pending-1', 'payment_status' => 'pending']);
        $paid = Loan::create($attributes + ['loan_code' => 'paid-1', 'payment_status' => 'paid']);
        $return = $paid->returnProduct()->create([
            'return_product_code' => 'return-1', 'user_id' => $member->id,
            'product_id' => $product->id, 'return_date' => '2026-09-09',
        ]);

        $this->artisan('migrate', ['--path' => $migrationPath])->assertSuccessful();

        $this->assertDatabaseCount('loans', 2);
        $this->assertSame('paid', $paid->fresh()->payment_status);
        $this->assertSame('pending', $pending->fresh()->payment_status);
        $this->assertDatabaseHas('return_products', ['id' => $return->id, 'loan_id' => $paid->id]);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 1, 'loan' => 1]);
        $pending->update(['payment_status' => 'failed']);
        $this->assertSame('failed', $pending->fresh()->payment_status);

        // Leave no rejected status behind so DatabaseMigrations can roll back.
        $pending->update(['payment_status' => 'pending']);
    }
}
