<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Bank;

class Rekening extends Model
{
    use HasFactory;
    
    protected $table = 'rekenings';

    protected $fillable = [
        'user_id',
        'kode_bank',
        'email',
        'nomor_rekening',
        'nama_bank',
        'nama_pemilik',
        'methode_pembayaran',
        'id_methode_pembayaran',
        'photo_rek'
    ];

    protected $hidden = [
        'email',
        'methode_pembayaran',
        'id_methode_pembayaran'
    ];

    /**
     * Get the user that owns the bank account
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the bank information
     * Fixed relationship: kode_bank should map to bank.kode_bank, not bank.id
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'kode_bank', 'kode_bank');
    }

    /**
     * Get the photo URL attribute
     */
    public function getPhotoUrlAttribute()
    {
        return $this->photo_rek ? asset('storage/' . $this->photo_rek) : null;
    }

    /**
     * Scope to get accounts for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to order by latest
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}