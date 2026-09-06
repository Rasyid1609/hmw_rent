<?php

namespace App\Models;

use App\Models\User;
use App\Models\Product;
use App\Models\RentPayment;
use App\Models\ReturnProduct;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    protected $fillable = [
        'loan_code',
        'user_id',
        'product_id',
        'rent_start_date',
        'rent_end_date',
        'rent_duration',
        'rent_price',
        'payment_status',
        'proof_image',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'rent_start_date' => 'date:Y-m-d',
            'rent_end_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function payments(): HasOne
    {
        return $this->hasOne(RentPayment::class);
    }

    public function returnProduct(): HasOne
    {
        return $this->hasOne(ReturnProduct::class);
    }

    public function canUploadPaymentProof(): bool
    {
        return ($this->payment_status === 'failed'
            || (in_array($this->payment_status, ['unpaid', 'pending'], true) && blank($this->proof_image)))
            && ! $this->returnProduct()->exists();
    }

    public function canReviewPayment(): bool
    {
        return $this->payment_status === 'pending'
            && filled($this->proof_image)
            && ! $this->returnProduct()->exists();
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Lunas',
            'failed' => 'Bukti pembayaran ditolak',
            'pending' => filled($this->proof_image) ? 'Menunggu verifikasi' : 'Belum dibayar',
            'unpaid' => 'Belum dibayar',
            default => 'Status tidak dikenal',
        };
    }

    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->when($filters['search'] ?? null, function($query, $search){
            $query->where(function($query) use ($search){
                $query->whereAny([
                    'loan_code',
                    'rent_start_date',
                    'rent_end_date',
                ], 'like', '%'.$search.'%');
            });
        });
    }

    public function scopeSorting(Builder $query, array $sorts): void
    {
        $field = $sorts['field'] ?? null;
        $direction = $sorts['direction'] ?? null;
        $field = match ($field) {
            'loan_date' => 'rent_start_date',
            'due_date' => 'rent_end_date',
            default => $field,
        };

        if (in_array($field, ['id', 'loan_code', 'user_id', 'product_id', 'rent_start_date', 'rent_end_date', 'rent_duration', 'rent_price', 'payment_status', 'created_at'], true)
            && in_array($direction, ['asc', 'desc'], true)) {
            $query->orderBy($field, $direction);
        }
    }

    public static function checkLoanProduct(int $user_id, int $product_id): bool
    {
        return self::query()
            ->where('user_id', $user_id)
            ->where('product_id', $product_id)
            ->whereDoesntHave('returnProduct', fn($query) => $query->where('product_id', $product_id)->where('user_id', $user_id))
            ->exists();
    }

    public static function totalLoanProducts(): array
    {
        return [
            'days' => self::whereDate('created_at', Carbon::now()->toDateString())->count(),
            'weeks' => self::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count(),
            'months' => self::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count(),
            'years' => self::whereYear('created_at', Carbon::now()->year)->count(),
        ];
    }
}
