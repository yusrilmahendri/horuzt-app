<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MidtransTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'url',
        'server_key',
        'client_key',
        'metode_production',
        'methode_pembayaran',
        'id_methode_pembayaran',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isProduction(): bool
    {
        return $this->metode_production === 'production';
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->whereNotNull('server_key')
                    ->whereNotNull('client_key');
    }
}
