<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\CountdownAcara;

class Acara extends Model
{
    use HasFactory;
    protected $guarded = [''];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function countDown(){
        return $this->belongsTo(CountdownAcara::class, 'countdown_id');
    }
}
