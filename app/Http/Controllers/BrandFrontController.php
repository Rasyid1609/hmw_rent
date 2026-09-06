<?php

namespace App\Http\Controllers;

use App\Http\Resources\BrandFrontResource;
use App\Http\Resources\CategoryFrontResource;
use App\Http\Resources\ProductFrontResource;
use App\Models\Brands;
use App\Models\Category;
use Inertia\Response;

class BrandFrontController extends Controller
{
    public function show(Category $category, Brands $brand): Response
    {
        $query = $brand->products()->where('category_id', $category->id);
        abort_unless($query->exists(), 404);

        $products = $query->with(['category', 'stock'])->latest('id')->paginate(12);

        return inertia('Front/Brands/Show', [
            'page_settings' => [
                'title' => $brand->name,
                'subtitle' => "Barang {$brand->name} dalam kategori {$category->name}.",
            ],
            'category' => new CategoryFrontResource($category),
            'brand' => new BrandFrontResource($brand),
            'products' => ProductFrontResource::collection($products)->additional([
                'meta' => ['has_pages' => $products->hasPages()],
            ]),
        ]);
    }
}
