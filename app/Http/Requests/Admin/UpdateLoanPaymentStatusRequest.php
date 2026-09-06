<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanPaymentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_status' => ['required', 'in:paid,failed'],
            'reviewed_proof' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_status' => 'Status Pembayaran',
            'reviewed_proof' => 'Bukti pembayaran yang diperiksa',
        ];
    }
}
