<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Setting extends Model
{
    use HasFactory;
     protected $fillable = [
        'user_id',
        'domain',
        'token',
        'musik',
        'salam_pembuka',
        'salam_atas',
        'salam_bawah',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
