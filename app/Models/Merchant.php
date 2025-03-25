<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Merchant extends Model
{
    use HasFactory, SoftDeletes, Notifiable, HasApiTokens;

    // const $deposit_type = [
    //     ( 0 === Manual)
    //     ( 1 === Auto)
    // ];

    protected $fillable = [
        'name',
        'manager_name',
        'email',
        'password',
        'dial_code',
        'phone',
        'url',
        'deposit_type',
        'api_key',
        'security_pin',
        'rate_id',
        'refresh_time',
        'role_id',
        'status',
        'bin',
        'handle_by',
        'total_amount',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'handle_by', 'id');
    }

    public function merchantWallet(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MerchantWallet::class, 'id', 'merchant_id');
    }

    public function merchantWalletAddress(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(MerchantWalletAdrress::class, 'merchant_id', 'id');
    }

    public function assignedWalletAddress(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(MerchantWalletAdrress::class, 'merchant_id', 'id')->where('status', 'unassigned');
    }
    
    public function merchantEmailContent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MerchantEmailContent::class, 'id', 'merchant_id');
    }

    public function merchantEmail(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(MerchantEmail::class, 'merchant_id', 'id');
    }
}
