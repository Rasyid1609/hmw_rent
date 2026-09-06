<?php

namespace App\Http\Controllers\Admin;

use Throwable;
use Inertia\Response;
use App\Models\Stocks;
use App\Enums\MessageType;
use App\Enums\ProductStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Admin\StockRequest;
use App\Http\Resources\Admin\StockResource;

class ProductStockReportController extends Controller
{
    public function index(): Response
    {
        $stocks = Stocks::query()
            ->select(['stocks.id', 'product_id', 'total', 'available', 'loan', 'lost', 'damaged', 'stocks.created_at'])
            ->filter(request()->only(['search']))
            ->sorting(request()->only(['field', 'direction']))
            ->paginate(request()-> load ?? 10)
            ->withQueryString();

        return inertia('Admin/ProductStockReports/Index', [
            'page_settings' => [
                'title' =>'Laporan Stok Barang',
                'subtitle' =>'Menampilkan laporan stok barang yang tersedia pada platform ini.',
            ],
            'stocks' => StockResource::collection($stocks)->additional([
                'has_pages' => [
                    $stocks->hasPages(),
                ],
            ]),
            'state' => [
                'page' => request()->page ?? 1,
                'search' => request()->search ?? '',
                'load' => 10,
            ],
        ]);
    }

    public function edit(Stocks $stock): Response
    {
        return inertia('Admin/ProductStockReports/Edit', [
            'page_settings' => [
                'title' => 'Edit Stok',
                'subtitle' => 'Edit stok disini. Klik simpan setelah selesai.',
                'method' => 'PUT',
                'action' => route('admin.product-stock-reports.update', $stock)
            ],
            'stock' => $stock,
        ]);
    }

    public function update(Stocks $stock, StockRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($stock, $request): void {
                $lockedStock = Stocks::query()->lockForUpdate()->findOrFail($stock->id);
                $reserved = (int) $lockedStock->loan + (int) $lockedStock->lost + (int) $lockedStock->damaged;
                $total = $request->integer('total');

                if ($total < $reserved) {
                    throw ValidationException::withMessages([
                        'total' => 'Total stok tidak boleh lebih kecil dari unit yang dipinjam, hilang, atau rusak.',
                    ]);
                }

                $available = $total - $reserved;
                $lockedStock->update(['total' => $total, 'available' => $available]);
                $product = $lockedStock->product;
                $product->status = $available > 0 ? ProductStatus::AVAILABLE : ProductStatus::UNAVAILABLE;
                $product->save();
            }, 3);

            flashMessage(MessageType::UPDATED->message('Stok'));
            return to_route('admin.product-stock-reports.index');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch(Throwable $err) {
            flashMessage(MessageType::ERROR->message($err->getMessage()), 'error');
            return to_route('admin.product-stock-reports.index');
        }
    }
}
