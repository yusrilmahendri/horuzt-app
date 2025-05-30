<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Galery extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'photo', 'status'
    ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
}
