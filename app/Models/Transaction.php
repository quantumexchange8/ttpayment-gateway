<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'client_id',
        'transaction_type',
        'from_wallet',
        'to_wallet',
        'txID',
        'amount',
        'fee',
        'transaction_number',
        'payment_method',
        'status',
        'description',
        'handle_by',
    ];
}
