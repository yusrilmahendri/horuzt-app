<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ucapan extends Model
{
    use HasFactory;
    protected $gaurded = [''];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
