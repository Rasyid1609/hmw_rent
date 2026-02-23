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

class LoanFrontController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('password.confirm', except: ['store']),
        ];
    }

    public function index(): Response
    {
        $loans = Loan::query()
            ->select(['id', 'loan_code', 'user_id', 'product_id', 'rent_start_date','rent_end_date', 'rent_price', 'rent_duration', 'payment_status', 'proof_image', 'created_at'])
            ->where('user_id', auth()->user()->id)
            ->filter(request()->only(['search']))
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
        $request->validate([
            'rent_duration' => ['required', 'integer', 'min:1'],
            'rent_start_date' => ['required', 'date'],
        ]);

        if (Loan::checkLoanProduct(auth()->user()->id, $product->id)){
            flashMessage('Anda sudah meminjam barang ini, harap kembalikan barangnya terlebih dahulu', 'error');
            return  to_route('front.products.show', $product->slug);
        }

        if ($product->stock->available <= 0){
            flashMessage('Stok barang tidak tersedia.', 'error');
            return to_route('front.products.show');
        }

        $rentStart = Carbon::createFromFormat('Y-m-d', $request->rent_start_date, 'Asia/Jakarta')->startOfDay();
        $rentEnd = $rentStart->copy()->addDays($request->rent_duration);

        $loan = tap(Loan::create([
            'loan_code' => str()->lower(str()->random(10)),
            'user_id' => auth()->user()->id,
            'product_id' => $product->id,
            'rent_start_date' => $rentStart,
            'rent_end_date' => $rentEnd,
            'rent_duration' => $request->rent_duration,
            'rent_price' => $product->price * $request->rent_duration,
            'payment_status' => 'pending',
        ]), function($loan){
            $loan->product->stock_loan();
            flashMessage('Berhasil melakukan peminjaman barang');
        });

        return to_route('front.loans.checkout', $loan->id);
    }

    public function payment(Request $request, Loan $loan): RedirectResponse
    {
        $request->validate([
            'proof_image' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('proof_image')
            ->store('payment-proofs', 'public');

        $loan->update([
            'proof_image' => $path,
            'payment_status' => 'pending',
        ]);

        flashMessage('Bukti pembayaran berhasil dikirim, menunggu verifikasi admin');

        return to_route('front.loans.index', $loan->id);
    }

    public function checkout(Loan $loan): Response
    {
        return inertia('Front/Loans/Checkout', [
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
