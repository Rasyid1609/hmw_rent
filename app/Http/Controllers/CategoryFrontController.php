<?php

namespace App\Http\Controllers;

use Inertia\Response;
use App\Models\Brands;
use App\Models\Category;
use App\Http\Resources\BrandFrontResource;
use App\Http\Resources\CategoryFrontResource;

class CategoryFrontController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->select(['id', 'name', 'slug', 'cover', 'created_at'])
            ->latest()
            ->paginate(8);

        return inertia('Front/Categories/Index', [
            'page_settings' => [
                'title' => 'Kategori',
                'subtitle' => 'Jelajahi barang sewaan berdasarkan kategori.',
            ],
            'categories' => CategoryFrontResource::collection($categories)->additional([
                'meta' => [
                    'has_pages' => $categories->hasPages(),
                ],
            ]),
        ]);
    }

    public function show(Category $category): Response
    {
        $inCategory = fn ($query) => $query->where('category_id', $category->id);
        $brands = Brands::query()
            ->select(['id', 'name', 'slug', 'logo'])
            ->whereHas('products', $inCategory)
            ->withCount(['products' => $inCategory])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12);

        return inertia('Front/Brands/Index', [
            'page_settings' => [
                'title' => "Brand {$category->name}",
                'subtitle' => "Pilih brand untuk melihat barang sewaan dalam kategori {$category->name}.",
            ],
            'category' => new CategoryFrontResource($category),
            'brands' => BrandFrontResource::collection($brands)->additional([
                'meta' => [
                    'has_pages' => $brands->hasPages(),
                ],
            ]),
        ]);
    }
}
