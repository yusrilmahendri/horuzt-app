<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Mempelai extends Model
{
    use HasFactory;
    protected $guarded = [''];

   public function usersw(){
        return $this->belongsTo(User::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
