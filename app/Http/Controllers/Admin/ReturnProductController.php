<?php

namespace App\Http\Controllers\Admin;

use Throwable;
use RuntimeException;
use App\Models\Fine;
use App\Models\Loan;
use Inertia\Response;
use App\Enums\MessageType;
use App\Models\FineSetting;
use App\Models\ReturnProduct;
use Illuminate\Support\Carbon;
use App\Enums\ReturnProductStatus;
use App\Models\ReturnProductCheck;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\ReturnProductCondition;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Admin\ReturnProductRequest;
use App\Http\Resources\Admin\ReturnProductResource;



class ReturnProductController extends Controller
{
    public function index(): Response
    {
        $return_products = ReturnProduct::query()
            ->select(['id', 'return_product_code', 'status', 'loan_id', 'user_id', 'product_id', 'return_date', 'created_at'])
            ->filter(request()->only(['search']))
            ->sorting(request()->only(['field', 'direction']))
            ->with(['product', 'fine', 'loan', 'user', 'returnProductCheck'])
            ->latest('created_at')
            ->paginate(request()->load ?? 10)
            ->withQueryString();

        return inertia('Admin/ReturnProducts/Index', [
            'page_settings' => [
                'title' => 'Pengembalian',
                'subtitle' => 'Menampilkan semua data pengembalian yang tersedia pada platform ini. '
            ],
            'return_products' => ReturnProductResource::collection($return_products)->additional([
                'meta' => [
                    'has_pages' => $return_products->hasPages(),
                ],
            ]),
            'state' => [
                'page' => request()->page ?? 1,
                'search' => request()->search ?? '',
                'load' => 10
            ],
            'conditions' => ReturnProductCondition::options(),
        ]);
    }

    public function create(Loan $loan): Response|RedirectResponse
    {
        if ($loan->returnProduct()->exists()){
            return to_route('admin.loans.index');
        }

        if (!FineSetting::first()){
            return to_route('admin.fine-settings.create');
        }

        return inertia('Admin/ReturnProducts/Create', [
            'page_settings' => [
                'title' => 'Pengembalian Barang',
                'subtitle' => 'Kembalikan Barang yang dipinjam disini. Klik kembalikan setelah selesai',
                'method' => 'PUT',
                'action' => route('admin.return-products.store', $loan),
            ],
            'loan' => $loan->load([
                'user',
                'product' => fn($query) => $query->with('brand'),
            ]),
            'date' => [
                'return_date' => Carbon::now()->toDateString(),
            ],

            'conditions' => ReturnProductCondition::options(),
        ]);
    }

    public function store(Loan $loan, ReturnProductRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();

            $result = DB::transaction(function () use ($loan, $validated, $request): array {
                $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

                if ($lockedLoan->returnProduct()->exists()) {
                    return ['return_product' => null, 'fine' => null, 'message' => 'Barang ini sudah dikembalikan'];
                }

                $fineSetting = FineSetting::query()->first();

                if (! $fineSetting) {
                    throw new RuntimeException('Pengaturan denda belum dibuat.');
                }

                $returnProduct = $lockedLoan->returnProduct()->create([
                    'return_product_code' => str()->lower(str()->random(10)),
                    'product_id' => $lockedLoan->product_id,
                    'user_id' => $lockedLoan->user_id,
                    'return_date' => Carbon::today('Asia/Jakarta'),
                ]);

                $returnProductCheck = $returnProduct->returnProductCheck()->create([
                    'condition' => $validated['condition'],
                    'notes' => $request->input('notes'),
                ]);

                $this->moveStockForReturn($returnProduct, $returnProductCheck->condition);

                return [
                    'return_product' => $returnProduct,
                    'fine' => $this->calculateFine(
                        $returnProduct,
                        $returnProductCheck,
                        $fineSetting,
                        $returnProduct->getDaysLate(),
                    ),
                    'message' => null,
                ];
            }, 3);

            if (! $result['return_product']) {
                flashMessage($result['message'], 'error');

                return to_route('admin.loans.index');
            }

            if ($result['fine']) {
                flashMessage($result['fine']['message'], 'error');

                return to_route('admin.fines.create', $result['return_product']);
            }

            flashMessage('Berhasil mengembalikan barang');

            return to_route('admin.return-products.index');
        } catch (Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.loans.index');
        }
    }

    private function createFine(ReturnProduct $returnProduct, float $lateFee, float $otherFee): Fine
    {
        return $returnProduct->fine()->create([
            'user_id' => $returnProduct->user_id,
            'late_fee' => $lateFee,
            'other_fee' => $otherFee,
            'total_fee' => $lateFee + $otherFee,
            'fine_date' => Carbon::today(),
        ]);
    }

    private function calculateFine(ReturnProduct $returnProduct, ReturnProductCheck $returnProductCheck, FineSetting $fineSetting, int $daysLate): ?array
    {
        $late_fee = $fineSetting->late_fee_per_day * $daysLate;

        switch($returnProductCheck->condition->value){
            case ReturnProductCondition::DAMAGED->value:
                $other_fee = ($fineSetting->damage_fee_percentage / 100) * $returnProduct->product->price;
                $returnProduct->update([
                    'status' => ReturnProductStatus::FINE->value,
                ]);
                $this->createFine($returnProduct, $late_fee, $other_fee);

                return [
                    'message' => 'Kondisi barang rusak, harus membayar denda kerusakan',
                ];
            case ReturnProductCondition::LOST->value:
                $other_fee = ($fineSetting->lost_fee_percentage / 100)* 2 * $returnProduct->product->price;
                $returnProduct->update([
                    'status' => ReturnProductStatus::FINE->value,
                ]);
                $this->createFine($returnProduct, $late_fee, $other_fee);

                return [
                    'message' => 'Kondisi barang hilang, harus membayar denda kehilangan',
                ];
            default:
                if($daysLate > 0){
                    $returnProduct->update([
                        'status' => ReturnProductStatus::FINE->value,
                    ]);
                    $this->createFine($returnProduct, $late_fee, 0);
                    return [
                        'message' => 'Terlambat mengembalikan barang dan harus membayar denda keterlambatan',
                    ];
                } else {
                    $returnProduct->update([
                        'status' => ReturnProductStatus::RETURNED->value,
                    ]);

                    return null;
                }
        }
    }

    public function approve(ReturnProduct $returnProduct, ReturnProductRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();

            $result = DB::transaction(function () use ($returnProduct, $validated, $request): array {
                $lockedReturnProduct = ReturnProduct::query()
                    ->lockForUpdate()
                    ->findOrFail($returnProduct->getKey());

                // A completed return must be a no-op on retries. This guard is
                // evaluated while holding the return row lock, before stock moves.
                if ($lockedReturnProduct->status !== ReturnProductStatus::CHECKED) {
                    return ['processed' => false, 'fine' => null];
                }

                $fineSetting = FineSetting::query()->first();

                if (! $fineSetting) {
                    throw new RuntimeException('Pengaturan denda belum dibuat.');
                }

                $returnProductCheck = $lockedReturnProduct->returnProductCheck()->firstOrCreate([], [
                    'condition' => $validated['condition'],
                    'notes' => $request->input('notes'),
                ]);

                $this->moveStockForReturn($lockedReturnProduct, $returnProductCheck->condition);

                return [
                    'processed' => true,
                    'fine' => $this->calculateFine(
                        $lockedReturnProduct,
                        $returnProductCheck,
                        $fineSetting,
                        $lockedReturnProduct->getDaysLate(),
                    ),
                ];
            }, 3);

            if (! $result['processed']) {
                flashMessage('Pengembalian ini sudah diproses', 'error');

                return to_route('admin.return-products.index');
            }

            if ($result['fine']) {
                flashMessage($result['fine']['message'], 'error');

                return to_route('admin.return-products.index');
            }

            flashMessage('Berhasil menyetujui pengembalian barang');

            return to_route('admin.return-products.index');

        } catch(Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.loans.index');
        }
    }

    private function moveStockForReturn(ReturnProduct $returnProduct, ReturnProductCondition $condition): void
    {
        $product = $returnProduct->product()->firstOrFail();
        $stock = $product->stock()->lockForUpdate()->first();

        if (! $stock) {
            throw new RuntimeException('Data stok barang tidak ditemukan.');
        }

        $incrementColumn = match ($condition) {
            ReturnProductCondition::GOOD => 'available',
            ReturnProductCondition::LOST => 'lost',
            ReturnProductCondition::DAMAGED => 'damaged',
        };

        $updated = $product->stock()
            ->whereKey($stock->id)
            ->where('loan', '>', 0)
            ->update([
                'loan' => DB::raw('loan - 1'),
                $incrementColumn => DB::raw("{$incrementColumn} + 1"),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('Stok barang tidak dapat dikembalikan.');
        }
    }
}
