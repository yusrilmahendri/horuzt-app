<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageUpgradeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invitation_id',
        'package_before_id',
        'package_after_id',
        'payment_method',
        'payment_status',
        'amount',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];
}
