<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;

    protected $fillable = [
        'clientId',
        'merchantId',
        'token',
        'time',
        'status',
        'expired_at',
    ];
}
