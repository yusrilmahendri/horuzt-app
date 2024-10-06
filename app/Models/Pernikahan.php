<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ResultPernikahan;

class Pernikahan extends Model
{
    use HasFactory;
    protected $guarded = [''];

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resultPernikahan(){
        return $this->hasOne(ResultPernikahan::class, 'pernikahan_id');
    }
}
