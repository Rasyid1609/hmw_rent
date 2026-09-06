<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StorefrontRedirect
{
    public static function rememberProduct(Request $request): void
    {
        // Only construct a local product URL; never accept an arbitrary redirect URL.
        $validator = Validator::make($request->query(), [
            'product' => ['required', 'string', 'max:255'],
            'rent_duration' => ['nullable', 'integer', 'between:1,365'],
            'rent_start_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return;
        }

        $parameters = array_filter($validator->validated(), fn ($value) => $value !== null);

        if (Product::where('slug', $parameters['product'])->exists()) {
            $request->session()->put('url.intended', route('front.products.show', $parameters));
        }
    }
}
