<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivePaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'metode_transaction_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function metodeTransaction()
    {
        return $this->belongsTo(MetodeTransaction::class, 'metode_transaction_id');
    }
}
