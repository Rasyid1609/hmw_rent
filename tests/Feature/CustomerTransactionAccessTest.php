<?php

namespace Tests\Feature;

use App\Models\Brands;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Product;
use App\Models\ReturnProduct;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerTransactionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_customer_transactions(): void
    {
        $loan = $this->loan($this->member(), $this->product());
        $return = $this->returnedProduct($loan);

        $this->get(route('front.loans.show', $loan->loan_code))->assertRedirect(route('login'));
        $this->get(route('front.loans.checkout', $loan))->assertRedirect(route('login'));
        $this->post(route('front.loans.store', $loan->product->slug))->assertRedirect(route('login'));
        $this->post(route('front.loans.payment', $loan->loan_code))->assertRedirect(route('login'));
        $this->get(route('front.return-products.show', $return->return_product_code))->assertRedirect(route('login'));
        $this->post(route('front.return-products.store', [$loan->product->slug, $loan->loan_code]))
            ->assertRedirect(route('login'));
    }

    public function test_member_cannot_read_or_modify_another_members_transactions(): void
    {
        $loan = $this->loan($this->member(), $this->product());
        $return = $this->returnedProduct($loan);

        $this->actingAs($this->member());
        $this->get(route('front.loans.show', $loan->loan_code))->assertForbidden();
        $this->get(route('front.loans.checkout', $loan))->assertForbidden();
        $this->post(route('front.loans.payment', $loan->loan_code))->assertForbidden();
        $this->get(route('front.return-products.show', $return->return_product_code))->assertForbidden();
        $this->post(route('front.return-products.store', [$loan->product->slug, $loan->loan_code]))
            ->assertForbidden();

        $this->assertDatabaseCount('return_products', 1);
        $this->assertNull($loan->fresh()->proof_image);
    }

    public function test_member_can_view_their_own_loan_checkout_and_return(): void
    {
        $member = $this->member();
        $loan = $this->loan($member, $this->product());
        $return = $this->returnedProduct($loan);

        $this->actingAs($member);
        $this->get(route('front.loans.show', $loan->loan_code))->assertOk();
        $this->get(route('front.loans.checkout', $loan))->assertOk();
        $this->get(route('front.return-products.show', $return->return_product_code))->assertOk();
    }

    public function test_reservation_saves_server_price_and_moves_one_unit_of_stock(): void
    {
        $product = $this->product(2);

        $response = $this->actingAs($this->member())->post(route('front.loans.store', $product->slug), [
            ...$this->rentalData(),
            'rent_price' => 1,
            'payment_status' => 'paid',
        ]);

        $loan = Loan::sole();
        $response->assertRedirect(route('front.loans.checkout', $loan));
        $this->assertSame(150000, (int) $loan->rent_price);
        $this->assertSame('pending', $loan->payment_status);
        $this->assertSame($this->rentalData()['rent_start_date'], $loan->rent_start_date->toDateString());
        $this->assertSame($loan->rent_start_date->copy()->addDays(3)->toDateString(), $loan->rent_end_date->toDateString());
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'total' => 2, 'available' => 1, 'loan' => 1]);
    }

    public function test_out_of_stock_and_missing_stock_do_not_create_a_loan(): void
    {
        $this->actingAs($this->member());

        foreach ([$this->product(0), $this->product(null)] as $product) {
            $this->post(route('front.loans.store', $product->slug), $this->rentalData())
                ->assertRedirect(route('front.products.show', $product->slug))
                ->assertSessionHas('type', 'error');
        }

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_last_available_unit_cannot_be_reserved_by_a_second_member(): void
    {
        $product = $this->product(1);

        $this->actingAs($this->member())->post(route('front.loans.store', $product->slug), $this->rentalData())
            ->assertSessionHasNoErrors();
        $this->actingAs($this->member())->post(route('front.loans.store', $product->slug), $this->rentalData())
            ->assertRedirect(route('front.products.show', $product->slug))
            ->assertSessionHas('type', 'error');

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 0, 'loan' => 1]);
    }

    public function test_duplicate_reservations_and_unapproved_returns_do_not_consume_more_stock(): void
    {
        $product = $this->product(3);
        $this->actingAs($this->member());
        $this->post(route('front.loans.store', $product->slug), $this->rentalData())->assertSessionHasNoErrors();
        $this->post(route('front.loans.store', $product->slug), $this->rentalData())
            ->assertRedirect(route('front.products.show', $product->slug));

        $this->returnedProduct(Loan::sole());
        $this->post(route('front.loans.store', $product->slug), $this->rentalData())
            ->assertRedirect(route('front.products.show', $product->slug));

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 2, 'loan' => 1]);
    }

    public function test_failed_loan_insert_rolls_back_the_stock_reservation(): void
    {
        $existing = $this->loan($this->member(), $this->product());
        $existing->update(['loan_code' => 'aaaaaaaaaa']);
        $product = $this->product(1);
        $this->actingAs($this->member())->withoutExceptionHandling();
        Str::createRandomStringsUsing(fn ($length) => str_repeat('a', $length));

        try {
            $this->post(route('front.loans.store', $product->slug), $this->rentalData());
            $this->fail('The duplicate loan code should fail its database constraint.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('loan_code', $exception->getMessage());
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 1, 'loan' => 0]);
    }

    public function test_rental_date_uses_jakarta_today_and_rejects_invalid_dates_and_durations(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 18:00:00', 'UTC'));
        $product = $this->product(2);
        $this->actingAs($this->member());

        foreach (['2026-09-05', '2026-02-30', '06/09/2026'] as $date) {
            $this->post(route('front.loans.store', $product->slug), ['rent_start_date' => $date, 'rent_duration' => 1])
                ->assertSessionHasErrors('rent_start_date');
        }

        foreach ([0, -1, 1.5, 366] as $duration) {
            $this->post(route('front.loans.store', $product->slug), ['rent_start_date' => '2026-09-06', 'rent_duration' => $duration])
                ->assertSessionHasErrors('rent_duration');
        }

        $this->assertDatabaseCount('loans', 0);
        $this->post(route('front.loans.store', $product->slug), ['rent_start_date' => '2026-09-06', 'rent_duration' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_return_product_must_match_the_loan_and_retries_reuse_the_same_return(): void
    {
        $member = $this->member();
        $product = $this->product(2);
        $loan = $this->loan($member, $product);
        $this->actingAs($member);

        $this->post(route('front.return-products.store', [$this->product()->slug, $loan->loan_code]))->assertNotFound();
        $this->assertDatabaseCount('return_products', 0);
        $this->post(route('front.return-products.store', [$product->slug, $loan->loan_code]))->assertRedirect();
        $return = ReturnProduct::sole();
        $this->post(route('front.return-products.store', [$product->slug, $loan->loan_code]))
            ->assertRedirect(route('front.return-products.show', $return->return_product_code));

        $this->assertDatabaseCount('return_products', 1);
        $this->assertDatabaseHas('return_products', ['id' => $return->id, 'user_id' => $member->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 2, 'loan' => 0]);
    }

    public function test_relation_searches_only_return_the_authenticated_members_rows(): void
    {
        $member = $this->member();
        $ownLoan = $this->loan($member, $this->product());
        $otherLoan = $this->loan($this->member(), $this->product());
        $ownReturn = $this->returnedProduct($ownLoan);
        $this->returnedProduct($otherLoan);
        $this->actingAs($member);

        // All fixture product titles match, so relation OR conditions must remain inside the owner scope.
        $this->get(route('front.loans.index', ['search' => 'Rental equipment']))
            ->assertInertia(fn (Assert $page) => $page->has('loans.data', 1)->where('loans.data.0.id', $ownLoan->id));
        $this->get(route('front.return-products.index', ['search' => 'Rental equipment']))
            ->assertInertia(fn (Assert $page) => $page->has('return_products.data', 1)->where('return_products.data.0.id', $ownReturn->id));
        $this->get(route('front.return-products.index', ['search' => $otherLoan->loan_code]))
            ->assertInertia(fn (Assert $page) => $page->has('return_products.data', 0));
    }

    public function test_payment_proof_can_be_uploaded_only_for_an_unfinished_owned_loan(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $member = $this->member();
        $loan = $this->loan($member, $this->product());
        $this->actingAs($member);

        $this->post(route('front.loans.payment', $loan->loan_code), ['proof_image' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')])
            ->assertRedirect(route('front.loans.index'));
        $path = $loan->fresh()->proof_image;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->post(route('front.loans.payment', $loan->loan_code), ['proof_image' => UploadedFile::fake()->create('updated.jpg', 100, 'image/jpeg')])
            ->assertStatus(409);
        $this->assertSame($path, $loan->fresh()->proof_image);
        Storage::disk('local')->assertExists($path);
        $loan->update(['payment_status' => 'paid']);

        $this->post(route('front.loans.payment', $loan->loan_code), ['proof_image' => UploadedFile::fake()->create('replacement.jpg', 100, 'image/jpeg')])
            ->assertStatus(409);
        $this->assertSame('paid', $loan->fresh()->payment_status);
        $this->assertSame($path, $loan->fresh()->proof_image);

        $returnedLoan = $this->loan($member, $this->product());
        $this->returnedProduct($returnedLoan);
        $this->post(route('front.loans.payment', $returnedLoan->loan_code), ['proof_image' => UploadedFile::fake()->create('returned.jpg', 100, 'image/jpeg')])
            ->assertStatus(409);
        $this->assertNull($returnedLoan->fresh()->proof_image);
        $this->assertCount(1, Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_rejected_rental_payment_can_be_resubmitted_and_reviewed_by_staff(): void
    {
        Storage::fake('local');
        $member = $this->member();
        $loan = $this->loan($member, $this->product());
        $admin = User::factory()->unverified()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $operator = User::factory()->unverified()->create();
        $operator->assignRole(Role::findOrCreate('operator', 'web'));

        $this->actingAs($member)->get(route('front.loans.checkout', $loan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_upload_payment_proof', true)->where('payment_status_label', 'Belum dibayar'));
        $this->post(route('front.loans.payment', $loan->loan_code), [
            'proof_image' => UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg'),
            'payment_status' => 'paid',
        ])->assertSessionHasNoErrors();
        $firstProof = $loan->fresh()->proof_image;
        $this->assertSame('pending', $loan->fresh()->payment_status);
        $this->get(route('front.loans.checkout', $loan))->assertInertia(fn (Assert $page) => $page
            ->where('can_upload_payment_proof', false)->where('payment_status_label', 'Menunggu verifikasi'));
        $this->actingAs($admin)->get(route('admin.loans.edit', $loan))
            ->assertInertia(fn (Assert $page) => $page->where('page_data.can_review', true));
        $this->put(route('admin.loans.update', $loan), [
            'payment_status' => 'failed', 'reviewed_proof' => $firstProof,
        ])->assertRedirect(route('admin.loans.index'))->assertSessionHasNoErrors();
        $this->assertSame('failed', $loan->fresh()->payment_status);

        // A rejected proof cannot be approved until the customer submits again.
        $this->put(route('admin.loans.update', $loan), [
            'payment_status' => 'paid', 'reviewed_proof' => $firstProof,
        ])->assertSessionHasErrors('payment_status');
        $this->actingAs($member)->get(route('front.loans.show', $loan->loan_code))
            ->assertInertia(fn (Assert $page) => $page->where('loan.can_upload_payment_proof', true));
        $this->post(route('front.loans.payment', $loan->loan_code), [
            'proof_image' => UploadedFile::fake()->create('corrected.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();
        $currentProof = $loan->fresh()->proof_image;
        $this->assertNotSame($firstProof, $currentProof);
        Storage::disk('local')->assertMissing($firstProof);
        Storage::disk('local')->assertExists($currentProof);

        // Another reviewer may still have the old proof open in a tab.
        $this->actingAs($operator)->put(route('admin.loans.update', $loan), [
            'payment_status' => 'paid', 'reviewed_proof' => $firstProof,
        ])->assertSessionHasErrors('payment_status');
        $this->assertSame('pending', $loan->fresh()->payment_status);
        $this->put(route('admin.loans.update', $loan), [
            'payment_status' => 'paid', 'reviewed_proof' => $currentProof,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.loans.index'));
        $this->assertSame('paid', $loan->fresh()->payment_status);
        $this->get(route('admin.loans.edit', $loan))
            ->assertInertia(fn (Assert $page) => $page->where('page_data.can_review', false));
        $this->actingAs($member)->get(route('front.loans.checkout', $loan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_upload_payment_proof', false)->where('payment_status_label', 'Lunas'));
    }

    public function test_paid_rental_cannot_be_downgraded_then_deleted(): void
    {
        $product = $this->product();
        $product->stock()->update(['available' => 1, 'loan' => 1]);
        $loan = $this->loan($this->member(), $product);
        $loan->update(['payment_status' => 'paid', 'proof_image' => 'payment-proofs/paid.jpg']);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);

        foreach (['pending', 'failed', 'unpaid', 'paid', 'unknown'] as $status) {
            $this->put(route('admin.loans.update', $loan), [
                'payment_status' => $status, 'reviewed_proof' => $loan->proof_image,
            ])->assertSessionHasErrors('payment_status');
            $this->assertSame('paid', $loan->fresh()->payment_status);
        }

        $this->delete(route('admin.loans.destroy', $loan))->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => $loan->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'available' => 1, 'loan' => 1]);
    }

    public function test_rental_review_requires_a_submitted_proof_and_approval_requires_its_file(): void
    {
        Storage::fake('local');
        $loan = $this->loan($this->member(), $this->product());
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);

        foreach (['paid', 'failed'] as $status) {
            $this->put(route('admin.loans.update', $loan), [
                'payment_status' => $status, 'reviewed_proof' => 'payment-proofs/missing.jpg',
            ])->assertSessionHasErrors('payment_status');
            $this->assertSame('pending', $loan->fresh()->payment_status);
        }

        $loan->update(['proof_image' => 'payment-proofs/missing.jpg']);
        $this->put(route('admin.loans.update', $loan), ['payment_status' => 'paid'])
            ->assertSessionHasErrors('reviewed_proof');
        $this->put(route('admin.loans.update', $loan), [
            'payment_status' => 'paid', 'reviewed_proof' => $loan->proof_image,
        ])->assertSessionHasErrors('payment_status');
        $this->assertSame('pending', $loan->fresh()->payment_status);

        // Rejecting a lost file allows the customer to submit a replacement.
        $this->put(route('admin.loans.update', $loan), [
            'payment_status' => 'failed', 'reviewed_proof' => $loan->proof_image,
        ])->assertRedirect(route('admin.loans.index'))->assertSessionHasNoErrors();
        $this->assertSame('failed', $loan->fresh()->payment_status);
    }

    public function test_members_cannot_review_payments_and_returns_lock_payment_changes(): void
    {
        Storage::fake('local');
        $member = $this->member();
        $loan = $this->loan($member, $this->product());
        $loan->update(['proof_image' => 'payment-proofs/review.jpg']);
        Storage::disk('local')->put($loan->proof_image, 'proof');
        $review = ['payment_status' => 'paid', 'reviewed_proof' => $loan->proof_image];
        $this->actingAs($member)->put(route('admin.loans.update', $loan), $review)->assertForbidden();
        $this->assertSame('pending', $loan->fresh()->payment_status);

        $this->returnedProduct($loan);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin)->put(route('admin.loans.update', $loan), $review)
            ->assertSessionHasErrors('payment_status');
        $this->assertSame('pending', $loan->fresh()->payment_status);
    }

    public function test_customer_cannot_delete_their_account_to_erase_a_loan(): void
    {
        $member = $this->member();
        $loan = $this->loan($member, $this->product());

        $this->actingAs($member)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');
        $this->assertAuthenticatedAs($member);
        $this->assertDatabaseHas('loans', ['id' => $loan->id]);
    }

    public function test_return_list_sorts_by_rental_dates_without_leaking_other_members(): void
    {
        $member = $this->member();
        $later = $this->loan($member, $this->product());
        $earlier = $this->loan($member, $this->product());
        $earlier->update(['rent_start_date' => '2026-01-01', 'rent_end_date' => '2026-01-04']);
        $lateReturn = $this->returnedProduct($later);
        $earlyReturn = $this->returnedProduct($earlier);
        $this->returnedProduct($this->loan($this->member(), $this->product()));
        $this->actingAs($member);

        foreach (['rent_start_date', 'rent_end_date', 'loan_date', 'due_date'] as $field) {
            $this->get(route('front.return-products.index', ['field' => $field, 'direction' => 'asc']))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->has('return_products.data', 2)
                    ->where('return_products.data.0.id', $earlyReturn->id)
                    ->where('return_products.data.1.id', $lateReturn->id));
        }
    }

    private function member(): User
    {
        $member = User::factory()->unverified()->create(['username' => 'customer_'.Str::random(12)]);
        $member->assignRole(Role::findOrCreate('member', 'web'));

        return $member;
    }

    private function product(?int $available = 2): Product
    {
        $suffix = Str::random(12);
        $category = Category::create(['name' => 'Equipment', 'slug' => 'equipment-'.$suffix]);
        $brand = Brands::create(['name' => 'Rental brand', 'slug' => 'brand-'.$suffix]);
        $product = Product::withoutEvents(fn () => Product::create([
            'prod_code' => 'PRD-'.$suffix,
            'title' => 'Rental equipment '.$suffix,
            'slug' => 'equipment-'.$suffix,
            'description' => 'Equipment for customer rental tests.',
            'release_year' => '2026',
            'price' => 50000,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]));

        if ($available !== null) {
            $product->stock()->create(['total' => $available, 'available' => $available, 'loan' => 0]);
        }

        return $product;
    }

    private function loan(User $member, Product $product): Loan
    {
        $start = Carbon::today('Asia/Jakarta');

        return Loan::create([
            'loan_code' => Str::lower(Str::random(10)),
            'user_id' => $member->id,
            'product_id' => $product->id,
            'rent_start_date' => $start,
            'rent_end_date' => $start->copy()->addDays(3),
            'rent_duration' => 3,
            'rent_price' => 150000,
            'payment_status' => 'pending',
        ]);
    }

    private function returnedProduct(Loan $loan): ReturnProduct
    {
        return $loan->returnProduct()->create([
            'return_product_code' => Str::lower(Str::random(10)),
            'product_id' => $loan->product_id,
            'user_id' => $loan->user_id,
            'return_date' => Carbon::today('Asia/Jakarta'),
        ]);
    }

    private function rentalData(): array
    {
        return ['rent_start_date' => Carbon::today('Asia/Jakarta')->toDateString(), 'rent_duration' => 3];
    }
}
