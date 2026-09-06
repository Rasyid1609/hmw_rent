<?php

namespace App\Http\Controllers\Admin;

use Throwable;
use RuntimeException;
use App\Models\Loan;
use App\Models\User;
use Inertia\Response;
use App\Models\Product;
use App\Enums\MessageType;
use App\Enums\ReturnProductStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Admin\LoanRequest;
use App\Http\Resources\Admin\LoanResource;
use App\Http\Requests\Admin\UpdateLoanPaymentStatusRequest;



class LoanController extends Controller
{
    public function index(): Response
    {
        $loans = Loan::query()
            ->select(['id', 'loan_code', 'user_id', 'product_id', 'rent_start_date', 'rent_end_date', 'rent_duration', 'payment_status', 'proof_image', 'created_at'])
            ->filter(request()->only(['search']))
            ->sorting(request()->only(['field', 'direction']))
            ->with(['product', 'user', 'returnProduct'])
            ->latest('created_at')
            ->paginate(request()->load ?? 10)
            ->withQueryString();

        return inertia('Admin/Loans/Index', [
            'page_settings' => [
                'title' => 'Peminjaman',
                'subtitle' => 'Menampilkan semua data peminjaman yang tersedia pada platform ini.'
            ],
            'loans' => LoanResource::collection($loans)->additional([
                'meta' => [
                    'has_pages' => $loans->hasPages(),
                ],
            ]),
            'state' => [
                'page' => request()->page ?? 1,
                'search' => request()->search ?? '',
                'load' => 10
            ],
        ]);
    }

    public function create(): Response
    {
        return inertia('Admin/Loans/Create', [
            'page_settings' => [
                'title' => 'Tambah Peminjaman',
                'subtitle' => 'Buat peminjaman barang di sini. Klik simpan setelah selesai. ',
                'method' => 'POST',
                'action' => route('admin.loans.store'),
            ],
            'page_data' => [
                'date' => [
                    // Keep legacy fields until the admin form is updated, while
                    // publishing the canonical rent field names for new clients.
                    'loan_date' => $rentStart = Carbon::today('Asia/Jakarta')->toDateString(),
                    'due_date' => $rentEnd = Carbon::today('Asia/Jakarta')->addDays(7)->toDateString(),
                    'rent_start_date' => $rentStart,
                    'rent_end_date' => $rentEnd,
                    'rent_duration' => 7,
                ],
                'products' => Product::query()
                    ->select(['id', 'title', 'prod_code'])
                    ->whereHas('stock', fn($query) => $query->where('available', '>', 0))
                    ->get()
                    ->map(fn($item) => [
                        'value' => (string) $item->id,
                        'label' => $item->title.' ('.$item->prod_code.')'
                    ]),
                'users' => User::query()
                    ->select(['id', 'name', 'email'])
                    ->whereHas('roles', fn($query) => $query->where('name', 'member'))
                    ->get()
                    ->map(fn($item) => [
                        'value' => (string) $item->id,
                        'label' => $item->name.' ('.$item->email.')',
                    ]),
            ]
        ]);
    }

    public function store(LoanRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $rentStart = Carbon::createFromFormat('Y-m-d', $validated['rent_start_date'], 'Asia/Jakarta')->startOfDay();
            $rentEnd = Carbon::createFromFormat('Y-m-d', $validated['rent_end_date'], 'Asia/Jakarta')->startOfDay();

            $result = DB::transaction(function () use ($validated, $rentStart, $rentEnd): array {
                $product = Product::query()
                    ->whereKey($validated['product_id'])
                    ->firstOrFail();

                $user = User::query()
                    ->whereKey($validated['user_id'])
                    ->whereHas('roles', fn ($query) => $query->where('name', 'member'))
                    ->firstOrFail();

                // Serialise reservations for this product and only decrement an
                // available unit in the same transaction that creates the loan.
                $stock = $product->stock()->lockForUpdate()->first();

                if (! $stock || $stock->available <= 0) {
                    return ['loan' => null, 'message' => 'Stok barang tidak tersedia'];
                }

                $hasActiveLoan = Loan::query()
                    ->where('user_id', $user->id)
                    ->where('product_id', $product->id)
                    ->whereDoesntHave('returnProduct', fn ($query) => $query->whereIn('status', [
                        ReturnProductStatus::RETURNED->value,
                        ReturnProductStatus::FINE->value,
                    ]))
                    ->exists();

                if ($hasActiveLoan) {
                    return ['loan' => null, 'message' => 'Pengguna sudah meminjam barang ini'];
                }

                $reserved = $product->stock()
                    ->whereKey($stock->id)
                    ->where('available', '>', 0)
                    ->update([
                        'available' => DB::raw('available - 1'),
                        'loan' => DB::raw('loan + 1'),
                    ]);

                if ($reserved !== 1) {
                    throw new RuntimeException('Stok barang tidak dapat dipesan.');
                }

                $loan = Loan::create([
                    'loan_code' => str()->lower(str()->random(10)),
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'rent_start_date' => $rentStart,
                    'rent_end_date' => $rentEnd,
                    'rent_duration' => $validated['rent_duration'],
                    'rent_price' => $product->price * $validated['rent_duration'],
                    // A new loan is always awaiting payment; request payloads
                    // cannot mark it paid or supply a client-side price.
                    'payment_status' => 'pending',
                ]);

                return ['loan' => $loan, 'message' => null];
            }, 3);

            if (! $result['loan']) {
                flashMessage($result['message'], 'error');

                return to_route('admin.loans.index');
            }

            flashMessage('Berhasil menambahkan peminjaman');

            return to_route('admin.loans.index');
        } catch (Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.loans.index');
        }
    }

    public function edit(Loan $loan): Response
    {
        return inertia('Admin/Loans/Edit', [
            'page_settings' => [
                'title' => 'Verifikasi Pembayaran Sewa',
                'subtitle' => 'Periksa bukti pembayaran sebelum menerima atau menolaknya.',
                'method' => 'PUT',
                'action' => route('admin.loans.update', $loan),
            ],
            'page_data' => [
                'can_review' => $loan->canReviewPayment(),
                'payment_status_label' => $loan->paymentStatusLabel(),
                'proof_url' => $loan->proof_image ? route('payment-proofs.loans.show', $loan) : null,
                'loan' => $loan->load([
                    'user',
                    'product',
                ]),
            ],
        ]);
    }

    public function update(Loan $loan, UpdateLoanPaymentStatusRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($loan, $validated): void {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if (! $lockedLoan->canReviewPayment()) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Pembayaran tidak dapat diubah. Hanya bukti yang menunggu verifikasi yang dapat diperiksa.',
                ]);
            }

            // A stale review form must never approve a replacement proof.
            if (! hash_equals($lockedLoan->proof_image, $validated['reviewed_proof'])) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Bukti pembayaran sudah berubah. Muat ulang halaman dan periksa bukti terbaru.',
                ]);
            }

            if ($validated['payment_status'] === 'paid' && ! Storage::disk('local')->exists($lockedLoan->proof_image)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'File bukti pembayaran tidak ditemukan. Tolak bukti agar pelanggan dapat mengirim ulang.',
                ]);
            }

            $lockedLoan->update(['payment_status' => $validated['payment_status']]);
        }, 3);

        flashMessage('Status pembayaran berhasil diperbarui');
        return to_route('admin.loans.index');
    }

    public function destroy(Loan $loan): RedirectResponse
    {
        try {
            DB::transaction(function () use ($loan): void {
                $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

                if ($lockedLoan->payment_status === 'paid') {
                    throw new RuntimeException('Peminjaman yang telah dibayar tidak dapat dihapus.');
                }

                if ($lockedLoan->returnProduct()->exists()) {
                    throw new RuntimeException('Peminjaman yang sudah memiliki data pengembalian tidak dapat dihapus.');
                }

                $product = Product::query()->findOrFail($lockedLoan->product_id);
                $stock = $product->stock()->lockForUpdate()->first();

                if (! $stock) {
                    throw new RuntimeException('Data stok barang tidak ditemukan.');
                }

                $released = $product->stock()
                    ->whereKey($stock->id)
                    ->where('loan', '>', 0)
                    ->update([
                        'loan' => DB::raw('loan - 1'),
                        'available' => DB::raw('available + 1'),
                    ]);

                if ($released !== 1) {
                    throw new RuntimeException('Stok barang tidak dapat dikembalikan.');
                }

                $lockedLoan->delete();
            }, 3);

            flashMessage(MessageType::DELETED->message('Peminjaman'));
            return to_route('admin.loans.index');
        } catch (Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.loans.index');
        }
    }
}
