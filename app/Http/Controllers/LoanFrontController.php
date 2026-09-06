<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Inertia\Response;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use App\Http\Resources\LoanFrontResource;
use App\Http\Resources\LoanFrontSingleResource;
use App\Enums\ReturnProductStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LoanFrontController extends Controller
{
    public function index(): Response
    {
        $loans = Loan::query()
            ->select(['id', 'loan_code', 'user_id', 'product_id', 'rent_start_date','rent_end_date', 'rent_price', 'rent_duration', 'payment_status', 'proof_image', 'created_at'])
            ->where('user_id', auth()->user()->id)
            ->when(request()->filled('search'), function ($query) {
                $search = '%'.request('search').'%';
                $query->where(fn ($query) => $query
                    ->whereAny(['loan_code', 'rent_start_date', 'rent_end_date'], 'like', $search)
                    ->orWhereHas('product', fn ($query) => $query->where('title', 'like', $search)));
            })
            ->sorting(request()->only(['field', 'direction']))
            ->with(['product', 'user'])
            ->latest()
            ->paginate(request()->load ?? 10)
            ->withQueryString();

        return inertia('Front/Loans/Index', [
            'page_settings' => [
                'title' => 'Peminjaman',
                'subtitle' => 'Menampilkan semua data peminjaman yang tersedia pada platform ini.',
            ],
            'loans' => LoanFrontResource::collection($loans)->additional([
                'meta' => [
                    'has_pages' => $loans->hasPages(),
                ],
            ]),
            'state' => [
                'page' => request()->page ?? 1,
                'search' => request()->search ?? '',
                'load' => 10,
            ],
        ]);
    }

    public function show(Loan $loan): Response
    {
        abort_unless((int) $loan->user_id === (int) auth()->id(), 403);

        return inertia('Front/Loans/Show', [
            'page_settings' => [
                'title' => "Detail Peminjaman Barang",
                'subtitle' => "Dapat melihat informasi detail barang yang anda pinjam.",
            ],
            'loan' => new LoanFrontSingleResource($loan->load(['product', 'user', 'returnProduct'])),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'rent_duration' => ['required', 'integer', 'min:1', 'max:365'],
            'rent_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.Carbon::today('Asia/Jakarta')->toDateString()],
        ]);

        $rentStart = Carbon::createFromFormat('Y-m-d', $validated['rent_start_date'], 'Asia/Jakarta')->startOfDay();
        $rentEnd = $rentStart->copy()->addDays((int) $validated['rent_duration']);

        return DB::transaction(function () use ($product, $validated, $rentStart, $rentEnd): RedirectResponse {
            // Every reservation for this product checks availability while holding the same row lock.
            $stock = $product->stock()->lockForUpdate()->first();

            if (! $stock || $stock->available <= 0) {
                flashMessage('Stok barang tidak tersedia.', 'error');

                return to_route('front.products.show', $product->slug);
            }

            $hasActiveLoan = Loan::query()
                ->where('user_id', auth()->id())
                ->where('product_id', $product->id)
                ->whereDoesntHave('returnProduct', fn ($query) => $query->whereIn('status', [
                    ReturnProductStatus::RETURNED->value,
                    ReturnProductStatus::FINE->value,
                ]))
                ->exists();

            if ($hasActiveLoan) {
                flashMessage('Anda sudah meminjam barang ini, harap kembalikan barangnya terlebih dahulu', 'error');

                return to_route('front.products.show', $product->slug);
            }

            $reserved = $product->stock()->whereKey($stock->id)->where('available', '>', 0)->update([
                'available' => DB::raw('available - 1'),
                'loan' => DB::raw('loan + 1'),
            ]);

            if ($reserved !== 1) {
                throw new RuntimeException('Stok barang tidak dapat dipesan.');
            }

            $loan = Loan::create([
                'loan_code' => str()->lower(str()->random(10)),
                'user_id' => auth()->id(),
                'product_id' => $product->id,
                'rent_start_date' => $rentStart,
                'rent_end_date' => $rentEnd,
                'rent_duration' => $validated['rent_duration'],
                'rent_price' => $product->price * $validated['rent_duration'],
                'payment_status' => 'pending',
            ]);

            flashMessage('Berhasil melakukan peminjaman barang');

            return to_route('front.loans.checkout', $loan->id);
        }, 3);
    }

    public function payment(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless((int) $loan->user_id === (int) auth()->id(), 403);

        $request->validate([
            'proof_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $path = null;
        $previousPath = null;

        try {
            DB::transaction(function () use ($request, $loan, &$path, &$previousPath): void {
                $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
                abort_unless((int) $lockedLoan->user_id === (int) auth()->id(), 403);
                abort_unless($lockedLoan->canUploadPaymentProof(), 409, 'Bukti tidak dapat diganti saat menunggu verifikasi atau setelah transaksi selesai.');

                $path = $request->file('proof_image')->store('payment-proofs', 'local');

                if (! $path) {
                    throw new RuntimeException('Bukti pembayaran tidak dapat disimpan.');
                }

                $previousPath = $lockedLoan->proof_image;
                $lockedLoan->update([
                    'proof_image' => $path,
                    'payment_status' => 'pending',
                ]);
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $error;
        }

        if ($previousPath && $previousPath !== $path && str_starts_with($previousPath, 'payment-proofs/')) {
            Storage::disk('local')->delete($previousPath);
        }

        flashMessage('Bukti pembayaran berhasil dikirim, menunggu verifikasi admin');

        return to_route('front.loans.index');
    }

    public function checkout(Loan $loan): Response
    {
        abort_unless((int) $loan->user_id === (int) auth()->id(), 403);

        return inertia('Front/Loans/Checkout', [
        'can_upload_payment_proof' => $loan->canUploadPaymentProof(),
        'payment_status_label' => $loan->paymentStatusLabel(),
        'proof_url' => $loan->proof_image ? route('payment-proofs.loans.show', $loan) : null,
        'page_settings' => [
            'title' => 'Checkout Sewa',
            'subtitle' => 'Periksa kembali detail sewa sebelum melanjutkan pembayaran.',
        ],
        'loan' => $loan->load([
            'product.category',
            'product.brand',
        ]),
    ]);
    }
}
