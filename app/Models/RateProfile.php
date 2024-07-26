<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RateProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'deposit_fee',
        'withdrawal_fee',
        'merchant_id'
    ];
}
