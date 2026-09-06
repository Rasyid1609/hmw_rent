<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\User;
use App\Models\Loans;
use App\Models\Product;
use App\Enums\ReturnProductStatus;
use App\Models\ReturnProductCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnProduct extends Model
{
    protected $fillable = [
        'return_product_code',
        'loan_id',
        'user_id',
        'product_id',
        'return_date',
        'due_date',
        'status',
    ];

    protected $casts = [
        'return_date' => 'date',
        'status' => ReturnProductStatus::class,
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fine(): HasOne
    {
        return $this->hasOne(Fine::class);
    }

    public function returnProductCheck(): HasOne
    {
        return $this->hasOne(ReturnProductCheck::class);
    }

    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->when($filters['search'] ?? null, function($query, $search){
            $query->where(function($query) use($search){
                $query->whereAny([
                    'return_product_code',
                    'status',
                ], 'REGEXP', $search);
            })
            ->orWhereHas('loan', fn($query) => $query->where('loan_code', 'REGEXP', $search))
            ->orWhereHas('user', fn($query) => $query->where('name', 'REGEXP', $search))
            ->orWhereHas('product', fn($query) => $query->where('title', 'REGEXP', $search));
        });
    }

    public function scopeSorting(Builder $query, array $sorts): void
    {
        $field = $sorts['field'] ?? null;
        $direction = $sorts['direction'] ?? null;

        if (! in_array($direction, ['asc', 'desc'], true)) {
            return;
        }

        $loanFields = [
            'loan_code' => 'loan_code',
            'loan_date' => 'rent_start_date',
            'due_date' => 'rent_end_date',
            'rent_start_date' => 'rent_start_date',
            'rent_end_date' => 'rent_end_date',
        ];

        if (is_string($field) && isset($loanFields[$field])) {
            $query->orderBy(Loan::select($loanFields[$field])
                ->whereColumn('loans.id', 'return_products.loan_id')->limit(1), $direction);
        } elseif (in_array($field, ['id', 'return_product_code', 'user_id', 'product_id', 'status', 'return_date', 'created_at'], true)) {
            $query->orderBy('return_products.'.$field, $direction);
        }
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->where('status', ReturnProductStatus::RETURNED->value);
    }

    public function scopeFine(Builder $query): Builder
    {
        return $query->where('status', ReturnProductStatus::FINE->value);
    }

    public function scopeChecked(Builder $query): Builder
    {
        return $query->where('status', ReturnProductStatus::CHECKED->value);
    }

    public function scopeMember(Builder $query, int $user_id): Builder
    {
        return $query->where('user_id', $user_id);
    }

    public function isOnTime(): bool
    {
        if (! $this->return_date || ! $this->loan?->rent_end_date) {
            return false;
        }

        $returnDate = Carbon::parse($this->return_date)->startOfDay();
        $rentEndDate = Carbon::parse($this->loan->rent_end_date)->startOfDay();

        return $returnDate->lessThanOrEqualTo($rentEndDate);
    }

    public function getDaysLate(): int
    {
        if (! $this->return_date || ! $this->loan?->rent_end_date) {
            return 0;
        }

        $returnDate = Carbon::parse($this->return_date)->startOfDay();
        $rentEndDate = Carbon::parse($this->loan->rent_end_date)->startOfDay();

        return $returnDate->lessThanOrEqualTo($rentEndDate)
            ? 0
            : (int) $rentEndDate->diffInDays($returnDate);
    }
}
