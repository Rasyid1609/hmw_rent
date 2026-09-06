<?php

namespace Tests\Feature;

use App\Enums\FinePaymentStatus;
use App\Enums\ReturnProductStatus;
use App\Models\Brands;
use App\Models\Category;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\Product;
use App\Models\ReturnProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_a_valid_payment_proof(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $member = $this->member();
        $fine = $this->fineFor($member);

        $this->actingAs($member)
            ->from('/')
            ->post(route('payments.upload-proof', $fine), [
                'proof_image' => UploadedFile::fake()->create('fine-proof.png', 100, 'image/png'),
            ])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $fine->refresh();

        $this->assertSame(FinePaymentStatus::WAITING_VERIFICATION, $fine->payment_status);
        $this->assertNotNull($fine->proof_image);
        Storage::disk('local')->assertExists($fine->proof_image);
        Storage::disk('public')->assertMissing($fine->proof_image);
    }

    public function test_payment_proof_must_be_an_image(): void
    {
        Storage::fake('local');
        $member = $this->member();
        $fine = $this->fineFor($member);

        $this->actingAs($member)
            ->from('/')
            ->post(route('payments.upload-proof', $fine), [
                'proof_image' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('proof_image');

        $this->assertSame(FinePaymentStatus::PENDING, $fine->fresh()->payment_status);
        $this->assertNull($fine->fresh()->proof_image);
        Storage::disk('local')->assertDirectoryEmpty('payment-proofs');
    }

    public function test_member_cannot_upload_proof_for_someone_elses_fine(): void
    {
        Storage::fake('local');
        $fine = $this->fineFor($this->member());

        $this->actingAs($this->member())
            ->post(route('payments.upload-proof', $fine), [
                'proof_image' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
            ])
            ->assertForbidden();

        $this->assertSame(FinePaymentStatus::PENDING, $fine->fresh()->payment_status);
        $this->assertNull($fine->fresh()->proof_image);
        Storage::disk('local')->assertDirectoryEmpty('payment-proofs');
    }

    public function test_uploaded_file_is_deleted_when_the_fine_update_fails(): void
    {
        Storage::fake('local');
        $member = $this->member();
        $fine = $this->fineFor($member);

        Fine::saving(static function (): void {
            throw new RuntimeException('Simulated database failure.');
        });

        try {
            $this->actingAs($member)
                ->from('/')
                ->post(route('payments.upload-proof', $fine), [
                    'proof_image' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
                ])
                ->assertRedirect('/')
                ->assertSessionHas('type', 'error');
        } finally {
            Fine::flushEventListeners();
        }

        $this->assertSame(FinePaymentStatus::PENDING, $fine->fresh()->payment_status);
        $this->assertNull($fine->fresh()->proof_image);
        Storage::disk('local')->assertDirectoryEmpty('payment-proofs');
    }

    public function test_member_cannot_replace_a_proof_while_it_is_waiting_for_review(): void
    {
        Storage::fake('local');
        $member = $this->member();
        $fine = $this->fineFor(
            $member,
            FinePaymentStatus::WAITING_VERIFICATION,
            'payment-proofs/original.jpg'
        );
        Storage::disk('local')->put($fine->proof_image, 'original proof');

        $this->actingAs($member)
            ->post(route('payments.upload-proof', $fine), [
                'proof_image' => UploadedFile::fake()->create('replacement.jpg', 100, 'image/jpeg'),
            ])
            ->assertStatus(409);

        $this->assertSame(FinePaymentStatus::WAITING_VERIFICATION, $fine->fresh()->payment_status);
        $this->assertSame('payment-proofs/original.jpg', $fine->fresh()->proof_image);
        Storage::disk('local')->assertExists('payment-proofs/original.jpg');
        $this->assertCount(1, Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_admin_can_approve_a_waiting_payment_and_close_the_return(): void
    {
        $fine = $this->reviewableFine($this->member());
        $admin = $this->reviewer('admin');

        $this->actingAs($admin)
            ->from('/')
            ->patch(route('payments.approve', $fine))
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->assertSame(FinePaymentStatus::SUCCESS, $fine->fresh()->payment_status);
        $this->assertSame(ReturnProductStatus::RETURNED, $fine->returnProduct->fresh()->status);
    }

    public function test_accounting_can_reject_a_waiting_payment_without_closing_the_return(): void
    {
        $fine = $this->reviewableFine($this->member());
        $accounting = $this->reviewer('accounting');

        $this->actingAs($accounting)
            ->from('/')
            ->patch(route('payments.reject', $fine))
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->assertSame(FinePaymentStatus::FAILED, $fine->fresh()->payment_status);
        $this->assertSame(ReturnProductStatus::FINE, $fine->returnProduct->fresh()->status);
    }

    public function test_member_cannot_review_a_fine_payment(): void
    {
        $member = $this->member();
        $fine = $this->reviewableFine($member);

        $this->actingAs($member)
            ->patch(route('payments.approve', $fine))
            ->assertForbidden();

        $this->assertSame(FinePaymentStatus::WAITING_VERIFICATION, $fine->fresh()->payment_status);
        $this->assertSame(ReturnProductStatus::FINE, $fine->returnProduct->fresh()->status);
    }

    public function test_payment_cannot_be_approved_before_a_proof_is_waiting_for_review(): void
    {
        $fine = $this->fineFor($this->member());

        $this->actingAs($this->reviewer('admin'))
            ->patch(route('payments.approve', $fine))
            ->assertStatus(409);

        $this->assertSame(FinePaymentStatus::PENDING, $fine->fresh()->payment_status);
        $this->assertSame(ReturnProductStatus::FINE, $fine->returnProduct->fresh()->status);
    }

    public function test_only_wired_payment_routes_exist_and_success_page_uses_its_component(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName('payments.create'));
        $this->assertNull(app('router')->getRoutes()->getByName('payments.callback'));

        $this->actingAs($this->member())
            ->get(route('payments.success'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Payment/Success'));
    }

    public function test_reviewers_can_open_fine_reports_and_proof_details_but_members_cannot(): void
    {
        $fine = $this->reviewableFine($this->member());
        $detail = route('admin.fines.create', $fine->returnProduct->return_product_code);

        $this->actingAs($this->reviewer('accounting'))->get(route('admin.fine-reports.index'))->assertOk();
        $this->get($detail)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Fines/Create')
            ->where('can_review', true)
            ->where('return_product.fine.id', $fine->id)
            ->where('return_product.fine.proof_image', route('payment-proofs.fines.show', $fine)));

        $this->actingAs($this->reviewer('operator'))->get($detail)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_review', false));
        $this->patch(route('payments.approve', $fine))->assertForbidden();
        $this->actingAs($this->member())->get($detail)->assertForbidden();
    }

    public function test_payment_proof_images_require_login_and_transaction_access(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->member();
        $fine = $this->fineFor($owner, FinePaymentStatus::WAITING_VERIFICATION, 'payment-proofs/fine.png');
        $loan = $fine->returnProduct->loan;
        $loan->update(['proof_image' => 'payment-proofs/loan.png']);
        foreach ([$fine->proof_image, $loan->proof_image] as $path) {
            Storage::disk('local')->put($path, $this->proofBytes());
        }
        $urls = [route('payment-proofs.loans.show', $loan), route('payment-proofs.fines.show', $fine)];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->actingAs($this->member());
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach ([$owner, $this->reviewer('admin'), $this->reviewer('operator')] as $viewer) {
            $this->actingAs($viewer);
            foreach ($urls as $url) {
                $response = $this->get($url)->assertOk()
                    ->assertHeader('Content-Type', 'image/png')
                    ->assertHeader('X-Content-Type-Options', 'nosniff');
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
                $this->assertSame($this->proofBytes(), $response->streamedContent());
            }
        }
        $this->actingAs($this->reviewer('accounting'));
        $this->get($urls[0])->assertForbidden();
        $this->get($urls[1])->assertOk();
        $this->actingAs($owner)->get(route('front.loans.checkout', $loan))
            ->assertInertia(fn (Assert $page) => $page->where('proof_url', $urls[0]));
        $this->get(route('front.return-products.show', $fine->returnProduct->return_product_code))
            ->assertInertia(fn (Assert $page) => $page->where('return_product.fine.proof_url', $urls[1]));
    }

    public function test_private_proof_routes_do_not_serve_public_fallbacks_or_arbitrary_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->member();
        $fine = $this->fineFor($owner);
        $loan = $fine->returnProduct->loan;
        Storage::disk('public')->put('payment-proofs/public-only.png', $this->proofBytes());
        Storage::disk('local')->put('documents/private.png', $this->proofBytes());
        Storage::disk('local')->put('payment-proofs/pretend.png', '<html>Not an image</html>');
        $this->actingAs($owner);

        foreach ([null, 'payment-proofs/public-only.png', 'documents/private.png',
            'payment-proofs/../documents/private.png', 'payment-proofs/pretend.png'] as $path) {
            $fine->update(['proof_image' => $path]);
            $loan->update(['proof_image' => $path]);
            $this->get(route('payment-proofs.loans.show', $loan))->assertNotFound();
            $this->get(route('payment-proofs.fines.show', $fine))->assertNotFound();
        }
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    public function test_existing_payment_proofs_are_moved_without_changing_transactions(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->member();
        $fine = $this->fineFor($owner, FinePaymentStatus::WAITING_VERIFICATION, 'payment-proofs/fine.png');
        $loan = $fine->returnProduct->loan;
        $loan->update(['proof_image' => 'payment-proofs/loan.png']);
        $paths = [$fine->proof_image, $loan->proof_image, 'payment-proofs/orphan.png'];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, $this->proofBytes());
        }
        // Resume a previous run that stopped after copying but before deletion.
        Storage::disk('local')->put($fine->proof_image, $this->proofBytes());
        Storage::disk('public')->put('products/cover.png', $this->proofBytes());

        $this->artisan('payment-proofs:protect')->assertSuccessful();
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
            $this->assertSame($this->proofBytes(), Storage::disk('local')->get($path));
            $this->get('/storage/'.$path)->assertNotFound();
        }
        Storage::disk('public')->assertExists('products/cover.png');
        $this->assertSame($loan->proof_image, $loan->fresh()->proof_image);
        $this->assertSame($fine->proof_image, $fine->fresh()->proof_image);
        $this->assertSame(FinePaymentStatus::WAITING_VERIFICATION, $fine->fresh()->payment_status);
        $this->assertSame('pending', $loan->fresh()->payment_status);
        $this->actingAs($owner)->get(route('payment-proofs.fines.show', $fine))->assertOk();
        $this->get(route('payment-proofs.loans.show', $loan))->assertOk();
        $this->artisan('payment-proofs:protect')->assertSuccessful();
    }

    public function test_private_file_conflicts_keep_both_original_files_intact(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $path = 'payment-proofs/conflict.png';
        Storage::disk('public')->put($path, 'public original');
        Storage::disk('local')->put($path, 'private original');

        $this->artisan('payment-proofs:protect')->assertFailed();

        $this->assertSame('public original', Storage::disk('public')->get($path));
        $this->assertSame('private original', Storage::disk('local')->get($path));
    }

    private function proofBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aG1cAAAAASUVORK5CYII=');
    }

    private function member(): User
    {
        $member = User::factory()->unverified()->create([
            'username' => 'member_'.Str::random(12),
        ]);
        $member->assignRole(Role::findOrCreate('member', 'web'));

        return $member;
    }

    private function reviewer(string $role): User
    {
        $reviewer = User::factory()->unverified()->create([
            'username' => $role.'_'.Str::random(12),
        ]);
        $reviewer->assignRole(Role::findOrCreate($role, 'web'));

        return $reviewer;
    }

    private function fineFor(
        User $member,
        FinePaymentStatus $paymentStatus = FinePaymentStatus::PENDING,
        ?string $proofImage = null
    ): Fine {
        $suffix = Str::random(12);
        $category = Category::create([
            'name' => 'Fine category '.$suffix,
            'slug' => 'fine-category-'.$suffix,
        ]);
        $brand = Brands::create([
            'name' => 'Fine brand '.$suffix,
            'slug' => 'fine-brand-'.$suffix,
        ]);
        $product = Product::withoutEvents(fn () => Product::create([
            'prod_code' => 'FINE-'.$suffix,
            'title' => 'Fine payment product '.$suffix,
            'slug' => 'fine-payment-product-'.$suffix,
            'description' => 'Product for fine payment tests.',
            'release_year' => '2026',
            'price' => 150000,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]));
        $loan = Loan::create([
            'loan_code' => Str::lower(Str::random(10)),
            'user_id' => $member->id,
            'product_id' => $product->id,
            'rent_start_date' => Carbon::today('Asia/Jakarta'),
            'rent_end_date' => Carbon::today('Asia/Jakarta')->addDays(3),
            'rent_duration' => 3,
            'rent_price' => 150000,
            'payment_status' => 'pending',
        ]);
        $returnProduct = ReturnProduct::create([
            'return_product_code' => Str::lower(Str::random(10)),
            'loan_id' => $loan->id,
            'user_id' => $member->id,
            'product_id' => $product->id,
            'return_date' => Carbon::today('Asia/Jakarta'),
            'status' => ReturnProductStatus::FINE,
        ]);

        return Fine::create([
            'return_product_id' => $returnProduct->id,
            'user_id' => $member->id,
            'late_fee' => 10000,
            'other_fee' => 5000,
            'total_fee' => 15000,
            'fine_date' => Carbon::today('Asia/Jakarta'),
            'payment_status' => $paymentStatus,
            'proof_image' => $proofImage,
        ]);
    }

    private function reviewableFine(User $member): Fine
    {
        return $this->fineFor(
            $member,
            FinePaymentStatus::WAITING_VERIFICATION,
            'payment-proofs/'.Str::random(12).'.jpg'
        );
    }
}
