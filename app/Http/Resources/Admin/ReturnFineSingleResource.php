<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnFineSingleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'return_product_code' => $this->return_product_code,

        'return_date' => $this->return_date
            ? Carbon::parse($this->return_date)->format('d M Y')
            : null,

        'dayslate' => $this->dayslate,

        'product' => $this->product ? [
            'id' => $this->product->id,
            'title' => $this->product->title,
        ] : null,

        'fine' => $this->fine ? [
            'late_fee' => $this->fine->late_fee,
            'other_fee' => $this->fine->other_fee,
            'total_fee' => $this->fine->total_fee,
            'payment_status' => $this->fine->payment_status,
        ] : null,

        'loan' => $this->loan ? [
            'id' => $this->loan->id,
            'loan_code' => $this->loan->loan_code,
            'rent_start_date' => Carbon::parse($this->loan->rent_start_date)->format('d M Y'),
            'rent_end_date' => Carbon::parse($this->loan->rent_end_date)->format('d M Y'),
        ] : null,

        'user' => $this->user ? [
            'id' => $this->user->id,
            'name' => $this->user->name,
        ] : null,

        'return_product_check' => $this->returnProductCheck ? [
            'condition' => $this->returnProductCheck->condition?->value,
            'notes' => $this->returnProductCheck->notes,
        ] : null,
    ];
    }
}
