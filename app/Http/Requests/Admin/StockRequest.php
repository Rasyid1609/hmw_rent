<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
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
            'total' => [
                'required',
                'integer',
                'min:0'
            ],
            'available' => [
                'sometimes',
                'integer',
                'min:0'
            ],
            'loan' => [
                'sometimes',
                'integer',
                'min:0'
            ],
            'lost' => [
                'sometimes',
                'integer',
                'min:0'
            ],
            'damaged' => [
                'sometimes',
                'integer',
                'min:0'
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'total' => 'Total',
            'available' => 'Tersedia',
            'loan' => 'Dipinjam',
            'lost' => 'Hilang',
            'damaged' => 'Rusak',
        ];
    }
}
