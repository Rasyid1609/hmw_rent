<?php

namespace App\Http\Controllers\Admin;

use Throwable;
use App\Hasfile;
use Inertia\Response;
use App\Models\Brands;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Enums\MessageType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Admin\BrandRequest;
use App\Http\Resources\Admin\BrandResource;

class BrandController extends Controller
{
    use Hasfile;

    public function index(): Response
    {
        $brands = Brands::query()
            ->select(['id', 'name', 'slug', 'logo'])
            ->filter(request()->only(['search']))
            ->sorting(request()->only(['field', 'direction']))
            ->latest('created_at')
            ->paginate(request()->load ?? 10)
            ->withQueryString();

        return inertia('Admin/Brands/Index', [
            'page_settings' => [
                'title' => 'Brand',
                'subtitle' => 'Menampilkan semua data brand yang tersedia pada platform ini',
            ],
            'brands' => BrandResource::collection($brands)->additional([
                'meta' => [
                    'has_pages' => $brands->hasPages(),
                ],
            ]),
            'state' => [
                'page' => request()-> page ?? 1,
                'search' => request()-> search ?? '',
                'load' => 10,
            ],
        ]);
    }

    public function create(): Response
    {
        return inertia('Admin/Brands/Create', [
            'page_settings' => [
                'title' => 'Tambah Brand',
                'subtitle' => 'Buat brand baru disini. Klik simpan setelah selesai',
                'method' => 'POST',
                'action' => route('admin.brands.store'),
            ],
        ]);

    }

    public function store(BrandRequest $request): RedirectResponse
    {
        try {
            Brands::create([
                'name' => $name = $request->name,
                'slug' => str()->lower(str()->slug($name). str()->random(4)),
                'logo' => $this->upload_file($request, 'logo', 'brands')
            ]);

            flashMessage(MessageType::CREATED->message('Brand'));
            return to_route('admin.brands.index');
        } catch(Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.brands.index');
        }
    }

    public function edit(Brands $brand): Response
    {
        return inertia('Admin/Brands/Edit', [
            'page_settings' => [
                'title' => 'Edit Brand',
                'subtitle' => 'Edit brand disini. Klik simpan setelah selesai',
                'method' => 'PUT',
                'action' => route('admin.brands.update', $brand)
            ],
            'brands' => $brand,
            'logo_url' => $brand->logo ? Storage::disk('public')->url($brand->logo) : null,
        ]);
    }

    public function update(Brands $brand, BrandRequest $request): RedirectResponse
    {
        try {
            $brand->update([
                'name' => $name = $request->name,
                'slug' => $name !== $brand->name ? str()->lower(str()->slug($name) . str()->random(4)) : $brand->slug,
                'logo' => $this->update_file($request, $brand, 'logo', 'brands')
            ]);

            flashMessage(MessageType::UPDATED->message('Brand'));
            return to_route('admin.brands.index');
        } catch(Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.brands.index');
        }
    }

    public function destroy(Brands $brand): RedirectResponse
    {
        try {
            DB::transaction(function () use ($brand): void {
                $lockedBrand = Brands::query()->lockForUpdate()->findOrFail($brand->id);
                if (Product::where('brand_id', $brand->id)->exists()) {
                    throw new \RuntimeException('Brand yang masih digunakan oleh barang tidak dapat dihapus.');
                }
                $lockedBrand->delete();
            }, 3);
            $this->delete_file($brand, 'logo');
            flashMessage(MessageType::DELETED->message('Kategori'));
            return to_route('admin.brands.index');
        } catch (Throwable $err) {
            flashMessage(MessageType::ERROR->message(error: $err->getMessage()), 'error');
            return to_route('admin.brands.index');
        }
    }
}
