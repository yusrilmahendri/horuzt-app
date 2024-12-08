<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Cerita extends Model
{
    use HasFactory;
    protected $table = 'ceritas';

    public function user(){
        return $this->belongsTo(User::class);
    }
}
