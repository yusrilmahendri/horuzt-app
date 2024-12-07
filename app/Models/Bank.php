<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pembayaran;
use App\Models\Rekening;

class Bank extends Model
{
    use HasFactory;
    protected $guarded = [''];

    public function rekenings()
    {
        return $this->hasMany(Rekening::class);
    }

    public function pembayaran(){
        return $this->hasOne(Pembayaran::class);
    }
}
