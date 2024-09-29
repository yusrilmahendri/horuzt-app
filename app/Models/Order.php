<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\PaketNikah;
use App\Models\Pembayaran;

class Order extends Model
{
    use HasFactory;
    protected $guarded = [''];

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function paket(){
        return $this->belongsTo(PaketNikah::class, 'paket_id');
    }

    public function pembayaran(){
        return $this->belongsTo(Pembayaran::class);
    }

    
}
