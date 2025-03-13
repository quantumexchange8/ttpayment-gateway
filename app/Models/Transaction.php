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
        'client_name',
        'client_email',
        'transaction_type',
        'from_wallet',
        'to_wallet',
        'txID',
        'amount',
        'total_amount',
        'fee',
        'tt_txn',
        'transaction_number',
        'transaction_date',
        'payment_method',
        'status',
        'description',
        'handle_by',
        'block_time',
        'txn_amount',
        'origin_domain',
        'expired_at',
        'txreceipt_status',
        'block_number',
        'payment_type',
        'transfer_status',
        'token_symbol'
    ];
}
