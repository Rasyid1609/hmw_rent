<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Loan;
use Inertia\Response;
use App\Models\Product;
use App\Models\ReturnProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ReturnProductFrontResource;
use App\Http\Resources\ReturnProductFrontSingleResource;

class ReturnProductFrontController extends Controller
{
    public function index(): Response
    {
        $return_products = ReturnProduct::query()
        ->select(['id', 'return_product_code', 'status', 'loan_id', 'user_id', 'product_id', 'return_date', 'created_at'])
        ->where('user_id', auth()->user()->id)
        ->when(request()->filled('search'), function ($query) {
            $search = '%'.request('search').'%';
            $query->where(fn ($query) => $query
                ->whereAny(['return_product_code', 'status'], 'like', $search)
                ->orWhereHas('loan', fn ($query) => $query->where('loan_code', 'like', $search))
                ->orWhereHas('user', fn ($query) => $query->where('name', 'like', $search))
                ->orWhereHas('product', fn ($query) => $query->where('title', 'like', $search)));
        })
        ->sorting(request()->only(['field', 'direction']))
        ->with(['product', 'fine', 'loan', 'user', 'returnProductCheck'])
        ->latest('created_at')
        ->paginate(request()->load ?? 10)
        ->withQueryString();

        return inertia('Front/ReturnProducts/Index', [
            'page_settings' => [
                'title' => 'Pengembalian',
                'subtitle' => 'Menampilkan semua data pengembalian anda yang tersedia pada platform ini. '
            ],
            'return_products' => ReturnProductFrontResource::collection($return_products)->additional([
                'meta' => [
                    'has_pages' => $return_products->hasPages(),
                ],
            ]),
            'state' => [
                'page' => request()->page ?? 1,
                'search' => request()->search ?? '',
                'load' => 10
            ],
            'page_data' => [
                'returned' => ReturnProduct::query()->member(auth()->user()->id)->returned()->count(),
                'fine' => ReturnProduct::query()->member(auth()->user()->id)->fine()->count(),
                'checked' => ReturnProduct::query()->member(auth()->user()->id)->checked()->count(),
            ]
        ]);
    }

    public function show(ReturnProduct $returnProduct): Response
    {
        abort_unless((int) $returnProduct->user_id === (int) auth()->id(), 403);

        return inertia('Front/ReturnProducts/Show', [
            'page_settings' => [
                'title' => 'Detail Pengembalian Barang',
                'subtitle' => 'Dapat melihat informasi tentang detail barang yang anda kembalikan',
            ],
            'return_product' => new ReturnProductFrontSingleResource(
                $returnProduct->load([
                    'product',
                    'user',
                    'loan',
                    'fine',
                    'returnProductCheck'
                ])
            ),
        ]);
    }

    public function store(Product $product, Loan $loan): RedirectResponse
    {
        abort_unless((int) $loan->user_id === (int) auth()->id(), 403);
        abort_unless((int) $loan->product_id === (int) $product->id, 404);

        $return_product = DB::transaction(function () use ($loan, $product): ReturnProduct {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            abort_unless((int) $lockedLoan->user_id === (int) auth()->id(), 403);
            abort_unless((int) $lockedLoan->product_id === (int) $product->id, 404);

            // Retried submissions resolve to the original return without moving stock again.
            return $lockedLoan->returnProduct()->firstOrCreate([], [
                'return_product_code' => str()->lower(str()->random(10)),
                'product_id' => $lockedLoan->product_id,
                'user_id' => $lockedLoan->user_id,
                'return_date' => Carbon::today('Asia/Jakarta'),
            ]);
        }, 3);

        flashMessage('Barang anda sedang dilakukan pengecekan oleh petugas kami');
        return to_route(
        'front.return-products.show',
        [$return_product->return_product_code]
    );
    }
}
