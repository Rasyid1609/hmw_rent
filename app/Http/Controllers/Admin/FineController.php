<?php

namespace App\Http\Controllers\Admin;

use Inertia\Response;
use Illuminate\Http\Request;
use App\Models\ReturnProduct;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ReturnFineSingleResource;

class FineController extends Controller
{
    public function create(ReturnProduct $returnProduct) : Response
    {
        abort_unless($returnProduct->fine()->exists(), 404);

        return inertia('Admin/Fines/Create', [
            'can_review' => auth()->user()->hasAnyRole(['admin', 'accounting']),
            'page_settings' => [
                'title' => 'Denda',
                'subtitle' => 'Selesaikan pembayaran denda terlebih dahulu.'
            ],
            'return_product' => new ReturnFineSingleResource($returnProduct->load([
                'product',
                'fine',
                'loan',
                'user',
                'returnProductCheck'
            ])),
        ]);
    }
}
