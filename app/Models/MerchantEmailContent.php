<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantEmailContent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'client_name',
        'client_email',
        'client_id',
        'deposit_amount',
        'date_time',
        'client_usdt',
        'usdt_receive',
        'txid',
        'photo',
    ];
}
