<?php

namespace App\Models;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RentPayment extends Model
{
    protected $fillable = [
        'loan_id',
        'user_id',
        'amount',
        'payment_method',
        'payment_proof',
        'payment_status',
        'paid_at',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
