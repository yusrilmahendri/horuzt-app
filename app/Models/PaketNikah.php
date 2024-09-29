<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;

class PaketNikah extends Model
{
    use HasFactory;
    protected $guarded = [''];

    public function order(){
        return $this->hasMany(Order::class, 'orders');
    }
}
