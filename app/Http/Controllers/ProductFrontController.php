<?php

namespace App\Http\Controllers;

use Inertia\Response;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\CategoryFrontResource;
use App\Http\Resources\ProductFrontSingleResource;
use App\Http\Resources\ProductFrontResource;

class ProductFrontController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        $categories = Category::query()
            ->select(['id', 'name', 'slug', 'cover', 'created_at'])
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->with(['category', 'stock'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['category'] ?? null, fn ($query, $slug) =>
                $query->whereHas('category', fn ($category) => $category->where('slug', $slug)))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return inertia('Front/Products/Index', [
            'page_settings' => [
                'title' => 'Sewa Barang — HMW Rent',
                'subtitle' => 'Temukan barang yang kamu butuhkan, sewa sesuai rencana.',
            ],
            'categories' => CategoryFrontResource::collection($categories),
            'products' => ProductFrontResource::collection($products)->additional([
                'meta' => ['has_pages' => $products->hasPages()],
            ]),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
            ],
        ]);
    }

    public function show(Product $product): Response
    {
        return inertia('Front/Products/Show', [
            'page_settings' => [
                'title' => $product->title,
                'subtitle' => "Menampilkan detail informasi barang {$product->title}",
            ],
            'product' => new ProductFrontSingleResource($product->load(['category', 'brand', 'stock'])),
        ]);
    }
}
