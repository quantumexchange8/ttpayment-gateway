<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'merchant_wallet',
        'deposit_balance',
        'gross_deposit',
        'gross_withdrawal',
        'net_deposit',
        'net_withdrawal',
        'deposit_fee',
        'withdrawal_fee',
        'freezing_amount',
        'type',
        'total_deposit',
        'total_withdrawal',
        'total_fee',
    ];
}
