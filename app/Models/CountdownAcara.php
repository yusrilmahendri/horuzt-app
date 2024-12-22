<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Acara;
use App\Models\User;

class CountdownAcara extends Model
{
    use HasFactory;

    public function acara(){
        return $this->hasMany(Acara::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
