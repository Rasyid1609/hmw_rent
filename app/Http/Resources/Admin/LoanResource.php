<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
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
            'loan_code' => $this->loan_code,
            'rent_start_date' => $this->rent_start_date->format('d M Y'),
            'rent_end_date' => $this->rent_end_date->format('d M Y'),
            'rent_duration' => $this->rent_duration,
            'payment_status' => $this->payment_status,
            'rent_duration' => $this->rent_duration,
            'rent_price' => (float) $this->rent_price,
            'proof_image' => $this->proof_image,
            'created_at' => $this->created_at->format('d M Y'),
            'has_return_product' => $this->returnProduct()->exists(),
            'user' => $this->whenLoaded('user', [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'product' => $this->whenLoaded('product', [
                'id' => $this->product?->id,
                'title' => $this->product?->title,
                'slug' => $this->product?->slug,
            ])
        ];
    }
}
