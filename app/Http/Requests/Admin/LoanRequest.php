<?php

namespace App\Http\Requests\Admin;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class LoanRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id'
            ],
            'product_id' => [
                'required',
                'integer',
                'exists:products,id'
            ],
            'rent_start_date' => ['required', 'date_format:Y-m-d'],
            'rent_end_date' => ['required', 'date_format:Y-m-d', 'after:rent_start_date'],
            'rent_duration' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * Keep the older admin form working while ensuring the controller always
     * receives the current rental-date contract. When both dates are present,
     * duration is calculated on the server instead of trusting client input.
     */
    protected function prepareForValidation(): void
    {
        $rentStart = $this->input('rent_start_date', $this->input('loan_date'));
        $rentEnd = $this->input('rent_end_date', $this->input('due_date'));
        $rentDuration = $this->input('rent_duration');

        $startDate = $this->validDate($rentStart);
        $endDate = $this->validDate($rentEnd);

        if ($startDate && $endDate) {
            $rentDuration = (int) $startDate->diffInDays($endDate, false);
        } elseif ($startDate && $this->isPositiveInteger($rentDuration) && (int) $rentDuration <= 365) {
            $rentEnd = $startDate->copy()->addDays((int) $rentDuration)->toDateString();
        }

        $this->merge([
            'rent_start_date' => $rentStart,
            'rent_end_date' => $rentEnd,
            'rent_duration' => $rentDuration,
        ]);
    }

    private function validDate(mixed $value): ?Carbon
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            return $date->format('Y-m-d') === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isPositiveInteger(mixed $value): bool
    {
        return is_int($value)
            ? $value > 0
            : is_string($value) && ctype_digit($value) && (int) $value > 0;
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'Pengguna',
            'product_id' => 'Produk',
        ];
    }
}
