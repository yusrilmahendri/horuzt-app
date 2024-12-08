<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Qoute extends Model
{
    use HasFactory;
    protected $guarded = [''];
    protected $table = 'qoutes';
    protected $fillable = ['name', 'qoute'];

    public function user(){
        return $this->belongsTo(User::class);
    }
}