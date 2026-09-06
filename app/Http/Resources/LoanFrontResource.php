<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanFrontResource extends JsonResource
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
            'loan_date' => $this->rent_start_date?->format('d M Y'),
            'rent_start_date' => $this->rent_start_date?->format('d M Y'),
            'rent_end_date' => $this->rent_end_date?->format('d M Y'),

            'rent_duration' => $this->rent_duration,
            'rent_price' => (float) $this->rent_price,

            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->paymentStatusLabel(),
            'proof_image' => $this->proof_image ? route('payment-proofs.loans.show', $this->id) : null,

            'due_date' => $this->rent_end_date?->format('d M Y'),
            'created_at' => $this->created_at->format('d M Y'),
            'user' => $this->whenLoaded('user', [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'product' => $this->whenLoaded('product', [
                'id' => $this->product?->id,
                'title' => $this->product?->title,
                'slug' => $this->product?->slug,
            ]),
        ];
    }
}
