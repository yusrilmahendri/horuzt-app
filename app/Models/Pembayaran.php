<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Bank;

class Pembayaran extends Model
{
    use HasFactory;
    protected $guarded = [''];

    public function bank(){
        return $this->belongsTo(Bank::class);
    }

    public function order(){
        return $this->belongsTo(Order::class);
    }
}
